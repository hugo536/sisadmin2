<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/ClienteModel.php';

class ClienteController extends Controlador
{
    private ClienteModel $clienteModel;

    public function __construct()
    {
        $this->clienteModel = new ClienteModel();
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
                    $id = $this->clienteModel->crear($data);
                    $respuesta = ['ok' => true, 'mensaje' => 'Cliente registrado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Cliente registrado correctamente.'];
                }

                if ($accion === 'editar') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $data = $this->validarTercero($_POST);
                    $this->clienteModel->actualizar($id, $data);
                    $respuesta = ['ok' => true, 'mensaje' => 'Cliente actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Cliente actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    $id = (int) ($_POST['id'] ?? 0);
                    $this->clienteModel->eliminar($id);
                    $respuesta = ['ok' => true, 'mensaje' => 'Cliente eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Cliente eliminado correctamente.'];
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

        $this->render('clientes', [
            'clientes' => $this->clienteModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'terceros/clientes',
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

}
