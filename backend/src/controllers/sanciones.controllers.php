<?php
/* ==========================================================================
   sanciones.controllers.php - Historial activo y estado de la cuenta · Grupo 5
   Suspender, habilitar (o reactivar) y dar de baja, con sus reglas de negocio.
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/usuario.models.php';
require_once __DIR__ . '/../models/sancion.models.php';


// Usuario + historial activo + resumen (lo comparten la ficha del admin y el inicio del alumno/docente)
function armarHistorial(int $id): ?array
{
    $usuario = getUsuarioPorId($id);
    if (!$usuario) return null;

    ['prestamos' => $prestamos, 'sanciones' => $sanciones] = getHistorial($id);

    // Resumen con HOFs (array_filter es el .filter de JS)
    $cuantos = fn(callable $condicion) => count(array_filter($prestamos, $condicion));
    $resumen = [
        'entregas' => $cuantos(fn($p) => $p['fecha_entrega'] !== null),
        'devoluciones' => $cuantos(fn($p) => $p['estado'] === 'devuelto'),
        'atrasos' => $cuantos(fn($p) => $p['dias_atraso'] > 0),
        'en_curso' => $cuantos(fn($p) => in_array($p['estado'], ['prestado', 'vencido'], true)),
        'reservas' => $cuantos(fn($p) => $p['estado'] === 'pendiente')
    ];

    return ['usuario' => $usuario, 'resumen' => $resumen, 'prestamos' => $prestamos, 'sanciones' => $sanciones];
}


/* --- GET /api/usuarios/:id/historial   Historial activo de cualquier usuario (admin) --- */
function getHistorialUsuario(object $req): void
{
    try {
        $historial = armarHistorial($req->id);
    } catch (Throwable $error) {
        error500($error, 'No se pudo cargar el historial');
    }
    if (!$historial) responder(404, ['error' => 'No hay ningún usuario con ese ID.']);
    responder(200, ['payload' => $historial]);
}

/* --- GET /api/perfil/historial   Mi cuenta: el historial del usuario logueado (alumno o docente) --- */
function getMiHistorial(object $req): void
{
    try {
        $historial = armarHistorial($req->usuario['id']);
    } catch (Throwable $error) {
        error500($error, 'No se pudo cargar tu cuenta');
    }
    responder(200, ['payload' => $historial]);
}


/* --- PATCH /api/usuarios/:id/estado   Body: { estado: "suspendido" | "habilitado" | "baja", motivo } --- */
function patchEstado(object $req): void
{
    ['estado' => $estado, 'motivo' => $motivo] = $req->body;
    $adminId = $req->usuario['id'];

    if ($req->id === $adminId && $estado !== 'habilitado') {
        responder(409, ['error' => 'No podés suspender ni dar de baja tu propio usuario.']);
    }

    try {
        $usuario = getUsuarioPorId($req->id);
        if (!$usuario) responder(404, ['error' => 'No hay ningún usuario con ese ID.']);

        $nombre = $usuario['nombre_completo'];

        // --- Suspender ---
        if ($estado === 'suspendido') {
            if ($usuario['estado'] === 'baja') responder(409, ['error' => 'El usuario está dado de baja. Reactivalo primero.']);
            if ($usuario['condicion'] === 'suspendido') responder(409, ['error' => 'El usuario ya está suspendido.']);

            suspenderUsuario($req->id, $motivo, $adminId);
            responder(200, ['mensaje' => "Se suspendió a $nombre: no puede pedir libros hasta que lo habilites."]);
        }

        // --- Habilitar / reactivar ---
        if ($estado === 'habilitado') {
            if ($usuario['condicion'] === 'habilitado') responder(409, ['error' => 'El usuario ya está habilitado.']);

            habilitarUsuario($req->id);
            $aviso = $usuario['condicion'] === 'atraso' ? ' Igual tiene un préstamo atrasado: no va a poder pedir libros hasta devolverlo.' : '';
            responder(200, ['mensaje' => "Se habilitó a $nombre.$aviso"]);
        }

        // --- Dar de baja (lógica: no se borra, queda el historial) ---
        if ($usuario['estado'] === 'baja') responder(409, ['error' => 'El usuario ya está dado de baja.']);
        if ($usuario['prestamos_en_curso'] > 0) {
            responder(409, ['error' => "No se puede dar de baja: tiene {$usuario['prestamos_en_curso']} libro(s) sin devolver."]);
        }

        $canceladas = darDeBajaUsuario($req->id, $adminId);
        $reservas = $canceladas > 0 ? " Se cancelaron $canceladas reserva(s) pendiente(s)." : '';
        responder(200, ['mensaje' => "Se dio de baja a $nombre.$reservas"]);

    } catch (Throwable $error) {
        error500($error, 'No se pudo cambiar el estado del usuario');
    }
}
