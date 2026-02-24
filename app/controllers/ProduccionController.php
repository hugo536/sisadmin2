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
            // NUEVO: Enviamos el catálogo de parámetros a la vista
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
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                // 1. Crear Orden
                if ($accion === 'crear_orden') {
                    $this->produccionModel->crearOrden([
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'id_receta' => (int) ($_POST['id_receta'] ?? 0),
                        'id_almacen_origen' => (int) ($_POST['id_almacen_origen'] ?? 0),
                        'id_almacen_destino' => (int) ($_POST['id_almacen_destino'] ?? 0),
                        'cantidad_planificada' => (float) ($_POST['cantidad_planificada'] ?? 0),
                        'observaciones' => (string) ($_POST['observaciones'] ?? ''),
                    ], $userId);

                    $this->setFlash('success', 'Orden de producción creada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                // 2. Ejecutar Orden
                if ($accion === 'ejecutar_orden') {
                    $lotesConsumo = [];
                    foreach ((array) ($_POST['lote_consumo_item'] ?? []) as $idItem => $lote) {
                        $lotesConsumo[(int) $idItem] = (string) $lote;
                    }

                    $this->produccionModel->ejecutarOrden(
                        (int) ($_POST['id_orden'] ?? 0),
                        (float) ($_POST['cantidad_producida'] ?? 0),
                        $userId,
                        trim((string) ($_POST['lote_ingreso'] ?? '')),
                        $lotesConsumo
                    );

                    $this->setFlash('success', 'Orden ejecutada correctamente.');
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
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion_ordenes', [
            'flash' => $flash,
            'ordenes' => $this->produccionModel->listarOrdenes(),
            'recetas_activas' => $this->produccionModel->listarRecetasActivas(),
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
