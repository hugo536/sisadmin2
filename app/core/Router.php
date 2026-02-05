<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/core/Controlador.php';
require_once BASE_PATH . '/app/core/Modelo.php';

class Router
{
    public function dispatch(): void
    {
        $ruta = isset($_GET['ruta']) ? trim((string) $_GET['ruta']) : 'login/index';

        if ($ruta === '') {
            $ruta = 'login/index';
        }

        if (str_contains($ruta, '..')) {
            $this->render_not_found();
            return;
        }

        $partes = array_values(array_filter(explode('/', $ruta), static fn ($parte): bool => $parte !== ''));
        $modulo = $partes[0] ?? 'login';
        $accion = $partes[1] ?? 'index';

        if (!$this->segmento_valido($modulo) || !$this->segmento_valido($accion)) {
            $this->render_not_found();
            return;
        }

        $controlador_clase = ucfirst($modulo) . 'Controller';
        $aliases = [
            'LoginController' => 'AuthController',
        ];

        if (isset($aliases[$controlador_clase])) {
            $controlador_clase = $aliases[$controlador_clase];
        }

        $controlador_archivo = $this->resolver_controlador_archivo($controlador_clase);

        if ($controlador_archivo === null) {
            $this->render_not_found();
            return;
        }

        require_once $controlador_archivo;

        if (!class_exists($controlador_clase)) {
            $this->render_server_error();
            return;
        }

        $controlador = new $controlador_clase();

        if (!method_exists($controlador, $accion) || !is_callable([$controlador, $accion])) {
            $this->render_not_found();
            return;
        }

        try {
            $controlador->{$accion}();
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
            $this->render_server_error();
        }
    }

    private function resolver_controlador_archivo(string $controlador_clase): ?string
    {
        $directorios = [
            BASE_PATH . '/app/controladores/' . $controlador_clase . '.php',
            BASE_PATH . '/app/controllers/' . $controlador_clase . '.php',
        ];

        foreach ($directorios as $archivo) {
            if (is_file($archivo)) {
                return $archivo;
            }
        }

        return null;
    }

    private function segmento_valido(string $segmento): bool
    {
        return (bool) preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/', $segmento);
    }

    private function render_not_found(): void
    {
        http_response_code(404);
        echo '404 - Recurso no encontrado.';
    }

    private function render_server_error(): void
    {
        http_response_code(500);
        echo '500 - Error interno del servidor.';
    }
}