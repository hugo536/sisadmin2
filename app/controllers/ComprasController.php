<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ComprasOrdenModel.php';
require_once BASE_PATH . '/app/models/ComprasRecepcionModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';

class ComprasController extends Controlador
{
    private ComprasOrdenModel $ordenModel;
    private ComprasRecepcionModel $recepcionModel;

    public function __construct()
    {
        $this->ordenModel = new ComprasOrdenModel();
        $this->recepcionModel = new ComprasRecepcionModel();
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

        // Listar AJAX
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response([
                'ok' => true,
                'data' => $this->ordenModel->listar($filtros),
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
            
            // Convertir string vacío a NULL para la base de datos
            $fechaEntrega = !empty($payload['fecha_entrega']) ? trim((string) $payload['fecha_entrega']) : null;
            
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idProveedor <= 0 || !$this->ordenModel->proveedorEsValido($idProveedor)) {
                throw new RuntimeException('Seleccione un proveedor válido.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem.');
            }

            // Recalcular total en backend para evitar manipulación en frontend
            $total = 0.0;
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $costo = (float) ($linea['costo_unitario'] ?? 0);

                if ($cantidad <= 0) {
                    throw new RuntimeException('La cantidad de los ítems debe ser mayor a 0.');
                }
                if ($costo < 0) {
                    throw new RuntimeException('El costo no puede ser negativo.');
                }
                $total += $cantidad * $costo;
            }

            // Llamar al Modelo
            $id = $this->ordenModel->crearOActualizar([
                'id' => $idOrden,
                'id_proveedor' => $idProveedor,
                'fecha_entrega' => $fechaEntrega,
                'observaciones' => $observaciones,
                'subtotal' => round($total, 2), // Asumimos subtotal = total (sin impuestos por ahora)
                'total' => round($total, 2),
                'estado' => 0, // Siempre se guarda como Borrador al editar/crear
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
            $idAlmacen = (int) ($payload['id_almacen'] ?? 0);
            $userId = $this->obtenerUsuarioId();

            if ($idOrden <= 0 || $idAlmacen <= 0) {
                throw new RuntimeException('Debe seleccionar una orden y un almacén de destino.');
            }

            /**
             * NOTA IMPORTANTE:
             * Esta lógica asume una "Recepción Total" automática basada en lo que dice la Orden de Compra.
             * Si tu sistema requiere recepciones parciales (ej. llegaron 5 de 10),
             * deberías enviar el array 'detalle' desde el frontend y modificar
             * ComprasRecepcionModel::registrarRecepcion para usar ese array en vez de leer la orden.
             */
            $idRecepcion = $this->recepcionModel->registrarRecepcion(
                $idOrden,
                $idAlmacen,
                $userId
            );

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
}