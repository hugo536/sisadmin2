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
        // Asumo que tienes este modelo, si no, te lo paso después
        $this->permisoModel = new PermisoModel(); 
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('roles.ver');

        $flash = ['tipo' => '', 'texto' => ''];
        $userId = (int) ($_SESSION['id'] ?? 0);

        // --- MANEJO DE POST (ACCIONES) ---
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
                    // Validamos que 'estado' venga en el POST, sino asumimos activo (1) o lo que corresponda
                    $estado = isset($_POST['estado']) ? (int) $_POST['estado'] : 1;
                    $this->rolModel->actualizar((int) $_POST['id'], trim((string) $_POST['nombre']), $estado, $userId);
                    $flash = ['tipo' => 'success', 'texto' => 'Rol actualizado correctamente.'];
                }

                if ($accion === 'toggle') {
                    require_permiso('roles.editar');
                    $this->rolModel->cambiar_estado((int) $_POST['id'], (int) $_POST['estado'], $userId);
                    // No seteamos flash aquí porque esto suele venir de una petición AJAX (JS)
                    // pero si es form normal, está bien dejarlo.
                    $flash = ['tipo' => 'success', 'texto' => 'Estado del rol actualizado.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('roles.eliminar'); // Asegúrate que este permiso exista en BD
                    $this->rolModel->eliminar_logico((int) $_POST['id'], $userId);
                    $flash = ['tipo' => 'success', 'texto' => 'Rol eliminado correctamente.'];
                }

                if ($accion === 'permisos') {
                    // CORRECCIÓN: Usamos 'roles.editar' porque 'roles.permisos' no existía en tu SQL
                    require_permiso('roles.editar'); 
                    
                    $idRol = (int) ($_POST['id_rol'] ?? 0);
                    if ($idRol === (int) ($_SESSION['id_rol'] ?? 0)) {
                        $mensaje = 'Por seguridad, no puedes editar tu propio rol asignado';
                        if (function_exists('es_ajax') && es_ajax()) {
                            json_response(['ok' => false, 'mensaje' => $mensaje], 400);
                            return;
                        }
                        $flash = ['tipo' => 'error', 'texto' => $mensaje];
                        $accion = '';
                    }

                    // Convertimos a enteros para seguridad
                    $permisos = array_map('intval', $_POST['permisos'] ?? []);
                    
                    if ($accion === 'permisos') {
                        $this->rolModel->guardar_permisos($idRol, $permisos);
                    }
                    
                    // Importante: Si edito mis propios permisos, forzar recarga en el próximo request
                    if ($accion === 'permisos' && $idRol === (int)($_SESSION['id_rol'] ?? 0)) {
                        unset($_SESSION['permisos']); 
                    }
                    
                    $flash = ['tipo' => 'success', 'texto' => 'Permisos asignados correctamente.'];
                }

            } catch (Throwable $e) {
                // En producción, loguear el error real y mostrar mensaje genérico
                $flash = ['tipo' => 'error', 'texto' => 'Error: ' . $e->getMessage()];
            }
        }

        // --- PREPARAR DATOS PARA LA VISTA ---
        $roles = $this->rolModel->listar();
        
        // Agregamos los IDs de permisos actuales para poder marcar los checkboxes en la vista
        foreach ($roles as &$rol) {
            $rol['permisos_ids'] = $this->rolModel->permisos_por_rol((int) $rol['id']);
        }
        unset($rol); // Romper referencia

        // Renderizar vista
        $this->render('roles', [
            'roles' => $roles,
            'permisos' => $this->permisoModel->listar_activos(), // Necesitamos lista de TODOS los permisos disponibles
            'flash' => $flash,
            // CORRECCIÓN: Ruta sin /index para que coincida con sidebar
            'ruta_actual' => 'roles', 
        ]);
    }
}
