/* ==========================================================================
   notificacion.models.js - Actividad reciente del panel (campanita)
   Arma una sola línea de tiempo con los hechos que ya están guardados:
   usuarios dados de alta, pedidos/reservas, entregas, devoluciones,
   cancelaciones y sanciones. No duplica datos: se lee de las tablas reales.
   ========================================================================== */

import connection from "../database/db.js";

// Cada bloque trae los últimos 50 movimientos; después se mezclan y se
// recortan a la cantidad pedida. Así la campanita no recorre tablas enormes.
const ULTIMOS_POR_TIPO = 50;

export const listarNotificaciones = async (limite = 40) => {
    const sql = `
        SELECT * FROM (
            (SELECT CONCAT('usuario-', u.id) AS id,
                    'usuario' AS tipo,
                    'Nuevo usuario' AS titulo,
                    CONCAT(u.nombre_completo, ' se registró como ', r.nombre,
                           COALESCE(CONCAT(' de ', c.nombre), ''), '.') AS mensaje,
                    u.fecha_alta AS fecha
             FROM usuarios u
             JOIN roles r ON r.id = u.rol_id
             LEFT JOIN cursos c ON c.id = u.curso_id
             ORDER BY u.id DESC
             LIMIT ${ULTIMOS_POR_TIPO})

            UNION ALL

            (SELECT CONCAT('pedido-', p.id) AS id,
                    'pedido' AS tipo,
                    'Nuevo pedido' AS titulo,
                    CONCAT(u.nombre_completo, ' pidió ', p.cantidad,
                           IF(p.cantidad = 1, ' ejemplar', ' ejemplares'),
                           ' de «', l.titulo, '».') AS mensaje,
                    p.fecha_pedido AS fecha
             FROM pedidos p
             JOIN usuarios u ON u.id = p.usuario_id
             JOIN libros l ON l.id = p.libro_id
             ORDER BY p.id DESC
             LIMIT ${ULTIMOS_POR_TIPO})

            UNION ALL

            (SELECT CONCAT('movimiento-', h.id) AS id,
                    CASE h.estado_nuevo
                        WHEN 'prestado' THEN 'prestamo'
                        WHEN 'devuelto' THEN 'devolucion'
                        ELSE 'cancelacion'
                    END AS tipo,
                    CASE h.estado_nuevo
                        WHEN 'prestado' THEN 'Préstamo entregado'
                        WHEN 'devuelto' THEN 'Devolución registrada'
                        ELSE 'Reserva cancelada'
                    END AS titulo,
                    CONCAT(u.nombre_completo,
                           CASE h.estado_nuevo
                               WHEN 'prestado' THEN ' retiró '
                               WHEN 'devuelto' THEN ' devolvió '
                               ELSE ' canceló la reserva de '
                           END,
                           p.cantidad, IF(p.cantidad = 1, ' ejemplar', ' ejemplares'),
                           ' de «', l.titulo, '».') AS mensaje,
                    h.fecha AS fecha
             FROM pedido_historial h
             JOIN pedidos p ON p.id = h.pedido_id
             JOIN usuarios u ON u.id = p.usuario_id
             JOIN libros l ON l.id = p.libro_id
             WHERE h.estado_anterior IS NOT NULL
               AND h.estado_nuevo IN ('prestado', 'devuelto', 'cancelado')
             ORDER BY h.id DESC
             LIMIT ${ULTIMOS_POR_TIPO})

            UNION ALL

            (SELECT CONCAT('sancion-', s.id) AS id,
                    'sancion' AS tipo,
                    'Sanción aplicada' AS titulo,
                    CONCAT(u.nombre_completo, ': ',
                           COALESCE(s.motivo, IF(s.tipo = 'atraso',
                                                'atraso en una devolución',
                                                'sanción manual')), '.') AS mensaje,
                    TIMESTAMP(s.fecha_inicio) AS fecha
             FROM sanciones s
             JOIN usuarios u ON u.id = s.usuario_id
             ORDER BY s.id DESC
             LIMIT ${ULTIMOS_POR_TIPO})
        ) AS eventos
        ORDER BY fecha DESC
        LIMIT ?`;

    const [rows] = await connection.query(sql, [limite]);
    return rows;
};
