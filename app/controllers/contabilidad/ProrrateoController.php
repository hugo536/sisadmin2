<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ProrrateoModel.php';
// 👇 NUEVO: Importamos el modelo de Centros de Costo para reutilizarlo
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php'; 

class ProrrateoController extends Controlador
{
    private ProrrateoModel $model;
    private CentroCostoModel $centroCostoModel; // 👈 NUEVO

    public function __construct()
    {
        parent::__construct();
        $this->model = new ProrrateoModel();
        $this->centroCostoModel = new CentroCostoModel(); // 👈 NUEVO
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');

        $this->render('contabilidad/prorrateos', [
            'ruta_actual' => 'contabilidad/prorrateos',
            'reglas' => $this->model->listar(),
            // 👇 NUEVO: Usamos el modelo correcto para traer los centros activos
            'centros' => $this->centroCostoModel->listarActivos(),
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
            // 👇 NUEVO: Validar campos principales de la cabecera
            $nombre = trim((string)($_POST['nombre'] ?? ''));
            $centroOrigenId = (int)($_POST['centro_origen_id'] ?? 0);

            if ($nombre === '') {
                throw new RuntimeException('El nombre de la regla es obligatorio.');
            }
            if ($centroOrigenId <= 0) {
                throw new RuntimeException('Debe seleccionar un Centro de Costo de origen.');
            }

            $detallesRaw = $_POST['detalles'] ?? '[]';
            $detalles = is_array($detallesRaw)
                ? $detallesRaw
                : json_decode((string)$detallesRaw, true, 512, JSON_THROW_ON_ERROR);

            if (!is_array($detalles) || count($detalles) === 0) {
                throw new RuntimeException('Debe agregar al menos un centro de costo destino.');
            }

            $total = 0.0;
            $destinosUsados = []; // 👈 NUEVO: Arreglo para rastrear duplicados

            foreach ($detalles as $d) {
                $pct = (float)($d['porcentaje'] ?? 0);
                $destinoId = (int)($d['centro_destino_id'] ?? 0); // Asumiendo que así se llama tu campo

                if ($pct <= 0) {
                    throw new RuntimeException('Todos los porcentajes deben ser mayores a 0.');
                }
                
                // 👇 NUEVO: Validaciones lógicas de negocio
                if ($destinoId <= 0) {
                    throw new RuntimeException('Debe seleccionar un Centro de Costo válido en todas las filas.');
                }
                if ($destinoId === $centroOrigenId) {
                    throw new RuntimeException('El centro de costo destino no puede ser igual al de origen.');
                }
                if (in_array($destinoId, $destinosUsados, true)) {
                    throw new RuntimeException('No puede seleccionar el mismo centro de costo destino más de una vez.');
                }
                
                $destinosUsados[] = $destinoId;
                $total += $pct;
            }

            if (abs($total - 100.0) > 0.01) {
                throw new RuntimeException('La suma de porcentajes debe ser exactamente 100%.');
            }

            $payload = [
                'id' => (int)($_POST['id'] ?? 0),
                'nombre' => $nombre,
                'centro_origen_id' => $centroOrigenId,
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