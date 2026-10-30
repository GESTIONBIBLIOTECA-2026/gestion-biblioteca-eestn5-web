<?php
/* ==========================================================================
   libros.middleware.php - Validación del catálogo · Grupo 6
   Si un dato está mal responde 400 { error, campos } y NO se toca la base.
   ========================================================================== */

require_once __DIR__ . '/predicados.php';

function esEnteroEntre(int|float $valor, int $min, int $max): bool
{
    return esEntero($valor) && $valor >= $min && $valor <= $max;
}

function esIsbnValido(string $isbn): bool
{
    return preg_match('/^[\dXx-]{10,20}$/D', $isbn) === 1;
}

// Texto vacío → null (para guardar NULL en la base)
function nuloSiVacio(string $texto): ?string
{
    return $texto === '' ? null : $texto;
}

// JS: un valor "vacío" (null, "", 0, false) cuenta como falso; "0" en texto NO
function tieneValor(mixed $valor): bool
{
    return !($valor === null || $valor === '' || $valor === 0 || $valor === 0.0 || $valor === false);
}

/* --- Buscador: ?texto=&categoria=&disponibilidad= --- */
function validarFiltrosLibros(object $req): void
{
    $disponibilidad = $req->query['disponibilidad'] ?? '';
    $categoria = aNumero($req->query['categoria'] ?? 0);

    $req->filtros = [
        'texto' => mb_substr(limpiar($req->query['texto'] ?? ''), 0, 100),
        'categoria' => is_nan((float) $categoria) ? 0 : $categoria,
        'disponibilidad' => $disponibilidad
    ];

    if (!in_array($disponibilidad, ['', 'disponible', 'agotado'], true)) {
        responder(400, ['error' => 'La disponibilidad tiene que ser disponible o agotado.']);
    }
}

/* --- Alta y modificación: datos del libro y portada_url opcional (hasta 2000 caracteres) --- */
function validarLibro(object $req): void
{
    $body = $req->body;
    $autores = array_values(array_filter(
        array_map('trim', explode(',', limpiar($body['autores'] ?? ''))),   // "Borges, Bioy" → 2 autores
        fn($autor) => $autor !== ''
    ));
    $datos = [
        'titulo' => limpiar($body['titulo'] ?? ''),
        'autores' => $autores,
        'editorial' => nuloSiVacio(limpiar($body['editorial'] ?? '')),
        'isbn' => nuloSiVacio(limpiar($body['isbn'] ?? '')),
        'portada_url' => nuloSiVacio(limpiar($body['portada_url'] ?? '')),
        'categoria_id' => aNumero($body['categoria_id'] ?? NAN),
        'anio' => tieneValor($body['anio'] ?? null) ? aNumero($body['anio']) : null,
        'cantidad' => aNumero($body['cantidad'] ?? NAN)
    ];
    $campos = [];
    $largoTitulo = mb_strlen($datos['titulo']);

    if ($largoTitulo < 1 || $largoTitulo > 200) $campos['titulo'] = 'Escribí el título del libro.';
    if (count($autores) === 0 || count(array_filter($autores, fn($a) => mb_strlen($a) > 120)) > 0) {
        $campos['autores'] = 'Escribí al menos un autor (separados por coma).';
    }
    if ($datos['editorial'] !== null && mb_strlen($datos['editorial']) > 100) $campos['editorial'] = 'La editorial puede tener hasta 100 caracteres.';
    if ($datos['isbn'] !== null && !esIsbnValido($datos['isbn'])) $campos['isbn'] = 'El ISBN tiene que tener entre 10 y 20 números o guiones.';
    if (isset($body['portada_url']) && !is_string($body['portada_url'])) {
        $campos['portada_url'] = 'La imagen debe ingresarse como texto.';
    } elseif ($datos['portada_url'] !== null && mb_strlen($datos['portada_url']) > 2000) {
        $campos['portada_url'] = 'La imagen puede tener hasta 2000 caracteres.';
    }
    if (!esEnteroEntre($datos['categoria_id'], 1, 65535)) $campos['categoria_id'] = 'Elegí una categoría.';
    if ($datos['anio'] !== null && !esEnteroEntre($datos['anio'], 1450, (int) date('Y'))) $campos['anio'] = 'El año no es válido.';

    // La cantidad solo se pide en el alta (después se cambia con "Actualizar stock")
    if ($req->metodo === 'POST' && !esEnteroEntre($datos['cantidad'], 1, 50)) $campos['cantidad'] = 'La cantidad tiene que ser entre 1 y 50 ejemplares.';

    if (count($campos) > 0) rechazar($campos);

    // Ya validados: pasan a entero para la base
    $datos['categoria_id'] = (int) $datos['categoria_id'];
    $datos['anio'] = $datos['anio'] === null ? null : (int) $datos['anio'];
    $datos['cantidad'] = esEntero($datos['cantidad']) ? (int) $datos['cantidad'] : 0;
    $req->body = $datos;
}

/* --- Stock: { cambio } → +3 suma ejemplares, -2 da de baja ejemplares --- */
function validarStock(object $req): void
{
    $cambio = aNumero($req->body['cambio'] ?? NAN);

    if (!esEnteroEntre(abs($cambio), 1, 50)) {
        rechazar(['cambio' => 'Escribí cuántos ejemplares sumar (ej: 3) o sacar (ej: -2), hasta 50.']);
    }
    $req->body = ['cambio' => (int) $cambio];
}
