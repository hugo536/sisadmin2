<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ProduccionModel.php';

class ProduccionController extends Controlador
{
    private ProduccionModel $produccionModel;

    private function parseDecimal($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalizado = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalizado) ? (float) $normalizado : 0.0;
    }

    public function __construct()
    {
        $this->produccionModel = new ProduccionModel();
    }

    public function index(): void
    {
        header('Location: ' . route_url('produccion/recetas'));
        exit;
    }

    public function recetas(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver'); // O usa 'produccion.ver' si ya creaste el permiso

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $esAjaxReceta = ($accion === 'obtener_receta_ajax');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'crear_receta') {
                    // 1. Procesar Detalles (Insumos)
                    $detalles = [];
                    $insumos = $_POST['detalle_id_insumo'] ?? [];
                    $cantidades = $_POST['detalle_cantidad_por_unidad'] ?? [];
                    $mermas = $_POST['detalle_merma_porcentaje'] ?? [];
                    $costosUnitarios = $_POST['detalle_costo_unitario'] ?? [];
                    $etapas = $_POST['detalle_etapa'] ?? [];

                    foreach ((array) $insumos as $idx => $idInsumo) {
                        if (empty($idInsumo)) continue;

                        $detalles[] = [
                            'id_insumo' => (int) $idInsumo,
                            'etapa' => (string) ($etapas[$idx] ?? 'General'),
                            'cantidad_por_unidad' => $this->parseDecimal($cantidades[$idx] ?? 0),
                            'merma_porcentaje' => $this->parseDecimal($mermas[$idx] ?? 0),
                            'costo_unitario' => $this->parseDecimal($costosUnitarios[$idx] ?? 0),
                        ];
                    }

                    if (empty($detalles)) {
                        throw new Exception("La receta debe tener al menos un insumo o semielaborado.");
                    }

                    // 2. Procesar Parámetros Dinámicos (IPC)
                    $parametros = [];
                    $paramIds = $_POST['parametro_id'] ?? [];
                    $paramValores = $_POST['parametro_valor'] ?? [];

                    foreach ((array) $paramIds as $idx => $idParam) {
                        if (empty($idParam)) continue;
                        
                        $valor = trim((string) ($paramValores[$idx] ?? ''));
                        if ($valor !== '') {
                            $parametros[] = [
                                'id_parametro' => (int) $idParam,
                                'valor_objetivo' => (float) $valor,
                            ];
                        }
                    }

                    // 3. Enviar al Modelo
                    $this->produccionModel->crearReceta([
                        'id_producto' => (int) ($_POST['id_producto'] ?? 0),
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'version' => (int) ($_POST['version'] ?? 1),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                        'rendimiento_base' => (float) ($_POST['rendimiento_base'] ?? 0),
                        'unidad_rendimiento' => (string) ($_POST['unidad_rendimiento'] ?? ''),
                        'detalles' => $detalles,
                        'parametros' => $parametros, // Pasamos el array dinámico
                    ], $userId);

                    $this->setFlash('success', 'Receta creada correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'nueva_version') {
                    $this->produccionModel->crearNuevaVersion((int) ($_POST['id_receta_base'] ?? 0), $userId);
                    $this->setFlash('success', 'Nueva versión creada y activada correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'crear_parametro_catalogo') {
                    $this->produccionModel->crearParametroCatalogo([
                        'nombre' => (string) ($_POST['nombre'] ?? ''),
                        'unidad_medida' => (string) ($_POST['unidad_medida'] ?? ''),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                    ]);
                    $this->setFlash('success', 'Parámetro creado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'editar_parametro_catalogo') {
                    $this->produccionModel->actualizarParametroCatalogo((int) ($_POST['id_parametro_catalogo'] ?? 0), [
                        'nombre' => (string) ($_POST['nombre'] ?? ''),
                        'unidad_medida' => (string) ($_POST['unidad_medida'] ?? ''),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                    ]);
                    $this->setFlash('success', 'Parámetro actualizado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'eliminar_parametro_catalogo') {
                    $this->produccionModel->eliminarParametroCatalogo((int) ($_POST['id_parametro_catalogo'] ?? 0));
                    $this->setFlash('success', 'Parámetro eliminado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }
            } catch (Throwable $e) {
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion_recetas', [
            'flash' => $flash,
            'recetas' => $this->produccionModel->listarRecetas(),
            'items_stockeables' => $this->produccionModel->listarItemsStockeables(), 
            'parametros_catalogo' => $this->produccionModel->listarParametrosCatalogo(),
            'ruta_actual' => 'produccion/recetas',
        ]);
    }

    public function ordenes(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $esAjaxReceta = ($accion === 'obtener_receta_ajax');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                // --- NUEVO BLOQUE: AJAX PARA OBTENER RECETA Y STOCK ---
                if ($accion === 'obtener_receta_ajax') {
                    header('Content-Type: application/json; charset=utf-8');
                    
                    $idReceta = (int) ($_POST['id_receta'] ?? 0);
                    $cantidadPlanificada = (float) ($_POST['cantidad'] ?? 0);

                    if ($idReceta <= 0 || $cantidadPlanificada <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
                        exit;
                    }

                    $detalles = $this->produccionModel->obtenerDetalleReceta($idReceta);
                    $resultado = [];

                    foreach ($detalles as $d) {
                        $qtyBase = (float) $d['cantidad_por_unidad'];
                        $merma = (float) $d['merma_porcentaje'];
                        $cantidadRequerida = $qtyBase * $cantidadPlanificada * (1 + ($merma / 100));
                        $stockTotal = $this->produccionModel->obtenerStockTotalItem((int) $d['id_insumo']);

                        $resultado[] = [
                            'id_insumo' => $d['id_insumo'],
                            'insumo_nombre' => $d['insumo_nombre'],
                            'cantidad_calculada' => round($cantidadRequerida, 4),
                            'stock_disponible' => round($stockTotal, 4)
                        ];
                    }

                    echo json_encode(['success' => true, 'data' => $resultado]);
                    exit; // Detiene la ejecución aquí para que no imprima la vista HTML
                }

                // 1. Crear Orden
                if ($accion === 'crear_orden') {
                    $idAlmacenDestino = (int) ($_POST['id_almacen_destino'] ?? 0);
                    $idAlmacenOrigen = (int) ($_POST['id_almacen_origen'] ?? 0);
                    if ($idAlmacenOrigen <= 0) {
                        $idAlmacenOrigen = $idAlmacenDestino;
                    }

                    $this->produccionModel->crearOrden([
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'id_receta' => (int) ($_POST['id_receta'] ?? 0),
                        'id_almacen_origen' => $idAlmacenOrigen,
                        'id_almacen_destino' => $idAlmacenDestino,
                        'cantidad_planificada' => (float) ($_POST['cantidad_planificada'] ?? 0),
                        'observaciones' => (string) ($_POST['observaciones'] ?? ''),
                    ], $userId);

                    $this->setFlash('success', 'Orden de producción creada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                // 2. Ejecutar Orden
                if ($accion === 'ejecutar_orden') {
                    $idOrden = (int) ($_POST['id_orden'] ?? 0);

                    // A. Procesar arreglo de Consumos (Materia prima saliente)
                    $consumos = [];
                    $consIdsInsumo = $_POST['consumo_id_insumo'] ?? [];
                    $consIdsAlmacen = $_POST['consumo_id_almacen'] ?? [];
                    $consCantidades = $_POST['consumo_cantidad'] ?? [];
                    $consIdsLote = $_POST['consumo_id_lote'] ?? [];

                    foreach ((array) $consIdsInsumo as $idx => $idInsumo) {
                        if (empty($idInsumo)) continue;
                        $consumos[] = [
                            'id_insumo' => (int) $idInsumo,
                            'id_almacen' => (int) ($consIdsAlmacen[$idx] ?? 0),
                            'cantidad' => $this->parseDecimal($consCantidades[$idx] ?? 0),
                            'id_lote' => !empty($consIdsLote[$idx]) ? (int) $consIdsLote[$idx] : null,
                        ];
                    }

                    if (empty($consumos)) {
                        throw new Exception("Debe registrar al menos un consumo de insumos.");
                    }

                    // B. Procesar arreglo de Ingresos (Producto terminado entrante)
                    $ingresos = [];
                    $ingIdsAlmacen = $_POST['ingreso_id_almacen'] ?? [];
                    $ingCantidades = $_POST['ingreso_cantidad'] ?? [];
                    $ingIdsLote = $_POST['ingreso_id_lote'] ?? [];

                    foreach ((array) $ingIdsAlmacen as $idx => $idAlmacen) {
                        if (empty($idAlmacen)) continue;
                        $cantidad = $this->parseDecimal($ingCantidades[$idx] ?? 0);
                        if ($cantidad > 0) {
                            $ingresos[] = [
                                'id_almacen' => (int) $idAlmacen,
                                'cantidad' => $cantidad,
                                'id_lote' => !empty($ingIdsLote[$idx]) ? (int) $ingIdsLote[$idx] : null,
                            ];
                        }
                    }

                    if (empty($ingresos)) {
                        throw new Exception("Debe registrar al menos un ingreso de producto terminado.");
                    }

                    // C. Enviar al Modelo
                    $this->produccionModel->ejecutarOrden(
                        $idOrden,
                        $consumos,
                        $ingresos,
                        $userId
                    );

                    $this->setFlash('success', 'Orden ejecutada y existencias actualizadas correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                // 3. Anular Orden
                if ($accion === 'anular_orden') {
                    $this->produccionModel->anularOrden((int) ($_POST['id_orden'] ?? 0), $userId);

                    $this->setFlash('success', 'Orden anulada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

            } catch (Throwable $e) {
                if ($esAjaxReceta) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode([
                        'success' => false,
                        'message' => $e->getMessage(),
                    ]);
                    exit;
                }

                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion_ordenes', [
            'flash' => $flash,
            'ordenes' => $this->produccionModel->listarOrdenes(),
            'recetasActivas' => $this->produccionModel->listarRecetasActivas(),
            'almacenes' => $this->produccionModel->listarAlmacenesActivos(),
            'ruta_actual' => 'produccion/ordenes',
        ]);
    }

    // --- Helpers Privados ---

    private function setFlash(string $tipo, string $texto): void
    {
        $_SESSION['produccion_flash'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function obtenerFlash(): array
    {
        $flash = ['tipo' => '', 'texto' => ''];
        if (isset($_SESSION['produccion_flash']) && is_array($_SESSION['produccion_flash'])) {
            $flash = [
                'tipo' => (string) ($_SESSION['produccion_flash']['tipo'] ?? ''),
                'texto' => (string) ($_SESSION['produccion_flash']['texto'] ?? ''),
            ];
            unset($_SESSION['produccion_flash']);
        }
        return $flash;
    }
}
