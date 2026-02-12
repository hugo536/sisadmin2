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
            'estado' => isset($_GET['estado']) ? (string) $_GET['estado'] : '',
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response([
                'ok' => true,
                'data' => $this->ordenModel->listar($filtros),
            ]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            $id = (int) ($_GET['id'] ?? 0);
            json_response([
                'ok' => true,
                'data' => $this->ordenModel->obtener($id),
            ]);
            return;
        }

        $this->render('compras', [
            'ruta_actual' => 'compras',
            'ordenes' => $this->ordenModel->listar($filtros),
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

            $idProveedor = (int) ($payload['id_proveedor'] ?? 0);
            $fechaEntrega = trim((string) ($payload['fecha_entrega'] ?? ''));
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idProveedor <= 0) {
                throw new RuntimeException('Seleccione un proveedor válido.');
            }

            if (empty($detalle)) {
                throw new RuntimeException('Debe agregar al menos un ítem.');
            }

            $total = 0.0;
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $costo = (float) ($linea['costo_unitario'] ?? 0);
                if ($cantidad <= 0 || $costo < 0) {
                    throw new RuntimeException('Hay líneas con cantidad/costo inválido.');
                }
                $total += $cantidad * $costo;
            }

            $id = $this->ordenModel->crearOActualizar([
                'id' => (int) ($payload['id'] ?? 0),
                'id_proveedor' => $idProveedor,
                'fecha_entrega' => $fechaEntrega,
                'observaciones' => $observaciones,
                'subtotal' => round($total, 2),
                'total' => round($total, 2),
                'estado' => 0,
            ], $detalle, (int) ($_SESSION['id'] ?? 0));

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
            if ($idOrden <= 0) {
                throw new RuntimeException('Orden inválida.');
            }

            $ok = $this->ordenModel->aprobar($idOrden, (int) ($_SESSION['id'] ?? 0));
            if (!$ok) {
                throw new RuntimeException('Solo se pueden aprobar órdenes en borrador.');
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
            if ($idOrden <= 0) {
                throw new RuntimeException('Orden inválida.');
            }

            $this->ordenModel->anular($idOrden, (int) ($_SESSION['id'] ?? 0));
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

            if ($idOrden <= 0 || $idAlmacen <= 0) {
                throw new RuntimeException('Debe seleccionar orden y almacén.');
            }

            $idRecepcion = $this->recepcionModel->registrarRecepcion(
                $idOrden,
                $idAlmacen,
                (int) ($_SESSION['id'] ?? 0)
            );

            json_response([
                'ok' => true,
                'mensaje' => 'Recepción registrada y stock actualizado correctamente.',
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
        if (!is_array($data)) {
            throw new RuntimeException('JSON inválido.');
        }

        return $data;
    }
}
