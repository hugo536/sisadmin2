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
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));

        if ($fechaDesde === '' && $fechaHasta === '') {
            $hoy = new DateTimeImmutable('today');
            $fechaHasta = $hoy->format('Y-m-d');
            $fechaDesde = $hoy->sub(new DateInterval('P6D'))->format('Y-m-d');
        }

        $filtros = [
            'id_item' => $idItemFiltro,
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
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
