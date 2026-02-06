<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/UsuariosModel.php';

class AuthController extends Controlador
{
    private UsuariosModel $usuario_model;

    public function __construct()
    {
        $this->usuario_model = new UsuariosModel();
    }

    public function index(): void
    {
        $mensaje_error = '';
        $codigo_error = (string) ($_GET['error'] ?? '');

        if ($codigo_error === 'invalid') {
            $mensaje_error = 'Credenciales inválidas.';
        } elseif ($codigo_error === 'denied') {
            $mensaje_error = 'Usuario inactivo o bloqueado.';
        } elseif ($codigo_error === 'required') {
            $mensaje_error = 'Usuario y clave son obligatorios.';
        } elseif ($codigo_error === 'expired') {
            $mensaje_error = 'La sesión expiró por inactividad. Inicia sesión nuevamente.';
        }

        $this->render('login', ['error' => $mensaje_error]);
    }

    public function authenticate(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            header('Location: ?ruta=login/index');
            exit;
        }

        $usuario_ingresado = trim((string) ($_POST['usuario'] ?? ''));
        $clave_ingresada = (string) ($_POST['clave'] ?? '');

        if ($usuario_ingresado === '' || $clave_ingresada === '') {
            $this->usuario_model->insertar_bitacora(
                1,
                'LOGIN_FAIL',
                'Campos obligatorios faltantes. usuario=' . $usuario_ingresado,
                $this->obtener_ip(),
                $this->obtener_user_agent()
            );

            header('Location: ?ruta=login/index&error=required');
            exit;
        }

        $usuario = $this->usuario_model->buscar_por_usuario($usuario_ingresado);

        if ($usuario === null) {
            $this->usuario_model->insertar_bitacora(
                1,
                'LOGIN_FAIL',
                'Usuario no encontrado. usuario=' . $usuario_ingresado,
                $this->obtener_ip(),
                $this->obtener_user_agent()
            );

            header('Location: ?ruta=login/index&error=invalid');
            exit;
        }

        $id_usuario = (int) $usuario['id'];

        if ((int) $usuario['estado'] !== 1) {
            $this->usuario_model->insertar_bitacora(
                $id_usuario,
                'LOGIN_DENIED',
                'Acceso denegado por estado no activo.',
                $this->obtener_ip(),
                $this->obtener_user_agent()
            );

            header('Location: ?ruta=login/index&error=denied');
            exit;
        }

        if (!password_verify($clave_ingresada, (string) $usuario['clave'])) {
            $this->usuario_model->insertar_bitacora(
                $id_usuario,
                'LOGIN_FAIL',
                'Clave inválida.',
                $this->obtener_ip(),
                $this->obtener_user_agent()
            );

            header('Location: ?ruta=login/index&error=invalid');
            exit;
        }

        session_regenerate_id(true);

        $_SESSION['id'] = $id_usuario;
        $_SESSION['usuario'] = (string) $usuario['usuario'];
        $_SESSION['id_rol'] = (int) $usuario['id_rol'];
        $_SESSION['ultimo_acceso'] = time();
        unset($_SESSION['permisos']);

        $this->usuario_model->actualizar_ultimo_login($id_usuario);
        $this->usuario_model->insertar_bitacora(
            $id_usuario,
            'LOGIN_OK',
            'Inicio de sesión exitoso.',
            $this->obtener_ip(),
            $this->obtener_user_agent()
        );

        header('Location: ?ruta=dashboard/index');
        exit;
    }

    public function logout(): void
    {
        $id_usuario = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 1;

        $this->usuario_model->insertar_bitacora(
            $id_usuario,
            'LOGOUT',
            'Cierre de sesión.',
            $this->obtener_ip(),
            $this->obtener_user_agent()
        );

        unset($_SESSION['permisos']);
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

    private function obtener_ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    private function obtener_user_agent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    }
}