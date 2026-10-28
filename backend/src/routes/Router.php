<?php
/* ==========================================================================
   Router.php - El "conmutador" de rutas (lo que en Node hacía Express)
   - $router->get("/:id", middleware1, middleware2, controlador)
   - $router->usar(middleware)        → como router.use(): corre en TODAS sus rutas
   - $app->montar("/api/libros", $router) → como app.use("/api/libros", router)
   Cada paso recibe $req. Un middleware que rebota llama a responder() y
   corta todo ahí (el controlador nunca se entera); si no rebota, sigue
   el próximo paso (eso es el next() de Express).
   ========================================================================== */

// Respuesta HTTP en JSON (res.status(...).json(...)) y fin de la petición
function responder(int $status, mixed $datos): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

final class Router
{
    private array $middlewares = [];   // los de usar()
    private array $rutas = [];         // [método, regex, pasos]
    private array $montados = [];      // [regex del prefijo, Router]

    public function usar(callable $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    public function get(string $ruta, callable ...$pasos): void    { $this->agregar('GET', $ruta, $pasos); }
    public function post(string $ruta, callable ...$pasos): void   { $this->agregar('POST', $ruta, $pasos); }
    public function put(string $ruta, callable ...$pasos): void    { $this->agregar('PUT', $ruta, $pasos); }
    public function patch(string $ruta, callable ...$pasos): void  { $this->agregar('PATCH', $ruta, $pasos); }
    public function delete(string $ruta, callable ...$pasos): void { $this->agregar('DELETE', $ruta, $pasos); }

    public function montar(string $prefijo, Router $router): void
    {
        $this->montados[] = ['#^' . preg_quote(rtrim($prefijo, '/'), '#') . '(/.*)?$#i', $router];
    }

    // "/:id/historial" → expresión regular que captura el parámetro "id"
    private function agregar(string $metodo, string $ruta, array $pasos): void
    {
        $segmentos = array_map(
            fn($s) => str_starts_with($s, ':') ? '(?P<' . substr($s, 1) . '>[^/]+)' : preg_quote($s, '#'),
            explode('/', trim($ruta, '/'))
        );
        $this->rutas[] = [$metodo, '#^/' . implode('/', $segmentos) . '$#i', $pasos];
    }

    // "/usuarios/" y "/usuarios" son la misma ruta (como en Express)
    private function normalizar(string $ruta): string
    {
        return '/' . trim($ruta, '/');
    }

    // Busca la ruta que coincide y ejecuta sus pasos en orden. Devuelve false si no hay ninguna.
    public function despachar(object $req, string $ruta): bool
    {
        $ruta = $this->normalizar($ruta);

        // 1. Los middlewares de usar() corren primero (igual que router.use)
        foreach ($this->middlewares as $middleware) $middleware($req);

        // 2. Routers montados: "/libros/5" → el router de libros recibe "/5"
        foreach ($this->montados as [$regex, $router]) {
            if (preg_match($regex, $ruta, $partes) && $router->despachar($req, $partes[1] ?? '/')) return true;
        }

        // 3. Rutas propias: coincide la URL y el verbo HTTP
        foreach ($this->rutas as [$metodo, $regex, $pasos]) {
            if ($metodo !== $req->metodo || !preg_match($regex, $ruta, $partes)) continue;

            $req->params = array_map('rawurldecode', array_filter($partes, 'is_string', ARRAY_FILTER_USE_KEY));
            foreach ($pasos as $paso) $paso($req);
            return true;
        }

        return false;
    }
}
