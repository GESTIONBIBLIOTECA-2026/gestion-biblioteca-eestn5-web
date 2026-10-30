<?php
/* ==========================================================================
   prestamos.controllers.php - Controlador de Préstamos (solo lectura)
   Registrar préstamos y devoluciones es del Grupo 4.
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/prestamo.models.php';

/* --- GET /api/prestamos?limite=5 --- */
function getPrestamos(object $req): void
{
    try {
        $prestamos = getUltimosPrestamos($req->limite);
    } catch (Throwable $error) {
        error500($error, 'No se pudieron cargar los préstamos');
    }
    responder(200, ['payload' => $prestamos]);
}
