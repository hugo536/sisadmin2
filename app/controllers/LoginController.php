<?php
declare(strict_types=1);

class LoginController extends Controlador
{
    public function index(): void
    {
        // Si ya está logueado, manda a dashboard (o donde tengas)
        if (!empty($_SESSION['usuario']['id'])) {
            header('Location: ?ruta=dashboard/index');
            exit;
        }

        $this->render('auth/login', [
            'error' => $_GET['error'] ?? null
        ]);
    }

    public function authenticate(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo "Método no permitido";
            return;
        }

        $usuario = trim((string)($_POST['usuario'] ?? ''));
        $clave   = (string)($_POST['clave'] ?? '');

        if ($usuario === '' || $clave === '') {
            $this->registrarBitacora(1, 'LOGIN_FAIL', "Campos vacíos (usuario={$usuario})");
            header('Location: ?ruta=login/index&error=1');
            exit;
        }

        $model = new UsuarioModel();
        $row = $model->buscar_por_usuario($usuario);

        // Usuario no existe
        if (!$row) {
            $this->registrarBitacora(1, 'LOGIN_FAIL', "Usuario no existe (usuario={$usuario})");
            header('Location: ?ruta=login/index&error=1');
            exit;
        }

        // Validar estado (1=activo, 0=inactivo, 2=bloqueado)
        $estado = (int)($row['estado'] ?? 0);
        if ($estado !== 1) {
            $this->registrarBitacora((int)$row['id'], 'LOGIN_DENIED', "Estado={$estado} (usuario={$usuario})");
            header('Location: ?ruta=login/index&error=2');
            exit;
        }

        // Verificar contraseña contra usuarios.clave
        $hash = (string)($row['clave'] ?? '');
        if ($hash === '' || !password_verify($clave, $hash)) {
            $this->registrarBitacora((int)$row['id'], 'LOGIN_FAIL', "Password inválido (usuario={$usuario})");
            header('Location: ?ruta=login/index&error=1');
            exit;
        }

        // ✅ Login OK: endurecer sesión
        session_regenerate_id(true);

        $_SESSION['usuario'] = [
            'id'     => (int)$row['id'],
            'usuario'=> (string)$row['usuario'],
            'id_rol' => (int)$row['id_rol'],
        ];
        $_SESSION['ultimo_acceso'] = time();

        // Actualizar ultimo_login
        $model->actualizar_ultimo_login((int)$row['id']);

        // Bitácora
        $this->registrarBitacora((int)$row['id'], 'LOGIN_OK', "Login exitoso (usuario={$usuario})");

        // Redirigir a dashboard (crea un placeholder si aún no existe)
        header('Location: ?ruta=dashboard/index');
        exit;
    }

    public function logout(): void
    {
        $id = (int)($_SESSION['usuario']['id'] ?? 1);
        $this->registrarBitacora($id, 'LOGOUT', 'Cierre de sesión');

        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"], $params["secure"], $params["httponly"]
            );
        }
        session_destroy();

        header('Location: ?ruta=login/index');
        exit;
    }

    private function registrarBitacora(int $createdBy, string $evento, string $descripcion): void
    {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $model = new UsuarioModel();
            $model->insertar_bitacora($createdBy, $evento, $descripcion, $ip, $ua);
        } catch (Throwable $e) {
            // No rompas el login por falla de bitácora
        }
    }
}
