<?php
declare(strict_types=1);

class Router
{
    // 🔴 IMPORTANTE: Cambia esto a TRUE para ver qué está fallando.
    // Cuando arregles el error, cámbialo a FALSE.
    private bool $debug = false;

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

        if ($modulo === '') $modulo = 'login';
        if ($accion === '') $accion = 'index';

        // Lógica original de nombre de clase
        $controlador_original = ucfirst($modulo) . 'Controller';
        $controlador_clase = $controlador_original;

        // =========================================================
        // MAPEO DE ALIAS
        // =========================================================
        if ($controlador_clase === 'LoginController') {
            $controlador_clase = 'AuthController';
        }
        if ($controlador_clase === 'ConfiguracionController' || $controlador_clase === 'ConfigController') {
            $controlador_clase = 'EmpresaController';
        }

        // =========================================================
        // 🕵️ ZONA DE DEPURACIÓN (DEBUGGER)
        // =========================================================
        if ($this->debug) {
            echo "<div style='background:white; color:black; font-family:monospace; padding:20px; border:4px solid red; z-index:99999; position:relative;'>";
            echo "<h3>=== 🐞 MODO DEPURACIÓN ACTIVO ===</h3>";
            echo "<p><strong>Ruta solicitada URL:</strong> " . htmlspecialchars($ruta) . "</p>";
            echo "<p><strong>Controlador Original:</strong> " . $controlador_original . "</p>";
            echo "<p><strong>Controlador Final (Alias):</strong> <span style='color:blue'>" . $controlador_clase . "</span></p>";
            echo "<hr>";
            echo "<p><strong>Buscando archivo en estas rutas:</strong></p><ul>";
            
            $rutas_prueba = [
                BASE_PATH . '/app/controllers/' . $controlador_clase . '.php',
                BASE_PATH . '/app/controladores/' . $controlador_clase . '.php',
            ];
            
            foreach ($rutas_prueba as $r) {
                $existe = is_file($r) ? "<span style='color:green; font-weight:bold;'>ENCONTRADO ✅</span>" : "<span style='color:red;'>NO EXISTE ❌</span>";
                echo "<li>" . $r . " -> " . $existe . "</li>";
            }
            echo "</ul>";
            echo "<p><em>Nota: Verifica que las mayúsculas/minúsculas de las carpetas coincidan exactamente en Windows/Linux.</em></p>";
            
            // Si quieres ver si continúa o detenerlo aquí:
             die("<hr>Fin del reporte de depuración. Corrige la ruta o el nombre del archivo.");
        }
        // =========================================================

        $archivo = $this->resolver_controlador_archivo($controlador_clase);
        
        if (!$archivo) { 
            // Si llegamos aquí sin debug, es un 404 real
            $this->render_not_found(); 
            return; 
        }

        require_once $archivo;

        if (!class_exists($controlador_clase)) {
            $this->render_server_error("El archivo existe, pero la clase <strong>$controlador_clase</strong> no está definida dentro de él."); 
            return;
        }

        $controlador = new $controlador_clase();

        if (!method_exists($controlador, $accion)) {
            $this->render_server_error("El controlador existe, pero el método (función) <strong>$accion()</strong> no se encuentra."); 
            return;
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
        echo '404 - Recurso no encontrado (Desde Router Debug).';
    }

    private function render_server_error(string $msg = ''): void
    {
        http_response_code(500);
        echo "500 - Error de configuración: " . $msg;
    }
}