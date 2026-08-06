<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaIngresoModel.php';

class TesoreriaIngresosController extends Controlador
{
    private TesoreriaIngresoModel $model;
    private TesoreriaCuentaModel $cuentaModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new TesoreriaIngresoModel();
        $this->cuentaModel = new TesoreriaCuentaModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        $fechaDesdeDefault = date('Y-m-d', strtotime('-30 days'));
        $fechaHastaDefault = date('Y-m-d');
        $filtros = [
            'q' => trim((string) ($_GET['q'] ?? '')),
            'cuenta' => trim((string) ($_GET['cuenta'] ?? '')),
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? $fechaDesdeDefault)),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? $fechaHastaDefault)),
        ];

        $this->render('tesoreria/ingresos_extraordinarios', [
            'ruta_actual' => 'tesoreria/ingresos',
            'ingresos' => $this->model->listar($filtros),
            'cuentas' => $this->cuentaModel->listarActivas(),
            'filtros' => $filtros,
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['ok' => false, 'mensaje' => 'Método inválido.'], 405);
            return;
        }

        try {
            $id = $this->model->guardar($_POST, (int) ($_SESSION['id'] ?? 0));
            json_response(['ok' => true, 'mensaje' => 'Ingreso registrado correctamente.', 'id' => $id]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function anular(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['ok' => false, 'mensaje' => 'Método inválido.'], 405);
            return;
        }

        try {
            $this->model->anular((int) ($_POST['id'] ?? 0), (int) ($_SESSION['id'] ?? 0));
            json_response(['ok' => true, 'mensaje' => 'Ingreso anulado correctamente.']);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }
}
