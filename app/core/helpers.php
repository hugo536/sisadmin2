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

        // 3. CACHÉ DE SESIÓN (Optimización Crítica)
        // Si los permisos no están en memoria, los cargamos de la BD una sola vez.
        if (!isset($_SESSION['permisos'])) {
            // Aseguramos que el modelo esté cargado
            $modeloPath = BASE_PATH . '/app/models/PermisoModel.php';
            if (is_file($modeloPath)) {
                require_once $modeloPath;
                if (class_exists('PermisoModel')) {
                    $model = new PermisoModel();
                    // Obtenemos array simple de slugs: ['ventas.ver', 'roles.editar', ...]
                    $_SESSION['permisos'] = $model->obtener_slugs_por_rol($idRol);
                } else {
                    $_SESSION['permisos'] = []; // Fallback seguro
                }
            } else {
                $_SESSION['permisos'] = []; // Fallback si no existe modelo
            }
        }

        // 4. Verificación rápida en memoria
        return in_array($slug, $_SESSION['permisos'], true);
    }
}

if (!function_exists('require_permiso')) {
    function require_permiso(string $slug): void
    {
        if (tiene_permiso($slug)) {
            return;
        }

        // Si es AJAX, responder JSON 403
        if (es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Acceso denegado. No tienes permiso: ' . $slug], 403);
            exit;
        }

        // Si es vista normal, mostrar error
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

if (!function_exists('base_url')) {
    function base_url(): string
    {
        $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $dir = str_replace('\\', '/', dirname($scriptName));

        if ($dir === '/' || $dir === '.' || $dir === '\\') {
            return '';
        }

        return rtrim($dir, '/');
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return base_url() . '/assets/' . ltrim($path, '/');
    }
}

if (!function_exists('route_url')) {
    function route_url(string $ruta): string
    {
        return base_url() . '/?ruta=' . ltrim($ruta, '/');
    }
}