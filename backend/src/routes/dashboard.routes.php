<?php
/* ==========================================================================
   dashboard.routes.php - Rutas del Inicio  (/api/dashboard)
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/dashboard.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';

$router = new Router();

$router->get('/', verificarAdmin(...), getMetricasDashboard(...));   // GET /api/dashboard

return $router;
