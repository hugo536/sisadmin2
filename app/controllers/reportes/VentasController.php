<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteVentasModel.php';
require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';

class ReporteVentasController extends Controlador
{
    private ReporteVentasModel $ventas;
    private VentasDocumentoModel $ventasDocumentoModel;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->ventas = new ReporteVentasModel();
        $this->ventasDocumentoModel = new VentasDocumentoModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
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
        
        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'tendencias'));
        if (!in_array($seccionActiva, ['tendencias', 'clientes', 'productos', 'pendientes'])) {
            $seccionActiva = 'tendencias';
        }

        $f = $this->filtrosPeriodo();
        $f['id_cliente'] = (int) ($_GET['id_cliente'] ?? 0);
        $f['id_categoria'] = (int) ($_GET['id_categoria'] ?? 0);
        $f['tipo_tercero'] = $tipoTercero; 
        
        $productoFiltro = trim((string) ($_GET['id_item'] ?? ''));
        $f['producto_filtro'] = $productoFiltro;
        $f['id_item'] = 0;
        $f['id_presentacion'] = 0;
        if (preg_match('/^PACK-(\d+)$/', $productoFiltro, $coincidencia)) {
            $f['id_presentacion'] = (int) $coincidencia[1];
        } elseif (preg_match('/^ITEM-(\d+)$/', $productoFiltro, $coincidencia)) {
            $f['id_item'] = (int) $coincidencia[1];
        } elseif (ctype_digit($productoFiltro)) {
            $f['id_item'] = (int) $productoFiltro;
            $f['producto_filtro'] = 'ITEM-' . $f['id_item'];
        }
        $f['estado'] = $_GET['estado'] ?? 'validas';
        $agrupacionFiltro = $_GET['agrupacion'] ?? 'diaria';
        $f['agrupacion'] = in_array($agrupacionFiltro, ['diaria', 'semanal', 'mensual']) ? $agrupacionFiltro : 'diaria';
        $f['tipo_grafico'] = ($_GET['tipo_grafico'] ?? 'linea') === 'barras' ? 'barras' : 'linea';
        $f['seccion_activa'] = $seccionActiva;

        $limiteTendencia = 12;
        if ($f['agrupacion'] === 'diaria') $limiteTendencia = 365;
        elseif ($f['agrupacion'] === 'semanal') $limiteTendencia = 52;
        elseif ($f['agrupacion'] === 'mensual') $limiteTendencia = 24;

        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $porPeriodo = ($seccionActiva === 'tendencias') ? $this->ventas->ventasPorPeriodo($f, $f['agrupacion'], $limiteTendencia) : []; 
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
            $dompdf->setPaper('A4', 'landscape'); 
            $dompdf->render();
            
            $nombreArchivo = 'Reporte_Ventas_' . ucfirst($seccionActiva) . '.pdf';

            $dompdf->stream($nombreArchivo, ['Attachment' => false]);
            return;
        }

        $this->render('reportes/ventas', [
            'ruta_actual' => 'reportes/ventas',
            'filtros' => $f,
            'categoriasFiltro' => $this->ventas->categoriasProductosTerminados(), 
            'clientesFiltro' => $this->ventasDocumentoModel->buscarClientes('', 200, $tipoTercero),
            'productosFiltro' => $this->ventasDocumentoModel->buscarItems('', 0, 0, 1, 200),
            'porCliente' => ($seccionActiva === 'clientes') ? $this->ventas->ventasPorCliente($f, $pagina, $tamano) : [],
            'pendientes' => ($seccionActiva === 'pendientes') ? $this->ventas->pendientesDespacho($f, $pagina, $tamano) : [],
            'topProductos' => ($seccionActiva === 'productos') ? $this->ventas->topProductos($f, 999999) : [],
            'porPeriodo' => ($seccionActiva === 'tendencias') ? $this->ventas->ventasPorPeriodo($f, $f['agrupacion'], $limiteTendencia) : [],
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
