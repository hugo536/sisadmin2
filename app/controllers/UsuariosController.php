<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class UsuariosController extends Controlador
{
    private UsuariosModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('usuarios.ver');

        // Obtenemos ID del usuario actual de la sesión
        $currentUserId = (int)($_SESSION['id'] ?? 0);
        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            try {
                // --- CREAR ---
                if ($accion === 'crear') {
                    require_permiso('usuarios.crear');
                    $nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
                    $usuario = trim((string) ($_POST['usuario'] ?? ''));
                    $email = trim((string) ($_POST['email'] ?? ''));
                    $clave = (string) ($_POST['clave'] ?? '');
                    $idRol = (int) ($_POST['id_rol'] ?? 0);

                    if ($nombreCompleto === '' || $usuario === '' || $email === '' || $clave === '' || $idRol <= 0) {
                        throw new RuntimeException('Complete nombre, usuario, email, clave y rol.');
                    }

                    // Validar duplicados
                    if ($this->usuarioModel->existe_usuario($usuario)) {
                         throw new RuntimeException('El nombre de usuario ya existe.');
                    }
                    if ($this->usuarioModel->existe_email($email)) {
                        throw new RuntimeException('El correo electrónico ya está registrado en otro usuario.');
                    }

                    $this->usuarioModel->crear($nombreCompleto, $usuario, $email, $clave, $idRol, $currentUserId);
                    $flash = ['tipo' => 'success', 'texto' => 'Usuario creado correctamente.'];
                }

                // --- EDITAR ---
                if ($accion === 'editar') {
                    require_permiso('usuarios.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    $nombreCompleto = trim((string) ($_POST['nombre_completo'] ?? ''));
                    $usuario = trim((string) ($_POST['usuario'] ?? ''));
                    $email = trim((string) ($_POST['email'] ?? ''));
                    $idRol = (int) ($_POST['id_rol'] ?? 0);
                    $clave = trim((string) ($_POST['clave'] ?? ''));

                    if ($id <= 0 || $nombreCompleto === '' || $usuario === '' || $email === '' || $idRol <= 0) {
                        throw new RuntimeException('Datos inválidos para editar.');
                    }

                    if ($this->usuarioModel->existe_usuario($usuario, $id)) {
                        throw new RuntimeException('El nombre de usuario ya existe.');
                    }
                    if ($this->usuarioModel->existe_email($email, $id)) {
                        throw new RuntimeException('El correo electrónico ya está registrado en otro usuario.');
                    }

                    // PROTECCIÓN: No se puede cambiar el rol del usuario 'admin' si no eres superadmin o DB
                    // (Opcional, pero recomendado: validar que no le quiten admin al admin)

                    $this->usuarioModel->actualizar($id, $nombreCompleto, $usuario, $email, $idRol, $clave !== '' ? $clave : null);
                    $flash = ['tipo' => 'success', 'texto' => 'Usuario actualizado correctamente.'];
                }

                // --- ESTADO ---
                if ($accion === 'estado') {
                    require_permiso('usuarios.editar'); // Usamos permiso de editar para cambiar estado
                    $id = (int) ($_POST['id'] ?? 0);
                    $estado = (int) ($_POST['estado'] ?? 0);
                    
                    if ($id <= 0 || !in_array($estado, [0, 1], true)) {
                        throw new RuntimeException('Parámetros inválidos.');
                    }

                    // 1. Obtener datos del usuario objetivo
                    $targetUser = $this->usuarioModel->obtener_por_id($id);
                    if (!$targetUser) throw new RuntimeException('Usuario no encontrado.');

                    // 2. PROTECCIÓN: No desactivar al usuario 'admin'
                    if (strtolower($targetUser['usuario']) === 'admin') {
                        throw new RuntimeException('PROTECCIÓN: No se puede desactivar al usuario principal del sistema.');
                    }

                    // 3. PROTECCIÓN: No desactivarse a sí mismo
                    if ($id === $currentUserId) {
                        throw new RuntimeException('PROTECCIÓN: No puedes desactivar tu propia cuenta mientras estás logueado.');
                    }

                    $this->usuarioModel->cambiar_estado($id, $estado);
                    $flash = ['tipo' => 'success', 'texto' => 'Estado actualizado.'];
                }

                // --- ELIMINAR (Soft Delete) ---
                if ($accion === 'eliminar') {
                    require_permiso('usuarios.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);

                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    // 1. Obtener datos del usuario objetivo
                    $targetUser = $this->usuarioModel->obtener_por_id($id);
                    if (!$targetUser) throw new RuntimeException('Usuario no encontrado.');

                    // 2. PROTECCIÓN: No eliminar al usuario 'admin'
                    if (strtolower($targetUser['usuario']) === 'admin') {
                        throw new RuntimeException('ALERTA DE SEGURIDAD: El usuario "admin" es fundamental y no puede ser eliminado.');
                    }

                    // 3. PROTECCIÓN: No eliminarse a sí mismo
                    if ($id === $currentUserId) {
                        throw new RuntimeException('ALERTA DE SEGURIDAD: No puedes eliminar tu propia cuenta (Autosabotaje prevenido).');
                    }

                    $this->usuarioModel->eliminar($id, $currentUserId);
                    $flash = ['tipo' => 'success', 'texto' => 'Usuario eliminado correctamente.'];
                }

            } catch (Throwable $e) {
                $mensaje = $e->getMessage();

                // Fallback para errores de integridad no previstos (ej. condición de carrera).
                if ($e instanceof PDOException) {
                    $errorInfo = $e->errorInfo ?? [];
                    $codigoMotor = (string)($errorInfo[1] ?? '');
                    $detalle = (string)($errorInfo[2] ?? '');

                    if ($codigoMotor === '1062' || stripos($detalle, 'Duplicate entry') !== false) {
                        if (stripos($detalle, 'uq_usuarios_email') !== false || stripos($detalle, 'for key \'email\'') !== false) {
                            $mensaje = 'No se pudo guardar: el correo electrónico ya existe.';
                        } elseif (stripos($detalle, 'uq_usuarios_usuario') !== false || stripos($detalle, 'for key \'usuario\'') !== false) {
                            $mensaje = 'No se pudo guardar: el nombre de usuario ya existe.';
                        } else {
                            $mensaje = 'No se pudo guardar porque ya existe un valor duplicado.';
                        }
                    }
                }

                $flash = ['tipo' => 'error', 'texto' => $mensaje];
            }
        }

        $this->render('usuarios', [
            'usuarios' => $this->usuarioModel->listar_activos(),
            'roles' => $this->usuarioModel->listar_roles_activos(),
            'flash' => $flash,
            'ruta_actual' => 'usuarios/index',
            'current_user_id' => $currentUserId // Pasamos el ID a la vista
        ]);
    }
}
