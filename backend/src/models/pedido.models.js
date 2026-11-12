/* ==========================================================================
   pedido.models.js - Pedidos y préstamos (módulo del Grupo 4 integrado)
   Trabaja sobre las tablas REALES del proyecto:
     pedidos, pedido_ejemplares, pedido_historial, ejemplares,
     usuarios, libros y la vista vista_stock_libros.

   El "pedido" nace como reserva (pendiente) y descuenta stock reservado.
   Al entregarlo se asignan ejemplares físicos y pasan a "prestado".
   Al devolverlo los ejemplares vuelven a "disponible".
   ========================================================================== */

import connection, { conTransaccion } from "../database/db.js";
import { TRANSICIONES_PEDIDO } from "../config/config.js";
import { noExiste, conflicto } from "./errores.models.js";
import { CONDICION } from "./usuario.sql.js";

// Columnas que devuelve el panel. `estado` se calcula: un préstamo cuya
// fecha estimada ya pasó se muestra como "vencido" sin esperar un proceso.
const COLUMNAS_PEDIDO = `
    p.id AS id,
    p.id AS id_pedido,
    p.usuario_id,
    u.nombre_completo AS usuario,
    u.dni,
    p.libro_id,
    l.titulo AS libro,
    l.isbn,
    p.cantidad,
    p.fecha_pedido,
    p.fecha_devolucion_estimada AS fecha_devolucion,
    p.fecha_entrega,
    p.fecha_devolucion_real,
    CASE
        WHEN p.estado = 'prestado' AND p.fecha_devolucion_estimada < CURRENT_DATE THEN 'vencido'
        ELSE p.estado
    END AS estado,
    CASE
        WHEN p.estado IN ('prestado', 'vencido') AND p.fecha_devolucion_estimada < CURRENT_DATE
            THEN DATEDIFF(CURRENT_DATE, p.fecha_devolucion_estimada)
        ELSE 0
    END AS dias_atraso
`;

const DESDE_PEDIDO = `
    FROM pedidos p
    JOIN usuarios u ON u.id = p.usuario_id
    JOIN libros l ON l.id = p.libro_id
`;

// Filtro por estado calculado (el vencido no está guardado como tal: se deduce)
const FILTRO_ESTADO = {
    todos: "TRUE",
    pendiente: "p.estado = 'pendiente'",
    prestado: "p.estado = 'prestado' AND p.fecha_devolucion_estimada >= CURRENT_DATE",
    vencido: "p.estado IN ('prestado', 'vencido') AND p.fecha_devolucion_estimada < CURRENT_DATE",
    devuelto: "p.estado = 'devuelto'",
    cancelado: "p.estado = 'cancelado'"
};

/* --- Listado del panel: ?estado=&texto=&limite= --- */
export const listarPedidos = async ({ estado = "todos", texto = "", limite = 200 } = {}) => {
    const sql = `SELECT ${COLUMNAS_PEDIDO} ${DESDE_PEDIDO}
                 WHERE ${FILTRO_ESTADO[estado] ?? FILTRO_ESTADO.todos}
                   AND (
                        u.nombre_completo LIKE ?
                        OR u.dni LIKE ?
                        OR l.titulo LIKE ?
                        OR EXISTS (
                            SELECT 1
                            FROM libros_autores la
                            JOIN autores a ON a.id = la.autor_id
                            WHERE la.libro_id = l.id AND a.nombre_completo LIKE ?
                        )
                   )
                 ORDER BY p.id DESC
                 LIMIT ?`;
    const patron = `%${texto}%`;
    const [rows] = await connection.query(sql, [patron, patron, patron, patron, limite]);
    return rows;
};

/* --- Números del encabezado del panel --- */
export const obtenerResumenPedidos = async () => {
    const sql = `SELECT
            COUNT(*) AS total,
            COALESCE(SUM(estado = 'pendiente'), 0) AS pendientes,
            COALESCE(SUM(estado = 'prestado' AND fecha_devolucion_estimada >= CURRENT_DATE), 0) AS prestados,
            COALESCE(SUM(estado = 'prestado' AND fecha_devolucion_estimada < CURRENT_DATE), 0) AS atrasados,
            COALESCE(SUM(estado = 'devuelto'), 0) AS devueltos,
            COALESCE(SUM(estado = 'cancelado'), 0) AS cancelados
        FROM pedidos`;
    const [rows] = await connection.query(sql);
    return rows[0];
};

/* --- Usuarios habilitados para el buscador del formulario ---
   Se usa la misma CONDICION que ve el bibliotecario: no alcanza con
   usuarios.estado, porque un moroso sigue "habilitado" en esa columna. */
export const listarUsuariosHabilitados = async () => {
    const sql = `SELECT u.id AS id_usuario, u.id, u.nombre_completo AS nombre, u.dni,
                        c.nombre AS curso, r.nombre AS rol, ${CONDICION} AS condicion
                 FROM usuarios u
                 JOIN roles r ON r.id = u.rol_id
                 LEFT JOIN cursos c ON c.id = u.curso_id
                 WHERE ${CONDICION} = 'habilitado'
                 ORDER BY u.nombre_completo`;
    const [rows] = await connection.query(sql);
    return rows;
};

/* --- Libros con ejemplares libres para reservar --- */
export const listarLibrosDisponibles = async () => {
    const sql = `SELECT v.libro_id AS id_libro, v.libro_id AS id, v.titulo,
                        v.autores AS autor, ed.nombre AS editorial, v.categoria,
                        v.stock_reservable AS stock, v.ejemplares_disponibles
                 FROM vista_stock_libros v
                 JOIN libros l ON l.id = v.libro_id
                 LEFT JOIN editoriales ed ON ed.id = l.editorial_id
                 WHERE v.stock_reservable > 0
                 ORDER BY v.titulo`;
    const [rows] = await connection.query(sql);
    return rows;
};

/* --- Alta de un pedido (reserva) ---
   Todo dentro de una transacción: se bloquea el libro para que dos altas
   simultáneas no reserven el mismo último ejemplar. */
export const crearPedido = (datos, registradoPor) => conTransaccion(async (conexion) => {
    const [usuarios] = await conexion.query(
        `SELECT u.id, u.nombre_completo, ${CONDICION} AS condicion
         FROM usuarios u WHERE u.id = ? FOR UPDATE`,
        [datos.id_usuario]);
    if (!usuarios[0]) throw noExiste("El usuario no existe.");

    // La condición incluye sanciones activas y préstamos vencidos
    const motivos = {
        atraso: "El usuario tiene préstamos vencidos sin devolver.",
        suspendido: "El usuario está suspendido.",
        baja: "El usuario está dado de baja."
    };
    if (usuarios[0].condicion !== "habilitado") {
        throw conflicto(motivos[usuarios[0].condicion] ?? "El usuario no está habilitado para realizar pedidos.");
    }

    const [libros] = await conexion.query(
        "SELECT id, titulo FROM libros WHERE id = ? AND activo = TRUE FOR UPDATE",
        [datos.id_libro]);
    if (!libros[0]) throw noExiste("El libro no existe o está dado de baja.");

    // Ejemplares en el estante menos las reservas pendientes de ese título
    const [[stock]] = await conexion.query(
        `SELECT
            (SELECT COUNT(*) FROM ejemplares WHERE libro_id = ? AND estado = 'disponible') AS disponibles,
            (SELECT COALESCE(SUM(cantidad), 0) FROM pedidos WHERE libro_id = ? AND estado = 'pendiente') AS reservados`,
        [datos.id_libro, datos.id_libro]);
    const libres = Number(stock.disponibles) - Number(stock.reservados);

    if (libres < datos.cantidad) {
        const detalle = stock.reservados > 0 ? ` (${stock.reservados} ya reservado/s)` : "";
        throw conflicto(`No quedan ejemplares libres de «${libros[0].titulo}»: hay ${Math.max(libres, 0)}${detalle}.`);
    }

    const [alta] = await conexion.query(
        `INSERT INTO pedidos
            (usuario_id, libro_id, cantidad, fecha_pedido, fecha_devolucion_estimada, estado)
         VALUES (?, ?, ?, ?, ?, 'pendiente')`,
        [datos.id_usuario, datos.id_libro, datos.cantidad, datos.fecha_pedido, datos.fecha_devolucion]);

    await conexion.query(
        `INSERT INTO pedido_historial
            (pedido_id, estado_anterior, estado_nuevo, registrado_por, observacion)
         VALUES (?, NULL, 'pendiente', ?, ?)`,
        [alta.insertId, registradoPor, "Reserva registrada desde el panel de préstamos"]);

    return alta.insertId;
});

// Bloquea el pedido y devuelve solo lo necesario para decidir la transición
const bloquearPedido = async (conexion, idPedido) => {
    const [filas] = await conexion.query(
        `SELECT id, libro_id, cantidad, estado
         FROM pedidos WHERE id = ? FOR UPDATE`,
        [idPedido]);
    return filas[0] || null;
};

// Ejemplares físicos disponibles del título (bloqueados hasta el commit)
const tomarEjemplaresLibres = async (conexion, libroId, cantidad) => {
    const [filas] = await conexion.query(
        `SELECT id FROM ejemplares
         WHERE libro_id = ? AND estado = 'disponible'
         ORDER BY id
         LIMIT ? FOR UPDATE`,
        [libroId, cantidad]);

    if (filas.length < cantidad) {
        throw conflicto("Ya no quedan ejemplares físicos disponibles para entregar este pedido.");
    }

    return filas.map((fila) => fila.id);
};

const registrarHistorial = (conexion, idPedido, anterior, nuevo, registradoPor, observacion) =>
    conexion.query(
        `INSERT INTO pedido_historial
            (pedido_id, estado_anterior, estado_nuevo, registrado_por, observacion)
         VALUES (?, ?, ?, ?, ?)`,
        [idPedido, anterior, nuevo, registradoPor, observacion]);

/* --- Cambio de estado ---
   pendiente -> prestado   (entregar: asigna ejemplares y fija fecha_entrega)
   pendiente -> cancelado  (rechazar la reserva)
   prestado  -> devuelto   (libera los ejemplares y fija fecha_devolucion_real) */
export const cambiarEstadoPedido = (idPedido, nuevoEstado, registradoPor) => conTransaccion(async (conexion) => {
    const pedido = await bloquearPedido(conexion, idPedido);

    if (!pedido) throw noExiste("El pedido no existe.");

    const permitidos = TRANSICIONES_PEDIDO[nuevoEstado] ?? [];
    if (!permitidos.includes(pedido.estado)) {
        throw conflicto("El pedido no puede cambiar a ese estado.");
    }

    if (nuevoEstado === "prestado") {
        const ejemplares = await tomarEjemplaresLibres(conexion, pedido.libro_id, pedido.cantidad);

        for (const ejemplarId of ejemplares) {
            await conexion.query(
                "INSERT INTO pedido_ejemplares (pedido_id, ejemplar_id) VALUES (?, ?)",
                [idPedido, ejemplarId]);
        }

        await conexion.query(
            "UPDATE ejemplares SET estado = 'prestado' WHERE id IN (?)",
            [ejemplares]);

        await conexion.query(
            "UPDATE pedidos SET estado = 'prestado', fecha_entrega = NOW() WHERE id = ?",
            [idPedido]);
    }

    if (nuevoEstado === "devuelto") {
        const [asignados] = await conexion.query(
            "SELECT ejemplar_id FROM pedido_ejemplares WHERE pedido_id = ?",
            [idPedido]);

        if (asignados.length > 0) {
            await conexion.query(
                "UPDATE ejemplares SET estado = 'disponible' WHERE id IN (?)",
                [asignados.map((fila) => fila.ejemplar_id)]);
        }

        await conexion.query(
            "UPDATE pedidos SET estado = 'devuelto', fecha_devolucion_real = NOW() WHERE id = ?",
            [idPedido]);
    }

    if (nuevoEstado === "cancelado") {
        await conexion.query(
            "UPDATE pedidos SET estado = 'cancelado' WHERE id = ?",
            [idPedido]);
    }

    const observaciones = {
        prestado: "Préstamo entregado en el mostrador",
        devuelto: "Devolución registrada en el mostrador",
        cancelado: "Reserva rechazada desde el panel"
    };

    await registrarHistorial(
        conexion, idPedido, pedido.estado, nuevoEstado, registradoPor, observaciones[nuevoEstado]);

    return { anterior: pedido.estado, nuevo: nuevoEstado };
});
