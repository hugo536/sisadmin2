<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/PermisoModel.php';

class PermisosController extends Controlador
{
    private PermisoModel $model;

    public function __construct()
    {
        $this->model = new PermisoModel();
    }


    public static function tienePermiso(string $slug): bool
    {
        return function_exists('tiene_permiso') && tiene_permiso($slug);
    }

    public function index(): void
    {
        // 1. Seguridad
        AuthMiddleware::handle();
        
        // Generalmente, quien tiene acceso a ver roles, debe poder consultar el catálogo
        // Si prefieres segregarlo, cambia esto por 'permisos.ver' y regístralo en BD.
        require_permiso('roles.ver');

        // 2. Datos
        // Obtenemos la matriz agrupada por módulo para la vista
        $permisosAgrupados = $this->model->listar_agrupados_modulo();

        // 3. Renderizado
        $this->render('permisos', [
            'permisosAgrupados' => $permisosAgrupados,
            // Clave para resaltar el sidebar correctamente
            'ruta_actual' => 'permisos', 
        ]);
    }
}