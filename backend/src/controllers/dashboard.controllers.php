<?php
/* ==========================================================================
   dashboard.controllers.php - Controlador del Inicio (index.html) · Grupo 5
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/dashboard.models.php';

/* --- GET /api/dashboard → { payload: { libros_total, prestamos_activos, alumnos_morosos } } --- */
function getMetricasDashboard(object $req): void
{
    try {
        $metricas = getMetricas();
    } catch (Throwable $error) {
        error500($error, 'No se pudieron cargar las métricas del inicio');
    }
    responder(200, ['payload' => $metricas]);
}
