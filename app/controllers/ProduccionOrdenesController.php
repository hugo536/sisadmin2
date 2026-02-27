<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ProduccionOrdenesModel.php';

class ProduccionOrdenesController extends Controlador
{
    private ProduccionOrdenesModel $produccionOrdenesModel;

    public function __construct()
    {
        $this->produccionOrdenesModel = new ProduccionOrdenesModel();
    }

    private function parseDecimal($value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalizado = str_replace(',', '.', trim((string) $value));
        return is_numeric($normalizado) ? (float) $normalizado : 0.0;
    }

    public function ordenes(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $esAjaxReceta = in_array($accion, ['obtener_receta_ajax', 'iniciar_ejecucion_ajax'], true);
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'obtener_receta_ajax') {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idReceta = (int) ($_POST['id_receta'] ?? 0);
                    $cantidadPlanificada = (float) ($_POST['cantidad'] ?? 0);

                    if ($idReceta <= 0 || $cantidadPlanificada <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos.']);
                        exit;
                    }

                    $detalles = $this->produccionOrdenesModel->obtenerDetalleReceta($idReceta);
                    $resultado = [];

                    foreach ($detalles as $d) {
                        $qtyBase = (float) $d['cantidad_por_unidad'];
                        $merma = (float) $d['merma_porcentaje'];
                        $cantidadRequerida = $qtyBase * $cantidadPlanificada * (1 + ($merma / 100));

                        $resultado[] = [
                            'id_insumo' => $d['id_insumo'],
                            'insumo_nombre' => $d['insumo_nombre'],
                            'cantidad_calculada' => round($cantidadRequerida, 4),
                            'stock_disponible' => round($this->produccionOrdenesModel->obtenerStockTotalItem((int) $d['id_insumo']), 4),
                            'almacenes' => $this->produccionOrdenesModel->obtenerAlmacenesConStockItem((int) $d['id_insumo']),
                        ];
                    }

                    echo json_encode(['success' => true, 'data' => $resultado]);
                    exit;
                }

                if ($accion === 'iniciar_ejecucion_ajax') {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    if ($idOrden <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Orden inválida.']);
                        exit;
                    }

                    $this->produccionOrdenesModel->marcarOrdenEnProceso($idOrden, $userId);
                    echo json_encode(['success' => true]);
                    exit;
                }

                if ($accion === 'crear_orden') {
                    $this->produccionOrdenesModel->crearOrden([
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'id_receta' => (int) ($_POST['id_receta'] ?? 0),
                        'id_almacen_planta' => (int) ($_POST['id_almacen_planta'] ?? 0),
                        'cantidad_planificada' => (float) ($_POST['cantidad_planificada'] ?? 0),
                        'fecha_programada' => (string) ($_POST['fecha_programada'] ?? ''),
                        'turno_programado' => (string) ($_POST['turno_programado'] ?? ''),
                        'observaciones' => (string) ($_POST['observaciones'] ?? ''),
                    ], $userId);

                    $this->setFlash('success', 'Orden de producción planificada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                if ($accion === 'editar_orden') {
                    $this->produccionOrdenesModel->actualizarOrdenBorrador(
                        (int) ($_POST['id_orden'] ?? 0),
                        [
                            'cantidad_planificada' => (float) ($_POST['cantidad_planificada'] ?? 0),
                            'fecha_programada' => (string) ($_POST['fecha_programada'] ?? ''),
                            'turno_programado' => (string) ($_POST['turno_programado'] ?? ''),
                            'id_almacen_planta' => (int) ($_POST['id_almacen_planta'] ?? 0),
                            'observaciones' => (string) ($_POST['observaciones'] ?? ''),
                        ],
                        $userId
                    );

                    $this->setFlash('success', 'Orden borrador actualizada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                if ($accion === 'eliminar_borrador') {
                    $this->produccionOrdenesModel->eliminarOrdenBorrador((int) ($_POST['id_orden'] ?? 0), $userId);
                    $this->setFlash('success', 'Orden borrador eliminada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                if ($accion === 'ejecutar_orden') {
                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    $justificacion = trim((string) ($_POST['justificacion'] ?? ''));
                    $fechaInicio = trim((string) ($_POST['fecha_inicio'] ?? '')) ?: null;
                    $fechaFin = trim((string) ($_POST['fecha_fin'] ?? '')) ?: null;

                    $consumos = [];
                    $consIdsInsumo = $_POST['consumo_id_insumo'] ?? [];
                    $consIdsAlmacen = $_POST['consumo_id_almacen'] ?? [];
                    $consCantidades = $_POST['consumo_cantidad'] ?? [];
                    $consIdsLote = $_POST['consumo_id_lote'] ?? [];

                    foreach ((array) $consIdsInsumo as $idx => $idInsumo) {
                        if (empty($idInsumo)) {
                            continue;
                        }
                        $loteTexto = trim((string) ($consIdsLote[$idx] ?? ''));
                        $consumos[] = [
                            'id_insumo' => (int) $idInsumo,
                            'id_almacen' => (int) ($consIdsAlmacen[$idx] ?? 0),
                            'cantidad' => $this->parseDecimal($consCantidades[$idx] ?? 0),
                            'id_lote' => ctype_digit($loteTexto) ? (int) $loteTexto : null,
                            'lote' => $loteTexto,
                        ];
                    }

                    if (empty($consumos)) {
                        throw new Exception('Debe registrar al menos un consumo de insumos.');
                    }

                    $ingresos = [];
                    $ingIdsAlmacen = $_POST['ingreso_id_almacen'] ?? [];
                    $ingCantidades = $_POST['ingreso_cantidad'] ?? [];
                    $ingIdsLote = $_POST['ingreso_id_lote'] ?? [];
                    $ingFechasVencimiento = $_POST['ingresos_fecha_vencimiento'] ?? [];

                    foreach ((array) $ingIdsAlmacen as $idx => $idAlmacen) {
                        if (empty($idAlmacen)) {
                            continue;
                        }
                        $cantidad = $this->parseDecimal($ingCantidades[$idx] ?? 0);
                        if ($cantidad > 0) {
                            $loteTexto = trim((string) ($ingIdsLote[$idx] ?? ''));
                            $fechaVencTexto = trim((string) ($ingFechasVencimiento[$idx] ?? ''));
                            $ingresos[] = [
                                'id_almacen' => (int) $idAlmacen,
                                'cantidad' => $cantidad,
                                'id_lote' => ctype_digit($loteTexto) ? (int) $loteTexto : null,
                                'lote' => $loteTexto,
                                'fecha_vencimiento' => $fechaVencTexto,
                            ];
                        }
                    }

                    if (empty($ingresos)) {
                        throw new Exception('Debe registrar al menos un ingreso de producto terminado.');
                    }

                    $this->produccionOrdenesModel->ejecutarOrden(
                        $idOrden,
                        $consumos,
                        $ingresos,
                        $userId,
                        $justificacion,
                        $fechaInicio,
                        $fechaFin
                    );

                    $this->setFlash('success', 'Orden ejecutada y existencias actualizadas correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                if ($accion === 'anular_orden') {
                    $this->produccionOrdenesModel->anularOrden((int) ($_POST['id_orden'] ?? 0), $userId);
                    $this->setFlash('success', 'Orden anulada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }
            } catch (Throwable $e) {
                if ($esAjaxReceta) {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion_ordenes', [
            'flash' => $flash,
            'ordenes' => $this->produccionOrdenesModel->listarOrdenes(),
            'recetas_activas' => $this->produccionOrdenesModel->listarRecetasActivas(),
            'almacenes' => $this->produccionOrdenesModel->listarAlmacenesActivos(),
            'almacenes_planta' => $this->produccionOrdenesModel->listarAlmacenesActivosPorTipo('Planta'),
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
