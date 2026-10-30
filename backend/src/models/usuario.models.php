<?php
/* ==========================================================================
   usuario.models.php - Modelo de Usuarios (acceso a datos) · Grupo 5
   Solo consulta la base con comodines "?" y devuelve datos puros.
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/usuario.sql.php';


// Usuario para el login (con el hash de la contraseña)
function getUsuarioLogin(string $dni): ?array
{
    $sql = "SELECT u.id, u.nombre_completo, u.password_hash, u.estado, r.nombre AS rol
            FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.dni = ?";
    return consultar($sql, [$dni])[0] ?? null;
}

// Rol y estado ACTUALES de quien tiene la sesión (lo usa el middleware)
function getSesion(mixed $id): ?array
{
    $sql = "SELECT u.id, u.nombre_completo, u.estado, r.nombre AS rol
            FROM usuarios u JOIN roles r ON r.id = u.rol_id WHERE u.id = ?";
    return consultar($sql, [$id])[0] ?? null;
}

function getUsuarioPorId(int $id): ?array
{
    return consultar("SELECT " . SQL_USUARIO_COLUMNAS . " " . SQL_USUARIO_DESDE . " WHERE u.id = ?", [$id])[0] ?? null;
}

// Ficha corta por DNI para que el Grupo 4 valide al usuario antes de prestar
function getEstadoPorDni(string $dni): ?array
{
    $sql = "SELECT u.id, u.nombre_completo, c.nombre AS curso, r.nombre AS rol, " . SQL_CONDICION . " AS condicion "
         . SQL_USUARIO_DESDE . " WHERE u.dni = ?";
    return consultar($sql, [$dni])[0] ?? null;
}

// Otros usuarios que ya usan ese DNI o ese correo
function buscarRepetidos(string $dni, string $correo, int $idPropio = 0): array
{
    $sql = "SELECT dni, correo, nombre_completo FROM usuarios WHERE (dni = ? OR correo = ?) AND id <> ?";
    return consultar($sql, [$dni, $correo, $idPropio]);
}

// ID del curso por nombre. Si no existe, lo crea (INSERT IGNORE no falla si ya está).
function getIdCurso(?string $nombre): ?int
{
    if ($nombre === null || $nombre === '') return null;
    ejecutar("INSERT IGNORE INTO cursos (nombre) VALUES (?)", [$nombre]);
    return consultar("SELECT id FROM cursos WHERE nombre = ?", [$nombre])[0]['id'];
}

function crearUsuario(array $u): int
{
    $sql = "INSERT INTO usuarios (dni, nombre_completo, curso_id, correo, telefono, direccion, password_hash, rol_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, (SELECT id FROM roles WHERE nombre = ?))";
    $resultado = ejecutar($sql, [$u['dni'], $u['nombre_completo'], getIdCurso($u['curso']), $u['correo'],
        $u['telefono'], $u['direccion'], $u['password_hash'], $u['rol']]);
    return $resultado['insertId'];
}

// La contraseña solo cambia si viene password_hash (COALESCE deja la que estaba)
function actualizarUsuario(int $id, array $u): int
{
    $sql = "UPDATE usuarios SET dni = ?, nombre_completo = ?, curso_id = ?, correo = ?, telefono = ?, direccion = ?,
            rol_id = (SELECT id FROM roles WHERE nombre = ?), password_hash = COALESCE(?, password_hash)
            WHERE id = ?";
    $resultado = ejecutar($sql, [$u['dni'], $u['nombre_completo'], getIdCurso($u['curso']), $u['correo'],
        $u['telefono'], $u['direccion'], $u['rol'], $u['password_hash'], $id]);
    return $resultado['affectedRows'];
}
