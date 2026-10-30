<?php
/* ==========================================================================
   usuarios.routes.php - Rutas de usuarios  (/api/usuarios) · Grupo 5
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/usuarios.controllers.php';
require_once __DIR__ . '/../controllers/sanciones.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../middlewares/validaciones.middleware.php';
require_once __DIR__ . '/../middlewares/formularios.middleware.php';

$router = new Router();

$router->usar(verificarAdmin(...));                                                 // todas piden sesión de administrador

$router->get('/', validarBusqueda(...), getUsuarios(...));                          // búsqueda avanzada + listado
$router->get('/dni/:dni', validarDniParam(...), getUsuarioDni(...));                // ficha por DNI (Grupo 4)
$router->get('/:id', validarID(...), getUsuario(...));                              // datos de un usuario
$router->get('/:id/historial', validarID(...), getHistorialUsuario(...));           // historial activo
$router->post('/', validarUsuario(...), postUsuario(...));                          // alta
$router->put('/:id', validarID(...), validarUsuario(...), putUsuario(...));         // modificación
$router->patch('/:id/estado', validarID(...), validarEstado(...), patchEstado(...)); // suspender / habilitar / baja

return $router;
