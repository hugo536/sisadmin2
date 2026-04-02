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

        // Guardar Devolución AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'guardar_devolucion') {
            try {
                $payload = $this->leerJson();
                $userId = $this->obtenerUsuarioId();

                if (empty($payload['id_orden']) || empty($payload['motivo']) || empty($payload['detalle'])) {
                    throw new RuntimeException('Faltan datos obligatorios para la devolución.');
                }

                // Llamamos a una nueva función en el modelo
                $this->ordenModel->registrarDevolucion(
                    (int) $payload['id_orden'], 
                    $payload['motivo'], 
                    $payload['resolucion'], 
                    $payload['detalle'], 
                    $userId
                );

                json_response(['ok' => true, 'mensaje' => 'Devolución registrada correctamente. La cuenta por pagar y el inventario han sido actualizados.']);
            } catch (Throwable $e) {
                json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
            }
            return;
        }

        // Listar AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response([
                'ok' => true,
                'data' => $this->ordenModel->listar($filtros),
            ]);
            return;
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
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'precio_sugerido_proveedor') {
            $idProveedor = (int) ($_GET['id_proveedor'] ?? 0);
            $idItem = (int) ($_GET['id_item'] ?? 0);
            $idUnidad = (int) ($_GET['id_unidad'] ?? 0);
            if ($idProveedor <= 0 || $idItem <= 0) {
                json_response(['ok' => false, 'mensaje' => 'Parámetros inválidos.'], 422);
                return;
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
            return;
        }

        // Ver detalle AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            $id = (int) ($_GET['id'] ?? 0);
            json_response([
                'ok' => true,
                'data' => $this->ordenModel->obtener($id),
            ]);
            return;
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
            
            $fechaEntrega = !empty($payload['fecha_entrega']) ? trim((string) $payload['fecha_entrega']) : null;
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $tipoImpuesto = trim((string) ($payload['tipo_impuesto'] ?? 'incluido')); // NUEVO
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idProveedor <= 0 || !$this->ordenModel->proveedorEsValido($idProveedor)) {
                throw new RuntimeException('Seleccione un proveedor válido.');
            }

            if (empty($fechaEntrega)) {
                throw new RuntimeException('La fecha de entrega estimada es obligatoria.');
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
                'fecha_entrega' => $fechaEntrega,
                'observaciones' => $observaciones,
                'tipo_impuesto' => $tipoImpuesto,       // <-- NUEVO
                'subtotal' => round($subtotal, 4),      // <-- ACTUALIZADO
                'igv_monto' => round($igvMonto, 4),     // <-- NUEVO
                'total' => round($totalFinal, 2),       // <-- ACTUALIZADO
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
            
            // Recibimos los nuevos parámetros configurados en JS
            $detalleIngreso = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];
            $cerrarForzado = !empty($payload['cerrar_forzado']); // Convertimos a booleano
            
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0) {
                throw new RuntimeException('Debe seleccionar una orden válida.');
            }

            if (empty($detalleIngreso)) {
                throw new RuntimeException('Debe proporcionar el detalle de productos a ingresar.');
            }

            // Llamamos a la nueva función del modelo con los 4 parámetros
            $idRecepcion = $this->recepcionModel->registrarRecepcion(
                $idOrden,
                $detalleIngreso,
                $cerrarForzado,
                $userId
            );

            // Generamos la CxP (Cuentas por Pagar) enlazada a esta recepción
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
            // Instanciamos el modelo que editamos en el paso anterior
            $modelo = new ComprasOrdenModel();
            
            // Llamamos a la nueva función
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
