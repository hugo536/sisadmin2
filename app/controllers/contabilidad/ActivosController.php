<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ActivoFijoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaCuentaModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class ActivosController extends Controlador
{
    private ActivoFijoModel $model;
    private ContaCuentaModel $cuentas;
    private CentroCostoModel $centrosCosto;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ActivoFijoModel();
        $this->cuentas = new ContaCuentaModel();
        $this->centrosCosto = new CentroCostoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('activos.gestionar');

        $this->render('contabilidad/activos_fijos', [
            'ruta_actual' => 'activos/index',
            'activos' => $this->model->listar(),
            'cuentas' => $this->cuentas->listarMovimientoActivas(),
            'centrosCosto' => $this->centrosCosto->listar(), // Enviamos los centros a la vista
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('activos.gestionar');
        try {
            $this->model->guardar($_POST, (int)($_SESSION['id'] ?? 0));
            redirect('activos/index?ok=1');
        } catch (Throwable $e) {
            redirect('activos/index?error=' . urlencode($e->getMessage()));
        }
    }
}