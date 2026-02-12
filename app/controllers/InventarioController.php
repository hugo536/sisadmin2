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

        // Obtiene el stock general corregido (ya filtra por lotes de almacén en el modelo)
        $stockActual = $this->inventarioModel->obtenerStock();

        $datos = [
            'ruta_actual' => 'inventario',
            'stockActual' => $stockActual,
            'almacenes' => $this->almacenModel->listarActivos(),
            // 'items' podría ser pesado si son miles, mejor cargar vía AJAX o paginado,
            // pero si son pocos está bien dejarlo así.
            'items' => $this->inventarioModel->listarItems(),
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
            
            // Corrección: Convertir vacíos a NULL o mantener limpio
            $lote = trim((string) ($_POST['lote'] ?? ''));
            $fechaPost = trim((string) ($_POST['fecha_vencimiento'] ?? ''));
            $fechaVencimiento = $fechaPost !== '' ? $fechaPost : null;
            
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

            // Lógica de asignación de almacenes
            if ($tipo === 'TRF') {
                // En Transferencia, id_almacen del post suele ser el ORIGEN
                $datos['id_almacen_origen'] = $idAlmacen;
                $datos['id_almacen_destino'] = (int) ($_POST['id_almacen_destino'] ?? 0);
            } elseif (in_array($tipo, ['AJ-', 'CON'], true)) {
                // En Salidas, id_almacen es ORIGEN
                $datos['id_almacen_origen'] = $idAlmacen;
            } else {
                // En Entradas (INI, AJ+), id_almacen es DESTINO
                $datos['id_almacen_destino'] = $idAlmacen;
            }

            $idMovimiento = $this->inventarioModel->registrarMovimiento($datos);

            json_response([
                'ok' => true,
                'mensaje' => 'Movimiento registrado correctamente.',
                'id' => $idMovimiento,
            ]);
        } catch (Throwable $e) {
            // Loguear error real internamente si tienes logger
            // error_log($e->getMessage()); 
            json_response([
                'ok' => false,
                'mensaje' => $e->getMessage(),
            ], 400);
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

    // --- NUEVO MÉTODO IMPORTANTE ---
    // Permite al frontend llenar el select de "Lotes" cuando eliges un producto y almacén
    public function buscarLotes(): void 
    {
        AuthMiddleware::handle();
        
        if (!es_ajax()) {
            json_response(['ok' => false], 400);
            return;
        }

        $idItem = (int) ($_GET['id_item'] ?? 0);
        $idAlmacen = (int) ($_GET['id_almacen'] ?? 0);

        if ($idItem <= 0 || $idAlmacen <= 0) {
            json_response(['ok' => true, 'lotes' => []]);
            return;
        }

        // NOTA: Debes agregar este método pequeño a tu InventarioModel (ver abajo)
        $lotes = $this->inventarioModel->listarLotesDisponibles($idItem, $idAlmacen);
        
        json_response(['ok' => true, 'lotes' => $lotes]);
    }

    public function stockItem(): void
    {
        AuthMiddleware::handle();
        
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
        // Si necesitas listar items para el filtro del kardex
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
            echo "Exportación PDF pendiente de implementación.";
            return;
        }

        $filename = $formato === 'excel' ? 'inventario_stock.xls' : 'inventario_stock.csv';
        $separator = $formato === 'excel' ? "\t" : ',';
        $contentType = $formato === 'excel' ? 'application/vnd.ms-excel; charset=utf-8' : 'text/csv; charset=utf-8';

        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'wb');
        // Encabezados deben coincidir con tus datos
        $headers = ['SKU', 'Producto', 'Almacén', 'Lote', 'Stock actual', 'Stock mínimo', 'Estado', 'Vencimiento'];
        fputcsv($out, $headers, $separator);

        foreach ($filas as $fila) {
            $stock = (float) ($fila['stock_actual'] ?? 0);
            $stockMin = (float) ($fila['stock_minimo'] ?? 0);
            $estado = $stock <= 0 ? 'Agotado' : ($stock <= $stockMin ? 'Crítico' : 'Disponible');

            // Aseguramos que usamos los alias correctos del Modelo
            fputcsv($out, [
                (string) ($fila['sku'] ?? ''),
                (string) ($fila['item_nombre'] ?? ''),
                (string) ($fila['almacen_nombre'] ?? ''),
                (string) ($fila['lote_actual'] ?? '-'), // Alias corregido en el Modelo anterior
                number_format($stock, 4, '.', ''),
                number_format($stockMin, 4, '.', ''),
                $estado,
                (string) ($fila['proximo_vencimiento'] ?? '-'),
            ], $separator);
        }

        fclose($out);
    }

    private function vista(string $rutaVista, array $datos = []): void
    {
        $this->render($rutaVista, $datos);
    }
}