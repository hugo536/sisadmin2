<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/InventarioModel.php';
require_once BASE_PATH . '/app/models/AlmacenModel.php';

class InventarioController extends Controlador
{
    private InventarioModel $inventarioModel;
    private AlmacenModel $almacenModel;

    public function __construct()
    {
        $this->inventarioModel = new InventarioModel();
        $this->almacenModel = new AlmacenModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $datos = [
            'ruta_actual' => 'inventario',
            'stockActual' => $this->inventarioModel->obtenerStockActual(),
            'almacenes' => $this->almacenModel->listarActivos(),
            'items' => $this->inventarioModel->listarItems(),
            'flash' => ['tipo' => '', 'texto' => ''],
        ];

        $this->vista('inventario', $datos);
    }

    public function guardarMovimiento(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.movimiento.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $tipo = trim((string) ($_POST['tipo_movimiento'] ?? ''));
            $idAlmacen = (int) ($_POST['id_almacen'] ?? 0);
            $idItem = (int) ($_POST['id_item'] ?? 0);
            $cantidad = (float) ($_POST['cantidad'] ?? 0);
            $costoUnitario = (float) ($_POST['costo_unitario'] ?? 0);
            $referencia = trim((string) ($_POST['referencia'] ?? ''));

            $datos = [
                'tipo_movimiento' => $tipo,
                'id_item' => $idItem,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario,
                'referencia' => $referencia,
                'created_by' => (int) ($_SESSION['id'] ?? 0),
            ];

            if ($tipo === 'TRF') {
                $datos['id_almacen_origen'] = $idAlmacen;
                $datos['id_almacen_destino'] = (int) ($_POST['id_almacen_destino'] ?? 0);
            } elseif (in_array($tipo, ['AJ-', 'CON'], true)) {
                $datos['id_almacen_origen'] = $idAlmacen;
            } else {
                $datos['id_almacen_destino'] = $idAlmacen;
            }

            $idMovimiento = $this->inventarioModel->registrarMovimiento($datos);

            json_response([
                'ok' => true,
                'mensaje' => 'Movimiento registrado correctamente.',
                'id' => $idMovimiento,
            ]);
        } catch (Throwable $e) {
            json_response([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 400);
        }
    }

    private function vista(string $rutaVista, array $datos = []): void
    {
        $this->render($rutaVista, $datos);
    }
}
