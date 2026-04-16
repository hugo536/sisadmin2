<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteInventarioModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteComprasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteVentasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteProduccionModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';
require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';

class ReportesController extends Controlador
{
    private ReporteInventarioModel $inventario;
    private ReporteComprasModel $compras;
    private ReporteVentasModel $ventas;
    private ReporteProduccionModel $produccion;
    private ReporteTesoreriaModel $tesoreria;
    private UsuariosModel $usuariosModel;
    private VentasDocumentoModel $ventasDocumentoModel;

    public function __construct()
    {
        $this->inventario = new ReporteInventarioModel();
        $this->compras = new ReporteComprasModel();
        $this->ventas = new ReporteVentasModel();
        $this->produccion = new ReporteProduccionModel();
        $this->tesoreria = new ReporteTesoreriaModel();
        $this->usuariosModel = new UsuariosModel();
        $this->ventasDocumentoModel = new VentasDocumentoModel();
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

        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'stock'));
        
        // 1. Agregamos 'historico' a las secciones permitidas
        if (!in_array($seccionActiva, ['stock', 'historico', 'kardex', 'vencimientos'])) {
            $seccionActiva = 'stock';
        }

        // 2. Agregamos 'fecha_corte' a los filtros
        $f = [
            'fecha_desde' => (string) ($_GET['fecha_desde'] ?? date('Y-m-01')),
            'fecha_hasta' => (string) ($_GET['fecha_hasta'] ?? date('Y-m-d')),
            'fecha_corte' => trim((string) ($_GET['fecha_corte'] ?? date('Y-m-d\TH:i'))),
            'id_almacen' => (int) ($_GET['id_almacen'] ?? 0),
            'id_categoria' => (int) ($_GET['id_categoria'] ?? 0),
            'tipo_item' => isset($_GET['tipo_item']) ? (is_array($_GET['tipo_item']) ? $_GET['tipo_item'] : trim((string) $_GET['tipo_item'])) : '',
            'solo_bajo_minimo' => (int) ($_GET['solo_bajo_minimo'] ?? 0),
            'id_item' => (int) ($_GET['id_item'] ?? 0),
            'tipo_movimiento' => trim((string) ($_GET['tipo_movimiento'] ?? '')),
            'dias' => (int) ($_GET['dias'] ?? 30),
            'situacion_alerta' => trim((string) ($_GET['situacion_alerta'] ?? '')),
            'seccion_activa' => $seccionActiva 
        ];

        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $stock = ($seccionActiva === 'stock') ? $this->inventario->stockActual($f, 1, 999999) : [];
            // 3. Cargamos los datos del histórico si piden PDF
            $historico = ($seccionActiva === 'historico') ? $this->inventario->stockAFecha($f, 1, 999999) : [];
            $kardex = ($seccionActiva === 'kardex') ? $this->inventario->kardex($f, 1, 999999) : [];
            $vencimientos = ($seccionActiva === 'vencimientos') ? $this->inventario->vencimientos($f, 1, 999999) : [];

            $almacenNombre = 'TODOS LOS ALMACENES';
            $idAlmacenSeleccionado = (int) ($f['id_almacen'] ?? 0);
            if ($idAlmacenSeleccionado > 0) {
                foreach ($this->inventario->listarAlmacenesActivos() as $almacen) {
                    if ((int) ($almacen['id'] ?? 0) === $idAlmacenSeleccionado) {
                        $almacenNombre = mb_strtoupper((string) ($almacen['nombre'] ?? ''));
                        break;
                    }
                }
            }

            $filtros = $f;

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_inventario.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape'); 
            $dompdf->render();
            
            $nombreArchivo = 'Reporte_Inventario_' . ucfirst($seccionActiva) . '.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => false]);
            return;
        }

        $datosVista = [
            'ruta_actual' => 'reportes/inventario',
            'filtros' => $f,
            'almacenes' => $this->inventario->listarAlmacenesActivos(),
            'categorias' => $this->inventario->listarCategoriasActivas(),
            'stock' => [],
            'historico' => [], // Inicializamos la variable para la vista
            'kardex' => [],
            'vencimientos' => [],
            'pagina' => $pagina,
            'tamano' => $tamano,
            'datosGraficoDona' => [],
            'datosGraficoBarras' => []
        ];

        if ($seccionActiva === 'stock') {
            $datosVista['stock'] = $this->inventario->stockActual($f, $pagina, $tamano);
            
            $tiposValor = [];
            $topItems = [];
            
            if (!empty($datosVista['stock']['rows'])) {
                foreach ($datosVista['stock']['rows'] as $row) {
                    $claveDona = $row['alerta']; 
                    if (!isset($tiposValor[$claveDona])) {
                        $tiposValor[$claveDona] = 0;
                    }
                    $tiposValor[$claveDona] += (float) $row['valor_total'];

                    $topItems[] = [
                        'nombre' => $row['item'],
                        'valor' => (float) $row['valor_total']
                    ];
                }
                
                usort($topItems, fn($a, $b) => $b['valor'] <=> $a['valor']);
                $topItems = array_slice($topItems, 0, 5);
            }

            $datosVista['datosGraficoDona'] = [
                'labels' => array_keys($tiposValor),
                'data' => array_values($tiposValor)
            ];
            
            $datosVista['datosGraficoBarras'] = [
                'labels' => array_column($topItems, 'nombre'),
                'data' => array_column($topItems, 'valor')
            ];

        // 4. Ejecutamos la consulta si estamos en la pestaña histórico
        } elseif ($seccionActiva === 'historico') {
            $datosVista['historico'] = $this->inventario->stockAFecha($f, $pagina, $tamano);
        } elseif ($seccionActiva === 'kardex') {
            $datosVista['kardex'] = $this->inventario->kardex($f, $pagina, $tamano);
        } elseif ($seccionActiva === 'vencimientos') {
            $datosVista['vencimientos'] = $this->inventario->vencimientos($f, $pagina, $tamano);
        }

        $this->render('reportes/inventario', $datosVista);
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

    // =======================================================
    // FUNCIÓN VENTAS OPTIMIZADA ÚNICA
    // =======================================================
    public function ventas(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.ventas.ver');
        $this->registrarAuditoria('ventas');
        
        $tipoTercero = trim((string) ($_GET['tipo_tercero'] ?? ''));
        if (!in_array($tipoTercero, ['', 'cliente', 'cliente_distribuidor', 'distribuidor'], true)) {
            $tipoTercero = '';
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_clientes') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->ventasDocumentoModel->buscarClientes($q, 20, $tipoTercero)]);
            return;
        }
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_productos') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->ventasDocumentoModel->buscarItems($q, 0, 0, 1, 40)]);
            return;
        }

        [$pagina, $tamano] = $this->paginacion();
        
        // 1. Capturamos la pestaña activa
        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'tendencias'));
        if (!in_array($seccionActiva, ['tendencias', 'clientes', 'productos', 'pendientes'])) {
            $seccionActiva = 'tendencias';
        }

        $f = $this->filtrosPeriodo();
        $f['id_cliente'] = (int) ($_GET['id_cliente'] ?? 0);
        $f['tipo_tercero'] = $tipoTercero; // Aseguramos que pase el tipo tercero
        $f['id_item'] = (int) ($_GET['id_item'] ?? 0);
        $f['estado'] = $_GET['estado'] ?? '';
        $f['agrupacion'] = ($_GET['agrupacion'] ?? 'diaria') === 'semanal' ? 'semanal' : 'diaria';
        $f['tipo_grafico'] = ($_GET['tipo_grafico'] ?? 'barras') === 'linea' ? 'linea' : 'barras';
        $f['seccion_activa'] = $seccionActiva;

        // ==========================================
        // INTERCEPTAR LA PETICIÓN DE IMPRESIÓN PDF
        // ==========================================
        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            // Optimización: Solo cargamos los datos de la pestaña que se está exportando
            // Pasamos un límite alto (999999) para que el PDF imprima todo, no solo la primera página
            $porPeriodo = ($seccionActiva === 'tendencias') ? $this->ventas->ventasPorPeriodo($f, $f['agrupacion'], 365) : []; 
            $porCliente = ($seccionActiva === 'clientes') ? $this->ventas->ventasPorCliente($f, 1, 999999) : [];
            $topProductos = ($seccionActiva === 'productos') ? $this->ventas->topProductos($f, 100) : []; 
            $pendientes = ($seccionActiva === 'pendientes') ? $this->ventas->pendientesDespacho($f, 1, 999999) : [];
            
            $filtros = $f;

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_ventas.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait'); 
            $dompdf->render();
            
            $nombreArchivo = 'Reporte_Ventas_' . ucfirst($seccionActiva) . '.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => false]);
            return;
        }
        // ==========================================

        // Vista Web normal (También optimizada)
        $this->render('reportes/ventas', [
            'ruta_actual' => 'reportes/ventas',
            'filtros' => $f,
            'clientesFiltro' => $this->ventasDocumentoModel->buscarClientes('', 200, $tipoTercero),
            'productosFiltro' => $this->ventasDocumentoModel->buscarItems('', 0, 0, 1, 200),
            'porCliente' => ($seccionActiva === 'clientes') ? $this->ventas->ventasPorCliente($f, $pagina, $tamano) : [],
            'pendientes' => ($seccionActiva === 'pendientes') ? $this->ventas->pendientesDespacho($f, $pagina, $tamano) : [],
            'topProductos' => ($seccionActiva === 'productos') ? $this->ventas->topProductos($f, 10) : [],
            'porPeriodo' => ($seccionActiva === 'tendencias') ? $this->ventas->ventasPorPeriodo($f, $f['agrupacion'], 12) : [],
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
            'ruta_actual' => 'reportes/costos_produccion',
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
        
        // 1. Capturamos la pestaña activa (Igual que en ventas)
        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'cxc'));
        if (!in_array($seccionActiva, ['cxc', 'cxp', 'flujo', 'depositos'])) {
            $seccionActiva = 'cxc';
        }

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['id_tercero'] = (int) ($_GET['id_tercero'] ?? 0);
        $f['seccion_activa'] = $seccionActiva;

        // ==========================================
        // INTERCEPTAR LA PETICIÓN DE IMPRESIÓN PDF
        // ==========================================
        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            // Optimización: Solo cargamos los datos de la pestaña que se está exportando
            $agingCxc = ($seccionActiva === 'cxc') ? $this->tesoreria->agingCxc($f, 1, 999999) : [];
            $agingCxp = ($seccionActiva === 'cxp') ? $this->tesoreria->agingCxp($f, 1, 999999) : [];
            $flujo = ($seccionActiva === 'flujo') ? $this->tesoreria->flujoPorCuenta($f, 1, 999999) : [];
            $depositos = ($seccionActiva === 'depositos') ? $this->tesoreria->reporteDepositos($f, 1, 999999) : [];
            
            $filtros = $f;

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_tesoreria.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait'); 
            $dompdf->render();
            
            $nombreArchivo = 'Reporte_Tesoreria_' . ucfirst($seccionActiva) . '.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => false]);
            return;
        }
        // ==========================================

        // Vista Web normal
        $this->render('reportes/tesoreria', [
            'ruta_actual' => 'reportes/tesoreria',
            'filtros' => $f,
            'tercerosFiltro' => $this->tesoreria->listarTercerosFiltroTesoreria(),
            'agingCxc' => ($seccionActiva === 'cxc') ? $this->tesoreria->agingCxc($f, $pagina, $tamano) : [],
            'agingCxp' => ($seccionActiva === 'cxp') ? $this->tesoreria->agingCxp($f, $pagina, $tamano) : [],
            'flujo' => ($seccionActiva === 'flujo') ? $this->tesoreria->flujoPorCuenta($f, $pagina, $tamano) : [],
            'depositos' => ($seccionActiva === 'depositos') ? $this->tesoreria->reporteDepositos($f, $pagina, $tamano) : [],
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

        $accion = $_GET['accion'] ?? '';
        if ($accion === 'imprimir_estado_cuenta') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $empresaModel = new EmpresaModel();
            $config = $empresaModel->obtener();

            $detalle = $this->tesoreria->historialEstadoCuenta($f, 1, 999999);

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
