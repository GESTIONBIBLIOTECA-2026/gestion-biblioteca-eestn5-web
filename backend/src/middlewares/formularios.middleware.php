<?php
/* ==========================================================================
   formularios.middleware.php - Validación de los formularios
   Alta / modificación de usuarios, "Mi perfil" y cambio de contraseña.
   Si hay errores responde 400 con { error, campos: { campo: "mensaje" } }.
   ========================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/predicados.php';

function claveInvalida(string $clave): bool
{
    return mb_strlen($clave) < 6 || mb_strlen($clave) > 72;
}

// Nombre, correo, teléfono y dirección (los comparten el ABM y "Mi perfil")
function revisarContacto(array $datos, array &$campos): void
{
    $largoNombre = mb_strlen($datos['nombre_completo']);
    if ($largoNombre < 3 || $largoNombre > 120 || !esNombreValido($datos['nombre_completo'])) {
        $campos['nombre_completo'] = 'Escribí el nombre y apellido (solo letras).';
    }
    if (!esCorreoValido($datos['correo']) || mb_strlen($datos['correo']) > 120) $campos['correo'] = 'El correo electrónico no es válido.';
    if (!esTelefonoValido($datos['telefono'])) $campos['telefono'] = 'Escribí un teléfono válido (ej: 11 4444-5678).';
    $largoDireccion = mb_strlen($datos['direccion']);
    if ($largoDireccion < 3 || $largoDireccion > 150) $campos['direccion'] = 'Escribí la dirección (hasta 150 caracteres).';
}


/* --- Alta y modificación: { nombre_completo, dni, rol, curso, correo, telefono, direccion, clave } --- */
function validarUsuario(object $req): void
{
    $body = $req->body;
    $datos = [
        'nombre_completo' => limpiar($body['nombre_completo'] ?? ''),
        'dni' => limpiarDni($body['dni'] ?? ''),
        'rol' => mb_strtolower(limpiar($body['rol'] ?? '')),
        'curso' => limpiar($body['curso'] ?? ''),
        'correo' => mb_strtolower(limpiar($body['correo'] ?? '')),
        'telefono' => limpiar($body['telefono'] ?? ''),
        'direccion' => limpiar($body['direccion'] ?? ''),
        'clave' => texto($body['clave'] ?? '')
    ];
    $campos = [];

    revisarContacto($datos, $campos);

    if (!esDniValido($datos['dni'])) $campos['dni'] = 'El DNI tiene que tener entre 7 y 8 números.';
    if (!in_array($datos['rol'], ROLES, true)) $campos['rol'] = 'Elegí el tipo de usuario.';

    // El curso es obligatorio solo para alumnos
    if ($datos['rol'] !== 'alumno') $datos['curso'] = null;
    elseif ($datos['curso'] === '' || mb_strlen($datos['curso']) > 30) $campos['curso'] = 'El curso es obligatorio para los alumnos.';

    // Contraseña: obligatoria en el alta; en la modificación solo si se quiere cambiar
    if (($req->metodo === 'POST' || $datos['clave'] !== '') && claveInvalida($datos['clave'])) {
        $campos['clave'] = 'La contraseña tiene que tener entre 6 y 72 caracteres.';
    }

    if (count($campos) > 0) rechazar($campos);

    $req->body = $datos;
}


/* --- Mi perfil: { nombre_completo, correo, telefono, direccion } --- */
function validarPerfil(object $req): void
{
    $body = $req->body;
    $datos = [
        'nombre_completo' => limpiar($body['nombre_completo'] ?? ''),
        'correo' => mb_strtolower(limpiar($body['correo'] ?? '')),
        'telefono' => limpiar($body['telefono'] ?? ''),
        'direccion' => limpiar($body['direccion'] ?? '')
    ];
    $campos = [];

    revisarContacto($datos, $campos);

    if (count($campos) > 0) rechazar($campos);

    $req->body = $datos;
}


/* --- Cambio de contraseña: { clave_actual, clave_nueva, confirmacion } --- */
function validarClave(object $req): void
{
    $claveActual = texto($req->body['clave_actual'] ?? '');
    $claveNueva = texto($req->body['clave_nueva'] ?? '');
    $confirmacion = texto($req->body['confirmacion'] ?? '');
    $campos = [];

    if ($claveActual === '') $campos['clave_actual'] = 'Escribí tu contraseña actual.';

    if (claveInvalida($claveNueva)) $campos['clave_nueva'] = 'La contraseña tiene que tener entre 6 y 72 caracteres.';
    elseif ($claveNueva === $claveActual) $campos['clave_nueva'] = 'La contraseña nueva tiene que ser distinta de la actual.';

    if ($confirmacion !== $claveNueva) $campos['confirmacion'] = 'Las contraseñas no coinciden.';

    if (count($campos) > 0) rechazar($campos);

    $req->body = ['clave_actual' => $claveActual, 'clave_nueva' => $claveNueva, 'confirmacion' => $confirmacion];
}
