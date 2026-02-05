<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/PermisoModel.php';

class PermisosController extends Controlador
{
    private PermisoModel $permisoModel;

    public function __construct()
    {
        $this->permisoModel = new PermisoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('permisos.ver');

        $this->render('permisos', [
            'permisosAgrupados' => $this->permisoModel->listar_agrupados_modulo(),
            'ruta_actual' => 'permisos/index',
        ]);
    }
}
