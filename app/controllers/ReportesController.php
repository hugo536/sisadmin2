<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/reportes/ReporteInventarioModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteProduccionModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteTesoreriaMovimientoModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class ReportesController extends Controlador
{
    private ReporteInventarioModel $inventario;
    private ReporteProduccionModel $produccion;
    private ReporteTesoreriaModel $tesoreria;
    private ReporteTesoreriaMovimientoModel $reporteTesoreriaMov;
    private UsuariosModel $usuariosModel;

    private function normalizarIdsFiltro($valor): array
    {
        $lista = is_array($valor) ? $valor : [$valor];
        return array_values(array_unique(array_filter(array_map(static fn($v) => (int) $v, $lista), static fn($v) => $v > 0)));
    }

    private function normalizarTextoFiltro($valor): array
    {
        $lista = is_array($valor) ? $valor : [$valor];
        return array_values(array_unique(array_filter(array_map(static fn($v) => trim((string) $v), $lista), static fn($v) => $v !== '')));
    }

    public function __construct()
    {
        $this->inventario = new ReporteInventarioModel();
        $this->produccion = new ReporteProduccionModel();
        $this->tesoreria = new ReporteTesoreriaModel();
        $this->reporteTesoreriaMov = new ReporteTesoreriaMovimientoModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function inventario(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.inventario.ver');
        $this->registrarAuditoria('inventario');
        [$pagina, $tamano] = $this->paginacion();

        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'stock'));
        
        if (!in_array($seccionActiva, ['stock', 'historico', 'kardex', 'vencimientos'])) {
            $seccionActiva = 'stock';
        }

        $f = [
            'fecha_desde' => (string) ($_GET['fecha_desde'] ?? date('Y-m-01')),
            'fecha_hasta' => (string) ($_GET['fecha_hasta'] ?? date('Y-m-d')),
            'fecha_corte' => trim((string) ($_GET['fecha_corte'] ?? date('Y-m-d\TH:i'))),
            'id_almacen' => $this->normalizarIdsFiltro($_GET['id_almacen'] ?? []),
            'id_categoria' => $this->normalizarIdsFiltro($_GET['id_categoria'] ?? []),
            'tipo_item' => $this->normalizarTextoFiltro($_GET['tipo_item'] ?? []),
            'solo_bajo_minimo' => (int) ($_GET['solo_bajo_minimo'] ?? 0),
            'id_item' => (int) ($_GET['id_item'] ?? 0),
            'tipo_movimiento' => trim((string) ($_GET['tipo_movimiento'] ?? '')),
            'dias' => (int) ($_GET['dias'] ?? 30),
            'situacion_alerta' => trim((string) ($_GET['situacion_alerta'] ?? '')),
            'seccion_activa' => $seccionActiva,
            'ocultar_valores' => (int) ($_GET['ocultar_valores'] ?? 0)
        ];

        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $stock = ($seccionActiva === 'stock') ? $this->inventario->stockActual($f, 1, 999999) : [];
            $historico = ($seccionActiva === 'historico') ? $this->inventario->stockAFecha($f, 1, 999999) : [];
            $kardex = ($seccionActiva === 'kardex') ? $this->inventario->kardex($f, 1, 999999) : [];
            $vencimientos = ($seccionActiva === 'vencimientos') ? $this->inventario->vencimientos($f, 1, 999999) : [];

            $busqueda = mb_strtolower(trim((string) ($_GET['busqueda'] ?? '')));
            $rawAlertas = $_GET['alertas'] ?? [];
            $alertasSeleccionadas = is_array($rawAlertas) ? $rawAlertas : (trim((string)$rawAlertas) !== '' ? [$rawAlertas] : []);

            if ($seccionActiva === 'stock' && !empty($stock['rows']) && ($busqueda !== '' || !empty($alertasSeleccionadas))) {
                $filtro = [];
                $nuevoTotal = 0;
                foreach ($stock['rows'] as $r) {
                    $textoBusqueda = mb_strtolower(($r['item'] ?? '') . ' ' . ($r['almacen'] ?? ''));
                    if ($busqueda !== '' && mb_strpos($textoBusqueda, $busqueda) === false) {
                        continue;
                    }
                    if (!empty($alertasSeleccionadas)) {
                        $alertaRaw = mb_strtolower((string)($r['alerta'] ?? ''));
                        $estado = 'disponible';
                        if (mb_strpos($alertaRaw, 'bajo') !== false || mb_strpos($alertaRaw, 'crític') !== false) $estado = 'bajo_mínimo';
                        elseif (mb_strpos($alertaRaw, 'vencido') !== false) $estado = 'vencido';
                        elseif (mb_strpos($alertaRaw, 'agotado') !== false) $estado = 'agotado';
                        elseif (mb_strpos($alertaRaw, 'próximo') !== false || mb_strpos($alertaRaw, 'proximo') !== false) $estado = 'próximo_a_vencer';
                        elseif (mb_strpos($alertaRaw, 'sin mov') !== false) $estado = 'sin_movimientos';
                        
                        if (!in_array($estado, $alertasSeleccionadas)) continue;
                    }
                    
                    $filtro[] = $r;
                    $nuevoTotal += (float)($r['valor_total'] ?? 0);
                }
                $stock['rows'] = $filtro;
                $stock['valor_total'] = $nuevoTotal;

            } elseif ($seccionActiva === 'historico' && !empty($historico['rows']) && $busqueda !== '') {
                $filtro = [];
                $nuevoTotal = 0;
                foreach ($historico['rows'] as $r) {
                    $textoBusqueda = mb_strtolower(($r['item'] ?? '') . ' ' . ($r['almacen'] ?? ''));
                    if (mb_strpos($textoBusqueda, $busqueda) !== false) {
                        $filtro[] = $r;
                        $nuevoTotal += (float)($r['valor_total'] ?? 0);
                    }
                }
                $historico['rows'] = $filtro;
                $historico['valor_total'] = $nuevoTotal;

            } elseif ($seccionActiva === 'kardex' && !empty($kardex['rows']) && $busqueda !== '') {
                $filtro = [];
                foreach ($kardex['rows'] as $r) {
                    $textoBusqueda = mb_strtolower(($r['referencia'] ?? '') . ' ' . ($r['tipo'] ?? ''));
                    if (mb_strpos($textoBusqueda, $busqueda) !== false) {
                        $filtro[] = $r;
                    }
                }
                $kardex['rows'] = $filtro;

            } elseif ($seccionActiva === 'vencimientos' && !empty($vencimientos['rows']) && $busqueda !== '') {
                $filtro = [];
                foreach ($vencimientos['rows'] as $r) {
                    $textoBusqueda = mb_strtolower(($r['item'] ?? '') . ' ' . ($r['lote'] ?? ''));
                    if (mb_strpos($textoBusqueda, $busqueda) !== false) {
                        $filtro[] = $r;
                    }
                }
                $vencimientos['rows'] = $filtro;
            }

            $almacenNombre = 'TODOS LOS ALMACENES';
            $idsAlmacenSeleccionados = $this->normalizarIdsFiltro($f['id_almacen'] ?? []);
            if (!empty($idsAlmacenSeleccionados)) {
                $mapaAlmacenes = [];
                foreach ($this->inventario->listarAlmacenesActivos() as $almacen) {
                    $mapaAlmacenes[(int) ($almacen['id'] ?? 0)] = (string) ($almacen['nombre'] ?? '');
                }
                $nombres = [];
                foreach ($idsAlmacenSeleccionados as $idAlmacenSeleccionado) {
                    if (isset($mapaAlmacenes[$idAlmacenSeleccionado])) {
                        $nombres[] = mb_strtoupper($mapaAlmacenes[$idAlmacenSeleccionado]);
                    }
                }
                if (count($nombres) === 1) {
                    $almacenNombre = $nombres[0];
                } elseif (count($nombres) > 1) {
                    $almacenNombre = count($nombres) . ' ALMACENES SELECCIONADOS';
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
            'historico' => [], 
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

        } elseif ($seccionActiva === 'historico') {
            $datosVista['historico'] = $this->inventario->stockAFecha($f, $pagina, $tamano);
        } elseif ($seccionActiva === 'kardex') {
            $datosVista['kardex'] = $this->inventario->kardex($f, $pagina, $tamano);
        } elseif ($seccionActiva === 'vencimientos') {
            $datosVista['vencimientos'] = $this->inventario->vencimientos($f, $pagina, $tamano);
        }

        $this->render('reportes/inventario', $datosVista);
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
        
        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'cxc'));
        if (!in_array($seccionActiva, ['cxc', 'cxp', 'flujo', 'depositos'])) {
            $seccionActiva = 'cxc';
        }

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();
        $f['id_tercero'] = (int) ($_GET['id_tercero'] ?? 0);
        $f['seccion_activa'] = $seccionActiva;

        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

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

    public function tesoreria_movimientos(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.tesoreria.ver'); 
        $this->registrarAuditoria('tesoreria_movimientos');

        [$pagina, $tamano] = $this->paginacion();
        $f = $this->filtrosPeriodo();

        $f['id_cuenta'] = $this->normalizarIdsFiltro($_GET['id_cuenta'] ?? []);
        $f['id_metodo_pago'] = $this->normalizarTextoFiltro($_GET['id_metodo_pago'] ?? []);
        $f['origen'] = array_map('strtoupper', $this->normalizarTextoFiltro($_GET['origen'] ?? []));
        
        $f['busqueda'] = mb_strtolower(trim((string) ($_GET['busqueda'] ?? '')));
        $f['id_origen'] = (int) ($_GET['id_origen'] ?? 0);
        $f['id_tercero'] = (int) ($_GET['id_tercero'] ?? 0);

        $resumenCuentas = $this->reporteTesoreriaMov->listarCuentas(); 
        $metodos = $this->reporteTesoreriaMov->listarMetodosPago();

        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $resultadoPdf = $this->reporteTesoreriaMov->listarMovimientos($f, 1, 999999);
            $movimientosPdf = $resultadoPdf['rows'] ?? [];

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_tesoreria_movimiento.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape'); 
            $dompdf->render();
            
            $nombreArchivo = 'Reporte_Movimientos_Tesoreria.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => false]);
            return;
        }

        $resultado = $this->reporteTesoreriaMov->listarMovimientos($f, $pagina, $tamano);

        $this->render('reportes/tesoreria_movimiento', [
            'ruta_actual' => 'reportes/tesoreria_movimientos',
            'filtros' => $f,
            'resumenCuentas' => $resumenCuentas,
            'metodos' => $metodos,
            'movimientos' => $resultado['rows'] ?? [], 
            'total_registros' => $resultado['total'] ?? 0, 
            'pagina' => $pagina,
            'tamano' => $tamano,
        ]);
    }

    private function filtrosPeriodo(): array
    {
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));

        if ($fechaDesde === '' || $fechaHasta === '') {
            $fechaDesde = date('Y-m-d', strtotime('-30 days'));
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
            
        }
    }

    private function paginacion(): array
    {
        $pagina = max(1, (int) ($_GET['page'] ?? 1));
        $tamano = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        return [$pagina, $tamano];
    }
}
