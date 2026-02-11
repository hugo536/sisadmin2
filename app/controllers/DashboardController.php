<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/DashboardModel.php';

class DashboardController extends Controlador
{
    private DashboardModel $dashboardModel;

    public function __construct()
    {
        $this->dashboardModel = new DashboardModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('dashboard.ver');

        $this->render('dashboard', [
            'totales' => $this->dashboardModel->obtener_totales(),
            'eventos' => $this->dashboardModel->obtener_ultimos_eventos(10),
            'cumpleanosSemana' => $this->dashboardModel->obtener_cumpleanos_semana(),
            'ruta_actual' => 'dashboard/index',
        ]);
    }
}
