<?php
/* ==========================================================================
   curso.models.php - Modelo de Cursos · Grupo 5
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';

// Todos los cursos con cuántos alumnos activos tiene cada uno
function getCursos(): array
{
    $sql = "SELECT c.id, c.nombre, COUNT(u.id) AS alumnos
            FROM cursos c
            LEFT JOIN usuarios u ON u.curso_id = c.id AND u.estado <> 'baja'
            GROUP BY c.id, c.nombre
            ORDER BY c.nombre";
    return consultar($sql);
}
