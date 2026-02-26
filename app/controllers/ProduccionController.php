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
        require_permiso('inventario.ver');

        // --- AJAX: Búsqueda de insumos para Tom Select ---
        if (es_ajax() && ($_GET['accion'] ?? '') === 'buscar_insumos_ajax') {
            ob_clean(); // <-- BUENA PRÁCTICA: Limpia cualquier salida previa (espacios, warnings)
            header('Content-Type: application/json; charset=utf-8');
            $termino = trim((string) ($_GET['q'] ?? ''));
            $resultados = $termino !== '' 
                ? $this->produccionModel->buscarInsumosStockeables($termino) 
                : [];
            echo json_encode(['success' => true, 'data' => $resultados]);
            exit;
        }

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);
            
            // Detectar si la petición actual es de tipo AJAX basándonos en el nombre de la acción o cabeceras
            $esAjaxPost = es_ajax() || str_contains($accion, 'ajax');

            try {
                // =============================================================
                // Cargar datos para "Nueva versión de receta"
                // =============================================================
                if ($accion === 'obtener_datos_nueva_version_ajax') {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idRecetaBase = (int) ($_POST['id_receta_base'] ?? 0);

                    if ($idRecetaBase <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Receta base inválida.']);
                        exit;
                    }

                    $datos = $this->produccionModel->obtenerDatosParaNuevaVersion($idRecetaBase);
                    echo json_encode(['success' => true, 'data' => $datos]);
                    exit;
                }

                // =============================================================
                // Listar versiones
                // =============================================================
                if ($accion === 'listar_versiones_receta_ajax') {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idRecetaBase = (int) ($_POST['id_receta_base'] ?? 0);
                    if ($idRecetaBase <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Receta base inválida.']);
                        exit;
                    }

                    echo json_encode([
                        'success' => true,
                        'data'    => $this->produccionModel->listarVersionesReceta($idRecetaBase),
                    ]);
                    exit;
                }

                // =============================================================
                // Obtener receta para edición
                // =============================================================
                if ($accion === 'obtener_receta_version_ajax') {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idReceta = (int) ($_POST['id_receta'] ?? 0);
                    if ($idReceta <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Receta inválida.']);
                        exit;
                    }

                    echo json_encode([
                        'success' => true,
                        'data'    => $this->produccionModel->obtenerRecetaVersionParaEdicion($idReceta),
                    ]);
                    exit;
                }

                // =============================================================
                // CREAR / NUEVA VERSIÓN DE RECETA (CORREGIDO PARA AJAX Y NORMAL)
                // =============================================================
                if ($accion === 'crear_receta' || $accion === 'guardar_receta_ajax') {
                    $detalles = [];
                    $insumos     = $_POST['detalle_id_insumo'] ?? [];
                    $cantidades  = $_POST['detalle_cantidad_por_unidad'] ?? [];
                    $mermas      = $_POST['detalle_merma_porcentaje'] ?? [];
                    $costos      = $_POST['detalle_costo_unitario'] ?? [];
                    $etapas      = $_POST['detalle_etapa'] ?? [];

                    foreach ((array) $insumos as $idx => $idInsumo) {
                        if (empty($idInsumo)) continue;
                        $detalles[] = [
                            'id_insumo'          => (int) $idInsumo,
                            'etapa'              => (string) ($etapas[$idx] ?? 'General'),
                            'cantidad_por_unidad'=> $this->parseDecimal($cantidades[$idx] ?? 0),
                            'merma_porcentaje'   => $this->parseDecimal($mermas[$idx] ?? 0),
                            'costo_unitario'     => $this->parseDecimal($costos[$idx] ?? 0),
                        ];
                    }

                    if (empty($detalles)) {
                        throw new Exception("La receta debe tener al menos un insumo o semielaborado.");
                    }

                    $parametros = [];
                    $paramIds    = $_POST['parametro_id'] ?? [];
                    $paramValores= $_POST['parametro_valor'] ?? [];

                    foreach ((array) $paramIds as $idx => $idParam) {
                        if (empty($idParam)) continue;
                        $valor = trim((string) ($paramValores[$idx] ?? ''));
                        if ($valor !== '') {
                            $parametros[] = [
                                'id_parametro'   => (int) $idParam,
                                'valor_objetivo' => (float) $valor,
                            ];
                        }
                    }

                    $codigoIngresado = trim((string) ($_POST['codigo'] ?? ''));
                    $idProd = (int) ($_POST['id_producto'] ?? 0);
                    if ($codigoIngresado === '') {
                        $codigoIngresado = 'REC-ITEM-' . str_pad((string)$idProd, 6, '0', STR_PAD_LEFT);
                    }

                    $payloadReceta = [
                        'id_producto'       => $idProd,
                        'codigo'            => $codigoIngresado,
                        'version'           => (int) ($_POST['version'] ?? 1),
                        'descripcion'       => (string) ($_POST['descripcion'] ?? ''),
                        'rendimiento_base'  => 1.0,
                        'unidad_rendimiento'=> 'UND',
                        'detalles'          => $detalles,
                        'parametros'        => $parametros,
                    ];

                    $idRecetaBase = (int) ($_POST['id_receta_base'] ?? 0);

                    if ($idRecetaBase > 0) {
                        $this->produccionModel->crearNuevaVersionDesdePayload($idRecetaBase, $payloadReceta, $userId);
                        $mensajeExito = 'Nueva versión creada y activada correctamente.';
                    } else {
                        $this->produccionModel->crearReceta($payloadReceta, $userId);
                        $mensajeExito = 'Receta creada correctamente.';
                    }

                    // Respuesta dinámica dependiendo de si JS lo mandó por AJAX o si fue un Submit tradicional
                    if ($esAjaxPost) {
                        ob_clean();
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'message' => $mensajeExito]);
                        exit;
                    }

                    $this->setFlash('success', $mensajeExito);
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                // =============================================================
                // Nueva versión rápida (sin edición)
                // =============================================================
                if ($accion === 'nueva_version') {
                    $this->produccionModel->crearNuevaVersion((int) ($_POST['id_receta_base'] ?? 0), $userId);
                    $this->setFlash('success', 'Nueva versión creada y activada correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                // =============================================================
                // Gestión de parámetros del catálogo
                // =============================================================
                if ($accion === 'crear_parametro_catalogo') {
                    $this->produccionModel->crearParametroCatalogo([
                        'nombre'         => (string) ($_POST['nombre'] ?? ''),
                        'unidad_medida'  => (string) ($_POST['unidad_medida'] ?? ''),
                        'descripcion'    => (string) ($_POST['descripcion'] ?? ''),
                    ]);
                    $this->setFlash('success', 'Parámetro creado correctamente.');
                    header('Location: ' . route_url('produccion/recetas'));
                    exit;
                }

                if ($accion === 'editar_parametro_catalogo') {
                    $this->produccionModel->actualizarParametroCatalogo((int) ($_POST['id_parametro_catalogo'] ?? 0), [
                        'nombre'         => (string) ($_POST['nombre'] ?? ''),
                        'unidad_medida'  => (string) ($_POST['unidad_medida'] ?? ''),
                        'descripcion'    => (string) ($_POST['descripcion'] ?? ''),
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
                // Manejo de errores universal: Si es AJAX le manda JSON rojo, si no, le manda recarga con Flash rojo
                if (isset($esAjaxPost) && $esAjaxPost) {
                    ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion_recetas', [
            'flash'               => $flash,
            'recetas'             => $this->produccionModel->listarRecetas(),
            'items_stockeables'   => [],
            'parametros_catalogo' => $this->produccionModel->listarParametrosCatalogo(),
            'ruta_actual'         => 'produccion/recetas',
        ]);
    }

    // =============================================================
    // MÉTODOS DE ÓRDENES DE PRODUCCIÓN
    // =============================================================
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
                    ob_clean(); // <-- BUENA PRÁCTICA
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
                        $merma   = (float) $d['merma_porcentaje'];
                        $cantidadRequerida = $qtyBase * $cantidadPlanificada * (1 + ($merma / 100));

                        $resultado[] = [
                            'id_insumo'        => $d['id_insumo'],
                            'insumo_nombre'    => $d['insumo_nombre'],
                            'cantidad_calculada'=> round($cantidadRequerida, 4),
                            'stock_disponible' => round($this->produccionModel->obtenerStockTotalItem((int) $d['id_insumo']), 4),
                            'almacenes'        => $this->produccionModel->obtenerAlmacenesConStockItem((int) $d['id_insumo']),
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

                    $this->produccionModel->marcarOrdenEnProceso($idOrden, $userId);
                    echo json_encode(['success' => true]);
                    exit;
                }

                if ($accion === 'crear_orden') {
                    $this->produccionModel->crearOrden([
                        'codigo'             => (string) ($_POST['codigo'] ?? ''),
                        'id_receta'          => (int) ($_POST['id_receta'] ?? 0),
                        'id_almacen_planta'  => (int) ($_POST['id_almacen_planta'] ?? 0),
                        'cantidad_planificada'=> (float) ($_POST['cantidad_planificada'] ?? 0),
                        'fecha_programada'   => (string) ($_POST['fecha_programada'] ?? ''),
                        'turno_programado'   => (string) ($_POST['turno_programado'] ?? ''),
                        'observaciones'      => (string) ($_POST['observaciones'] ?? ''),
                    ], $userId);

                    $this->setFlash('success', 'Orden de producción planificada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                if ($accion === 'editar_orden') {
                    $this->produccionModel->actualizarOrdenBorrador(
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
                    $this->produccionModel->eliminarOrdenBorrador((int) ($_POST['id_orden'] ?? 0), $userId);
                    $this->setFlash('success', 'Orden borrador eliminada correctamente.');
                    header('Location: ' . route_url('produccion/ordenes'));
                    exit;
                }

                if ($accion === 'ejecutar_orden') {
                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    $justificacion = trim((string) ($_POST['justificacion'] ?? ''));

                    $consumos = [];
                    $consIdsInsumo  = $_POST['consumo_id_insumo'] ?? [];
                    $consIdsAlmacen = $_POST['consumo_id_almacen'] ?? [];
                    $consCantidades = $_POST['consumo_cantidad'] ?? [];
                    $consIdsLote    = $_POST['consumo_id_lote'] ?? [];

                    foreach ((array) $consIdsInsumo as $idx => $idInsumo) {
                        if (empty($idInsumo)) continue;
                        $loteTexto = trim((string) ($consIdsLote[$idx] ?? ''));
                        $consumos[] = [
                            'id_insumo' => (int) $idInsumo,
                            'id_almacen'=> (int) ($consIdsAlmacen[$idx] ?? 0),
                            'cantidad'  => $this->parseDecimal($consCantidades[$idx] ?? 0),
                            'id_lote'   => ctype_digit($loteTexto) ? (int) $loteTexto : null,
                            'lote'      => $loteTexto,
                        ];
                    }

                    if (empty($consumos)) {
                        throw new Exception("Debe registrar al menos un consumo de insumos.");
                    }

                    $ingresos = [];
                    $ingIdsAlmacen     = $_POST['ingreso_id_almacen'] ?? [];
                    $ingCantidades     = $_POST['ingreso_cantidad'] ?? [];
                    $ingIdsLote        = $_POST['ingreso_id_lote'] ?? [];
                    $ingFechasVencimiento = $_POST['ingresos_fecha_vencimiento'] ?? [];

                    foreach ((array) $ingIdsAlmacen as $idx => $idAlmacen) {
                        if (empty($idAlmacen)) continue;
                        $cantidad = $this->parseDecimal($ingCantidades[$idx] ?? 0);
                        if ($cantidad > 0) {
                            $loteTexto = trim((string) ($ingIdsLote[$idx] ?? ''));
                            $fechaVencTexto = trim((string) ($ingFechasVencimiento[$idx] ?? ''));
                            $ingresos[] = [
                                'id_almacen'     => (int) $idAlmacen,
                                'cantidad'       => $cantidad,
                                'id_lote'        => ctype_digit($loteTexto) ? (int) $loteTexto : null,
                                'lote'           => $loteTexto,
                                'fecha_vencimiento'=> $fechaVencTexto,
                            ];
                        }
                    }

                    if (empty($ingresos)) {
                        throw new Exception("Debe registrar al menos un ingreso de producto terminado.");
                    }

                    $this->produccionModel->ejecutarOrden(
                        $idOrden,
                        $consumos,
                        $ingresos,
                        $userId,
                        $justificacion
                    );

                    $this->setFlash('success', 'Orden ejecutada y existencias actualizadas correctamente.');
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
            'flash'            => $flash,
            'ordenes'          => $this->produccionModel->listarOrdenes(),
            'recetas_activas'  => $this->produccionModel->listarRecetasActivas(),
            'almacenes'        => $this->produccionModel->listarAlmacenesActivos(),
            'almacenes_planta' => $this->produccionModel->listarAlmacenesActivosPorTipo('Planta'),
            'ruta_actual'      => 'produccion/ordenes',
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
                'tipo'  => (string) ($_SESSION['produccion_flash']['tipo'] ?? ''),
                'texto' => (string) ($_SESSION['produccion_flash']['texto'] ?? ''),
            ];
            unset($_SESSION['produccion_flash']);
        }
        return $flash;
    }
}
