<?php
/* ==========================================================================
   auth.middleware.php - "Molinete" de las rutas protegidas
   - verificarSesion: cualquier usuario con sesión (alumno, docente o admin)
   - verificarAdmin:  solo el administrador (panel de gestión)
   Si el token no es válido o el usuario ya no puede entrar, la petición se
   rebota y nunca llega al controlador.
   ========================================================================== */

require_once __DIR__ . '/../routes/Router.php';
require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../models/usuario.models.php';

// Regla de acceso (la usa también el login): nadie de baja entra, y el
// administrador además tiene que estar habilitado. Un alumno o docente
// suspendido SÍ entra: tiene que poder ver por qué está suspendido.
function puedeEntrar(array $usuario): bool
{
    return $usuario['estado'] !== 'baja' && ($usuario['rol'] !== 'administrador' || $usuario['estado'] === 'habilitado');
}


// $soloAdmin = true deja pasar únicamente al administrador
function verificar(object $req, bool $soloAdmin): void
{
    // 1. El frontend manda el header  Authorization: Bearer <token>
    $partes = explode(' ', $req->autorizacion);
    $tipo = $partes[0];
    $token = $partes[1] ?? '';

    if ($tipo !== 'Bearer' || $token === '') {
        responder(401, ['error' => 'Tenés que iniciar sesión.']);
    }

    try {
        // 2. El token dice QUIÉN es...
        $datos = verificarToken($token);

        // 3. ...y la base dice si TODAVÍA puede entrar (si le dan de baja, pierde el acceso al instante)
        $usuario = getSesion($datos['id'] ?? 0);
    } catch (Throwable $error) {
        responder(401, ['error' => 'La sesión venció. Iniciá sesión de nuevo.']);
    }

    if (!$usuario || !puedeEntrar($usuario)) {
        responder(401, ['error' => 'Tu usuario ya no tiene acceso. Iniciá sesión de nuevo.']);
    }

    // 4. Sesión válida, pero la sección es solo para la bibliotecaria
    if ($soloAdmin && $usuario['rol'] !== 'administrador') {
        responder(403, ['error' => 'Esta sección es solo para el administrador de la biblioteca.']);
    }

    $req->usuario = ['id' => $usuario['id'], 'nombre' => $usuario['nombre_completo'], 'rol' => $usuario['rol']];
}

function verificarSesion(object $req): void { verificar($req, false); }
function verificarAdmin(object $req): void  { verificar($req, true); }
