<?php
/* ==========================================================================
   predicados.php - Limpieza y funciones predicado de validación
   Los usan validaciones.middleware.php (URL / query) y formularios.middleware.php (body).
   Cada predicado responde una pregunta con true / false: esDniValido("30111222") → true
   ========================================================================== */

require_once __DIR__ . '/../routes/Router.php';

// Cualquier dato → texto (como String() de JS): null → "", true → "true", [1,2] → "1,2"
function texto(mixed $valor): string
{
    return match (true) {
        $valor === null => '',
        is_bool($valor) => $valor ? 'true' : 'false',
        is_array($valor) => implode(',', array_map('texto', $valor)),
        default => (string) $valor
    };
}

// Cualquier dato → número (como Number() de JS): "12" → 12, "" → 0, "abc" → NAN
function aNumero(mixed $valor): int|float
{
    if (is_int($valor) || is_float($valor)) return $valor;
    if (is_bool($valor)) return (int) $valor;
    if ($valor === null) return 0;
    if (!is_string($valor)) return NAN;

    $valor = trim($valor);
    if ($valor === '') return 0;
    return is_numeric($valor) ? $valor + 0 : NAN;
}

// ¿Es un número entero? (3 y 3.0 sí; 3.5 y NAN no)
function esEntero(int|float $numero): bool
{
    return is_int($numero) || (is_finite($numero) && floor($numero) == $numero);
}

// Limpieza: espacios de más afuera y "30.111.222" → "30111222"
function limpiar(mixed $valor): string
{
    return trim(preg_replace('/[\s\p{Z}]+/u', ' ', texto($valor)) ?? '');
}

function limpiarDni(mixed $dni): string
{
    return preg_replace('/[.\s]/u', '', texto($dni)) ?? '';
}

// Funciones predicado: preguntan "¿es válido?" y devuelven true / false
function esDniValido(mixed $dni): bool      { return preg_match('/^\d{7,8}$/D', texto($dni)) === 1; }
function esNombreValido(mixed $nombre): bool { return preg_match("/^\\p{L}[\\p{L} .'-]*$/uD", texto($nombre)) === 1; }
function esCorreoValido(mixed $correo): bool { return preg_match('/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/uD', texto($correo)) === 1; }
function esTelefonoValido(mixed $tel): bool  { return preg_match('/^[\d\s()+-]{6,30}$/uD', texto($tel)) === 1; }

// Responde 400 con el primer error y todos los campos marcados: { error, campos }
function rechazar(array $campos): never
{
    responder(400, [
        'error' => count($campos) === 1 ? reset($campos) : 'Revisá los campos marcados.',
        'campos' => $campos
    ]);
}
