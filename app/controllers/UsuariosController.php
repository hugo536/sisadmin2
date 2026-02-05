<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/UsuarioModel.php';

class UsuariosController extends Controlador
{
    private UsuarioModel $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('usuarios.ver');

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            try {
                if ($accion === 'crear') {
                    require_permiso('usuarios.crear');
                    $usuario = trim((string) ($_POST['usuario'] ?? ''));
                    $clave = (string) ($_POST['clave'] ?? '');
                    $idRol = (int) ($_POST['id_rol'] ?? 0);

                    if ($usuario === '' || $clave === '' || $idRol <= 0) {
                        throw new RuntimeException('Complete usuario, clave y rol.');
                    }

                    $this->usuarioModel->crear($usuario, $clave, $idRol);
                    $flash = ['tipo' => 'success', 'texto' => 'Usuario creado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('usuarios.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    $usuario = trim((string) ($_POST['usuario'] ?? ''));
                    $idRol = (int) ($_POST['id_rol'] ?? 0);
                    $clave = trim((string) ($_POST['clave'] ?? ''));
                    if ($id <= 0 || $usuario === '' || $idRol <= 0) {
                        throw new RuntimeException('Datos inválidos para editar.');
                    }
                    $this->usuarioModel->actualizar($id, $usuario, $idRol, $clave !== '' ? $clave : null);
                    $flash = ['tipo' => 'success', 'texto' => 'Usuario actualizado correctamente.'];
                }

                if ($accion === 'estado') {
                    require_permiso('usuarios.estado');
                    $id = (int) ($_POST['id'] ?? 0);
                    $estado = (int) ($_POST['estado'] ?? 0);
                    if ($id <= 0 || !in_array($estado, [0, 1], true)) {
                        throw new RuntimeException('Parámetros inválidos de estado.');
                    }
                    $this->usuarioModel->cambiar_estado($id, $estado);
                    $flash = ['tipo' => 'success', 'texto' => 'Estado actualizado.'];
                }
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('usuarios', [
            'usuarios' => $this->usuarioModel->listar_activos(),
            'roles' => $this->usuarioModel->listar_roles_activos(),
            'flash' => $flash,
            'ruta_actual' => 'usuarios/index',
        ]);
    }
}
