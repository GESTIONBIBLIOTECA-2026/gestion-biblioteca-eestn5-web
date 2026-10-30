<?php
/* ==========================================================================
   ejemplar.models.php - Stock de ejemplares de un libro · Grupo 6
   Se usan DENTRO de una transacción (conTransaccion).
   FOR UPDATE bloquea el libro: dos cambios de stock no se pisan.
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/errores.models.php';

// Bloquea la fila del libro hasta el commit (y avisa si no existe o ya está de baja)
function bloquearLibro(int $libroId): array
{
    $libros = consultar("SELECT id, titulo FROM libros WHERE id = ? AND activo = TRUE FOR UPDATE", [$libroId]);
    if (!$libros) throw noExiste('No hay ningún libro activo con ese ID.');
    return $libros[0];
}

// ¿Quedan ejemplares libres en el estante? (los reservados ya los separó alguien)
function exigirStock(array $libro, int $cantidad): void
{
    $stock = consultar(
        "SELECT (SELECT COUNT(*) FROM ejemplares WHERE libro_id = ? AND estado = 'disponible') AS disponibles,
                (SELECT COALESCE(SUM(cantidad), 0) FROM pedidos WHERE libro_id = ? AND estado = 'pendiente') AS reservados",
        [$libro['id'], $libro['id']]
    )[0];
    $libres = $stock['disponibles'] - $stock['reservados'];
    if ($libres < $cantidad) {
        $detalle = $stock['reservados'] > 0 ? " ({$stock['reservados']} reservado/s para retirar)" : '';
        throw conflicto("No quedan ejemplares libres de «{$libro['titulo']}»: hay " . max($libres, 0) . "$detalle.");
    }
}
