<?php
/* ==========================================================================
   index.php - Punto de entrada del backend PHP · Biblioteca Invaluable (Grupo 5)
   Circuito:  index.php ➔ Router ➔ Controller ➔ Model ➔ MySQL (PDO)

   Se levanta desde backend/ con:
       php -S localhost:3001 -t ../frontend index.php
   y el panel se abre en http://localhost:3001 (igual que con Express).
   ========================================================================== */

declare(strict_types=1);

// 0. Ruta pedida: lo que NO es /api son archivos del frontend (HTML, CSS, JS)
$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$inicioApi = strpos($ruta . '/', '/api/');

if ($inicioApi === false) {
    if (PHP_SAPI === 'cli-server') return false;   // el servidor de PHP manda el archivo de ../frontend
    http_response_code(404);
    exit;
}

$rutaApi = substr($ruta, $inicioApi + 4) ?: '/';   // "/api/libros/5" → "/libros/5"

// 1. Configuración (frena todo si falta algo en el .env), router y rutas
require_once __DIR__ . '/src/config/config.php';
require_once __DIR__ . '/src/routes/Router.php';

// 2. Errores: nunca se muestran en la respuesta (romperían el JSON), se anotan en la consola
ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if ($nivel & (E_DEPRECATED | E_USER_DEPRECATED)) return false;
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

// Error no previsto (el "middleware de errores" de 4 parámetros de Express)
set_exception_handler(function (Throwable $error): void {
    error_log('Error no controlado: ' . $error->getMessage() . " ({$error->getFile()}:{$error->getLine()})");
    responder(500, ['error' => 'Error interno del servidor']);
});

// 3. CORS: el frontend puede consumir la API aunque venga de otro origen (Live Server)
header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET,HEAD,PUT,PATCH,POST,DELETE');
    header('Access-Control-Allow-Headers: ' . ($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? 'Content-Type, Authorization'));
    http_response_code(204);
    exit;
}

// 4. Cuerpo JSON → array (lo que hacía express.json())
$cuerpo = [];
$crudo = file_get_contents('php://input');
if ($crudo !== '' && str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')) {
    $cuerpo = json_decode($crudo, true);
    if (!is_array($cuerpo)) {
        responder(400, ['error' => 'El cuerpo de la petición no es un JSON válido.']);
    }
}

// 5. Header Authorization (Apache a veces lo esconde: se busca en todos lados)
$autorizacion = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if ($autorizacion === '' && function_exists('getallheaders')) {
    $autorizacion = array_change_key_case(getallheaders())['authorization'] ?? '';
}

// 6. La petición ($req): lo que en Express era req
$req = new stdClass();
$req->metodo = $_SERVER['REQUEST_METHOD'] === 'HEAD' ? 'GET' : $_SERVER['REQUEST_METHOD'];
$req->query = $_GET;
$req->body = $cuerpo;
$req->params = [];
$req->autorizacion = $autorizacion;
$req->ip = $_SERVER['REMOTE_ADDR'] ?? '';

// 7. Rutas de la API
$app = new Router();
$app->montar('/auth', require __DIR__ . '/src/routes/auth.routes.php');
$app->montar('/perfil', require __DIR__ . '/src/routes/perfil.routes.php');
$app->montar('/dashboard', require __DIR__ . '/src/routes/dashboard.routes.php');
$app->montar('/usuarios', require __DIR__ . '/src/routes/usuarios.routes.php');
$app->montar('/cursos', require __DIR__ . '/src/routes/cursos.routes.php');
$app->montar('/prestamos', require __DIR__ . '/src/routes/prestamos.routes.php');
$app->montar('/libros', require __DIR__ . '/src/routes/libros.routes.php');

// 8. Ruta de la API que no existe → 404
if (!$app->despachar($req, $rutaApi)) {
    responder(404, ['error' => 'Ruta no encontrada']);
}
