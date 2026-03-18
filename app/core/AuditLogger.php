<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/config/Conexion.php';

class AuditLogger
{
    private const EVENTO = 'DB_MUTATION';

    /**
     * Arma una auditoría automática para toda petición de mutación.
     */
    public static function arm(string $ruta, string $controlador, string $accion): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $rutaNormalizada = trim($ruta);
        if ($rutaNormalizada === '') {
            return;
        }

        // Evitar ruido/dobles logs en login (ya tienen eventos dedicados)
        if (str_starts_with($rutaNormalizada, 'login/')) {
            return;
        }

        register_shutdown_function(static function () use ($method, $rutaNormalizada, $controlador, $accion): void {
            $userId = (int) ($_SESSION['id'] ?? 0);
            if ($userId <= 0) {
                return;
            }

            $evento = self::resolverEvento($rutaNormalizada, $accion);
            $descripcion = self::buildDescripcion($method, $rutaNormalizada, $controlador, $accion);

            try {
                $sql = 'INSERT INTO bitacora_seguridad (created_by, evento, descripcion, ip_address, user_agent, created_at)
                        VALUES (:created_by, :evento, :descripcion, :ip_address, :user_agent, NOW())';

                $stmt = Conexion::get()->prepare($sql);
                $stmt->execute([
                    'created_by'  => $userId,
                    'evento'      => $evento,
                    'descripcion' => $descripcion,
                    'ip_address'  => (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
                    'user_agent'  => (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'),
                ]);
            } catch (Throwable $e) {
                // Nunca romper el flujo principal por auditoría.
            }
        });
    }

    private static function resolverEvento(string $ruta, string $accion): string
    {
        $base = mb_strtolower($ruta . ' ' . $accion . ' ' . (string) ($_POST['accion'] ?? ''));

        if (str_contains($base, 'eliminar') || str_contains($base, 'delete')) {
            return 'DB_DELETE';
        }
        if (str_contains($base, 'estado') || str_contains($base, 'toggle') || str_contains($base, 'activar') || str_contains($base, 'desactivar')) {
            return 'DB_STATUS_CHANGE';
        }
        if (str_contains($base, 'editar') || str_contains($base, 'actualizar') || str_contains($base, 'update')) {
            return 'DB_UPDATE';
        }
        if (str_contains($base, 'crear') || str_contains($base, 'guardar') || str_contains($base, 'registrar') || str_contains($base, 'insert')) {
            return 'DB_CREATE';
        }

        return self::EVENTO;
    }

    private static function buildDescripcion(string $method, string $ruta, string $controlador, string $accion): string
    {
        $payload = $_POST;

        // Ocultar campos sensibles
        $sensibles = ['clave', 'password', 'pass', 'token', 'csrf', 'authorization'];
        foreach ($payload as $k => $v) {
            if (in_array(mb_strtolower((string) $k), $sensibles, true)) {
                $payload[$k] = '[PROTEGIDO]';
            }
        }

        $resumenPayload = self::resumirPayload($payload);

        return sprintf(
            'Mutación detectada | metodo=%s | ruta=%s | controlador=%s | accion=%s | payload=%s',
            $method,
            $ruta,
            $controlador,
            $accion,
            $resumenPayload
        );
    }

    private static function resumirPayload(array $payload): string
    {
        if ($payload === []) {
            return '{}';
        }

        $normalizado = [];
        foreach ($payload as $k => $v) {
            $key = (string) $k;
            if (is_array($v)) {
                $normalizado[$key] = '[array:' . count($v) . ']';
                continue;
            }

            $value = trim((string) $v);
            if (mb_strlen($value) > 80) {
                $value = mb_substr($value, 0, 77) . '...';
            }
            $normalizado[$key] = $value;
        }

        $json = json_encode($normalizado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return $json !== false ? $json : '{}';
    }
}
