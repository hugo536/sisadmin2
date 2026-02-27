<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';
require_once BASE_PATH . '/app/models/VentasDespachoModel.php'; // Corregido el nombre del archivo (Plural)
require_once BASE_PATH . '/app/controllers/PermisosController.php';

class VentasController extends Controlador
{
    private VentasDocumentoModel $documentoModel;
    private VentasDespachoModel $despachoModel;

    public function __construct()
    {
        $this->documentoModel = new VentasDocumentoModel();
        $this->despachoModel = new VentasDespachoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.ver');

        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'estado' => isset($_GET['estado']) && $_GET['estado'] !== '' ? (string) $_GET['estado'] : null,
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        // 1. Listar Ventas (AJAX)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response(['ok' => true, 'data' => $this->documentoModel->listar($filtros)]);
            return;
        }

        // 2. Ver Detalle Venta (AJAX)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            $id = (int) ($_GET['id'] ?? 0);
            json_response(['ok' => true, 'data' => $this->documentoModel->obtener($id)]);
            return;
        }

        // 3. Buscador de Clientes (AJAX para Select2 o similar)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_clientes') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->documentoModel->buscarClientes($q)]);
            return;
        }

        // 4. Buscador de Items con Stock (AJAX)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_items') {
            $q = trim((string) ($_GET['q'] ?? ''));
            $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $cantidad = (float) ($_GET['cantidad'] ?? 1);
            $metaAcuerdo = $this->documentoModel->tieneAcuerdoConProductosVigentes($idCliente);

            json_response([
                'ok' => true,
                'data' => $this->documentoModel->buscarItems($q, $idAlmacen, $idCliente, $cantidad),
                'meta' => $metaAcuerdo,
            ]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'precio_item') {
            $idCliente = (int) ($_GET['id_cliente'] ?? 0);
            $idItem = (int) ($_GET['id_item'] ?? 0);
            $cantidad = (float) ($_GET['cantidad'] ?? 1);

            json_response([
                'ok' => true,
                'data' => $this->documentoModel->obtenerPrecioUnitario($idCliente, $idItem, $cantidad),
            ]);
            return;
        }

        // 5. Renderizado de Vista Principal
        $this->render('ventas', [
            'ruta_actual' => 'ventas',
            'ventas' => $this->documentoModel->listar($filtros), // Carga inicial
            'filtros' => $filtros,
            'almacenes' => $this->documentoModel->listarAlmacenesActivos(),
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $userId = $this->obtenerUsuarioId();

            $idCliente = (int) ($payload['id_cliente'] ?? 0);
            // Capturamos la fecha de emisión (si viene vacía, el modelo pondrá HOY)
            $fechaEmision = !empty($payload['fecha_emision']) ? trim((string) $payload['fecha_emision']) : null;
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idCliente <= 0 || !$this->documentoModel->clienteEsValido($idCliente)) {
                throw new RuntimeException('Seleccione un cliente válido.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem al pedido.');
            }

            // Recálculo de seguridad en Backend + validaciones de negocio.
            $total = 0.0;
            $itemsUnicos = [];
            $cantidadesPorItem = [];

            foreach ($detalle as $linea) {
                $idItem = (int) ($linea['id_item'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);

                if ($idItem <= 0) {
                    throw new RuntimeException('Hay líneas sin producto válido.');
                }

                if (isset($itemsUnicos[$idItem])) {
                    throw new RuntimeException('No se permiten productos repetidos en el pedido.');
                }
                $itemsUnicos[$idItem] = true;
                
                if ($cantidad <= 0) {
                    throw new RuntimeException('La cantidad de los ítems debe ser mayor a 0.');
                }
                if ($precio < 0) {
                    throw new RuntimeException('El precio no puede ser negativo.');
                }

                $cantidadesPorItem[$idItem] = ($cantidadesPorItem[$idItem] ?? 0.0) + $cantidad;
                $total += $cantidad * $precio;
            }

            foreach ($cantidadesPorItem as $idItem => $cantidadSolicitada) {
                $stockActual = $this->documentoModel->obtenerStockDisponibleItem((int) $idItem);
                if ($cantidadSolicitada > $stockActual) {
                    throw new RuntimeException('No puede registrar cantidades mayores al stock disponible.');
                }
            }

            $id = $this->documentoModel->crearOActualizar([
                'id' => (int) ($payload['id'] ?? 0),
                'id_cliente' => $idCliente,
                'fecha_emision' => $fechaEmision, // Agregado al payload
                'observaciones' => $observaciones,
                'subtotal' => round($total, 2), // Asumiendo sin impuestos por ahora
                'total' => round($total, 2),
            ], $detalle, $userId);

            json_response(['ok' => true, 'mensaje' => 'Pedido guardado correctamente.', 'id' => $id]);

        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function aprobar(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.aprobar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idDocumento = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idDocumento <= 0) {
                throw new RuntimeException('Pedido inválido.');
            }

            $ok = $this->documentoModel->aprobar($idDocumento, $userId);
            if (!$ok) {
                throw new RuntimeException('No se pudo aprobar. Verifique que el pedido esté en borrador.');
            }

            json_response(['ok' => true, 'mensaje' => 'Pedido aprobado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function anular(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.eliminar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idDocumento = (int) ($payload['id'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idDocumento <= 0) {
                throw new RuntimeException('Pedido inválido.');
            }

            $this->documentoModel->anular($idDocumento, $userId);
            json_response(['ok' => true, 'mensaje' => 'Pedido anulado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function despachar()
    {
        // Verificar si es AJAX
        if (!es_ajax()) {
            http_response_code(400); // Bad Request
            echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        
        $idDocumento = (int) ($data['id_documento'] ?? 0);
        $cerrarForzado = (bool) ($data['cerrar_forzado'] ?? false);
        $observaciones = trim($data['observaciones'] ?? '');
        $detalle = $data['detalle'] ?? [];

        // VALIDACIÓN MODIFICADA: Ya no exigimos 'id_almacen' global
        if ($idDocumento <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Documento inválido']);
            return;
        }

        if (empty($detalle) || !is_array($detalle)) {
            echo json_encode(['ok' => false, 'mensaje' => 'No hay ítems para despachar']);
            return;
        }

        // Validamos que CADA línea tenga su almacén
        foreach ($detalle as $linea) {
            if (empty($linea['id_almacen']) || $linea['id_almacen'] <= 0) {
                echo json_encode(['ok' => false, 'mensaje' => 'Error: Hay filas sin almacén seleccionado.']);
                return;
            }
        }

        try {
            // Llamamos al modelo (Nota: ya no pasamos un ID Almacén único)
            $this->documentoModel->guardarDespacho($idDocumento, $detalle, $observaciones, $cerrarForzado, $_SESSION['user_id'] ?? 1);
            
            echo json_encode(['ok' => true, 'mensaje' => 'Despacho registrado correctamente']);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
    }

    // --- Helpers Privados ---

    private function leerJson(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode((string) $input, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            throw new RuntimeException('Error en los datos enviados (JSON inválido).');
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
}
