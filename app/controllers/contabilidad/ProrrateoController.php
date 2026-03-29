<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ProrrateoModel.php';

class ProrrateoController extends Controlador
{
    private ProrrateoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ProrrateoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');

        $this->render('contabilidad/prorrateos', [
            'ruta_actual' => 'contabilidad/prorrateos',
            'reglas' => $this->model->listar(),
            'centros' => $this->model->listarCentrosActivos(),
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');
        header('Content-Type: application/json; charset=utf-8');

        $tokenEnviado = (string)($_POST['csrf_token'] ?? '');
        $tokenGuardado = (string)($_SESSION['csrf_token'] ?? '');

        if ($tokenEnviado === '' || !hash_equals($tokenGuardado, $tokenEnviado)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Sesión caducada o token de seguridad inválido. Por favor, recarga la página.',
            ]);
            exit;
        }

        try {
            $detallesRaw = $_POST['detalles'] ?? '[]';
            $detalles = is_array($detallesRaw)
                ? $detallesRaw
                : json_decode((string)$detallesRaw, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($detalles) || count($detalles) === 0) {
                throw new RuntimeException('Debe agregar al menos un centro de costo destino.');
            }

            $total = 0.0;
            foreach ($detalles as $d) {
                $pct = (float)($d['porcentaje'] ?? 0);
                if ($pct <= 0) {
                    throw new RuntimeException('Todos los porcentajes deben ser mayores a 0.');
                }
                $total += $pct;
            }

            if (abs($total - 100.0) > 0.01) {
                throw new RuntimeException('La suma de porcentajes debe ser exactamente 100%.');
            }

            $payload = [
                'id' => (int)($_POST['id'] ?? 0),
                'nombre' => trim((string)($_POST['nombre'] ?? '')),
                'centro_origen_id' => (int)($_POST['centro_origen_id'] ?? 0),
                'estado' => (int)($_POST['estado'] ?? 1),
                'detalles' => $detalles,
            ];

            $this->model->guardar($payload, $this->uid());

            echo json_encode([
                'status' => 'success',
                'message' => 'La regla de prorrateo se guardó correctamente.',
            ]);
            exit;
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
            exit;
        }
    }

    private function uid(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }
}
