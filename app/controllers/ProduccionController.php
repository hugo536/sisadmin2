<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ProduccionModel.php';

class ProduccionController extends Controlador
{
    private ProduccionModel $produccionModel;

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
        require_permiso('inventario.ver');

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'crear_receta') {
                    $detalles = [];
                    $insumos = $_POST['detalle_id_insumo'] ?? [];
                    $cantidades = $_POST['detalle_cantidad'] ?? [];
                    $mermas = $_POST['detalle_merma'] ?? [];

                    foreach ((array) $insumos as $idx => $idInsumo) {
                        $detalles[] = [
                            'id_insumo' => (int) $idInsumo,
                            'cantidad_por_unidad' => (float) ($cantidades[$idx] ?? 0),
                            'merma_porcentaje' => (float) ($mermas[$idx] ?? 0),
                        ];
                    }

                    $this->produccionModel->crearReceta([
                        'id_producto' => (int) ($_POST['id_producto'] ?? 0),
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'version' => (int) ($_POST['version'] ?? 1),
                        'descripcion' => (string) ($_POST['descripcion'] ?? ''),
                        'detalles' => $detalles,
                    ], $userId);

                    $this->setFlash('success', 'Receta creada correctamente.');
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

                if ($accion === 'ejecutar_orden') {
                    $this->produccionModel->ejecutarOrden(
                        (int) ($_POST['id_orden'] ?? 0),
                        (float) ($_POST['cantidad_producida'] ?? 0),
                        $userId,
                        trim((string) ($_POST['lote_ingreso'] ?? ''))
                    );

                    $this->setFlash('success', 'Orden ejecutada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

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
