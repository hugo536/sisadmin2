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
        // 1. Autenticación y Autorización Base
        AuthMiddleware::handle();
        require_permiso('roles.ver');

        $userId = (int)($_SESSION['id'] ?? 0);
        $esAjax = function_exists('es_ajax') && es_ajax();

        // 2. Procesar Acciones (POST)
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $accion = $_POST['accion'] ?? '';
            $response = ['ok' => false, 'mensaje' => 'Acción no reconocida'];
            $httpCode = 400;

            try {
                switch ($accion) {
                    case 'crear':
                        require_permiso('roles.crear');
                        $nombre = trim($_POST['nombre'] ?? '');
                        if (empty($nombre)) throw new Exception("El nombre del rol es obligatorio.");
                        
                        $this->rolModel->crear($nombre, $userId);
                        $response = ['ok' => true, 'mensaje' => 'Rol creado correctamente.'];
                        $httpCode = 200;
                        break;

                    case 'editar':
                        require_permiso('roles.editar');
                        $id = (int)($_POST['id'] ?? 0);
                        $nombre = trim($_POST['nombre'] ?? '');
                        $estado = isset($_POST['estado']) ? (int)$_POST['estado'] : 1;

                        if (empty($nombre)) throw new Exception("El nombre es obligatorio.");
                        
                        // Regla de seguridad: No desactivar el rol propio
                        if ($estado === 0 && $id === (int)($_SESSION['id_rol'] ?? 0)) {
                            throw new Exception("Por seguridad, no puedes desactivar tu propio rol actual.");
                        }

                        $this->rolModel->actualizar($id, $nombre, $estado, $userId);
                        $response = ['ok' => true, 'mensaje' => 'Rol actualizado correctamente.'];
                        $httpCode = 200;
                        break;

                    case 'toggle': // Cambio rápido de estado desde lista
                        require_permiso('roles.editar');
                        $id = (int)($_POST['id'] ?? 0);
                        $estado = (int)($_POST['estado'] ?? 0);

                        if ($id === (int)($_SESSION['id_rol'] ?? 0)) {
                            throw new Exception("No puedes cambiar el estado de tu propio rol.");
                        }

                        $this->rolModel->cambiar_estado($id, $estado, $userId);
                        $response = ['ok' => true, 'mensaje' => 'Estado actualizado.'];
                        $httpCode = 200;
                        break;

                    case 'eliminar':
                        require_permiso('roles.eliminar');
                        $id = (int)($_POST['id'] ?? 0);

                        // Reglas de protección críticas
                        if ($id === 1) throw new Exception("El rol Super Admin es inmutable.");
                        if ($id === (int)($_SESSION['id_rol'] ?? 0)) throw new Exception("No puedes eliminar tu propio rol.");

                        $this->rolModel->eliminar_logico($id, $userId);
                        $response = ['ok' => true, 'mensaje' => 'Rol eliminado correctamente.'];
                        $httpCode = 200;
                        break;

                    case 'permisos': // Guardar asignación de permisos
                        require_permiso('roles.editar');
                        $idRol = (int)($_POST['id_rol'] ?? 0);
                        
                        // Validar y limpiar array de permisos
                        $permisos = isset($_POST['permisos']) && is_array($_POST['permisos']) 
                                    ? array_map('intval', $_POST['permisos']) 
                                    : [];

                        // Regla 3.4 Manual: Prevención de Auto-Bloqueo
                        if ($idRol === (int)($_SESSION['id_rol'] ?? 0)) {
                            throw new Exception("Por seguridad, no puedes editar los permisos de tu propio rol.");
                        }

                        // Guardar (Auditoría: $userId)
                        $this->rolModel->guardar_permisos($idRol, $permisos, $userId);
                        
                        $response = ['ok' => true, 'mensaje' => 'Permisos actualizados correctamente.'];
                        $httpCode = 200;
                        break;
                }

            } catch (Throwable $e) {
                $response = ['ok' => false, 'mensaje' => $e->getMessage()];
                $httpCode = 400;
            }

            // Retorno JSON si es AJAX (Estándar Frontend)
            if ($esAjax) {
                header('Content-Type: application/json');
                http_response_code($httpCode);
                echo json_encode($response);
                exit;
            } else {
                // Fallback para POST tradicional (recargar página)
                // Se podría implementar redirección con $_SESSION['flash'] si fuera necesario
                header('Location: ' . BASE_URL . '/roles');
                exit;
            }
        }

        // 3. Renderizado de Vista (GET)
        $roles = $this->rolModel->listar();

        // Inyectar permisos actuales a cada rol (para pintar los checkboxes)
        foreach ($roles as &$rol) {
            $rol['permisos_ids'] = $this->rolModel->permisos_por_rol((int)$rol['id']);
        }
        unset($rol);

        // Obtener catálogo completo de permisos activos
        $permisosActivos = $this->permisoModel->listar_activos();

        $this->render('roles', [
            'roles' => $roles,
            'permisos' => $permisosActivos,
            'ruta_actual' => 'roles'
        ]);
    }
}