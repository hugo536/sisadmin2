<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ComprasOrdenModel.php';
require_once BASE_PATH . '/app/models/ComprasRecepcionModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';
require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
// 👇 AÑADIDO: Importamos el modelo de Movimientos de Tesorería 👇
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaMovimientoModel.php';

class ComprasController extends Controlador
{
    private ComprasOrdenModel $ordenModel;
    private ComprasRecepcionModel $recepcionModel;
    private TesoreriaCxpModel $tesoreriaCxpModel;
    private CentroCostoModel $centroCostoModel;
    private ListaPrecioModel $listaPrecioModel;
    // 👇 AÑADIDO: Propiedad para el modelo de movimientos 👇
    private TesoreriaMovimientoModel $tesoreriaMovModel;

    public function __construct()
    {
        $this->ordenModel = new ComprasOrdenModel();
        $this->recepcionModel = new ComprasRecepcionModel();
        $this->tesoreriaCxpModel = new TesoreriaCxpModel();
        $this->centroCostoModel = new CentroCostoModel();
        $this->listaPrecioModel = new ListaPrecioModel();
        // 👇 AÑADIDO: Inicializamos el modelo de movimientos 👇
        $this->tesoreriaMovModel = new TesoreriaMovimientoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'estado' => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string) $_GET['estado'] : null,
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        if ($filtros['estado'] === null) {
            $filtros['excluir_estado'] = 9; 
        }

        if ($filtros['fecha_desde'] === '' && $filtros['fecha_hasta'] === '') {
            $hoy = new DateTimeImmutable('today');
            $filtros['fecha_hasta'] = $hoy->format('Y-m-d');
            $filtros['fecha_desde'] = $hoy->sub(new DateInterval('P6D'))->format('Y-m-d');
        }

        // Guardar Devolución AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'guardar_devolucion') {
            try {
                $payload = $this->leerJson();
                $userId = $this->obtenerUsuarioId();

                if (empty($payload['id_orden']) || empty($payload['motivo']) || empty($payload['detalle'])) {
                    throw new RuntimeException('Faltan datos obligatorios para la devolución.');
                }

                $esperarReemplazo = isset($payload['esperar_reemplazo']) ? (bool) $payload['esperar_reemplazo'] : true;

                $this->ordenModel->registrarDevolucion(
                    (int) $payload['id_orden'], 
                    $payload['motivo'], 
                    $payload['resolucion'], 
                    $payload['detalle'], 
                    $userId,
                    $esperarReemplazo
                );

                json_response(['ok' => true, 'mensaje' => 'Devolución registrada correctamente. La cuenta por pagar y el inventario han sido actualizados.']);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
            }
            exit; 
        }

        // Listar AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response([
                'ok' => true,
                'data' => $this->ordenModel->listar($filtros),
            ]);
            exit;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'unidades_item') {
            try {
                $idItem = (int) ($_GET['id_item'] ?? 0);
                json_response([
                    'ok' => true,
                    'items' => $this->ordenModel->listarUnidadesConversionItem($idItem),
                ]);
            } catch (Throwable $e) {
                json_response([
                    'ok' => false,
                    'mensaje' => 'No se pudieron cargar unidades de conversión.',
                ], 500);
            }
            exit;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'precio_sugerido_proveedor') {
            $idProveedor = (int) ($_GET['id_proveedor'] ?? 0);
            $idItem = (int) ($_GET['id_item'] ?? 0);
            $idUnidad = (int) ($_GET['id_unidad'] ?? 0);
            if ($idProveedor <= 0 || $idItem <= 0) {
                json_response(['ok' => false, 'mensaje' => 'Parámetros inválidos.'], 422);
                exit;
            }

            $precio = $this->listaPrecioModel->obtenerPrecioRecomendadoProveedor(
                $idProveedor,
                $idItem,
                $idUnidad > 0 ? $idUnidad : null
            );
            json_response([
                'ok' => true,
                'encontrado' => $precio !== null,
                'precio_recomendado' => $precio,
            ]);
            exit;
        }

        // Ver detalle AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            try {
                $id = (int) ($_GET['id'] ?? 0);
                json_response([
                    'ok' => true,
                    'data' => $this->ordenModel->obtener($id),
                ]);
            } catch (Throwable $e) {
                json_response([
                    'ok' => false,
                    'mensaje' => 'Error al obtener los detalles de la orden: ' . $e->getMessage()
                ], 500);
            }
            exit;
        }

        // Renderizar Vista
        $this->render('compras', [
            'ruta_actual'   => 'compras',
            'ordenes'       => $this->ordenModel->listar($filtros),
            'filtros'       => $filtros,
            'proveedores'   => $this->ordenModel->listarProveedoresActivos(),
            'items'         => $this->ordenModel->listarItemsActivos(),
            'almacenes'     => $this->recepcionModel->listarAlmacenesActivos(),
            'centros_costo' => $this->centroCostoModel->listar(),
            'cuentas'       => (new TesoreriaCuentaModel())->listarActivas(), 
            'metodos'       => (new TesoreriaCxcModel())->obtenerMetodosActivos(),
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $userId = $this->obtenerUsuarioId();

            $idOrden = (int) ($payload['id'] ?? 0);
            $idProveedor = (int) ($payload['id_proveedor'] ?? 0);
            
            $fechaEmision = !empty($payload['fecha_emision'])
                ? trim((string) $payload['fecha_emision'])
                : trim((string) ($payload['fecha_entrega'] ?? ''));
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $tipoImpuesto = trim((string) ($payload['tipo_impuesto'] ?? 'incluido'));
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idProveedor <= 0 || !$this->ordenModel->proveedorEsValido($idProveedor)) {
                throw new RuntimeException('Seleccione un proveedor válido.');
            }

            if (empty($fechaEmision)) {
                throw new RuntimeException('La fecha de emisión es obligatoria.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem.');
            }

            // Recalcular la suma de líneas en backend
            $sumaLineas = 0.0;
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $cantidadBase = (float) ($linea['cantidad_base'] ?? 0);
                $factor = (float) ($linea['factor_conversion_aplicado'] ?? 1);
                $costo = (float) ($linea['costo_unitario'] ?? 0);
                $idCentroCosto = (int) ($linea['id_centro_costo'] ?? 0);

                if ($cantidad <= 0) {
                    throw new RuntimeException('La cantidad de compra de los ítems debe ser mayor a 0.');
                }
                if ($cantidadBase <= 0 || $factor <= 0) {
                    throw new RuntimeException('La conversión de unidades del ítem no es válida.');
                }
                if ($costo < 0) {
                    throw new RuntimeException('El costo no puede ser negativo.');
                }
                if ($idCentroCosto > 0 && !$this->centroCostoModel->existe($idCentroCosto)) {
                    throw new RuntimeException('Uno de los centros de costo seleccionados no es válido.');
                }
                $sumaLineas += ($cantidad * $costo);
            }

            // LÓGICA DE IMPUESTOS EN BACKEND
            $subtotal = 0.0;
            $igvMonto = 0.0;
            $totalFinal = 0.0;

            if ($tipoImpuesto === 'incluido') {
                $totalFinal = $sumaLineas;
                $subtotal = $totalFinal / 1.18;
                $igvMonto = $totalFinal - $subtotal;
            } elseif ($tipoImpuesto === 'mas_igv') {
                $subtotal = $sumaLineas;
                $igvMonto = $subtotal * 0.18;
                $totalFinal = $subtotal + $igvMonto;
            } else { // exonerado
                $subtotal = $sumaLineas;
                $igvMonto = 0.0;
                $totalFinal = $subtotal;
            }

            $cobroInmediato = !empty($payload['cobro_inmediato']) ? 1 : 0;
            $metodosPago = is_array($payload['metodos_pago'] ?? null) ? $payload['metodos_pago'] : [];

            // Llamar al Modelo enviando los nuevos campos
            $id = $this->ordenModel->crearOActualizar([
                'id' => $idOrden,
                'id_proveedor' => $idProveedor,
                'fecha_emision' => $fechaEmision,
                'observaciones' => $observaciones,
                'tipo_impuesto' => $tipoImpuesto,       
                'subtotal' => round($subtotal, 4),      
                'igv_monto' => round($igvMonto, 4),     
                'total' => round($totalFinal, 2),       
                'estado' => 0, 
                'cobro_inmediato' => $cobroInmediato,
                'metodos_pago' => json_encode($metodosPago)
            ], $detalle, $userId);

            $mensaje = 'Orden guardada correctamente.';
            
            // Si hubo pago al contado, auto-aprobamos la orden para que pase a recepción
            if ($cobroInmediato) {
                $this->ordenModel->aprobar($id, $userId);
                $mensaje = 'Orden guardada y aprobada automáticamente por pago al contado.';
            }

            json_response(['ok' => true, 'mensaje' => $mensaje, 'id' => $id]);

        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function aprobar(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.aprobar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idOrden = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0) {
                throw new RuntimeException('Orden inválida.');
            }

            $ok = $this->ordenModel->aprobar($idOrden, $userId);
            if (!$ok) {
                throw new RuntimeException('No se pudo aprobar la orden (tal vez ya no está en borrador).');
            }

            json_response(['ok' => true, 'mensaje' => 'Orden aprobada correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function anular(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.eliminar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idOrden = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0) {
                throw new RuntimeException('Orden inválida.');
            }

            $this->ordenModel->anular($idOrden, $userId);
            json_response(['ok' => true, 'mensaje' => 'Orden anulada correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function revertirBorrador(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.aprobar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idOrden = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0) {
                throw new RuntimeException('Orden inválida.');
            }

            $this->ordenModel->revertirABorrador($idOrden, $userId);
            json_response(['ok' => true, 'mensaje' => 'Orden revertida a borrador correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function recepcionar(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.recepcionar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idOrden = (int) ($payload['id_orden'] ?? 0);
            
            $detalleIngreso = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];
            $cerrarForzado = !empty($payload['cerrar_forzado']); 
            $fechaRecepcion = $this->normalizarFechaRecepcionPayload((string) ($payload['fecha_recepcion'] ?? ''));
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0) {
                throw new RuntimeException('Debe seleccionar una orden válida.');
            }

            if (empty($detalleIngreso)) {
                throw new RuntimeException('Debe proporcionar el detalle de productos a ingresar.');
            }

            if ($fechaRecepcion !== '') {
                $ordenData = $this->ordenModel->obtener($idOrden);
                if (!empty($ordenData['fecha_orden'])) {
                    $fechaOrdenSoloDia = explode(' ', $ordenData['fecha_orden'])[0];
                    if ($fechaRecepcion < $fechaOrdenSoloDia) {
                        throw new RuntimeException("Error: La fecha de recepción ($fechaRecepcion) no puede ser anterior a la emisión del pedido ($fechaOrdenSoloDia).");
                    }
                }
            }

            $idRecepcion = $this->recepcionModel->registrarRecepcion(
                $idOrden,
                $detalleIngreso,
                $cerrarForzado,
                $userId,
                $fechaRecepcion,
                $observaciones
            );

            // Nace la deuda en Tesorería
            $this->tesoreriaCxpModel->crearDesdeRecepcion($idRecepcion, $userId);

            // 👇 INYECCIÓN: EL PUENTE DE PAGO AUTOMÁTICO 👇
            $ordenData = $this->ordenModel->obtener($idOrden);
            
            // Verificamos si el cobro viene en el POST actual (JS) o ya estaba en la BD
            $esCobroInmediato = !empty($payload['cobro_inmediato']) || !empty($ordenData['cobro_inmediato']);
            $rawMetodos = !empty($payload['metodos_pago']) ? $payload['metodos_pago'] : ($ordenData['metodos_pago'] ?? null);

            if ($esCobroInmediato && !empty($rawMetodos)) {
                
                // 1. DESENCAPSULADO EXTREMO (Anti-Doble-Encode): 
                // Pelamos el JSON como una cebolla hasta obtener el Array real.
                $metodosArray = $rawMetodos;
                while (is_string($metodosArray)) {
                    $dec = json_decode($metodosArray, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $metodosArray = $dec;
                    } else {
                        break;
                    }
                }

                // Ahora sí, si es un array válido, procesamos el pago
                if (is_array($metodosArray) && count($metodosArray) > 0) {
                    
                    // Buscamos el ID y Saldo de la CXP que se acaba de crear arriba
                    $cxpData = $this->tesoreriaCxpModel->obtenerPorRecepcion($idRecepcion);

                    if ($cxpData) {
                        $idCxp = (int) $cxpData['id'];
                        $saldoCxp = (float) $cxpData['saldo'];
                        
                        foreach ($metodosArray as $pago) {
                            // Por si los objetos internos también quedaron como string
                            if (is_string($pago)) {
                                $pago = json_decode($pago, true) ?: [];
                            }
                            if (!is_array($pago)) continue;

                            // 2. ATRAPAMOS TODAS LAS VARIACIONES DE LLAVES
                            $idMetodo = (int) ($pago['id_metodo'] ?? $pago['id_metodo_pago'] ?? $pago['metodo'] ?? 0);
                            $idCuenta = (int) ($pago['id_cuenta'] ?? $pago['cuenta'] ?? 0);
                            $montoPago = (float) ($pago['monto'] ?? 0);
                            
                            // Prevención antibalas
                            if ($montoPago > $saldoCxp) {
                                $montoPago = $saldoCxp; 
                            }
                            
                            // Si todo es válido, disparamos el cobro
                            if ($montoPago > 0 && $idMetodo > 0 && $idCuenta > 0) {
                                $fechaPago = $fechaRecepcion !== '' ? $fechaRecepcion : date('Y-m-d');
                                $observacion = 'Pago al contado (Automático) desde Recepción de Orden ' . ($ordenData['codigo'] ?? $idOrden);

                                // Registramos el pago en caja/bancos oficialmente
                                $this->tesoreriaCxpModel->registrarPagoDirecto(
                                    $idCxp,
                                    $idCuenta,
                                    $idMetodo,
                                    $montoPago,
                                    $fechaPago,
                                    $observacion,
                                    $userId
                                );
                                
                                $saldoCxp -= $montoPago; 
                            }
                        }
                    }
                }
            }
            // 👆 FIN DE LA INYECCIÓN 👆

            json_response([
                'ok' => true,
                'mensaje' => 'Mercadería ingresada al almacén correctamente.',
                'id' => $idRecepcion,
            ]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    // --- Helpers Privados ---

    private function leerJson(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode((string) $input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException('Error al procesar los datos enviados (JSON inválido).');
        }

        return $data;
    }

    private function obtenerUsuarioId(): int
    {
        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('La sesión ha expirado o es inválida.');
        }
        return $id;
    }

    private function normalizarFechaRecepcionPayload(string $fecha): string
    {
        $fecha = trim($fecha);
        if ($fecha === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $fecha;
    }

    public function precioSugeridoAjax(): void
    {
        header('Content-Type: application/json');

        $idProveedor = (int)($_GET['id_proveedor'] ?? 0);
        $idItem      = (int)($_GET['id_item'] ?? 0);
        $idUnidad    = !empty($_GET['id_unidad']) ? (int)$_GET['id_unidad'] : null;

        if ($idProveedor <= 0 || $idItem <= 0) {
            echo json_encode([
                'ok' => false, 
                'mensaje' => 'Proveedor o ítem no válidos.'
            ]);
            exit;
        }

        try {
            $modelo = new ComprasOrdenModel();
            $precio = $modelo->obtenerPrecioProveedor($idProveedor, $idItem, $idUnidad);

            echo json_encode([
                'ok'                 => true,
                'encontrado'         => $precio > 0,
                'precio_recomendado' => $precio
            ]);
        } catch (Throwable $e) {
            echo json_encode([
                'ok' => false, 
                'mensaje' => 'Error al obtener precio sugerido: ' . $e->getMessage()
            ]);
        }
        exit;
    }
}