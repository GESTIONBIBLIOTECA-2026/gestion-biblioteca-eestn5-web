<?php
/* ==========================================================================
   busqueda.models.php - Búsqueda avanzada de usuarios · Grupo 5
   Nombre, DNI y curso combinados + filtro rápido (chips) + paginación.
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/usuario.sql.php';

// Condición SQL de cada chip del buscador
const FILTROS_SQL = [
    'todos' => "u.estado <> 'baja'",
    'alumnos' => "u.estado <> 'baja' AND r.nombre = 'alumno'",
    'docentes' => "u.estado <> 'baja' AND r.nombre = 'docente'",
    'administradores' => "u.estado <> 'baja' AND r.nombre = 'administrador'",
    'morosos' => "u.estado <> 'baja' AND " . SQL_ATRASO,
    'suspendidos' => "u.estado <> 'baja' AND (" . SQL_CONDICION . ") IN ('suspendido', 'atraso')",
    'bajas' => "u.estado = 'baja'"
];

function buscarUsuarios(array $f): array
{
    // 1. Condiciones de texto con "?" (cada palabra del nombre tiene que aparecer)
    $condiciones = ['TRUE'];
    $valores = [];

    foreach (array_filter(explode(' ', $f['nombre']), fn($p) => $p !== '') as $palabra) {
        $condiciones[] = 'u.nombre_completo LIKE ?';
        $valores[] = "%$palabra%";
    }
    if ($f['dni'] !== '') {
        $condiciones[] = 'u.dni LIKE ?';
        $valores[] = "{$f['dni']}%";
    }
    if ($f['curso'] !== '') {
        $condiciones[] = 'c.nombre LIKE ?';
        $valores[] = "%{$f['curso']}%";
    }

    $where = implode(' AND ', $condiciones);

    // 2. Cuántos hay en cada chip, en UNA sola consulta (SUM de cada condición)
    $columnas = implode(', ', array_map(
        fn($clave, $sql) => "COALESCE(SUM($sql), 0) AS $clave",
        array_keys(FILTROS_SQL), FILTROS_SQL
    ));
    $contadores = consultar("SELECT $columnas " . SQL_USUARIO_DESDE . " WHERE $where", $valores)[0];

    // 3. La página pedida (si ya no existe, la última)
    $totalPaginas = max(1, (int) ceil($contadores[$f['filtro']] / $f['porPagina']));
    $paginaReal = min($f['pagina'], $totalPaginas);

    $usuarios = consultar(
        "SELECT " . SQL_USUARIO_COLUMNAS . " " . SQL_USUARIO_DESDE . " WHERE $where AND " . FILTROS_SQL[$f['filtro']] . "
         ORDER BY u.nombre_completo LIMIT ? OFFSET ?",
        [...$valores, $f['porPagina'], ($paginaReal - 1) * $f['porPagina']]
    );

    return ['usuarios' => $usuarios, 'contadores' => $contadores, 'pagina' => $paginaReal, 'total_paginas' => $totalPaginas];
}
