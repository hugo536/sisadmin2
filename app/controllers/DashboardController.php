<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/DashboardModel.php';

class DashboardController extends Controlador
{
    private DashboardModel $dashboard_model;

    public function __construct()
    {
        $this->dashboard_model = new DashboardModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();

        $totales = $this->dashboard_model->obtener_totales();
        $movimientos = $this->dashboard_model->obtener_movimientos_recientes(10);

        $this->render('dashboard/index', [
            'totales' => $totales,
            'movimientos' => $movimientos,
            'usuario' => (string) ($_SESSION['usuario'] ?? ''),
            'ruta_actual' => 'dashboard/index',
        ]);
    }
}
