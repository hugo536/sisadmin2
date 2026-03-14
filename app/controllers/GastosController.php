<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/gastos/GastoConceptoModel.php';
require_once BASE_PATH . '/app/models/gastos/GastoRegistroModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';
require_once BASE_PATH . '/app/models/gastos/GastoProveedorModel.php';

class GastosController extends Controlador
{
    private GastoConceptoModel $conceptoModel;
    private GastoRegistroModel $registroModel;
    private CentroCostoModel $centroCostoModel;
    private GastoProveedorModel $proveedorModel;

    public function __construct()
    {
        parent::__construct();
        $this->conceptoModel = new GastoConceptoModel();
        $this->registroModel = new GastoRegistroModel();
        $this->centroCostoModel = new CentroCostoModel();
        $this->proveedorModel = new GastoProveedorModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');
        redirect('gastos/conceptos');
    }

    public function conceptos(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        $filtros = [
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];

        $this->render('gastos/conceptos_gasto', [
            'ruta_actual' => 'gastos/conceptos',
            'registros' => $this->conceptoModel->listar($filtros),
            'filtros' => $filtros,
            'centrosCosto' => $this->centroCostoModel->listarActivos(),
            'codigoSugerido' => $this->conceptoModel->siguienteCodigo(),
        ]);
    }

    public function actualizar_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $payload = [
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
                'es_recurrente' => isset($_POST['es_recurrente']) ? 1 : 0,
                'dia_vencimiento' => (int) ($_POST['dia_vencimiento'] ?? 0),
                'dias_anticipacion' => (int) ($_POST['dias_anticipacion'] ?? 0),
            ];

            $this->conceptoModel->actualizar($id, $payload, $this->uid());
            redirect('gastos/conceptos?ok=1');
        } catch (Throwable $e) {
            redirect('gastos/conceptos?error=' . urlencode($e->getMessage()));
        }
    }

    public function toggle_estado_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $estado = (int) ($_POST['estado'] ?? 0) === 1 ? 1 : 0;
            $this->conceptoModel->cambiarEstado($id, $estado, $this->uid());
            redirect('gastos/conceptos?ok=1');
        } catch (Throwable $e) {
            redirect('gastos/conceptos?error=' . urlencode($e->getMessage()));
        }
    }

    public function eliminar_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->conceptoModel->eliminar($id, $this->uid());
            redirect('gastos/conceptos?ok=1');
        } catch (Throwable $e) {
            redirect('gastos/conceptos?error=' . urlencode($e->getMessage()));
        }
    }

    public function guardar_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $payload = [
                'codigo' => trim((string) ($_POST['codigo'] ?? '')),
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
                'es_recurrente' => isset($_POST['es_recurrente']) ? 1 : 0,
                'dia_vencimiento' => (int) ($_POST['dia_vencimiento'] ?? 0),
                'dias_anticipacion' => (int) ($_POST['dias_anticipacion'] ?? 0),
            ];
            $this->conceptoModel->crear($payload, $this->uid());
            redirect('gastos/conceptos?ok=1');
        } catch (Throwable $e) {
            redirect('gastos/conceptos?error=' . urlencode($e->getMessage()));
        }
    }

    public function registros(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        $filtros = [
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? '')),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? '')),
        ];

        $this->render('gastos/registro_gastos', [
            'ruta_actual' => 'gastos/registros',
            'registros' => $this->registroModel->listar($filtros),
            'filtros' => $filtros,
            'conceptos' => $this->conceptoModel->listarActivos(),
            'proveedores' => $this->proveedorModel->listarActivos(),
        ]);
    }

    public function guardar_registro(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/registros');
        }

        try {
            $payload = [
                'fecha' => trim((string) ($_POST['fecha'] ?? date('Y-m-d'))),
                'id_proveedor' => (int) ($_POST['id_proveedor'] ?? 0),
                'id_concepto' => (int) ($_POST['id_concepto'] ?? 0),
                'monto' => (float) ($_POST['monto'] ?? 0),
                'impuesto_tipo' => trim((string) ($_POST['impuesto_tipo'] ?? 'NINGUNO')),
            ];

            $this->registroModel->crear($payload, $this->uid());
            redirect('gastos/registros?ok=1');
        } catch (Throwable $e) {
            redirect('gastos/registros?error=' . urlencode($e->getMessage()));
        }
    }

    private function uid(): int
    {
        return (int)($_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 1);
    }
}
