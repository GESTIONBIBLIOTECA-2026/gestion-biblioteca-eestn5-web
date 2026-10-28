<?php
/* ==========================================================================
   dashboard.models.php - Métricas del Inicio del administrador · Grupo 5
   Solo lectura: cuenta filas de libros (G6), pedidos (G4) y usuarios (G5).
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/usuario.sql.php';

// Los 3 totales en UNA consulta (cada subconsulta devuelve un número)
function getMetricas(): array
{
    $sql = "SELECT
            (SELECT COUNT(*) FROM libros WHERE activo = TRUE) AS libros_total,
            (SELECT COUNT(*) FROM pedidos WHERE estado IN ('prestado', 'vencido')) AS prestamos_activos,
            (SELECT COUNT(*) FROM usuarios u WHERE u.estado <> 'baja' AND " . SQL_ATRASO . ") AS alumnos_morosos";
    return consultar($sql)[0];
}
