<?php
/* ==========================================================================
   libros.controllers.php - Controlador del Catálogo e Inventario · Grupo 6
   Las reglas del stock las controla el Modelo dentro de la transacción
   (lanza ErrorNegocio) y acá se traducen a 404 / 409 con responderError.
   ========================================================================== */

require_once __DIR__ . '/respuestas.php';
require_once __DIR__ . '/../models/libro.models.php';
require_once __DIR__ . '/../models/inventario.models.php';

/* --- GET /api/libros?texto=&categoria=&disponibilidad= --- */
function getCatalogo(object $req): void
{
    try {
        $libros = getCatalogoLibros($req->filtros);
    } catch (Throwable $error) {
        error500($error, 'No se pudo cargar el catálogo');
    }
    responder(200, ['payload' => $libros]);
}

/* --- GET /api/libros/:id --- */
function getLibro(object $req): void
{
    try {
        $libro = getLibroPorId($req->id);
    } catch (Throwable $error) {
        error500($error, 'No se pudo consultar el libro');
    }
    if (!$libro) responder(404, ['error' => 'No hay ningún libro con ese ID.']);
    responder(200, ['payload' => $libro]);
}

/* --- GET /api/libros/resumen   y   GET /api/libros/categorias --- */
function getResumen(object $req): void
{
    try {
        $resumen = getResumenCatalogo();
    } catch (Throwable $error) {
        error500($error, 'No se pudo calcular el resumen del catálogo');
    }
    responder(200, ['payload' => $resumen]);
}

function getListaCategorias(object $req): void
{
    try {
        $categorias = getCategorias();
    } catch (Throwable $error) {
        error500($error, 'No se pudieron cargar las categorías');
    }
    responder(200, ['payload' => $categorias]);
}

/* --- POST /api/libros   Body: { titulo, autores, editorial, isbn, categoria_id, anio, cantidad } --- */
function postLibro(object $req): void
{
    try {
        $id = crearLibro($req->body);
    } catch (Throwable $error) {
        responderError($error, 'No se pudo guardar el libro');
    }
    responder(201, ['mensaje' => "Se cargó «{$req->body['titulo']}» con {$req->body['cantidad']} ejemplar(es).", 'payload' => ['id' => $id]]);
}

/* --- PUT /api/libros/:id   (mismo body, sin cantidad) --- */
function putLibro(object $req): void
{
    try {
        actualizarLibro($req->id, $req->body);
    } catch (Throwable $error) {
        responderError($error, 'No se pudieron guardar los cambios');
    }
    responder(200, ['mensaje' => "Se guardaron los cambios de «{$req->body['titulo']}»."]);
}

/* --- PATCH /api/libros/:id/stock   Body: { cambio } --- */
function patchStock(object $req): void
{
    $cambio = $req->body['cambio'];
    try {
        cambiarStock($req->id, $cambio);
    } catch (Throwable $error) {
        responderError($error, 'No se pudo actualizar el stock');
    }
    $texto = $cambio > 0 ? "Se sumaron $cambio ejemplar(es)." : 'Se dieron de baja ' . (-$cambio) . ' ejemplar(es).';
    responder(200, ['mensaje' => $texto]);
}

/* --- DELETE /api/libros/:id   (baja lógica: el historial de préstamos se conserva) --- */
function deleteLibro(object $req): void
{
    try {
        $titulo = darDeBajaLibro($req->id);
    } catch (Throwable $error) {
        responderError($error, 'No se pudo dar de baja el libro');
    }
    responder(200, ['mensaje' => "Se dio de baja «{$titulo}» del catálogo."]);
}
