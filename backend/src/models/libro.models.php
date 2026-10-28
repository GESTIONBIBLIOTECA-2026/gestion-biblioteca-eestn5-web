<?php
/* ==========================================================================
   libro.models.php - Catálogo de libros (consultas) · Grupo 6
   El stock sale de vista_stock_libros (cuenta los ejemplares de cada título).
   Altas, cambios, stock y bajas: inventario.models.php
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';

// Columnas de un libro del catálogo (stock_reservable = disponibles - reservados)
const SQL_LIBRO_COLUMNAS = "v.libro_id AS id, v.titulo, v.isbn, v.autores, v.categoria, l.categoria_id,
    ed.nombre AS editorial, l.anio_publicacion AS anio, l.portada_url, v.ejemplares_totales, v.ejemplares_disponibles,
    v.cantidad_reservada, v.stock_reservable";

const SQL_LIBRO_DESDE = "FROM vista_stock_libros v JOIN libros l ON l.id = v.libro_id
    LEFT JOIN editoriales ed ON ed.id = l.editorial_id";

// Filtro de disponibilidad → condición SQL (texto fijo, nunca datos del usuario)
const DISPONIBILIDAD = [
    '' => 'TRUE',
    'disponible' => 'v.stock_reservable > 0',
    'agotado' => 'v.stock_reservable = 0'
];

// Catálogo ordenado por título, con buscador (título o autor), categoría y disponibilidad
function getCatalogoLibros(array $f): array
{
    $sql = "SELECT " . SQL_LIBRO_COLUMNAS . " " . SQL_LIBRO_DESDE . "
            WHERE (v.titulo LIKE ? OR v.autores LIKE ?)
              AND (? = 0 OR l.categoria_id = ?)
              AND " . DISPONIBILIDAD[$f['disponibilidad']] . "
            ORDER BY v.titulo";
    return consultar($sql, ["%{$f['texto']}%", "%{$f['texto']}%", $f['categoria'], $f['categoria']]);
}

function getLibroPorId(int $id): ?array
{
    return consultar("SELECT " . SQL_LIBRO_COLUMNAS . " " . SQL_LIBRO_DESDE . " WHERE v.libro_id = ?", [$id])[0] ?? null;
}

// Números del catálogo: títulos activos y ejemplares según su estado
function getResumenCatalogo(): array
{
    $sql = "SELECT
            (SELECT COUNT(*) FROM libros WHERE activo = TRUE) AS titulos,
            COALESCE(SUM(e.estado <> 'baja'), 0) AS ejemplares,
            COALESCE(SUM(e.estado = 'disponible'), 0) AS disponibles,
            COALESCE(SUM(e.estado = 'prestado'), 0) AS prestados,
            COALESCE(SUM(e.estado IN ('en_reparacion', 'extraviado', 'baja')), 0) AS fuera_de_servicio
        FROM ejemplares e JOIN libros l ON l.id = e.libro_id AND l.activo = TRUE";
    return consultar($sql)[0];
}

function getCategorias(): array
{
    return consultar("SELECT id, nombre FROM categorias ORDER BY nombre");
}
