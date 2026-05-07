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

        // --- NUEVA LÓGICA PARA MÚLTIPLES ÍTEMS ---
        // 1. Recibimos el valor de la vista, que ahora debería ser un array
        $idItemRaw = $_GET['id_item'] ?? ($_GET['item_id'] ?? []);
        
        // 2. Por seguridad, si llega un dato suelto (no array), lo convertimos a array
        if (!is_array($idItemRaw)) {
            $idItemRaw = [$idItemRaw];
        }
        
        // 3. Limpiamos la lista: convertimos todo a enteros y quitamos los ceros
        $idItemsFiltro = array_filter(array_map('intval', $idItemRaw), function($id) {
            return $id > 0;
        });
        
        // 4. Reindexamos las posiciones del array para evitar huecos lógicos
        $idItemsFiltro = array_values($idItemsFiltro);

        // --- LÓGICA DE FECHAS (Se mantiene igual) ---
        $fechaDesde = trim((string) ($_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_GET['fecha_hasta'] ?? ''));

        if ($fechaDesde === '' && $fechaHasta === '') {
            $hoy = new DateTimeImmutable('today');
            $fechaHasta = $hoy->format('Y-m-d');
            $fechaDesde = $hoy->sub(new DateInterval('P6D'))->format('Y-m-d');
        }

        // Ahora 'id_item' envía una lista de IDs en lugar de uno solo
        $filtros = [
            'id_item' => $idItemsFiltro,
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