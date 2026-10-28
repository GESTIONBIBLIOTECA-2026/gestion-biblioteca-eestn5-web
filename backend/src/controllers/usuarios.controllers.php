<?php
/* ==========================================================================
   usuarios.controllers.php - Controlador de Usuarios · Grupo 5
   Recibe la petición ($req), aplica las reglas de negocio, le pide los datos
   al Modelo y arma la respuesta con su código HTTP.
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/usuario.models.php';
require_once __DIR__ . '/../models/busqueda.models.php';

// Si el DNI o el correo ya son de otro usuario → ['dni' => "...", 'correo' => "..."]
function camposRepetidos(array $datos, int $idPropio = 0): array
{
    $campos = [];
    foreach (buscarRepetidos($datos['dni'], $datos['correo'], $idPropio) as $otro) {
        if ($otro['dni'] === $datos['dni']) $campos['dni'] = "Ese DNI ya es de {$otro['nombre_completo']}.";
        if ($otro['correo'] === $datos['correo']) $campos['correo'] = "Ese correo ya es de {$otro['nombre_completo']}.";
    }
    return $campos;
}

/* --- GET /api/usuarios?nombre=&dni=&curso=&filtro=&pagina= --- */
function getUsuarios(object $req): void
{
    try {
        $resultado = buscarUsuarios($req->filtros);
    } catch (Throwable $error) {
        error500($error, 'No se pudieron buscar los usuarios');
    }
    responder(200, ['payload' => $resultado]);
}

/* --- GET /api/usuarios/:id --- */
function getUsuario(object $req): void
{
    try {
        $usuario = getUsuarioPorId($req->id);
    } catch (Throwable $error) {
        error500($error, 'No se pudo leer el usuario');
    }
    if (!$usuario) responder(404, ['error' => 'No hay ningún usuario con ese ID.']);
    responder(200, ['payload' => $usuario]);
}

/* --- GET /api/usuarios/dni/:dni → { id, nombre_completo, curso, rol, estado, puede_pedir } (lo consume el Grupo 4) ---
   estado: "habilitado" | "suspendido" | "moroso" (préstamo vencido) | "baja" */
function getUsuarioDni(object $req): void
{
    try {
        $usuario = getEstadoPorDni($req->dni);
    } catch (Throwable $error) {
        error500($error, 'No se pudo leer el usuario');
    }
    if (!$usuario) responder(404, ['error' => 'No hay ningún usuario con ese DNI.']);

    $condicion = $usuario['condicion'];
    unset($usuario['condicion']);
    $estado = $condicion === 'atraso' ? 'moroso' : $condicion;
    responder(200, ['payload' => [...$usuario, 'estado' => $estado, 'puede_pedir' => $estado === 'habilitado']]);
}

/* --- POST /api/usuarios   Body: { nombre_completo, dni, rol, curso, correo, telefono, direccion, clave } --- */
function postUsuario(object $req): void
{
    try {
        $datos = $req->body;
        $clave = $datos['clave'];
        unset($datos['clave']);

        // 1. El DNI y el correo no pueden estar repetidos
        $campos = camposRepetidos($datos);
        if ($campos) responder409($campos);

        // 2. Se guarda con la contraseña como hash bcrypt
        $id = crearUsuario([...$datos, 'password_hash' => password_hash($clave, PASSWORD_BCRYPT, ['cost' => 10])]);
    } catch (Throwable $error) {
        error500($error, 'No se pudo guardar el usuario');
    }
    responder(201, ['mensaje' => "Se registró a {$datos['nombre_completo']}.", 'payload' => ['id' => $id]]);
}

/* --- PUT /api/usuarios/:id   (la clave es opcional: vacía = no se cambia) --- */
function putUsuario(object $req): void
{
    $datos = $req->body;
    $clave = $datos['clave'];
    unset($datos['clave']);

    // 1. Un administrador no puede sacarse el rol a sí mismo (se quedaría afuera del panel)
    if ($req->id === $req->usuario['id'] && $datos['rol'] !== 'administrador') {
        responder409(['rol' => 'No podés quitarte el rol de administrador a vos mismo.']);
    }

    try {
        // 2. DNI y correo repetidos
        $campos = camposRepetidos($datos, $req->id);
        if ($campos) responder409($campos);

        // 3. Guardar (si no existe, no se encontró ninguna fila → 404)
        $hash = $clave !== '' ? password_hash($clave, PASSWORD_BCRYPT, ['cost' => 10]) : null;
        $modificados = actualizarUsuario($req->id, [...$datos, 'password_hash' => $hash]);
    } catch (Throwable $error) {
        error500($error, 'No se pudieron guardar los cambios');
    }
    if ($modificados === 0) responder(404, ['error' => 'No hay ningún usuario con ese ID.']);

    responder(200, ['mensaje' => "Se guardaron los cambios de {$datos['nombre_completo']}."]);
}
