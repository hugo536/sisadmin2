<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ProductoModel.php';

class ProductoController extends Controlador
{
    private ProductoModel $productoModel;

    public function __construct()
    {
        $this->productoModel = new ProductoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'datatable') {
            json_response($this->productoModel->datatable());
            return;
        }

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'crear') {
                    require_permiso('items.crear');
                    $data = $this->validarProducto($_POST, false);
                    if ($data['sku'] !== '' && $this->productoModel->skuExiste($data['sku'])) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }
                    $nuevoId = $this->productoModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Producto creado correctamente.', 'id' => $nuevoId];
                    $flash = ['tipo' => 'success', 'texto' => 'Producto creado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('items.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID inválido.');
                    }
                    $data = $this->validarProducto($_POST, true);
                    $actual = $this->productoModel->obtener($id);
                    if ($actual === []) {
                        throw new RuntimeException('El ítem no existe.');
                    }
                    $skuIngresado = trim((string) ($data['sku'] ?? ''));
                    if ($skuIngresado !== '' && $skuIngresado !== (string) ($actual['sku'] ?? '')) {
                        throw new RuntimeException('El SKU es inmutable y no puede modificarse.');
                    }
                    $this->productoModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Producto actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Producto actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('items.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID inválido.');
                    }
                    $this->productoModel->eliminar($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Producto eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Producto eliminado correctamente.'];
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

        $this->render('productos', [
            'items' => $this->productoModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'items',
        ]);
    }

    private function validarProducto(array $data, bool $esEdicion): array
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $tipo = trim((string) ($data['tipo_item'] ?? ''));

        if ($nombre === '' || $tipo === '') {
            throw new RuntimeException('Nombre y tipo de ítem son obligatorios.');
        }

        if (!$esEdicion) {
            return $data;
        }

        return $data;
    }

}
