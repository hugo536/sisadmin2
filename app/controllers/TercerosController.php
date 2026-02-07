<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
// CAMBIO: Apunta al archivo en plural
require_once BASE_PATH . '/app/models/TercerosModel.php';

class TercerosController extends Controlador
{
    // CAMBIO: Propiedad en plural
    private TercerosModel $tercerosModel;

    public function __construct()
    {
        // CAMBIO: Instancia la clase en plural
        $this->tercerosModel = new TercerosModel();
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
                // Validación AJAX de documento duplicado
                if (es_ajax() && $accion === 'validar_documento') {
                    require_permiso('terceros.crear');
                    $tipoDoc = trim((string) ($_POST['tipo_documento'] ?? ''));
                    $numeroDoc = trim((string) ($_POST['numero_documento'] ?? ''));
                    $excludeId = isset($_POST['exclude_id']) ? (int) $_POST['exclude_id'] : null;
                    
                    $existe = $tipoDoc !== '' && $numeroDoc !== ''
                        ? $this->tercerosModel->documentoExiste($tipoDoc, $numeroDoc, $excludeId)
                        : false;
                    
                    json_response(['ok' => true, 'existe' => $existe]);
                    return;
                }

                // Cambio de estado AJAX (Switch)
                if (es_ajax() && $accion === 'toggle_estado') {
                    require_permiso('terceros.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    $estado = (int) ($_POST['estado'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
                    $this->tercerosModel->actualizarEstado($id, $estado, $userId);
                    json_response(['ok' => true, 'mensaje' => 'Estado actualizado.']);
                    return;
                }

                // --- ACCIÓN: CREAR ---
                if ($accion === 'crear') {
                    require_permiso('terceros.crear');
                    $data = $this->validarTercero($_POST);
                    // Verificación extra en el backend
                    if ($this->tercerosModel->documentoExiste($data['tipo_documento'], $data['numero_documento'])) {
                        throw new RuntimeException('El documento ya se encuentra registrado.');
                    }
                    $id = $this->tercerosModel->crear($data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero registrado correctamente.', 'id' => $id];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero registrado correctamente.'];
                }

                // --- ACCIÓN: EDITAR ---
                if ($accion === 'editar') {
                    require_permiso('terceros.editar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
                    $data = $this->validarTercero($_POST);
                    if ($this->tercerosModel->documentoExiste($data['tipo_documento'], $data['numero_documento'], $id)) {
                        throw new RuntimeException('El documento ya se encuentra registrado.');
                    }
                    
                    $this->tercerosModel->actualizar($id, $data, $userId);
                    $respuesta = ['ok' => true, 'mensaje' => 'Tercero actualizado correctamente.'];
                    $flash = ['tipo' => 'success', 'texto' => 'Tercero actualizado correctamente.'];
                }

                // --- ACCIÓN: ELIMINAR ---
                if ($accion === 'eliminar') {
                    require_permiso('terceros.eliminar');
                    $id = (int) ($_POST['id'] ?? 0);
                    if ($id <= 0) throw new RuntimeException('ID inválido.');
                    
                    $this->tercerosModel->eliminar($id, $userId);
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
            'terceros' => $this->tercerosModel->listar(),
            'flash' => $flash,
            'ruta_actual' => 'tercero', // Mantenemos singular si así está en tu sidebar
        ]);
    }

    private function validarTercero(array $data): array
    {
        $tipo = trim((string) ($data['tipo_documento'] ?? ''));
        // Solo eliminamos espacios al inicio/final, permitimos guiones si es pasaporte, pero mejor sanitizar solo alfanuméricos para DNI/RUC
        $numero = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['numero_documento'] ?? '')));
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

        $data['numero_documento'] = $numero; // Guardamos el limpio

        return $data;
    }
}