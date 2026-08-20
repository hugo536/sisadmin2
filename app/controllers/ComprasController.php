<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ComprasOrdenModel.php';
require_once BASE_PATH . '/app/models/ComprasRecepcionModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';
require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';

class ComprasController extends Controlador
{
    private ComprasOrdenModel $ordenModel;
    private ComprasRecepcionModel $recepcionModel;
    private TesoreriaCxpModel $tesoreriaCxpModel;
    private CentroCostoModel $centroCostoModel;
    private ListaPrecioModel $listaPrecioModel;

    public function __construct()
    {
        $this->ordenModel = new ComprasOrdenModel();
        $this->recepcionModel = new ComprasRecepcionModel();
        $this->tesoreriaCxpModel = new TesoreriaCxpModel();
        $this->centroCostoModel = new CentroCostoModel();
        $this->listaPrecioModel = new ListaPrecioModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        $fechaHastaDef = date('Y-m-d');
        $fechaDesdeDef = date('Y-m-d', strtotime('-30 days'));

        $esVistaInicial = empty($_GET['q']) && !isset($_GET['estado']) && empty($_GET['fecha_desde']) && empty($_GET['fecha_hasta']);

        $filtros = [
            'q'           => trim((string) ($_GET['q'] ?? '')),
            'estado'      => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string) $_GET['estado'] : null,
            'fecha_desde' => $esVistaInicial ? $fechaDesdeDef : trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => $esVistaInicial ? $fechaHastaDef : trim((string) ($_GET['fecha_hasta'] ?? '')),
            'orden_fecha' => trim((string) ($_GET['orden_fecha'] ?? 'orden')),
        ];

        // Ocultamos las anuladas por defecto
        if ($filtros['estado'] === null) {
            $filtros['excluir_estado'] = 9; 
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response(['ok' => true, 'data' => $this->ordenModel->listar($filtros)]);
            exit; 
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            try {
                $id = (int) ($_GET['id'] ?? 0);
                $orden = $this->ordenModel->obtener($id);

                // 👇 MAGIA PARA EL JS: Consultamos la deuda y el DETALLE de los pagos 👇
                $deuda = $this->tesoreriaCxpModel->obtenerPorOrden($id);
                $orden['monto_pagado'] = $deuda ? (float) ($deuda['monto_pagado'] ?? 0) : 0.0;
                $orden['pagos_detallados'] = $this->tesoreriaCxpModel->obtenerDetallePagosOrden($id);
                // 👆 FIN DE LA MAGIA 👆

                json_response(['ok' => true, 'data' => $orden]);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => 'Error al obtener los detalles: ' . $e->getMessage()], 500);
            }
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
                json_response(['ok' => false, 'mensaje' => 'No se pudieron cargar unidades de conversión.'], 500);
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

            try {
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
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => 'Error al obtener precio: ' . $e->getMessage()], 500);
            }
            exit;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'guardar_devolucion') {
            require_algun_permiso(['compras.recepcionar', 'compras.aprobar']);
            try {
                $payload = $this->leerJson();
                $userId = $this->obtenerUsuarioId();

                if (empty($payload['id_orden']) || empty($payload['motivo']) || empty($payload['detalle'])) {
                    throw new RuntimeException('Faltan datos obligatorios para la devolución.');
                }

                $esperarReemplazo = isset($payload['esperar_reemplazo']) ? (bool) $payload['esperar_reemplazo'] : true;

                $this->ordenModel->registrarDevolucion(
                    (int) $payload['id_orden'], 
                    (string) $payload['motivo'], 
                    (string) ($payload['resolucion'] ?? ''), 
                    is_array($payload['detalle']) ? $payload['detalle'] : [], 
                    $userId,
                    $esperarReemplazo
                );

                json_response([
                    'ok' => true, 
                    'mensaje' => 'Devolución registrada correctamente. La cuenta por pagar y el inventario han sido actualizados.'
                ]);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
            }
            exit; 
        }

        // Carga inicial de la página
        $this->render('compras', [
            'ruta_actual'   => 'compras',
            'ordenes'       => $this->ordenModel->listar($filtros),
            'filtros'       => $filtros,
            'proveedores'   => $this->ordenModel->listarProveedoresActivos(),
            'items'         => $this->ordenModel->listarItemsActivos(),
            'almacenes'     => $this->recepcionModel->listarAlmacenesActivos(),
            'centros_costo' => $this->centroCostoModel->listar(),
            'cuentas'       => (new TesoreriaCuentaModel())->listarActivas(), 
            // 👇 LÍNEA CORREGIDA 👇
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
            $moneda = strtoupper(trim((string) ($payload['moneda'] ?? 'PEN')));
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];
            
            $esCobroInmediato = filter_var($payload['cobro_inmediato'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $metodosPago = is_array($payload['metodos_pago'] ?? null) ? $payload['metodos_pago'] : [];

            if (!in_array($moneda, ['PEN', 'USD'], true)) {
                throw new RuntimeException('La moneda de la orden debe ser PEN o USD.');
            }

            if ($idProveedor <= 0 || !$this->ordenModel->proveedorEsValido($idProveedor)) {
                throw new RuntimeException('Seleccione un proveedor válido.');
            }

            if (empty($fechaEmision)) {
                throw new RuntimeException('La fecha de emisión es obligatoria.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem al pedido.');
            }

            if ($esCobroInmediato) {
                if (empty($metodosPago)) {
                    throw new RuntimeException('Debe especificar al menos un método de pago para el pago anticipado.');
                }
                foreach ($metodosPago as $pago) {
                    if (empty($pago['id_cuenta']) || empty($pago['id_metodo']) || empty($pago['monto']) || (float)$pago['monto'] <= 0) {
                        throw new RuntimeException('Todos los métodos de pago ingresados deben tener cuenta, método y un monto válido.');
                    }
                }
            }

            $sumaLineas = 0.0;
            $itemsUnicos = [];

            foreach ($detalle as $linea) {
                $rawId = trim((string) ($linea['id_item'] ?? ''));
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $cantidadBase = (float) ($linea['cantidad_base'] ?? 0);
                $factor = (float) ($linea['factor_conversion_aplicado'] ?? 1);
                $costo = (float) ($linea['costo_unitario'] ?? 0);
                $idCentroCosto = (int) ($linea['id_centro_costo'] ?? 0);

                if ($rawId === '' || $rawId === '0') {
                    throw new RuntimeException('Hay líneas sin producto válido.');
                }

                if (isset($itemsUnicos[$rawId])) {
                    throw new RuntimeException('No se permiten productos repetidos dentro de la misma orden.');
                }
                $itemsUnicos[$rawId] = true;

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
            } else { 
                $subtotal = $sumaLineas;
                $igvMonto = 0.0;
                $totalFinal = $subtotal;
            }

            $id = $this->ordenModel->crearOActualizar([
                'id' => $idOrden,
                'id_proveedor' => $idProveedor,
                'fecha_emision' => $fechaEmision,
                'observaciones' => $observaciones,
                'tipo_impuesto' => $tipoImpuesto,
                'moneda' => $moneda,       
                'subtotal' => round($subtotal, 4),      
                'igv_monto' => round($igvMonto, 4),     
                'total' => round($totalFinal, 2),       
                'estado' => 0, 
                'cobro_inmediato' => $esCobroInmediato ? 1 : 0,
                'metodos_pago' => json_encode($metodosPago)
            ], $detalle, $userId);

            $mensaje = 'Orden guardada correctamente.';
            
            // 👇 LÓGICA: CXP Y PAGO EN EL INSTANTE 👇
            if ($esCobroInmediato) {
                // 1. Aprobamos la orden automáticamente
                $this->ordenModel->aprobar($id, $userId);
                
                // 2. Creamos la deuda vinculada a esta orden de inmediato
                $idCxp = $this->tesoreriaCxpModel->crearDesdeOrden($id, $userId);

                // 3. Procesamos los pagos registrados
                if ($idCxp > 0 && !empty($metodosPago)) {
                    $saldoCxp = (float) $totalFinal; 

                    foreach ($metodosPago as $pago) {
                        $idMetodo = (int) ($pago['id_metodo'] ?? 0);
                        $idCuenta = (int) ($pago['id_cuenta'] ?? 0);
                        $montoPago = (float) ($pago['monto'] ?? 0);
                        $tipoCambio = (float) ($pago['tipo_cambio'] ?? 1);
                        if ($tipoCambio <= 0) $tipoCambio = 1;

                        if ($montoPago > $saldoCxp) {
                            $montoPago = $saldoCxp; 
                        }

                        if ($montoPago > 0 && $idMetodo > 0 && $idCuenta > 0) {
                            $observacion = 'Pago al contado anticipado desde OC ID ' . $id;
                            if ($tipoCambio !== 1.0) {
                                $observacion .= ' (T.C. aplicado: ' . $tipoCambio . ')';
                            }

                            $this->tesoreriaCxpModel->registrarPagoDirecto(
                                $idCxp, $idCuenta, $idMetodo, $montoPago, $fechaEmision, $observacion, $userId, $tipoCambio
                            );
                            $saldoCxp -= $montoPago; 
                        }
                    }
                }
                $mensaje = 'Orden guardada, CxP generada y pagada en Tesorería.';
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

            if ($idOrden <= 0) throw new RuntimeException('Orden inválida.');

            $ok = $this->ordenModel->aprobar($idOrden, $userId);
            if (!$ok) throw new RuntimeException('No se pudo aprobar la orden (tal vez ya no está en borrador).');

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

            if ($idOrden <= 0) throw new RuntimeException('Orden inválida.');

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

            if ($idOrden <= 0) throw new RuntimeException('Orden inválida.');

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
            json_response(['ok' => false, 'mensaje' => 'Acceso denegado'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            
            $idOrden = (int) ($payload['id_orden'] ?? 0);
            $cerrarForzado = filter_var(($payload['cerrar_forzado'] ?? false), FILTER_VALIDATE_BOOLEAN);
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $detalleIngreso = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];
            
            $fechaRecepcion = $this->normalizarFechaRecepcionPayload((string) ($payload['fecha_recepcion'] ?? ''));
            if (empty($fechaRecepcion)) {
                $fechaRecepcion = date('Y-m-d'); 
            }
            
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0) throw new RuntimeException('Documento inválido');
            if (empty($detalleIngreso) && !$cerrarForzado) throw new RuntimeException('No hay ítems para recepcionar o debe marcar la opción de forzar cierre.');

            foreach ($detalleIngreso as $linea) {
                if (empty($linea['id_almacen']) || $linea['id_almacen'] <= 0) {
                    throw new RuntimeException('Error: Hay filas sin almacén destino seleccionado.');
                }
            }

            // Validación de fechas
            $ordenData = $this->ordenModel->obtener($idOrden);
            if (!empty($ordenData['fecha_orden'])) {
                $fechaOrdenSoloDia = explode(' ', $ordenData['fecha_orden'])[0];
                if ($fechaRecepcion < $fechaOrdenSoloDia) {
                    throw new RuntimeException("Error: La fecha de recepción ($fechaRecepcion) no puede ser anterior a la emisión del pedido ($fechaOrdenSoloDia).");
                }
            }

            // 1. Registrar ingreso de mercadería
            $idRecepcion = $this->recepcionModel->registrarRecepcion(
                $idOrden,
                $detalleIngreso,
                $cerrarForzado,
                $userId,
                $fechaRecepcion,
                $observaciones
            );

            // 2. Crear Cuenta por Pagar si NO se pagó por anticipado
            $esCobroInmediato = !empty($ordenData['cobro_inmediato']);
            if (!$esCobroInmediato) {
                $this->tesoreriaCxpModel->crearDesdeRecepcion($idRecepcion, $userId);
            }

            json_response([
                'ok' => true,
                'mensaje' => 'Mercadería ingresada al almacén correctamente.',
                'id' => $idRecepcion,
            ]);

        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

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
        if ($fecha === '') return '';

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        return $fecha;
    }
}