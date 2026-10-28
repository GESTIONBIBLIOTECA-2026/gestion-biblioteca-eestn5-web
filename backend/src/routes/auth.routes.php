<?php
/* ==========================================================================
   auth.routes.php - Rutas de autenticación  (/api/auth)
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/auth.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../middlewares/validaciones.middleware.php';
require_once __DIR__ . '/../middlewares/limiteIntentos.middleware.php';

$router = new Router();

$router->post('/login', limitarIntentos(...), validarLogin(...), postLogin(...));   // POST /api/auth/login
$router->get('/perfil', verificarSesion(...), getPerfil(...));                      // GET  /api/auth/perfil (cualquier rol)

return $router;
