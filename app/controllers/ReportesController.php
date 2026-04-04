<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteInventarioModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteComprasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteVentasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteProduccionModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
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

        $this->render('reportes/inventario', [
            'ruta_actual' => 'reportes/inventario',
            'filtros' => $f,
            'almacenes' => $this->inventario->listarAlmacenesActivos(),
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

        $this->render('reportes/compras', [
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
        $f['agrupacion'] = ($_GET['agrupacion'] ?? 'diaria') === 'semanal' ? 'semanal' : 'diaria';

        $this->render('reportes/ventas', [
            'ruta_actual' => 'reportes/ventas',
            'filtros' => $f,
            'porCliente' => $this->ventas->ventasPorCliente($f, $pagina, $tamano),
            'pendientes' => $this->ventas->pendientesDespacho($f, $pagina, $tamano),
            'topProductos' => $this->ventas->topProductos($f, 10),
            'porPeriodo' => $this->ventas->ventasPorPeriodo($f, $f['agrupacion'], 12),
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

        $this->render('reportes/produccion', [
            'ruta_actual' => 'reportes/produccion',
            'filtros' => $f,
            'porProducto' => $this->produccion->produccionPorProducto($f, $pagina, $tamano),
            'consumos' => $this->produccion->consumoInsumos($f, $pagina, $tamano),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function costos_produccion(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.produccion.ver');
        $this->registrarAuditoria('costos_produccion');
        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();

        $costosPorOrden = $this->produccion->costosPorOrden($f, $pagina, $tamano);
        $costosMensuales = $this->produccion->costosMensualesMdModCif($f);

        $rows = $costosPorOrden['rows'] ?? [];
        $resumen = [
            'ordenes' => (int) ($costosPorOrden['total'] ?? 0),
            'teorico_total' => 0.0,
            'real_total' => 0.0,
            'variacion_total' => 0.0,
            'desviadas' => 0,
        ];

        foreach ($rows as $row) {
            $teorico = (float) ($row['costo_teorico_total_snapshot'] ?? 0);
            $real = (float) ($row['costo_real_total'] ?? 0);
            $variacion = (float) ($row['variacion_total'] ?? 0);

            $resumen['teorico_total'] += $teorico;
            $resumen['real_total'] += $real;
            $resumen['variacion_total'] += $variacion;

            if (abs($variacion) > 0.0001) {
                $resumen['desviadas']++;
            }
        }

        $insightMensual = [
            'periodo' => '-',
            'variacion_total' => 0.0,
            'variacion_pct' => 0.0,
            'ordenes' => 0,
        ];

        foreach ($costosMensuales as $mes) {
            $varMes = (float) ($mes['variacion_total'] ?? 0);
            if (abs($varMes) < abs($insightMensual['variacion_total'])) {
                continue;
            }

            $teoricoMes = (float) ($mes['costo_teorico_total'] ?? 0);
            $pctMes = $teoricoMes > 0 ? (($varMes / $teoricoMes) * 100) : 0;

            $insightMensual = [
                'periodo' => (string) ($mes['periodo'] ?? '-'),
                'variacion_total' => $varMes,
                'variacion_pct' => $pctMes,
                'ordenes' => (int) ($mes['ordenes'] ?? 0),
            ];
        }

        $this->render('costos/produccion', [
            'ruta_actual' => 'reportes/costos_produccion', // <-- Esto se queda igual para el sidebar
            'filtros' => $f,
            'costosPorOrden' => $costosPorOrden,
            'costosMensuales' => $costosMensuales,
            'insightMensual' => $insightMensual,
            'resumenCostos' => $resumen,
            'resumenGerencial' => $this->produccion->resumenGerencialMensual($f),
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

        $this->render('reportes/tesoreria', [
            'ruta_actual' => 'reportes/tesoreria',
            'filtros' => $f,
            'agingCxc' => $this->tesoreria->agingCxc($f, $pagina, $tamano),
            'agingCxp' => $this->tesoreria->agingCxp($f, $pagina, $tamano),
            'flujo' => $this->tesoreria->flujoPorCuenta($f, $pagina, $tamano),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function estado_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver');
        $this->registrarAuditoria('estado_cuenta');

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['cliente'] = trim((string) ($_GET['cliente'] ?? ''));
        $f['producto'] = trim((string) ($_GET['producto'] ?? ''));
        $f['estado'] = strtoupper(trim((string) ($_GET['estado'] ?? '')));
        $f['vista'] = trim((string) ($_GET['vista'] ?? 'DETALLE'));
        
        if (!in_array($f['estado'], ['', 'PENDIENTE', 'PARCIAL', 'PAGADA', 'VENCIDA', 'ANULADA'], true)) {
            $f['estado'] = '';
        }
        if (!in_array($f['vista'], ['DETALLE', 'PRODUCTO'], true)) {
            $f['vista'] = 'DETALLE';
        }

        // ==========================================
        // INTERCEPTAR LA PETICIÓN DE IMPRESIÓN
        // ==========================================
        $accion = $_GET['accion'] ?? '';
        if ($accion === 'imprimir_estado_cuenta') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();

            // Traemos TODOS los registros sin paginación para el PDF.
            $detalle = $this->tesoreria->historialEstadoCuenta($f, 1, 999999);

            // Capturamos la vista HTML y la convertimos a PDF, igual que en ventas.
            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_estado_cuenta.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('Estado_Cuenta.pdf', ['Attachment' => false]);
            return;
        }
        // ==========================================

        $detalle = $this->tesoreria->historialEstadoCuenta($f, $pagina, $tamano);
        $porProducto = $this->tesoreria->estadoCuentaPorProducto($f, 200);

        $this->render('reportes/estado_cuenta', [
            'ruta_actual' => 'reportes/estado_cuenta',
            'filtros' => $f,
            'detalle' => $detalle,
            'porProducto' => $porProducto,
            'clientesEstadoCuenta' => $this->tesoreria->listarClientesEstadoCuenta(),
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    public function estado_cuenta_proveedores(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver');
        $this->registrarAuditoria('estado_cuenta_proveedores');

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['proveedor'] = trim((string) ($_GET['proveedor'] ?? ''));
        $f['producto'] = trim((string) ($_GET['producto'] ?? ''));
        $f['estado'] = strtoupper(trim((string) ($_GET['estado'] ?? '')));
        $f['vista'] = trim((string) ($_GET['vista'] ?? 'DETALLE'));

        if (!in_array($f['estado'], ['', 'PENDIENTE', 'PARCIAL', 'PAGADA', 'VENCIDA', 'ANULADA'], true)) {
            $f['estado'] = '';
        }
        if (!in_array($f['vista'], ['DETALLE', 'PRODUCTO'], true)) {
            $f['vista'] = 'DETALLE';
        }

        $accion = $_GET['accion'] ?? '';
        if ($accion === 'imprimir_estado_cuenta_proveedores') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();

            $detalle = $this->tesoreria->historialEstadoCuentaProveedores($f, 1, 999999);

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_estado_cuenta_proveedores.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $dompdf->stream('Estado_Cuenta_Proveedores.pdf', ['Attachment' => false]);
            return;
        }

        $detalle = $this->tesoreria->historialEstadoCuentaProveedores($f, $pagina, $tamano);
        $porProducto = $this->tesoreria->estadoCuentaProveedoresPorProducto($f, 200);

        $this->render('reportes/estado_cuenta_proveedores', [
            'ruta_actual' => 'reportes/estado_cuenta_proveedores',
            'filtros' => $f,
            'detalle' => $detalle,
            'porProducto' => $porProducto,
            'proveedoresEstadoCuenta' => $this->tesoreria->listarProveedoresEstadoCuenta(),
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
            $fechaHasta = date('Y-m-t');
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
