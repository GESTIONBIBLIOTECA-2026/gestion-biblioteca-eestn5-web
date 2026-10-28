<?php
/* ==========================================================================
   prestamo.models.php - Modelo de Préstamos (solo lectura)
   Registrar préstamos y devoluciones es del Grupo 4.
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';

// Últimos préstamos entregados, vencidos o devueltos (para el Inicio)
function getUltimosPrestamos(int $limite): array
{
    $sql = "SELECT nombre_completo, titulo, COALESCE(fecha_entrega, fecha_pedido) AS fecha_prestamo,
                   fecha_devolucion_estimada, fecha_devolucion_real, estado, dias_atraso
            FROM vista_historial_usuario
            WHERE estado IN ('prestado', 'vencido', 'devuelto')
            ORDER BY fecha_prestamo DESC
            LIMIT ?";
    return consultar($sql, [$limite]);
}
