<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ReporteInventarioModel.php';
require_once BASE_PATH . '/app/models/ReporteComprasModel.php';
require_once BASE_PATH . '/app/models/ReporteVentasModel.php';
require_once BASE_PATH . '/app/models/ReporteProduccionModel.php';
require_once BASE_PATH . '/app/models/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class ReportesController extends Controlador
{
    private ReporteInventarioModel $inventario;
    private ReporteComprasModel $compras;
    private ReporteVentasModel $ventas;
    private ReporteProduccionModel $produccion;
    private ReporteTesoreriaModel $tesoreria;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        $this->inventario = new ReporteInventarioModel();
        $this->compras = new ReporteComprasModel();
        $this->ventas = new ReporteVentasModel();
        $this->produccion = new ReporteProduccionModel();
        $this->tesoreria = new ReporteTesoreriaModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function dashboard(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.dashboard.ver');
        $this->registrarAuditoria('dashboard');

        $this->render('dashboard', [
            'ruta_actual' => 'reportes/dashboard',
            'reportes_widgets' => [
                'stock_critico' => $this->inventario->contarStockCritico(),
                'compras_pendientes' => $this->compras->contarPendientes(),
                'ventas_por_despachar' => $this->ventas->contarPorDespachar(),
                'produccion_proceso' => $this->produccion->contarEnProceso(),
                'cxc_vencida' => $this->tesoreria->contarCxcVencida(),
                'cxp_vencida' => $this->tesoreria->contarCxpVencida(),
            ],
        ]);
    }

    public function inventario(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.inventario.ver');
        $this->registrarAuditoria('inventario');
        [$pagina, $tamano] = $this->paginacion();

        $f = [
            'fecha_desde' => (string) ($_GET['fecha_desde'] ?? date('Y-m-01')),
            'fecha_hasta' => (string) ($_GET['fecha_hasta'] ?? date('Y-m-d')),
            'id_almacen' => (int) ($_GET['id_almacen'] ?? 0),
            'id_categoria' => (int) ($_GET['id_categoria'] ?? 0),
            'tipo_item' => trim((string) ($_GET['tipo_item'] ?? '')),
            'estado' => $_GET['estado'] ?? '',
            'solo_bajo_minimo' => (int) ($_GET['solo_bajo_minimo'] ?? 0),
            'id_item' => (int) ($_GET['id_item'] ?? 0),
            'tipo_movimiento' => trim((string) ($_GET['tipo_movimiento'] ?? '')),
            'dias' => (int) ($_GET['dias'] ?? 30),
        ];

        $this->render('reportes_inventario', [
            'ruta_actual' => 'reportes/inventario',
            'filtros' => $f,
            'stock' => $this->inventario->stockActual($f, $pagina, $tamano),
            'kardex' => $this->inventario->kardex($f, $pagina, $tamano),
            'vencimientos' => $this->inventario->vencimientos($f, $pagina, $tamano),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function compras(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.compras.ver');
        $this->registrarAuditoria('compras');
        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['id_proveedor'] = (int) ($_GET['id_proveedor'] ?? 0);
        $f['id_almacen'] = (int) ($_GET['id_almacen'] ?? 0);

        $this->render('reportes_compras', [
            'ruta_actual' => 'reportes/compras',
            'filtros' => $f,
            'porProveedor' => $this->compras->comprasPorProveedor($f, $pagina, $tamano),
            'ocCumplimiento' => $this->compras->ocCumplimiento($f, $pagina, $tamano),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function ventas(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.ventas.ver');
        $this->registrarAuditoria('ventas');
        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['id_cliente'] = (int) ($_GET['id_cliente'] ?? 0);
        $f['estado'] = $_GET['estado'] ?? '';

        $this->render('reportes_ventas', [
            'ruta_actual' => 'reportes/ventas',
            'filtros' => $f,
            'porCliente' => $this->ventas->ventasPorCliente($f, $pagina, $tamano),
            'pendientes' => $this->ventas->pendientesDespacho($f, $pagina, $tamano),
            'topProductos' => $this->ventas->topProductos($f, 10),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function produccion(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.produccion.ver');
        $this->registrarAuditoria('produccion');
        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['id_item'] = (int) ($_GET['id_item'] ?? 0);

        $this->render('reportes_produccion', [
            'ruta_actual' => 'reportes/produccion',
            'filtros' => $f,
            'porProducto' => $this->produccion->produccionPorProducto($f, $pagina, $tamano),
            'consumos' => $this->produccion->consumoInsumos($f, $pagina, $tamano),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function tesoreria(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver');
        $this->registrarAuditoria('tesoreria');
        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['id_cuenta'] = (int) ($_GET['id_cuenta'] ?? 0);

        $this->render('reportes_tesoreria', [
            'ruta_actual' => 'reportes/tesoreria',
            'filtros' => $f,
            'agingCxc' => $this->tesoreria->agingCxc($f, $pagina, $tamano),
            'agingCxp' => $this->tesoreria->agingCxp($f, $pagina, $tamano),
            'flujo' => $this->tesoreria->flujoPorCuenta($f, $pagina, $tamano),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    private function filtrosPeriodo(): array
    {
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));

        if ($fechaDesde === '' || $fechaHasta === '') {
            $fechaDesde = date('Y-m-01');
            $fechaHasta = date('Y-m-d');
        }

        if ($fechaDesde > $fechaHasta) {
            [$fechaDesde, $fechaHasta] = [$fechaHasta, $fechaDesde];
        }

        return [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
        ];
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
            // no-op para no interrumpir la visualización de reportes
        }
    }

    private function paginacion(): array
    {
        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $tamano = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        return [$pagina, $tamano];
    }
}
