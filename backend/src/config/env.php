<?php
/* ==========================================================================
   env.php - Infraestructura: variables de entorno del archivo .env
   Fail-fast: si falta una variable obligatoria, el backend NO responde
   y avisa cuál es (nada de "localhost" ni "root" por defecto).
   (Equivale a dotenv + env.js de la versión Node)
   ========================================================================== */

// 1. Carpeta backend/ (así el .env y el ca.pem se encuentran aunque se arranque desde otra carpeta)
define('CARPETA_BACKEND', dirname(__DIR__, 2) . DIRECTORY_SEPARATOR);

// 2. Lector del .env: cada línea CLAVE=valor se carga en getenv() / $_ENV
function cargarEnv(string $archivo): void
{
    if (!is_file($archivo)) return;

    foreach (file($archivo, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;

        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));

        // Valor entre comillas: se sacan. Sin comillas: se corta un comentario " # ..."
        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && str_ends_with($valor, $valor[0])) {
            $valor = substr($valor, 1, -1);
        } else {
            $valor = trim(preg_replace('/\s+#.*$/', '', $valor));
        }

        // Como dotenv: si la variable ya existe en el sistema, no se pisa
        if (getenv($clave) === false) {
            putenv("$clave=$valor");
            $_ENV[$clave] = $valor;
        }
    }
}

cargarEnv(CARPETA_BACKEND . '.env');

// 3. Variables sin las que el sistema no puede funcionar (Aiven Cloud + sesión)
$obligatorias = ['JWT_SECRET', 'DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASSWORD', 'DB_NAME'];
$faltantes = array_values(array_filter($obligatorias, fn($nombre) => (string) getenv($nombre) === ''));

if (count($faltantes) > 0) {
    $mensaje = 'Faltan variables en backend/.env: ' . implode(', ', $faltantes) . ' (mirá .env.example)';
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "❌ $mensaje\n");
        exit(1);
    }
    error_log("❌ $mensaje");
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Sesión (token JWT)
define('JWT_SECRET', getenv('JWT_SECRET'));
define('JWT_DURACION', 8 * 60 * 60);   // 8 horas, en segundos

// 5. Base de datos MySQL en Aiven (exige SSL: solo se apaga con DB_SSL=false)
define('DB', [
    'host' => getenv('DB_HOST'),
    'port' => (int) getenv('DB_PORT'),
    'user' => getenv('DB_USER'),
    'password' => getenv('DB_PASSWORD'),
    'database' => getenv('DB_NAME'),
    'ssl' => getenv('DB_SSL') !== 'false',
    'caPath' => getenv('DB_CA_PATH') ? CARPETA_BACKEND . getenv('DB_CA_PATH') : '',
    'zonaHoraria' => getenv('DB_ZONA_HORARIA') ?: '-03:00'
]);
