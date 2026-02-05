<?php
declare(strict_types=1);

class AuthMiddleware
{
    private const TIMEOUT_SEGUNDOS = 1800;

    public static function handle(): void
    {
        if (!isset($_SESSION['id'], $_SESSION['usuario'], $_SESSION['id_rol'])) {
            self::redirect('login/index');
        }

        $ahora = time();
        $ultimo_acceso = (int) ($_SESSION['ultimo_acceso'] ?? 0);

        if ($ultimo_acceso > 0 && ($ahora - $ultimo_acceso) > self::TIMEOUT_SEGUNDOS) {
            self::cerrarSesionPorExpiracion();
            self::redirect('login/index&error=expired');
        }

        $_SESSION['ultimo_acceso'] = $ahora;
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
