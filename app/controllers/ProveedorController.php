<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/modelos/ProveedorModel.php';

class ProveedorController extends Controlador
{
    private ProveedorModel $proveedorModel;

    public function __construct()
    {
        $this->proveedorModel = new ProveedorModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');

            try {
                if ($accion === 'crear') {
                    $data = $this->validarTercero($_POST);
                    $id = $this->proveedorModel->crear($data);
                    $respuesta = ['ok' => true, 'mensaje' => 'Proveedor registrado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Proveedor registrado correctamente.'];
                }

                if ($accion === 'editar') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $data = $this->validarTercero($_POST);
                    $this->proveedorModel->actualizar($id, $data);
                    $respuesta = ['ok' => true, 'mensaje' => 'Proveedor actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Proveedor actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $this->proveedorModel->eliminar($id);
                    $respuesta = ['ok' => true, 'mensaje' => 'Proveedor eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Proveedor eliminado correctamente.'];
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

        $this->renderVista('proveedores', [
            'proveedores' => $this->proveedorModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'terceros/proveedores',
        ]);
    }

    private function validarTercero(array $data): array
    {
        $tipo = trim((string) ($data['tipo_documento'] ?? ''));
        $numero = trim((string) ($data['numero_documento'] ?? ''));
        $nombre = trim((string) ($data['nombre_completo'] ?? ''));

        if ($tipo === '' || $numero === '' || $nombre === '') {
            throw new RuntimeException('Tipo de documento, número y nombre completo son obligatorios.');
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