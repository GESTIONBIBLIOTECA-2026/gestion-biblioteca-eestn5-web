<?php
/* ==========================================================================
   prestamos.routes.php - Rutas de préstamos  (/api/prestamos) · solo lectura
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/prestamos.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../middlewares/validaciones.middleware.php';

$router = new Router();

$router->get('/', verificarAdmin(...), validarLimite(...), getPrestamos(...));   // GET /api/prestamos?limite=5

return $router;
