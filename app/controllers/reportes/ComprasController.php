<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteComprasModel.php';
require_once BASE_PATH . '/app/models/reportes/ReporteInventarioModel.php';

class ReporteComprasController extends Controlador
{
    private ReporteComprasModel $compras;
    private ReporteInventarioModel $inventario;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->compras = new ReporteComprasModel();
        $this->inventario = new ReporteInventarioModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.compras.ver');
        $this->registrarAuditoria('compras');

        // 1. MANEJO DE BÚSQUEDA AJAX PARA INSUMOS (TomSelect / Select2)
        $accionAjax = (string) ($_GET['accion'] ?? '');
        if (es_ajax() && $accionAjax === 'buscar_insumos') {
            $q = trim((string) ($_GET['q'] ?? ''));
            $idCategoria = (int) ($_GET['id_categoria'] ?? 0);
            
            // Llamamos a un método específico de compras, no al de ventas
            json_response(['ok' => true, 'data' => $this->compras->buscarInsumosAjax($q, $idCategoria, 40)]);
            return;
        }
        if (es_ajax() && $accionAjax === 'buscar_proveedores') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->compras->buscarProveedoresAjax($q, 40)]);
            return;
        }

        [$pagina, $tamano] = $this->paginacion();
        
        // 2. CONTROL DE SECCIÓN ACTIVA
        $seccionActiva = trim((string)($_GET['seccion_activa'] ?? 'tendencias'));
        if (!in_array($seccionActiva, ['tendencias', 'insumos', 'proveedores', 'cumplimiento', 'variacion'])) {
            $seccionActiva = 'tendencias';
        }

        // 3. CAPTURA Y NORMALIZACIÓN DE FILTROS
        $f = $this->filtrosPeriodo();
        $f['id_proveedor'] = (int) ($_GET['id_proveedor'] ?? 0);
        $f['id_almacen'] = (int) ($_GET['id_almacen'] ?? 0);
        $f['id_categoria'] = (int) ($_GET['id_categoria'] ?? 0);
        $f['id_item'] = (int) ($_GET['id_item'] ?? 0);
        
        $agrupacionFiltro = $_GET['agrupacion'] ?? 'diaria';
        $f['agrupacion'] = in_array($agrupacionFiltro, ['diaria', 'semanal', 'mensual']) ? $agrupacionFiltro : 'diaria';
        $f['tipo_grafico'] = ($_GET['tipo_grafico'] ?? 'linea') === 'barras' ? 'barras' : 'linea';
        $f['seccion_activa'] = $seccionActiva;

        // Configuración de límites para gráficos de tendencias
        $limiteTendencia = 12; 
        if ($f['agrupacion'] === 'diaria') $limiteTendencia = 365;
        elseif ($f['agrupacion'] === 'semanal') $limiteTendencia = 52;
        elseif ($f['agrupacion'] === 'mensual') $limiteTendencia = 24;

        // 4. EXPORTACIÓN A PDF
        if ((string)($_GET['exportar_pdf'] ?? '') === '1') {
            require_once BASE_PATH . '/app/models/configuracion/EmpresaModel.php';
            require_once BASE_PATH . '/vendor/autoload.php';

            $porPeriodo = ($seccionActiva === 'tendencias') ? $this->compras->comprasPorPeriodo($f, $f['agrupacion'], $limiteTendencia) : [];
            $topInsumos = ($seccionActiva === 'insumos') ? $this->compras->topInsumos($f, 100) : [];
            $porProveedor = ($seccionActiva === 'proveedores') ? $this->compras->comprasPorProveedor($f, 1, 999999) : [];
            $ocCumplimiento = ($seccionActiva === 'cumplimiento') ? $this->compras->ocCumplimiento($f, 1, 999999) : [];
            $variacionCostos = ($seccionActiva === 'variacion') ? $this->compras->variacionCostos($f, 1, 999999) : [];
            
            $filtros = $f;

            ob_start();
            require BASE_PATH . '/app/views/reportes/pdf_compras.php';
            $html = ob_get_clean();

            $dompdf = new \Dompdf\Dompdf();
            $options = $dompdf->getOptions();
            $options->set(['isRemoteEnabled' => true]);
            $dompdf->setOptions($options);

            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape'); 
            $dompdf->render();
            
            $nombreArchivo = 'Reporte_Compras_' . ucfirst($seccionActiva) . '.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => false]);
            return;
        }

        // 5. RENDERIZADO DE LA VISTA WEB
        $this->render('reportes/compras', [
            'ruta_actual' => 'reportes/compras',
            'filtros' => $f,
            'almacenesFiltro' => $this->inventario->listarAlmacenesActivos(), // <- INCLUIDO PARA EL SELECT DINÁMICO
            'categoriasFiltro' => $this->inventario->listarCategoriasActivas(),
            'insumoSeleccionado' => $this->compras->obtenerInsumoPorId($f['id_item']),
            'proveedorSeleccionado' => $this->compras->obtenerProveedorPorId($f['id_proveedor']),
            'porPeriodo' => ($seccionActiva === 'tendencias') ? $this->compras->comprasPorPeriodo($f, $f['agrupacion'], $limiteTendencia) : [],
            'topInsumos' => ($seccionActiva === 'insumos') ? $this->compras->topInsumos($f, 999999) : [],
            'porProveedor' => ($seccionActiva === 'proveedores') ? $this->compras->comprasPorProveedor($f, $pagina, $tamano) : [],
            'ocCumplimiento' => ($seccionActiva === 'cumplimiento') ? $this->compras->ocCumplimiento($f, $pagina, $tamano) : [],
            'variacionCostos' => ($seccionActiva === 'variacion') ? $this->compras->variacionCostos($f, $pagina, $tamano) : [],
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
