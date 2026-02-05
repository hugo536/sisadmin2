<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/UsuarioModel.php';

class LoginController extends Controlador
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(): void
    {
        if ($this->usuario_autenticado()) {
            header('Location: ?ruta=login/bienvenido');
            exit;
        }

        $this->render('auth/login');
    }

    public function login(): void
    {
        $this->index();
    }

    public function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Método no permitido.';
            return;
        }

        $usuarioIngresado = trim((string) ($_POST['usuario'] ?? ''));
        $claveIngresada = (string) ($_POST['password'] ?? '');

        if ($usuarioIngresado === '' || $claveIngresada === '') {
            $this->registrar_evento(1, 'login_fallido', 'Intento con campos vacíos. usuario=' . $usuarioIngresado);
            $this->render('auth/login', ['error' => 'Usuario y contraseña son obligatorios.']);
            return;
        }

        $usuario = $this->usuarioModel->buscar_por_usuario($usuarioIngresado);

        if ($usuario === null) {
            $this->registrar_evento(1, 'login_fallido', 'Usuario no existe. usuario=' . $usuarioIngresado);
            $this->render('auth/login', ['error' => 'Credenciales inválidas.']);
            return;
        }

        if (strtolower((string) $usuario['estado']) !== 'activo') {
            $this->registrar_evento((int) $usuario['id'], 'login_fallido', 'Usuario inactivo.');
            $this->render('auth/login', ['error' => 'Usuario inactivo.']);
            return;
        }

        if (!password_verify($claveIngresada, (string) $usuario['clave'])) {
            $this->registrar_evento((int) $usuario['id'], 'login_fallido', 'Contraseña incorrecta.');
            $this->render('auth/login', ['error' => 'Credenciales inválidas.']);
            return;
        }

        session_regenerate_id(true);
        $_SESSION['auth'] = [
            'id' => (int) $usuario['id'],
            'usuario' => (string) $usuario['usuario'],
            'rol' => (int) $usuario['id_rol'],
        ];

        $this->usuarioModel->actualizar_ultimo_login((int) $usuario['id']);
        $this->registrar_evento((int) $usuario['id'], 'login_exitoso', 'Inicio de sesión correcto.');

        header('Location: ?ruta=login/bienvenido');
        exit;
    }

    public function logout(): void
    {
        $auth = $_SESSION['auth'] ?? null;

        if (is_array($auth) && isset($auth['id'])) {
            $this->registrar_evento((int) $auth['id'], 'logout', 'Cierre de sesión.');
        }

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

        header('Location: ?ruta=login/index');
        exit;
    }

    public function bienvenido(): void
    {
        if (!$this->usuario_autenticado()) {
            header('Location: ?ruta=login/index');
            exit;
        }

        $usuario = $_SESSION['auth']['usuario'] ?? 'usuario';
        echo '<h1>Bienvenido, ' . htmlspecialchars((string) $usuario, ENT_QUOTES, 'UTF-8') . '</h1>';
        echo '<p><a href="?ruta=login/logout">Cerrar sesión</a></p>';
    }

    private function registrar_evento(int $createdBy, string $evento, string $descripcion): void
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

        $this->usuarioModel->registrar_evento_seguridad(
            $createdBy,
            $evento,
            $descripcion,
            $ip,
            $userAgent
        );
    }

    private function usuario_autenticado(): bool
    {
        return isset($_SESSION['auth'])
            && is_array($_SESSION['auth'])
            && isset($_SESSION['auth']['id'], $_SESSION['auth']['usuario'], $_SESSION['auth']['rol']);
    }
}