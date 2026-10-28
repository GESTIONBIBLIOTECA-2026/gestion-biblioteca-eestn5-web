<?php
/* ==========================================================================
   auth.controllers.php - Controlador de Autenticación
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/usuario.models.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../middlewares/jwt.php';

// Hash de relleno: si el DNI no existe igual se compara una clave (así no se puede adivinar qué DNI existe)
const HASH_FALSO = '$2y$10$tu4lvQk9YryA5osNyhqEb.vwNa5zIDPOqd9/r932HlWCMSlQ5j73m';


/* --- POST /api/auth/login   Body: { dni, clave } --- */
function postLogin(object $req): void
{
    try {
        ['dni' => $dni, 'clave' => $clave] = $req->body;

        // 1. Pedirle el usuario al Modelo y comparar la contraseña con el hash
        $usuario = getUsuarioLogin($dni);
        $claveOk = password_verify($clave, $usuario['password_hash'] ?? HASH_FALSO);
    } catch (Throwable $error) {
        error500($error, 'No se pudo conectar con la base de datos');
    }

    if (!$usuario || !$claveOk) {
        responder(401, ['error' => 'DNI o contraseña incorrectos.']);
    }

    // 2. Reglas de negocio: entran alumnos, docentes y administradores.
    //    De baja no entra nadie; un alumno/docente suspendido sí (ve su estado en el inicio).
    if (!puedeEntrar($usuario)) {
        responder(403, ['error' => 'Tu usuario está ' . ($usuario['estado'] === 'baja' ? 'dado de baja' : 'suspendido') . '.']);
    }

    // 3. Token de sesión (nunca se manda el password_hash)
    $sesion = ['id' => $usuario['id'], 'nombre' => $usuario['nombre_completo'], 'rol' => $usuario['rol']];
    $token = firmarToken($sesion);

    responder(200, ['payload' => ['token' => $token, 'usuario' => $sesion]]);
}


/* --- GET /api/auth/perfil   Quién está logueado: { id, nombre, rol } (lo carga el middleware) --- */
function getPerfil(object $req): void
{
    responder(200, ['payload' => $req->usuario]);
}
