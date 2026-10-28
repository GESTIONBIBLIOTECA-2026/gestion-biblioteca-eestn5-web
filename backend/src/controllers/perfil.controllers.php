<?php
/* ==========================================================================
   perfil.controllers.php - "Mi perfil" (pantalla Configuración)
   Siempre se usa $req->usuario['id'] (sale del token), nunca un ID del navegador.
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/usuario.models.php';
require_once __DIR__ . '/../models/perfil.models.php';


/* --- GET /api/perfil --- */
function getMiPerfil(object $req): void
{
    try {
        $usuario = getUsuarioPorId($req->usuario['id']);
    } catch (Throwable $error) {
        error500($error, 'No se pudieron leer tus datos');
    }
    responder(200, ['payload' => $usuario]);
}


/* --- PUT /api/perfil   Body: { nombre_completo, correo, telefono, direccion } --- */
function putMiPerfil(object $req): void
{
    $id = $req->usuario['id'];

    try {
        // 1. El correo no puede ser de otro usuario
        $repetido = buscarRepetidos('', $req->body['correo'], $id)[0] ?? null;
        if ($repetido) {
            $mensaje = "Ese correo ya es de {$repetido['nombre_completo']}.";
            responder(409, ['error' => $mensaje, 'campos' => ['correo' => $mensaje]]);
        }

        // 2. Guardar y devolver los datos actualizados
        actualizarPerfil($id, $req->body);
        $usuario = getUsuarioPorId($id);
    } catch (Throwable $error) {
        error500($error, 'No se pudieron guardar tus datos');
    }

    responder(200, ['mensaje' => 'Se guardaron los cambios de tu perfil.', 'payload' => $usuario]);
}


/* --- PUT /api/perfil/clave   Body: { clave_actual, clave_nueva, confirmacion } --- */
function putMiClave(object $req): void
{
    ['clave_actual' => $claveActual, 'clave_nueva' => $claveNueva] = $req->body;

    try {
        // 1. La contraseña actual tiene que coincidir con la guardada
        $hash = getHashClave($req->usuario['id']);
        if (!password_verify($claveActual, $hash ?? '')) {
            $mensaje = 'La contraseña actual no es correcta.';
            responder(400, ['error' => $mensaje, 'campos' => ['clave_actual' => $mensaje]]);
        }

        // 2. Se guarda la nueva como hash bcrypt (nunca en texto)
        actualizarClave($req->usuario['id'], password_hash($claveNueva, PASSWORD_BCRYPT, ['cost' => 10]));
    } catch (Throwable $error) {
        error500($error, 'No se pudo cambiar la contraseña');
    }

    responder(200, ['mensaje' => 'Tu contraseña se cambió correctamente.']);
}
