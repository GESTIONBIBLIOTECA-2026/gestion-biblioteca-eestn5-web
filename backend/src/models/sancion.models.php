<?php
/* ==========================================================================
   sancion.models.php - Historial activo y estado de la cuenta · Grupo 5
   Suspender (sanción), habilitar y dar de baja.
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';

// Historial activo: préstamos (la vista calcula los días de atraso) y sanciones.
// dias_restantes: cuántos días faltan para la fecha de devolución (negativo = ya pasó)
function getHistorial(int $id): array
{
    $prestamos = consultar(
        "SELECT pedido_id, titulo, cantidad, fecha_pedido, fecha_entrega, fecha_devolucion_estimada,
                fecha_devolucion_real, estado, dias_atraso,
                DATEDIFF(fecha_devolucion_estimada, CURRENT_DATE) AS dias_restantes
         FROM vista_historial_usuario WHERE usuario_id = ? ORDER BY fecha_pedido DESC",
        [$id]
    );
    $sanciones = consultar(
        "SELECT s.tipo, s.motivo, s.fecha_inicio, s.fecha_fin, a.nombre_completo AS aplicada_por,
                (s.activa = TRUE AND (s.fecha_fin IS NULL OR s.fecha_fin >= CURRENT_DATE)) AS en_vigor
         FROM sanciones s LEFT JOIN usuarios a ON a.id = s.aplicada_por
         WHERE s.usuario_id = ? ORDER BY en_vigor DESC, s.fecha_inicio DESC",
        [$id]
    );
    return ['prestamos' => $prestamos, 'sanciones' => $sanciones];
}

// Suspender: guarda la sanción (asociada al préstamo atrasado si tiene uno) y marca al usuario
function suspenderUsuario(int $id, ?string $motivo, int $adminId): void
{
    $atrasados = consultar(
        "SELECT id FROM pedidos WHERE usuario_id = ?
         AND (estado = 'vencido' OR (estado = 'prestado' AND fecha_devolucion_estimada < CURRENT_DATE)) LIMIT 1",
        [$id]
    );
    $pedidoId = $atrasados[0]['id'] ?? null;

    ejecutar(
        "INSERT INTO sanciones (usuario_id, pedido_id, tipo, motivo, fecha_inicio, activa, aplicada_por)
         VALUES (?, ?, ?, ?, CURRENT_DATE, TRUE, ?)",
        [$id, $pedidoId, $pedidoId ? 'atraso' : 'manual', $motivo, $adminId]
    );
    ejecutar("UPDATE usuarios SET estado = 'suspendido' WHERE id = ?", [$id]);
}

// Habilitar (o reactivar una baja): las sanciones no se borran, se cierran con fecha de hoy
function habilitarUsuario(int $id): void
{
    ejecutar(
        "UPDATE sanciones SET activa = FALSE, fecha_fin = LEAST(COALESCE(fecha_fin, CURRENT_DATE), CURRENT_DATE)
         WHERE usuario_id = ? AND activa = TRUE",
        [$id]
    );
    ejecutar("UPDATE usuarios SET estado = 'habilitado' WHERE id = ?", [$id]);
}

// Baja lógica: no se borra (queda el historial) y sus reservas pendientes se cancelan
function darDeBajaUsuario(int $id, int $adminId): int
{
    ejecutar(
        "INSERT INTO pedido_historial (pedido_id, estado_anterior, estado_nuevo, registrado_por, observacion)
         SELECT id, 'pendiente', 'cancelado', ?, 'Reserva cancelada por la baja del usuario'
         FROM pedidos WHERE usuario_id = ? AND estado = 'pendiente'",
        [$adminId, $id]
    );
    $resultado = ejecutar("UPDATE pedidos SET estado = 'cancelado' WHERE usuario_id = ? AND estado = 'pendiente'", [$id]);
    ejecutar("UPDATE usuarios SET estado = 'baja' WHERE id = ?", [$id]);
    return $resultado['affectedRows'];   // reservas canceladas
}
