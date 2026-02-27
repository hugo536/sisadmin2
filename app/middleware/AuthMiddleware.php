<?php
declare(strict_types=1);

class AuthMiddleware
{
    private const TIMEOUT_INACTIVIDAD_DEFECTO = 1800;
    private const TIMEOUT_ABSOLUTO_DEFECTO = 28800;

    public static function handle(): void
    {
        if (!isset($_SESSION['id'], $_SESSION['usuario'], $_SESSION['id_rol'])) {
            self::redirect('login/index');
        }

        $ahora = time();
        $ultimo_acceso = (int) ($_SESSION['ultimo_acceso'] ?? 0);
        $inicio_sesion = (int) ($_SESSION['inicio_sesion'] ?? 0);
        $timeout_inactividad = self::envInt('SESSION_IDLE_TIMEOUT', self::TIMEOUT_INACTIVIDAD_DEFECTO);
        $timeout_absoluto = self::envInt('SESSION_ABSOLUTE_TIMEOUT', self::TIMEOUT_ABSOLUTO_DEFECTO);

        if ($ultimo_acceso > 0 && ($ahora - $ultimo_acceso) > $timeout_inactividad) {
            self::cerrarSesionPorExpiracion();
            self::redirect('login/index&error=expired');
        }

        if ($inicio_sesion > 0 && ($ahora - $inicio_sesion) > $timeout_absoluto) {
            self::cerrarSesionPorExpiracion();
            self::redirect('login/index&error=expired');
        }

        if ($inicio_sesion <= 0) {
            $_SESSION['inicio_sesion'] = $ahora;
        }

        $_SESSION['ultimo_acceso'] = $ahora;
    }

    private static function envInt(string $key, int $default): int
    {
        $value = $_ENV[$key] ?? getenv($key);
        if (!is_scalar($value)) {
            return $default;
        }

        $parsed = filter_var((string) $value, FILTER_VALIDATE_INT);
        if ($parsed === false || $parsed <= 0) {
            return $default;
        }

        return $parsed;
    }

    private static function cerrarSesionPorExpiracion(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }

    private static function redirect(string $ruta): void
    {
        header('Location: ?ruta=' . $ruta);
        exit;
    }
}
