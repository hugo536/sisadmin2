<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/modelos/ProductoModel.php';

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

        if (es_ajax() && (string) ($_GET['accion'] ?? '') === 'datatable') {
            json_response($this->productoModel->datatable());
            return;
        }

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');

            try {
                if ($accion === 'crear') {
                    $data = $this->validarProducto($_POST);
                    if ($this->productoModel->skuExiste($data['sku'])) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }
                    $nuevoId = $this->productoModel->crear($data);
                    $respuesta = ['ok' => true, 'mensaje' => 'Producto creado correctamente.', 'id' => $nuevoId];
                    $flash = ['tipo' => 'success', 'texto' => 'Producto creado correctamente.'];
                }

                if ($accion === 'editar') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $data = $this->validarProducto($_POST);
                    if ($this->productoModel->skuExiste($data['sku'], $id)) {
                        throw new RuntimeException('El SKU ya se encuentra registrado.');
                    }
                    $this->productoModel->actualizar($id, $data);
                    $respuesta = ['ok' => true, 'mensaje' => 'Producto actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Producto actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $this->productoModel->eliminar($id);
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

        $this->renderVista('productos', [
            'items' => $this->productoModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'items',
        ]);
    }

    private function validarProducto(array $data): array
    {
        $sku = trim((string) ($data['sku'] ?? ''));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $tipo = trim((string) ($data['tipo_item'] ?? ''));

        if ($sku === '' || $nombre === '' || $tipo === '') {
            throw new RuntimeException('SKU, nombre y tipo de ítem son obligatorios.');
        }

        return $data;
    }

    private function renderVista(string $rutaVista, array $datos = []): void
    {
        $archivoVista = BASE_PATH . '/app/vistas/' . $rutaVista . '.php';

        if (!is_readable($archivoVista)) {
            die('Error: No se encontró la vista en: ' . $archivoVista);
        }

        $configEmpresa = $this->obtenerConfigEmpresa();
        extract(array_merge($datos, ['configEmpresa' => $configEmpresa]));
        $vista = $archivoVista;

        require BASE_PATH . '/app/views/layout.php';
    }

    private function obtenerConfigEmpresa(): array
    {
        if (isset($_SESSION['config_empresa']) && is_array($_SESSION['config_empresa'])) {
            return $_SESSION['config_empresa'];
        }

        $empresaModelPath = BASE_PATH . '/app/models/EmpresaModel.php';
        if (!is_readable($empresaModelPath)) {
            return [];
        }

        require_once $empresaModelPath;

        try {
            $configEmpresa = (new EmpresaModel())->obtenerConfigActiva();
            $_SESSION['config_empresa'] = $configEmpresa;
            return $configEmpresa;
        } catch (Throwable $e) {
            return [];
        }
    }
}