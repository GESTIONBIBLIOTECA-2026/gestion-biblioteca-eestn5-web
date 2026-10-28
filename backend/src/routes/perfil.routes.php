<?php
/* ==========================================================================
   perfil.routes.php - Rutas de "Mi perfil" y "Mi cuenta"  (/api/perfil)
   Las usan TODOS los roles: alumno, docente y administrador.
   Siempre trabajan con el usuario del token, nunca con un ID que mande el navegador.
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/perfil.controllers.php';
require_once __DIR__ . '/../controllers/sanciones.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../middlewares/formularios.middleware.php';

$router = new Router();

$router->usar(verificarSesion(...));                          // todas piden sesión (cualquier rol)

$router->get('/', getMiPerfil(...));                          // GET /api/perfil
$router->get('/historial', getMiHistorial(...));              // GET /api/perfil/historial  (inicio del alumno/docente)
$router->put('/', validarPerfil(...), putMiPerfil(...));      // PUT /api/perfil
$router->put('/clave', validarClave(...), putMiClave(...));   // PUT /api/perfil/clave

return $router;
