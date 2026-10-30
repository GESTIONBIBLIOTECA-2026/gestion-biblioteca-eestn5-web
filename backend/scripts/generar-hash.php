<?php
/* ==========================================================================
   generar-hash.php - Genera el hash bcrypt para usuarios.password_hash
   Uso (desde backend/):  php scripts/generar-hash.php miClave123
   ========================================================================== */

$clave = $argv[1] ?? '';

if ($clave === '') {
    echo "Uso: php scripts/generar-hash.php <contraseña>\n";
    exit(1);
}

echo password_hash($clave, PASSWORD_BCRYPT, ['cost' => 10]), "\n";
