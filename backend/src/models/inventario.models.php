<?php
/* ==========================================================================
   inventario.models.php - Alta, modificación, stock y baja de libros · Grupo 6
   Cada operación toca varias tablas (libros, autores, editoriales, ejemplares):
   por eso va en una transacción: se guarda todo o nada.
   ========================================================================== */

require_once __DIR__ . '/../database/db.php';
require_once __DIR__ . '/ejemplar.models.php';
require_once __DIR__ . '/errores.models.php';

// ID de una fila por nombre; si no existe, la crea (editoriales y autores)
function idPorNombre(string $tabla, string $columna, string $nombre): int
{
    $filas = consultar("SELECT id FROM $tabla WHERE $columna = ? LIMIT 1", [$nombre]);
    if ($filas) return $filas[0]['id'];
    return ejecutar("INSERT INTO $tabla ($columna) VALUES (?)", [$nombre])['insertId'];
}

// Editorial, ISBN repetido y autores (lo comparten el alta y la modificación)
function guardarDatos(?int $id, array $d): int
{
    if ($d['isbn'] !== null) {
        $repetido = consultar("SELECT titulo FROM libros WHERE isbn = ? AND id <> ?", [$d['isbn'], $id ?? 0]);
        if ($repetido) throw conflicto("Ese ISBN ya es de «{$repetido[0]['titulo']}».");
    }
    $editorialId = $d['editorial'] !== null ? idPorNombre('editoriales', 'nombre', $d['editorial']) : null;
    $datos = [$d['isbn'], $d['titulo'], $d['categoria_id'], $editorialId, $d['anio'], $d['portada_url']];

    if ($id) {
        $r = ejecutar(
            "UPDATE libros SET isbn = ?, titulo = ?, categoria_id = ?, editorial_id = ?, anio_publicacion = ?, portada_url = ? WHERE id = ? AND activo = TRUE",
            [...$datos, $id]);
        if ($r['affectedRows'] === 0) throw noExiste('No hay ningún libro activo con ese ID.');
        ejecutar("DELETE FROM libros_autores WHERE libro_id = ?", [$id]);
    } else {
        $id = ejecutar(
            "INSERT INTO libros (isbn, titulo, categoria_id, editorial_id, anio_publicacion, portada_url) VALUES (?, ?, ?, ?, ?, ?)", $datos)['insertId'];
    }
    foreach ($d['autores'] as $autor) {
        $autorId = idPorNombre('autores', 'nombre_completo', $autor);
        ejecutar("INSERT IGNORE INTO libros_autores (libro_id, autor_id) VALUES (?, ?)", [$id, $autorId]);
    }
    return $id;
}

// Suma ejemplares nuevos con código de inventario INV-<libro>-<número>
function agregarEjemplares(int $libroId, int $cantidad): void
{
    $total = consultar("SELECT COUNT(*) AS total FROM ejemplares WHERE libro_id = ?", [$libroId])[0]['total'];

    $valores = [];
    for ($i = 1; $i <= $cantidad; $i++) {
        array_push($valores, $libroId, sprintf('INV-%d-%03d', $libroId, $total + $i));
    }
    $filas = implode(', ', array_fill(0, $cantidad, '(?, ?)'));   // (?, ?), (?, ?), ...
    ejecutar("INSERT INTO ejemplares (libro_id, codigo_inventario) VALUES $filas", $valores);
}

function crearLibro(array $datos): int
{
    return conTransaccion(function () use ($datos) {
        $id = guardarDatos(null, $datos);
        agregarEjemplares($id, $datos['cantidad']);
        return $id;
    });
}

function actualizarLibro(int $id, array $datos): void
{
    conTransaccion(fn() => guardarDatos($id, $datos));
}

// Stock: cambio > 0 suma ejemplares; cambio < 0 da de baja ejemplares que están en el estante
function cambiarStock(int $id, int $cambio): void
{
    conTransaccion(function () use ($id, $cambio) {
        $libro = bloquearLibro($id);
        if ($cambio > 0) return agregarEjemplares($id, $cambio);

        exigirStock($libro, -$cambio);   // no se pueden sacar los que están reservados
        ejecutar(
            "UPDATE ejemplares SET estado = 'baja' WHERE libro_id = ? AND estado = 'disponible' ORDER BY id DESC LIMIT ?",
            [$id, -$cambio]);
    });
}

// Baja lógica del título: solo si no tiene ejemplares afuera ni reservas
function darDeBajaLibro(int $id): string
{
    return conTransaccion(function () use ($id) {
        $libro = bloquearLibro($id);
        $activos = consultar(
            "SELECT COUNT(*) AS activos FROM pedidos WHERE libro_id = ? AND estado IN ('pendiente', 'prestado', 'vencido')", [$id]
        )[0]['activos'];
        if ($activos > 0) throw conflicto("«{$libro['titulo']}» tiene $activos préstamo(s) o reserva(s) sin cerrar.");

        ejecutar("UPDATE libros SET activo = FALSE WHERE id = ?", [$id]);
        return $libro['titulo'];
    });
}
