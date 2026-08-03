<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteInventarioModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteComprasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteVentasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteProduccionModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteDashboardModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class DashboardController extends Controlador
{
    private ReporteInventarioModel $inventario;
    private ReporteComprasModel $compras;
    private ReporteVentasModel $ventas;
    private ReporteProduccionModel $produccion;
    private ReporteTesoreriaModel $tesoreria;
    private ReporteDashboardModel $dashboardModel;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->inventario = new ReporteInventarioModel();
        $this->compras = new ReporteComprasModel();
        $this->ventas = new ReporteVentasModel();
        $this->produccion = new ReporteProduccionModel();
        $this->tesoreria = new ReporteTesoreriaModel();
        $this->dashboardModel = new ReporteDashboardModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.dashboard.ver');
        $this->registrarAuditoria('dashboard');

        // Obtenemos los productos críticos usando el nuevo modelo
        $productosCriticos = $this->dashboardModel->obtenerProductosCriticos(10);

        $this->render('reportes/dashboard', [
            'ruta_actual' => 'reportes/dashboard',
            'inventario_valorizado' => $this->inventario->resumenValorizacionDashboard(),
            'reportes_widgets' => [
                'stock_critico' => $this->inventario->contarStockCritico(),
                'compras_pendientes' => $this->compras->contarPendientes(),
                'ventas_por_despachar' => $this->ventas->contarPorDespachar(),
                'produccion_proceso' => $this->produccion->contarEnProceso(),
                'cxc_vencida' => $this->tesoreria->contarCxcVencida(),
                'cxp_vencida' => $this->tesoreria->contarCxpVencida(),
            ],
            'productosCriticos' => $productosCriticos
        ]);
    }

    private function registrarAuditoria(string $reporte): void
    {
        try {
            $this->usuariosModel->insertar_bitacora(
                (int) ($_SESSION['id'] ?? 0),
                'REPORTES_VER',
                'Consulta reporte: ' . $reporte,
                (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            );
        } catch (Throwable $e) {
            // no-op para no interrumpir
        }
    }
}