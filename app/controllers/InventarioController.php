<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/InventarioModel.php';
require_once BASE_PATH . '/app/models/AlmacenModel.php';
require_once BASE_PATH . '/app/controllers/PermisosController.php';

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
        if (!PermisosController::tienePermiso('inventario.ver')) {
            require_permiso('inventario.ver');
        }

        $stockActual = $this->inventarioModel->obtenerStock();

        $datos = [
            'ruta_actual' => 'inventario',
            'stockActual' => $stockActual,
            'almacenes' => $this->almacenModel->listarActivos(),
            'items' => $this->inventarioModel->listarItems(),
            'kpis' => $this->calcularKpis($stockActual),
            'flash' => ['tipo' => '', 'texto' => ''],
        ];

        $this->vista('inventario', $datos);
    }

    public function guardarMovimiento(): void
    {
        AuthMiddleware::handle();
        if (!PermisosController::tienePermiso('inventario.movimiento.crear')) {
            require_permiso('inventario.movimiento.crear');
        }

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        try {
            $tipo = trim((string) ($_POST['tipo_movimiento'] ?? ''));
            $idAlmacen = (int) ($_POST['id_almacen'] ?? 0);
            $idItem = (int) ($_POST['id_item'] ?? 0);
            $cantidad = (float) ($_POST['cantidad'] ?? 0);
            $referencia = trim((string) ($_POST['referencia'] ?? ''));
            $lote = trim((string) ($_POST['lote'] ?? ''));
            $fechaVencimiento = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
            $costoUnitario = (float) ($_POST['costo_unitario'] ?? 0);

            $datos = [
                'tipo_movimiento' => $tipo,
                'id_item' => $idItem,
                'cantidad' => $cantidad,
                'referencia' => $referencia,
                'lote' => $lote,
                'fecha_vencimiento' => $fechaVencimiento,
                'costo_unitario' => $costoUnitario,
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
            return;
        } catch (Throwable $e) {
            json_response([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 400);
            return;
        }
    }

    public function buscarItems(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.movimiento.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        $q = trim((string) ($_GET['q'] ?? ''));
        if ($q === '') {
            json_response(['ok' => true, 'items' => []]);
            return;
        }

        $items = $this->inventarioModel->buscarItems($q, 25);
        json_response(['ok' => true, 'items' => $items]);
    }

    public function stockItem(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.movimiento.crear');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
            return;
        }

        $idItem = (int) ($_GET['id_item'] ?? 0);
        $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);

        if ($idItem <= 0 || $idAlmacen <= 0) {
            json_response(['ok' => true, 'stock' => 0]);
            return;
        }

        $stock = $this->inventarioModel->obtenerStockPorItemAlmacen($idItem, $idAlmacen);
        json_response(['ok' => true, 'stock' => $stock]);
    }

    public function kardex(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $filtros = [
            'id_item' => (int) ($_GET['id_item'] ?? 0),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        $movimientos = $this->inventarioModel->obtenerKardex($filtros);
        $items = $this->inventarioModel->listarItems();

        $this->vista('inventario_kardex', [
            'ruta_actual' => 'inventario',
            'movimientos' => $movimientos,
            'items' => $items,
            'filtros' => $filtros,
        ]);
    }

    public function exportar(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $formato = strtolower(trim((string) ($_GET['formato'] ?? 'csv')));
        $filas = $this->inventarioModel->obtenerStock();

        if ($formato === 'pdf') {
            header('Content-Type: text/plain; charset=utf-8');
            header('Content-Disposition: attachment; filename="inventario_stock.pdf"');
            echo "Exportación PDF no disponible aún. Usa CSV o Excel.\n";
            return;
        }

        $filename = $formato === 'excel' ? 'inventario_stock.xls' : 'inventario_stock.csv';
        $separator = $formato === 'excel' ? "\t" : ',';
        $contentType = $formato === 'excel' ? 'application/vnd.ms-excel; charset=utf-8' : 'text/csv; charset=utf-8';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        $headers = ['SKU', 'Producto', 'Almacén', 'Stock actual', 'Stock mínimo', 'Estado'];
        fputcsv($out, $headers, $separator);

        foreach ($filas as $fila) {
            $stock = (float) ($fila['stock_actual'] ?? 0);
            $stockMin = (float) ($fila['stock_minimo'] ?? 0);
            $estado = $stock <= 0 ? 'Agotado' : ($stock <= $stockMin ? 'Crítico' : 'Disponible');

            fputcsv($out, [
                (string) ($fila['sku'] ?? ''),
                (string) ($fila['item_nombre'] ?? ''),
                (string) ($fila['almacen_nombre'] ?? ''),
                number_format($stock, 4, '.', ''),
                number_format($stockMin, 4, '.', ''),
                $estado,
            ], $separator);
        }

        fclose($out);
    }

    private function calcularKpis(array $stockActual): array
    {
        $mapaItems = [];
        $sinStock = 0;
        $critico = 0;
        $porVencer = 0;
        $hoy = new DateTimeImmutable('today');

        foreach ($stockActual as $stock) {
            $idItem = (int) ($stock['id_item'] ?? 0);
            $stockItem = (float) ($stock['stock_actual'] ?? 0);
            $stockMin = (float) ($stock['stock_minimo'] ?? 0);
            if (!isset($mapaItems[$idItem])) {
                $mapaItems[$idItem] = 0.0;
            }
            $mapaItems[$idItem] += $stockItem;

            if ($stockItem <= 0) {
                $sinStock++;
            }

            if ($stockItem > 0 && $stockItem <= $stockMin) {
                $critico++;
            }

            $venc = (string) ($stock['proximo_vencimiento'] ?? '');
            $diasAlerta = (int) ($stock['dias_alerta_vencimiento'] ?? 0);
            if ($venc !== '') {
                $fechaV = DateTimeImmutable::createFromFormat('Y-m-d', $venc);
                if ($fechaV instanceof DateTimeImmutable && $fechaV <= $hoy->modify('+' . max(1, $diasAlerta) . ' days')) {
                    $porVencer++;
                }
            }
        }

        return [
            'total_items' => count($mapaItems),
            'sin_stock' => $sinStock,
            'critico' => $critico,
            'por_vencer' => $porVencer,
        ];
    }

    private function vista(string $rutaVista, array $datos = []): void
    {
        $this->render($rutaVista, $datos);
    }
}
