<?php
/* ==========================================================================
   cursos.controllers.php - Controlador de Cursos · Grupo 5
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/curso.models.php';

/* --- GET /api/cursos --- */
function getListaCursos(object $req): void
{
    try {
        $cursos = getCursos();
    } catch (Throwable $error) {
        error500($error, 'No se pudieron cargar los cursos');
    }
    responder(200, ['payload' => $cursos]);
}
