<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ItemModel.php';

class ItemController extends Controlador
{
    private ItemModel $itemModel; // Cambiado de productoModel a itemModel

    public function __construct()
    {
        // IMPORTANTE: Aquí instanciamos ItemModel, no ProductoModel
        $this->itemModel = new ItemModel(); 
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('item.ver');

        // Manejo de DataTable (AJAX)
        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'datatable') {
            json_response($this->itemModel->datatable());
            return;
        }

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                // --- ACCIÓN: CREAR ---
                if ($accion === 'crear') {
                    require_permiso('item.crear');
                    $data = $this->validarItem($_POST, false);
                    
                    if ($data['sku'] !== '' && $this->itemModel->skuExiste($data['sku'])) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }
                    
                    $nuevoId = $this->itemModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem creado correctamente.', 'id' => $nuevoId];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem creado correctamente.'];
                }

                // --- ACCIÓN: EDITAR ---
                if ($accion === 'editar') {
                    require_permiso('item.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $data = $this->validarItem($_POST, true);
                    $actual = $this->itemModel->obtener($id);
                    
                    if ($actual === []) throw new RuntimeException('El ítem no existe.');

                    $skuIngresado = trim((string) ($data['sku'] ?? ''));
                    if ($skuIngresado !== '' && $skuIngresado !== (string) ($actual['sku'] ?? '')) {
                        throw new RuntimeException('El SKU es inmutable y no puede modificarse.');
                    }

                    $this->itemModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem actualizado correctamente.'];
                }

                // --- ACCIÓN: ELIMINAR ---
                if ($accion === 'eliminar') {
                    require_permiso('item.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $this->itemModel->eliminar($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem eliminado correctamente.'];
                }

                if (isset($respuesta) && es_ajax()) {
                    json_response($respuesta);
                    return;
                }
            } catch (Throwable $e) {
                if (es_ajax()) {
                    json_response(['ok' => false, 'mensaje' => $e->getMessage()], 400);
                    return;
                }
                $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
            }
        }

        // Renderizado de la vista
        $this->render('item', [ // Si renombras tu vista a 'item', cambia esto también
            'item' => $this->itemModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'item', 
        ]);
    }

    private function validarItem(array $data, bool $esEdicion): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $tipo = trim((string) ($data['tipo_item'] ?? ''));

        if ($nombre === '' || $tipo === '') {
            throw new RuntimeException('Nombre y tipo de ítem son obligatorios.');
        }

        return $data;
    }
}