<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ComprasOrdenModel.php';
require_once BASE_PATH . '/app/models/ComprasRecepcionModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';
require_once BASE_PATH . '/app/models/comercial/ListaPrecioModel.php';

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

        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'estado' => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string) $_GET['estado'] : null,
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        // --- NUEVO CÓDIGO ---
        // Si el usuario no filtró por un estado específico, le decimos al modelo que ignore las anuladas (9)
        if ($filtros['estado'] === null) {
            $filtros['excluir_estado'] = 9; 
        }
        // --------------------

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

                // NUEVO: Capturamos la decisión logística (por defecto true por seguridad)
                $esperarReemplazo = isset($payload['esperar_reemplazo']) ? (bool) $payload['esperar_reemplazo'] : true;

                // Llamamos al modelo pasando el nuevo parámetro al final
                $this->ordenModel->registrarDevolucion(
                    (int) $payload['id_orden'], 
                    $payload['motivo'], 
                    $payload['resolucion'], 
                    $payload['detalle'], 
                    $userId,
                    $esperarReemplazo // <-- AQUÍ PASAMOS EL DATO
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
            exit; // <-- FIX: Usar exit en lugar de return
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
            exit; // <-- FIX: Usar exit en lugar de return
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'precio_sugerido_proveedor') {
            $idProveedor = (int) ($_GET['id_proveedor'] ?? 0);
            $idItem = (int) ($_GET['id_item'] ?? 0);
            $idUnidad = (int) ($_GET['id_unidad'] ?? 0);
            if ($idProveedor <= 0 || $idItem <= 0) {
                json_response(['ok' => false, 'mensaje' => 'Parámetros inválidos.'], 422);
                exit; // <-- FIX: Usar exit en lugar de return
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
            exit; // <-- FIX: Usar exit en lugar de return
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
            exit; // <-- FIX: Usar exit en lugar de return
        }

        // Renderizar Vista
        $this->render('compras', [
            'ruta_actual' => 'compras',
            'ordenes' => $this->ordenModel->listar($filtros), // Carga inicial
            'filtros' => $filtros,
            'proveedores' => $this->ordenModel->listarProveedoresActivos(),
            'items' => $this->ordenModel->listarItemsActivos(),
            'almacenes' => $this->recepcionModel->listarAlmacenesActivos(),
            'centros_costo' => $this->centroCostoModel->listar(),
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
            ], $detalle, $userId);

            json_response(['ok' => true, 'mensaje' => 'Orden guardada correctamente.', 'id' => $id]);

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
            return; // Aquí está bien usar return porque no hay html que se renderice después
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

            // --- TOQUE DE ORO: VALIDACIÓN ESTRICTA DE FECHAS EN BACKEND ---
            if ($fechaRecepcion !== '') {
                $ordenData = $this->ordenModel->obtener($idOrden);
                if (!empty($ordenData['fecha_orden'])) {
                    // Extraemos solo el YYYY-MM-DD por si viene con horas
                    $fechaOrdenSoloDia = explode(' ', $ordenData['fecha_orden'])[0];
                    if ($fechaRecepcion < $fechaOrdenSoloDia) {
                        throw new RuntimeException("Error: La fecha de recepción ($fechaRecepcion) no puede ser anterior a la emisión del pedido ($fechaOrdenSoloDia).");
                    }
                }
            }
            // --------------------------------------------------------------

            $idRecepcion = $this->recepcionModel->registrarRecepcion(
                $idOrden,
                $detalleIngreso,
                $cerrarForzado,
                $userId,
                $fechaRecepcion,
                $observaciones
            );

            $this->tesoreriaCxpModel->crearDesdeRecepcion($idRecepcion, $userId);

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
        // Aseguramos que la respuesta sea JSON
        header('Content-Type: application/json');

        // Capturamos los datos que envía tu fetch en JS
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
            // Instanciamos el modelo 
            $modelo = new ComprasOrdenModel();
            
            // Llamamos a la función
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
