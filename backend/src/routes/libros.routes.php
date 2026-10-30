<?php
/* ==========================================================================
   libros.routes.php - Rutas del Catálogo e Inventario  (/api/libros) · Grupo 6
   Leer el catálogo: cualquier usuario con sesión (el alumno busca y reserva).
   Cargar, modificar, cambiar stock y dar de baja: solo el administrador.
   ========================================================================== */

require_once __DIR__ . '/Router.php';
require_once __DIR__ . '/../controllers/libros.controllers.php';
require_once __DIR__ . '/../middlewares/auth.middleware.php';
require_once __DIR__ . '/../middlewares/validaciones.middleware.php';
require_once __DIR__ . '/../middlewares/libros.middleware.php';

$router = new Router();

$router->get('/', verificarSesion(...), validarFiltrosLibros(...), getCatalogo(...));                 // catálogo + buscador
$router->get('/resumen', verificarAdmin(...), getResumen(...));                                      // números del inventario
$router->get('/categorias', verificarSesion(...), getListaCategorias(...));                          // para los selectores
$router->get('/:id', verificarSesion(...), validarID(...), getLibro(...));                           // un libro con su stock
$router->post('/', verificarAdmin(...), validarLibro(...), postLibro(...));                          // alta
$router->put('/:id', verificarAdmin(...), validarID(...), validarLibro(...), putLibro(...));         // modificación
$router->patch('/:id/stock', verificarAdmin(...), validarID(...), validarStock(...), patchStock(...)); // sumar / sacar ejemplares
$router->delete('/:id', verificarAdmin(...), validarID(...), deleteLibro(...));                      // baja lógica

return $router;
