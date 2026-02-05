<?php
declare(strict_types=1);

class Router
{
    public function dispatch(): void
    {
        $ruta = trim((string)($_GET['ruta'] ?? 'login/index'));
        if ($ruta === '') $ruta = 'login/index';

        if (str_contains($ruta, '..')) {
            $this->render_not_found(); return;
        }

        $partes = array_values(array_filter(explode('/', $ruta)));
        $modulo = $partes[0] ?? 'login';
        $accion = $partes[1] ?? 'index';

        $controlador_clase = ucfirst($modulo) . 'Controller';

        // Alias: login/* -> AuthController
        if ($controlador_clase === 'LoginController') {
            $controlador_clase = 'AuthController';
        }

        $archivo = $this->resolver_controlador_archivo($controlador_clase);
        if (!$archivo) { $this->render_not_found(); return; }

        require_once $archivo;

        if (!class_exists($controlador_clase)) {
            $this->render_server_error(); return;
        }

        $controlador = new $controlador_clase();

        if (!method_exists($controlador, $accion)) {
            $this->render_not_found(); return;
        }

        $controlador->{$accion}();
    }

    private function resolver_controlador_archivo(string $controlador_clase): ?string
    {
        $candidatos = [
            BASE_PATH . '/app/controllers/' . $controlador_clase . '.php',
            BASE_PATH . '/app/controladores/' . $controlador_clase . '.php',
        ];
        foreach ($candidatos as $f) if (is_file($f)) return $f;
        return null;
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
