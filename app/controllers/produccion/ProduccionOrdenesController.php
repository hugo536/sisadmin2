<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/produccion/ProduccionOrdenesModel.php';

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
            
            // AÑADIDOS LOS NUEVOS ENDPOINTS A LA LISTA AJAX
            $esAjaxReceta = in_array($accion, [
                'obtener_receta_ajax', 
                'iniciar_ejecucion_ajax', 
                'obtener_planificador_ajax',
                'crear_orden_ajax',
                'analizar_subordenes_ajax',
                'generar_subordenes_ajax',
                'guardar_tiempos_mod_ajax',
                'reportar_avance_diario_ajax',
                'sincronizar_mod_asistencia_ajax',
                'obtener_detalle_costos_ajax'
            ], true);
            
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                // ==========================================================
                // ENDPOINTS DEL PLANIFICADOR
                // ==========================================================
                if ($accion === 'crear_orden_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    
                    $idNuevaOrden = $this->produccionOrdenesModel->crearOrden([
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'id_receta' => (int) ($_POST['id_receta'] ?? 0),
                        'id_almacen_planta' => (int) ($_POST['id_almacen_planta'] ?? 0),
                        'cantidad_planificada' => (float) ($_POST['cantidad_planificada'] ?? 0),
                        'fecha_programada' => (string) ($_POST['fecha_programada'] ?? ''),
                        'observaciones' => (string) ($_POST['observaciones'] ?? ''),
                    ], $userId);

                    // Devolvemos el ID de la orden recién creada para poder analizarla en JS
                    echo json_encode(['success' => true, 'message' => 'Orden planificada.', 'id_orden' => $idNuevaOrden]);
                    exit;
                }

                if ($accion === 'obtener_planificador_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    
                    $desde = trim((string) ($_POST['desde'] ?? ''));
                    $hasta = trim((string) ($_POST['hasta'] ?? ''));

                    if ($desde === '' || $hasta === '') {
                        echo json_encode(['success' => false, 'message' => 'Fechas inválidas.']);
                        exit;
                    }

                    $datos = $this->produccionOrdenesModel->obtenerDatosPlanificador($desde, $hasta);
                    echo json_encode(['success' => true, 'data' => $datos]);
                    exit;
                }

                // ==========================================================
                // ENDPOINTS MOTOR MRP (EXPLOSIÓN DE MATERIALES)
                // ==========================================================
                if ($accion === 'analizar_subordenes_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    
                    $faltantes = $this->produccionOrdenesModel->analizarSemielaboradosFaltantes($idOrden);
                    
                    echo json_encode(['success' => true, 'data' => $faltantes]);
                    exit;
                }

                if ($accion === 'generar_subordenes_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    $idOrdenPadre = (int) ($_POST['id_orden_padre'] ?? 0);
                    
                    // Re-analizamos por seguridad
                    $faltantes = $this->produccionOrdenesModel->analizarSemielaboradosFaltantes($idOrdenPadre);
                    
                    if (count($faltantes) > 0) {
                        $this->produccionOrdenesModel->generarSubOrdenesAutomatica($idOrdenPadre, $faltantes, $userId);
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Sub-órdenes generadas correctamente.']);
                    exit;
                }

                // ==========================================================
                // ENDPOINTS DE ÓRDENES DE PRODUCCIÓN
                // ==========================================================
                if ($accion === 'obtener_receta_ajax') {
                    if (ob_get_level() > 0) ob_clean();
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
                        $rendimientoBase = (float) ($d['rendimiento_base'] ?? 0);
                        $factorEscala = $rendimientoBase > 0 ? ($cantidadPlanificada / $rendimientoBase) : 0;
                        $cantidadRequerida = $qtyBase * $factorEscala * (1 + ($merma / 100));

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
                    if (ob_get_level() > 0) ob_clean();
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


                if ($accion === 'obtener_detalle_costos_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');

                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    if ($idOrden <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Orden inválida.']);
                        exit;
                    }

                    $data = $this->produccionOrdenesModel->obtenerDesgloseCostosOrden($idOrden);
                    echo json_encode(['success' => true, 'data' => $data]);
                    exit;
                }

                if ($accion === 'sincronizar_mod_asistencia_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');

                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    if ($idOrden <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Orden inválida.']);
                        exit;
                    }

                    $resultado = $this->produccionOrdenesModel->sincronizarModDesdeAsistencia($idOrden, $userId);
                    echo json_encode([
                        'success' => true,
                        'message' => 'MOD sincronizada desde asistencia.',
                        'data' => $resultado,
                    ]);
                    exit;
                }

                if ($accion === 'reportar_avance_diario_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');

                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    $cantidadAvance = $this->parseDecimal($_POST['cantidad_avance'] ?? 0);
                    $fechaOperacion = trim((string) ($_POST['fecha_operacion'] ?? ''));
                    $nota = trim((string) ($_POST['nota'] ?? ''));

                    if ($idOrden <= 0 || $cantidadAvance <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Parámetros inválidos para reportar avance.']);
                        exit;
                    }

                    $resultado = $this->produccionOrdenesModel->reportarAvanceDiario(
                        $idOrden,
                        $cantidadAvance,
                        $userId,
                        $fechaOperacion !== '' ? $fechaOperacion : null,
                        $nota
                    );

                    echo json_encode([
                        'success' => true,
                        'message' => 'Avance diario registrado con consumo teórico.',
                        'data' => $resultado,
                    ]);
                    exit;
                }

                if ($accion === 'guardar_tiempos_mod_ajax') {
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');

                    $idOrden = (int) ($_POST['id_orden'] ?? 0);
                    if ($idOrden <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Orden inválida.']);
                        exit;
                    }

                    $modEmpleados = $_POST['orden_mod_id_empleado'] ?? [];
                    $modHoras = $_POST['orden_mod_horas_reales'] ?? [];
                    $modCostoHora = $_POST['orden_mod_costo_hora_real'] ?? [];
                    $tiempos = [];
                    foreach ((array) $modEmpleados as $idx => $idEmpleado) {
                        $idEmpleadoInt = (int) $idEmpleado;
                        if ($idEmpleadoInt <= 0) {
                            continue;
                        }
                        $tiempos[] = [
                            'id_empleado' => $idEmpleadoInt,
                            'horas_reales' => $this->parseDecimal($modHoras[$idx] ?? 0),
                            'costo_hora_real' => $this->parseDecimal($modCostoHora[$idx] ?? 0),
                        ];
                    }

                    $resultado = $this->produccionOrdenesModel->guardarTiemposModOrden($idOrden, $tiempos);
                    echo json_encode([
                        'success' => true,
                        'message' => 'Tiempos MOD guardados correctamente.',
                        'data' => $resultado,
                    ]);
                    exit;
                }

                if ($accion === 'crear_orden') {
                    $this->produccionOrdenesModel->crearOrden([
                        'codigo' => (string) ($_POST['codigo'] ?? ''),
                        'id_receta' => (int) ($_POST['id_receta'] ?? 0),
                        'id_almacen_planta' => (int) ($_POST['id_almacen_planta'] ?? 0),
                        'cantidad_planificada' => (float) ($_POST['cantidad_planificada'] ?? 0),
                        'fecha_programada' => (string) ($_POST['fecha_programada'] ?? ''),
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
                    
                    // Capturamos las variables nuevas
                    $horasParada = $this->parseDecimal($_POST['horas_parada'] ?? 0);
                    $idCentroCosto = (int) ($_POST['id_centro_costo'] ?? 0); // <--- CAMBIO 1: Capturamos el Centro de Costos

                    // 1. Procesar Consumos
                    $consumos = [];
                    $consIdsInsumo = $_POST['consumo_id_insumo'] ?? [];
                    $consIdsAlmacen = $_POST['consumo_id_almacen'] ?? [];
                    $consCantidades = $_POST['consumo_cantidad'] ?? [];
                    $consIdsLote = $_POST['consumo_id_lote'] ?? [];

                    foreach ((array) $consIdsInsumo as $idx => $idInsumo) {
                        if (empty($idInsumo)) continue;
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

                    // 2. Procesar Ingresos
                    $ingresos = [];
                    $ingIdsAlmacen = $_POST['ingreso_id_almacen'] ?? [];
                    $ingCantidades = $_POST['ingreso_cantidad'] ?? [];
                    $ingIdsLote = $_POST['ingreso_id_lote'] ?? [];
                    $ingFechasVencimiento = $_POST['ingresos_fecha_vencimiento'] ?? [];

                    foreach ((array) $ingIdsAlmacen as $idx => $idAlmacen) {
                        if (empty($idAlmacen)) continue;
                        $cantidad = $this->parseDecimal($ingCantidades[$idx] ?? 0);
                        if ($cantidad > 0) {
                            $loteTexto = trim((string) ($ingIdsLote[$idx] ?? ''));
                            $ingresos[] = [
                                'id_almacen' => (int) $idAlmacen,
                                'cantidad' => $cantidad,
                                'id_lote' => ctype_digit($loteTexto) ? (int) $loteTexto : null,
                                'lote' => $loteTexto,
                                'fecha_vencimiento' => trim((string) ($ingFechasVencimiento[$idx] ?? '')),
                            ];
                        }
                    }

                    if (empty($ingresos)) {
                        throw new Exception('Debe registrar al menos un ingreso de producto terminado.');
                    }

                    // 3. Ejecutar en el Modelo
                    $this->produccionOrdenesModel->ejecutarOrden(
                        $idOrden,
                        $consumos,
                        $ingresos,
                        $userId,
                        $justificacion,
                        $fechaInicio,
                        $fechaFin,
                        $horasParada,
                        $idCentroCosto // <--- CAMBIO 2: Pasamos el ID al modelo
                    );

                    $this->setFlash('success', 'Orden ejecutada y costeada correctamente por Tarifa de Planta.');
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
                    if (ob_get_level() > 0) ob_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    exit;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        $this->render('produccion/produccion_ordenes', [
            'flash' => $flash,
            'ordenes' => $this->produccionOrdenesModel->listarOrdenes(),
            'recetas_activas' => $this->produccionOrdenesModel->listarRecetasActivas(),
            'almacenes' => $this->produccionOrdenesModel->listarAlmacenesActivos(),
            'almacenes_planta' => $this->produccionOrdenesModel->listarAlmacenesActivosPorTipo('Planta'),
            'centros' => $this->produccionOrdenesModel->listarCentrosCosto(), // <--- CAMBIO 3: Enviamos los centros a la vista
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