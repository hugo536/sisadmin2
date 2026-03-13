<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/inventario/InventarioKardexModel.php';

class InventarioKardexController extends Controlador
{
    private InventarioKardexModel $inventarioKardexModel;

    public function __construct()
    {
        $this->inventarioKardexModel = new InventarioKardexModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('inventario.ver');

        $idItemFiltro = (int) ($_GET['id_item'] ?? ($_GET['item_id'] ?? 0));
        $filtros = [
            'id_item' => $idItemFiltro,
            'lote' => trim((string) ($_GET['lote'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        $movimientos = $this->inventarioKardexModel->obtenerKardex($filtros);
        $items = $this->inventarioKardexModel->listarItems();

        $this->vista('inventario/inventario_kardex', [
            'ruta_actual' => 'inventario',
            'movimientos' => $movimientos,
            'items' => $items,
            'filtros' => $filtros,
        ]);
    }
}
