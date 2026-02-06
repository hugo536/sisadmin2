<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/RolModel.php';
require_once BASE_PATH . '/app/models/PermisoModel.php';

class RolesController extends Controlador
{
    private RolModel $rolModel;
    private PermisoModel $permisoModel;

    public function __construct()
    {
        $this->rolModel = new RolModel();
        $this->permisoModel = new PermisoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('roles.ver');

        $flash = ['tipo' => '', 'texto' => ''];
        $userId = (int) ($_SESSION['id'] ?? 0);

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            try {
                if ($accion === 'crear') {
                    require_permiso('roles.crear');
                    $this->rolModel->crear(trim((string) ($_POST['nombre'] ?? '')), $userId);
                    $flash = ['tipo' => 'success', 'texto' => 'Rol creado correctamente.'];
                }
                if ($accion === 'editar') {
                    require_permiso('roles.editar');
                    $this->rolModel->actualizar((int) $_POST['id'], trim((string) $_POST['nombre']), (int) $_POST['estado'], $userId);
                    $flash = ['tipo' => 'success', 'texto' => 'Rol actualizado correctamente.'];
                }
                if ($accion === 'toggle') {
                    require_permiso('roles.editar');
                    $this->rolModel->cambiar_estado((int) $_POST['id'], (int) $_POST['estado'], $userId);
                    $flash = ['tipo' => 'success', 'texto' => 'Estado del rol actualizado.'];
                }
                if ($accion === 'eliminar') {
                    require_permiso('roles.eliminar');
                    $this->rolModel->eliminar_logico((int) $_POST['id'], $userId);
                    $flash = ['tipo' => 'success', 'texto' => 'Rol eliminado correctamente.'];
                }
                if ($accion === 'permisos') {
                    require_permiso('roles.permisos');
                    $idRol = (int) ($_POST['id_rol'] ?? 0);
                    $permisos = array_map('intval', $_POST['permisos'] ?? []);
                    $this->rolModel->guardar_permisos($idRol, $permisos);
                    unset($_SESSION['permisos']);
                    $flash = ['tipo' => 'success', 'texto' => 'Permisos actualizados para el rol.'];
                }
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
            }
        }

        $roles = $this->rolModel->listar();
        foreach ($roles as &$rol) {
            $rol['permisos_ids'] = $this->rolModel->permisos_por_rol((int) $rol['id']);
        }

        $this->render('roles', [
            'roles' => $roles,
            'permisos' => $this->permisoModel->listar_activos(),
            'flash' => $flash,
            'ruta_actual' => 'roles/index',
        ]);
    }
}
