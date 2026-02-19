<?php
declare(strict_types=1);

class Router
{
    // Cambiar a true solo si necesitas depurar rutas fallidas
    private bool $debug = false;

    public function dispatch(): void
    {
        // Normalización de ruta: 'modulo/accion'
        $ruta = trim((string)($_GET['ruta'] ?? 'login/index'));
        if ($ruta === '') $ruta = 'login/index';

        // Seguridad básica: evitar Directory Traversal
        if (str_contains($ruta, '..')) {
            $this->render_not_found();
            return;
        }

        $partes = array_values(array_filter(explode('/', $ruta)));
        $modulo = $partes[0] ?? 'login';
        $accion = $partes[1] ?? 'index';

        if ($modulo === '') $modulo = 'login';
        if ($accion === '') $accion = 'index';

        // Convención: 'roles' -> 'RolesController'
        $controlador_clase_base = ucfirst($modulo) . 'Controller';
        
        // Mapeo de Alias (Si tus archivos o clases no siguen la convención exacta)
        $mapa_alias = [
            'LoginController'         => 'AuthController', // alias -> real
            'ConfiguracionController' => 'EmpresaController',
            'ConfigController'        => 'EmpresaController'
        ];

        $controlador_clase = $mapa_alias[$controlador_clase_base] ?? $controlador_clase_base;

        // Búsqueda del archivo
        $archivo = $this->resolver_controlador_archivo($controlador_clase);
        
        if (!$archivo) {
            if ($this->debug) {
                die("<h3>Debug Router:</h3><p>No se encontró el archivo para <strong>$controlador_clase</strong>.</p>");
            }
            $this->render_not_found();
            return;
        }

        require_once $archivo;

        // Instancia y Ejecución
        if (!class_exists($controlador_clase)) {
            $this->render_server_error("La clase <strong>$controlador_clase</strong> no está definida en el archivo.");
            return;
        }

        $controlador = new $controlador_clase();

        if (!method_exists($controlador, $accion)) {
            if ($this->debug) {
                die("<h3>Debug Router:</h3><p>Método <strong>$accion()</strong> no encontrado en $controlador_clase.</p>");
            }
            // Opción: render_not_found() si prefieres 404 en vez de error 500
            $this->render_not_found(); 
            return;
        }

        // Ejecutar acción
        $controlador->{$accion}();
    }

    private function resolver_controlador_archivo(string $clase): ?string
    {
        // Rutas posibles (inglés/español por si acaso)
        $rutas_posibles = [
            BASE_PATH . '/app/controllers/' . $clase . '.php',
            BASE_PATH . '/app/controllers/configuracion/' . $clase . '.php',
            BASE_PATH . '/app/controladores/' . $clase . '.php',
        ];

        foreach ($rutas_posibles as $ruta) {
            if (is_file($ruta)) return $ruta;
        }
        return null;
    }

    private function render_not_found(): void
    {
        http_response_code(404);
        // Puedes requerir una vista 404 bonita aquí
        if (is_file(BASE_PATH . '/app/views/404.php')) {
            require BASE_PATH . '/app/views/404.php';
        } else {
            echo "<h1>404 Not Found</h1><p>La página solicitada no existe.</p>";
        }
    }

    private function render_server_error(string $msg = ''): void
    {
        http_response_code(500);
        echo "<h1>500 Server Error</h1><p>$msg</p>";
    }
}