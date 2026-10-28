<?php
/* ==========================================================================
   probar-conexion.php - ¿El backend llega a la base de datos?
   Uso (desde backend/):  php scripts/probar-conexion.php
   (En Express esto lo mostraba index.js al arrancar.)
   ========================================================================== */

require_once __DIR__ . '/../src/database/db.php';

try {
    $fila = consultar('SELECT VERSION() AS version, DATABASE() AS base')[0];
    $ssl = consultar("SHOW SESSION STATUS LIKE 'Ssl_cipher'")[0]['Value'] ?? '';
    echo "✅ MySQL {$fila['version']} conectado a la base \"{$fila['base']}\"" . ($ssl !== '' ? " (SSL: $ssl)" : ' (sin SSL)') . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, '⚠️  No se pudo conectar a la base de datos: ' . $error->getMessage() . "\n");
    exit(1);
}
