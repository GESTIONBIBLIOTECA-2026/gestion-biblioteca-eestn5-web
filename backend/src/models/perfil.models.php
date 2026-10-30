<?php
/* ==========================================================================
   perfil.models.php - "Mi perfil": datos propios y contraseña
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';

function getHashClave(int $id): ?string
{
    return consultar("SELECT password_hash FROM usuarios WHERE id = ?", [$id])[0]['password_hash'] ?? null;
}

function actualizarPerfil(int $id, array $datos): void
{
    $sql = "UPDATE usuarios SET nombre_completo = ?, correo = ?, telefono = ?, direccion = ? WHERE id = ?";
    ejecutar($sql, [$datos['nombre_completo'], $datos['correo'], $datos['telefono'], $datos['direccion'], $id]);
}

function actualizarClave(int $id, string $passwordHash): void
{
    ejecutar("UPDATE usuarios SET password_hash = ? WHERE id = ?", [$passwordHash, $id]);
}
