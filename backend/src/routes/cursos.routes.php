<?php
/* ==========================================================================
   cursos.routes.php - Rutas de cursos  (/api/cursos)
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/cursos.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';

$router = new Router();

$router->get('/', verificarAdmin(...), getListaCursos(...));   // GET /api/cursos

return $router;
