<?php
/* ==========================================================================
   inicializar-bd.php - Crea las tablas, vistas y datos de referencia
   Uso (desde backend/):  php scripts/inicializar-bd.php
   Es idempotente: se puede correr más de una vez y no borra filas.
   ========================================================================== */

require_once __DIR__ . '/../src/database/db.php';

$esquema = file_get_contents(__DIR__ . '/schema.sql');

// Se sacan los comentarios "-- ..." y se separa sentencia por sentencia
$sentencias = array_filter(
    array_map('trim', explode(';', preg_replace('/^\s*--.*$/m', '', $esquema))),
    fn($sentencia) => $sentencia !== ''
);

foreach ($sentencias as $sentencia) {
    conexion()->exec($sentencia);
}

echo 'Base "' . DB['database'] . '" inicializada correctamente (' . count($sentencias) . " sentencias).\n";
