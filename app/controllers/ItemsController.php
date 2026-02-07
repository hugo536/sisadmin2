<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
// CAMBIO: Apunta al archivo en plural
require_once BASE_PATH . '/app/models/ItemsModel.php';

class ItemsController extends Controlador
{
    // CAMBIO: Propiedad en plural
    private ItemsModel $itemsModel;

    public function __construct()
    {
        // CAMBIO: Instancia la clase en plural
        $this->itemsModel = new ItemsModel(); 
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'datatable') {
            json_response($this->itemsModel->datatable());
            return;
        }

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'crear') {
                    require_permiso('items.crear');
                    $data = $this->validarItem($_POST, false);
                    
                    if ($data['sku'] !== '' && $this->itemsModel->skuExiste($data['sku'])) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }
                    
                    $nuevoId = $this->itemsModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem creado correctamente.', 'id' => $nuevoId];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem creado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('items.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $data = $this->validarItem($_POST, true);
                    $actual = $this->itemsModel->obtener($id);
                    
                    if ($actual === []) throw new RuntimeException('El ítem no existe.');

                    $skuIngresado = trim((string) ($data['sku'] ?? ''));
                    if ($skuIngresado !== '' && $skuIngresado !== (string) ($actual['sku'] ?? '')) {
                        throw new RuntimeException('El SKU es inmutable y no puede modificarse.');
                    }

                    $this->itemsModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Ítem actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Ítem actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('items.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');

                    $this->itemsModel->eliminar($id, $userId);
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

        $this->render('items', [
            'items' => $this->itemsModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'items', 
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