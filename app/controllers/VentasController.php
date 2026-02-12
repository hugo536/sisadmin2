<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/VentasDocumentoModel.php';
require_once BASE_PATH . '/app/models/VentaDespachoModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';

class VentasController extends Controlador
{
    private VentasDocumentoModel $documentoModel;
    private VentaDespachoModel $despachoModel;

    public function __construct()
    {
        $this->documentoModel = new VentasDocumentoModel();
        $this->despachoModel = new VentaDespachoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.ver');

        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'estado' => isset($_GET['estado']) ? (string) $_GET['estado'] : '',
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'listar') {
            json_response(['ok' => true, 'data' => $this->documentoModel->listar($filtros)]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'ver') {
            $id = (int) ($_GET['id'] ?? 0);
            json_response(['ok' => true, 'data' => $this->documentoModel->obtener($id)]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_clientes') {
            $q = trim((string) ($_GET['q'] ?? ''));
            json_response(['ok' => true, 'data' => $this->documentoModel->buscarClientes($q)]);
            return;
        }

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'buscar_items') {
            $q = trim((string) ($_GET['q'] ?? ''));
            $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);
            json_response(['ok' => true, 'data' => $this->documentoModel->buscarItems($q, $idAlmacen)]);
            return;
        }

        $this->render('ventas', [
            'ruta_actual' => 'ventas',
            'ventas' => $this->documentoModel->listar($filtros),
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
            $idCliente = (int) ($payload['id_cliente'] ?? 0);
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $detalle = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            if ($idCliente <= 0) {
                throw new RuntimeException('Seleccione un cliente válido.');
            }

            if ($detalle === []) {
                throw new RuntimeException('Debe agregar al menos un ítem.');
            }

            $total = 0.0;
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);
                if ($cantidad <= 0 || $precio < 0) {
                    throw new RuntimeException('Hay líneas con cantidad/precio inválido.');
                }
                $total += $cantidad * $precio;
            }

            $id = $this->documentoModel->crearOActualizar([
                'id' => (int) ($payload['id'] ?? 0),
                'id_cliente' => $idCliente,
                'observaciones' => $observaciones,
                'subtotal' => round($total, 2),
                'total' => round($total, 2),
            ], $detalle, (int) ($_SESSION['id'] ?? 0));

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
            if ($idDocumento <= 0) {
                throw new RuntimeException('Pedido inválido.');
            }

            $ok = $this->documentoModel->aprobar($idDocumento, (int) ($_SESSION['id'] ?? 0));
            if (!$ok) {
                throw new RuntimeException('Solo se pueden aprobar pedidos en borrador.');
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
            if ($idDocumento <= 0) {
                throw new RuntimeException('Pedido inválido.');
            }

            $this->documentoModel->anular($idDocumento, (int) ($_SESSION['id'] ?? 0));
            json_response(['ok' => true, 'mensaje' => 'Pedido anulado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
        }
    }

    public function despachar(): void
    {
        AuthMiddleware::handle();
        require_permiso('ventas.despachar');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $payload = $this->leerJson();
            $idDocumento = (int) ($payload['id_documento'] ?? 0);
            $idAlmacen = (int) ($payload['id_almacen'] ?? 0);
            $cerrarForzado = !empty($payload['cerrar_forzado']);
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));
            $lineas = is_array($payload['detalle'] ?? null) ? $payload['detalle'] : [];

            $idDespacho = $this->despachoModel->registrarDespacho(
                $idDocumento,
                $idAlmacen,
                $lineas,
                $cerrarForzado,
                $observaciones,
                (int) ($_SESSION['id'] ?? 0)
            );

            json_response([
                'ok' => true,
                'mensaje' => $cerrarForzado
                    ? 'Despacho registrado y pedido cerrado (saldos cancelados).'
                    : 'Despacho registrado correctamente.',
                'id' => $idDespacho,
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
