<?php
/* ==========================================================================
   limiteIntentos.middleware.php - Freno contra adivinar contraseñas
   Después de 5 logins fallidos, esa IP queda bloqueada 5 minutos.
   En Node los intentos vivían en la memoria del servidor; en PHP cada
   petición arranca de cero, así que se guardan en un archivo temporal.
   ========================================================================== */

require_once __DIR__ . '/../routes/Router.php';

const MAX_INTENTOS = 5;
const BLOQUEO_SEGUNDOS = 5 * 60;

// Abre el archivo de intentos (ip → { cantidad, hasta }), deja cambiarlo y lo guarda
function modificarIntentos(callable $cambio): array
{
    $archivo = fopen(sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'biblioteca-intentos-login.json', 'c+');
    flock($archivo, LOCK_EX);

    $intentos = json_decode(stream_get_contents($archivo) ?: '{}', true) ?: [];
    $nuevos = $cambio($intentos);

    if ($nuevos !== null) {
        ftruncate($archivo, 0);
        rewind($archivo);
        fwrite($archivo, json_encode($nuevos));
    }
    flock($archivo, LOCK_UN);
    fclose($archivo);
    return $intentos;
}

function limitarIntentos(object $req): void
{
    $registro = modificarIntentos(fn() => null)[$req->ip] ?? null;

    // 1. ¿Está bloqueada?
    if ($registro && ($registro['hasta'] ?? 0) > time()) {
        responder(429, ['error' => 'Demasiados intentos fallidos. Esperá unos minutos y probá de nuevo.']);
    }

    // 2. Cuando termina la respuesta: 401 suma un intento, 200 los borra
    register_shutdown_function(function () use ($req, $registro): void {
        $status = http_response_code();
        if ($status !== 200 && $status !== 401) return;

        modificarIntentos(function (array $intentos) use ($req, $registro, $status): array {
            if ($status === 200) {
                unset($intentos[$req->ip]);
                return $intentos;
            }
            $cantidad = $registro && empty($registro['hasta']) ? $registro['cantidad'] + 1 : 1;
            $intentos[$req->ip] = ['cantidad' => $cantidad, 'hasta' => $cantidad >= MAX_INTENTOS ? time() + BLOQUEO_SEGUNDOS : null];
            return $intentos;
        });
    });
}
