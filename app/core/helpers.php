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
        if (!isset($_SESSION['id_rol'])) {
            return false;
        }

        $id_rol = (int) $_SESSION['id_rol'];
        if ($id_rol === 1) {
            return true;
        }

        require_once BASE_PATH . '/app/config/Conexion.php';
        $db = Conexion::get();
        $sql = 'SELECT 1
                FROM roles_permisos rp
                INNER JOIN permisos_def pd ON pd.id = rp.id_permiso
                INNER JOIN roles r ON r.id = rp.id_rol
                WHERE rp.id_rol = :id_rol
                  AND pd.slug = :slug
                  AND rp.deleted_at IS NULL
                  AND pd.deleted_at IS NULL
                  AND r.estado = 1
                  AND r.deleted_at IS NULL
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_rol' => $id_rol,
            'slug' => $slug,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}

if (!function_exists('require_permiso')) {
    function require_permiso(string $slug): void
    {
        if (tiene_permiso($slug)) {
            return;
        }

        http_response_code(403);
        require_once BASE_PATH . '/app/views/403.php';
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
