<?php
/* ==========================================================================
   jwt.php - Token de sesión JWT (HS256), lo que hacía "jsonwebtoken"
   Un token tiene 3 partes separadas por punto:  cabecera.datos.firma
   La firma se hace con JWT_SECRET: si alguien cambia los datos, no coincide.
   Es el mismo formato que usa la app en Node, así que los tokens sirven en las dos.
   ========================================================================== */

require_once __DIR__ . '/../config/config.php';

function base64url(string $texto): string
{
    return rtrim(strtr(base64_encode($texto), '+/', '-_'), '=');
}

function desdeBase64url(string $texto): string
{
    return base64_decode(strtr($texto, '-_', '+/'), true) ?: '';
}

// jwt.sign(datos, JWT_SECRET, { expiresIn: "8h" })
function firmarToken(array $datos): string
{
    $ahora = time();
    $cabecera = base64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $cuerpo = base64url(json_encode($datos + ['iat' => $ahora, 'exp' => $ahora + JWT_DURACION], JSON_UNESCAPED_UNICODE));
    $firma = base64url(hash_hmac('sha256', "$cabecera.$cuerpo", JWT_SECRET, true));
    return "$cabecera.$cuerpo.$firma";
}

// jwt.verify(token, JWT_SECRET): devuelve los datos o lanza una excepción
function verificarToken(string $token): array
{
    $partes = explode('.', $token);
    if (count($partes) !== 3) throw new RuntimeException('Token mal armado');

    [$cabecera, $cuerpo, $firma] = $partes;
    $esperada = base64url(hash_hmac('sha256', "$cabecera.$cuerpo", JWT_SECRET, true));

    $alg = json_decode(desdeBase64url($cabecera), true)['alg'] ?? '';
    if ($alg !== 'HS256' || !hash_equals($esperada, $firma)) throw new RuntimeException('Firma inválida');

    $datos = json_decode(desdeBase64url($cuerpo), true);
    if (!is_array($datos)) throw new RuntimeException('Token mal armado');
    if (isset($datos['exp']) && $datos['exp'] <= time()) throw new RuntimeException('Token vencido');

    return $datos;
}
