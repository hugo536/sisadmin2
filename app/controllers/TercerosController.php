<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/TerceroModel.php';

class TercerosController extends Controlador
{
    private TerceroModel $terceroModel;

    public function __construct()
    {
        $this->terceroModel = new TerceroModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        $flash = ['tipo' => '', 'texto' => ''];

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = (string) ($_POST['accion'] ?? '');
            $userId = (int) ($_SESSION['id'] ?? 0);

            try {
                if ($accion === 'crear') {
                    require_permiso('terceros.crear');
                    $data = $this->validarTercero($_POST);
                    $id = $this->terceroModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero registrado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero registrado correctamente.'];
                }

                if ($accion === 'editar') {
                    require_permiso('terceros.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID inválido.');
                    }
                    $data = $this->validarTercero($_POST);
                    if ($this->terceroModel->documentoExiste($data['tipo_documento'], $data['numero_documento'], $id)) {
                        throw new RuntimeException('El documento ya se encuentra registrado.');
                    }
                    $this->terceroModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero actualizado correctamente.'];
                }

                if ($accion === 'eliminar') {
                    require_permiso('terceros.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) {
                        throw new RuntimeException('ID inválido.');
                    }
                    $this->terceroModel->eliminar($id, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero eliminado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero eliminado correctamente.'];
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

        $this->render('terceros', [
            'terceros' => $this->terceroModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'terceros',
        ]);
    }

    private function validarTercero(array $data): array
    {
        $tipo = trim((string) ($data['tipo_documento'] ?? ''));
        $numero = trim((string) ($data['numero_documento'] ?? ''));
        $nombre = trim((string) ($data['nombre_completo'] ?? ''));
        $roles = [
            !empty($data['es_cliente']),
            !empty($data['es_proveedor']),
            !empty($data['es_empleado']),
        ];

        if ($tipo === '' || $numero === '' || $nombre === '') {
            throw new RuntimeException('Tipo de documento, número y nombre completo son obligatorios.');
        }

        if (!in_array(true, $roles, true)) {
            throw new RuntimeException('Seleccione al menos un rol para el tercero.');
        }

        return $data;
    }
}
