<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e(?string $str): string
    {
        return htmlspecialchars((string) $str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('es_ajax')) {
    function es_ajax(): bool
    {
        $header = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        return strtolower($header) === 'xmlhttprequest';
    }
}

if (!function_exists('json_response')) {
    function json_response($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if (!function_exists('tiene_permiso')) {
    function tiene_permiso(string $slug): bool
    {
        // 1. Validar sesión
        if (!isset($_SESSION['id_rol'])) {
            return false;
        }

        $idRol = (int) $_SESSION['id_rol'];

        // 2. Super Admin (ID 1) siempre tiene acceso total
        if ($idRol === 1) {
            return true;
        }

        // 3. CACHÉ DE SESIÓN
        if (!isset($_SESSION['permisos'])) {
            $modeloPath = BASE_PATH . '/app/models/PermisoModel.php';
            if (is_file($modeloPath)) {
                require_once $modeloPath;
                if (class_exists('PermisoModel')) {
                    $model = new PermisoModel();
                    $_SESSION['permisos'] = $model->obtener_slugs_por_rol($idRol);
                } else {
                    $_SESSION['permisos'] = [];
                }
            } else {
                $_SESSION['permisos'] = [];
            }
        }

        return in_array($slug, $_SESSION['permisos'], true);
    }
}

if (!function_exists('require_permiso')) {
    function require_permiso(string $slug): void
    {
        if (tiene_permiso($slug)) {
            return;
        }

        if (es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Acceso denegado: ' . $slug], 403);
            exit;
        }

        http_response_code(403);
        $vista403 = BASE_PATH . '/app/views/403.php';
        if (is_file($vista403)) {
            require_once $vista403;
        } else {
            echo "<h1>403 Forbidden</h1><p>No tienes permiso para realizar esta acción ($slug).</p>";
        }
        exit;
    }
}

// --------------------------------------------------------------------------
// FUNCIONES DE URL Y REDIRECCIÓN (AQUÍ ESTABA EL FALTANTE)
// --------------------------------------------------------------------------

if (!function_exists('base_url')) {
    function base_url(): string
    {
        // Detecta el protocolo y host para generar URL absoluta (recomendado para redirects)
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_replace('\\', '/', dirname($scriptName));

        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            $path = '';
        } else {
            $path = rtrim($dir, '/');
        }
        
        return $protocol . $host . $path;
    }
}

if (!function_exists('route_url')) {
    function route_url(string $ruta): string
    {
        // Genera: http://localhost/sisadmin2/?ruta=controlador/metodo
        return base_url() . '/?ruta=' . ltrim($ruta, '/');
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        // Ajusta si tu carpeta 'assets' está dentro de 'public' o en la raíz
        // Según tu estructura parece ser raíz/assets o public/assets. 
        // Si usas public/index.php, el assets suele estar al nivel de index.php
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}

// ESTA ES LA FUNCIÓN QUE FALTABA
if (!function_exists('redirect')) {
    function redirect(string $ruta): void
    {
        // Lógica inteligente para manejar parámetros GET
        // Si pasas 'comercial/presentaciones?error=1', esto lo convierte correctamente
        // para que funcione con tu sistema de ?ruta=...
        
        if (!str_starts_with($ruta, 'http')) {
            $parts = explode('?', $ruta, 2);
            $cleanPath = $parts[0];
            $queryParams = $parts[1] ?? '';

            $url = route_url($cleanPath); // .../?ruta=comercial/presentaciones
            
            if ($queryParams !== '') {
                // Cambiamos el ? del parámetro por & porque ?ruta ya usó el primero
                $url .= '&' . $queryParams; 
            }
        } else {
            $url = $ruta;
        }

        if (!headers_sent()) {
            header('Location: ' . $url);
        } else {
            echo '<script>window.location.href="' . $url . '";</script>';
        }
        exit;
    }
}