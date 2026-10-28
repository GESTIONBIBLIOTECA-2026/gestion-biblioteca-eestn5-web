<?php
/* ==========================================================================
   validaciones.middleware.php - Validación de parámetros y consultas
   Frenan los datos mal cargados ANTES de llegar al controlador y a la base.
   Si algo está mal: responder(400). Si está bien: sigue el próximo paso.
   Los formularios (alta, perfil, contraseña) están en formularios.middleware.php
   ========================================================================== */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/predicados.php';

/* --- ID de la URL: /api/usuarios/:id --- */
function validarID(object $req): void
{
    $id = aNumero($req->params['id'] ?? null);

    if (!esEntero($id) || $id <= 0) {
        responder(400, ['error' => 'El ID tiene que ser un número entero mayor a 0.']);
    }

    $req->id = (int) $id;
}

/* --- DNI de la URL: /api/usuarios/dni/:dni --- */
function validarDniParam(object $req): void
{
    $req->dni = limpiarDni($req->params['dni'] ?? '');

    if (!esDniValido($req->dni)) {
        responder(400, ['error' => 'El DNI tiene que tener entre 7 y 8 números.']);
    }
}

/* --- Login: { dni, clave } --- */
function validarLogin(object $req): void
{
    $dni = limpiarDni($req->body['dni'] ?? null);
    $clave = texto($req->body['clave'] ?? '');

    if (!esDniValido($dni) || $clave === '') {
        responder(400, ['error' => 'Completá el DNI (7 u 8 números) y la contraseña.']);
    }

    $req->body = ['dni' => $dni, 'clave' => $clave];
}

/* --- ?limite= de los últimos préstamos (1 a 50, por defecto 5) --- */
function validarLimite(object $req): void
{
    $limite = aNumero($req->query['limite'] ?? 5);

    if (!esEntero($limite) || $limite < 1 || $limite > 50) {
        responder(400, ['error' => 'El límite tiene que ser un número entre 1 y 50.']);
    }

    $req->limite = (int) $limite;
}

/* --- Búsqueda: ?nombre=&dni=&curso=&filtro=&pagina= --- */
function validarBusqueda(object $req): void
{
    $filtro = $req->query['filtro'] ?? 'todos';
    $pagina = aNumero($req->query['pagina'] ?? 1);

    if (!in_array($filtro, FILTROS, true) || !esEntero($pagina) || $pagina < 1) {
        responder(400, ['error' => 'El filtro o la página no son válidos.']);
    }

    $req->filtros = [
        'nombre' => mb_substr(limpiar($req->query['nombre'] ?? ''), 0, 120),
        'dni' => mb_substr(limpiarDni($req->query['dni'] ?? ''), 0, 8),
        'curso' => mb_substr(limpiar($req->query['curso'] ?? ''), 0, 30),
        'filtro' => $filtro,
        'pagina' => (int) $pagina,
        'porPagina' => USUARIOS_POR_PAGINA
    ];
}

/* --- Cambio de estado: { estado, motivo } --- */
function validarEstado(object $req): void
{
    $estado = texto($req->body['estado'] ?? '');
    $motivo = mb_substr(limpiar($req->body['motivo'] ?? ''), 0, 255);

    if (!in_array($estado, ESTADOS, true)) {
        responder(400, ['error' => 'El estado tiene que ser habilitado, suspendido o baja.']);
    }

    if ($estado === 'suspendido' && mb_strlen($motivo) < 3) {
        rechazar(['motivo' => 'Escribí el motivo de la suspensión.']);
    }

    $req->body = ['estado' => $estado, 'motivo' => $motivo !== '' ? $motivo : null];
}
