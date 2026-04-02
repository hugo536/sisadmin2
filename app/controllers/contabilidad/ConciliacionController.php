<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ConciliacionBancariaModel.php';
require_once BASE_PATH . '/app/models/UsuariosModel.php';

class ConciliacionController extends Controlador
{
    private ConciliacionBancariaModel $model;
    private UsuariosModel $usuariosModel;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ConciliacionBancariaModel();
        $this->usuariosModel = new UsuariosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.conciliacion.gestionar');
        $id = (int)($_GET['id'] ?? 0);
        $this->render('contabilidad/conciliaciones', [
            'ruta_actual' => 'conciliacion/index',
            'conciliaciones' => $this->model->listar(),
            'cuentas' => $this->model->cuentasBancarias(),
            'detalle' => $id > 0 ? $this->model->obtenerDetalle($id) : [],
            'idConciliacionActiva' => $id,
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.conciliacion.gestionar');
        try {
            $id = $this->model->guardar($_POST, (int)($_SESSION['id'] ?? 0));
            $this->logEvento('conciliacion_creada', 'Conciliación bancaria registrada ID ' . $id);
            redirect('conciliacion/index?id=' . $id . '&ok=1');
        } catch (Throwable $e) {
            redirect('conciliacion/index?error=' . urlencode($e->getMessage()));
        }
    }

    public function importar(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.conciliacion.gestionar');
        try {
            $id = (int)($_POST['id_conciliacion'] ?? 0);
            $n = $this->model->importarCsv($id, $_FILES['archivo'] ?? [], (int)($_SESSION['id'] ?? 0));
            $this->logEvento('conciliacion_importacion', 'Movimientos importados en conciliación ' . $id . ': ' . $n);
            redirect('conciliacion/index?id=' . $id . '&ok=1');
        } catch (Throwable $e) {
            redirect('conciliacion/index?error=' . urlencode($e->getMessage()));
        }
    }

    public function marcar_detalle(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.conciliacion.gestionar');
        $idConc = (int)($_POST['id_conciliacion'] ?? 0);
        $idDet = (int)($_POST['id_detalle'] ?? 0);
        $conciliado = (int)($_POST['conciliado'] ?? 0) === 1;
        $this->model->marcarDetalle($idDet, $conciliado, (int)($_SESSION['id'] ?? 0));
        $this->logEvento('conciliacion_marcado', 'Detalle conciliación ' . $idDet . ' marcado=' . ($conciliado ? '1' : '0'));
        redirect('conciliacion/index?id=' . $idConc);
    }

    public function cerrar(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.conciliacion.gestionar');
        try {
            $id = (int)($_POST['id_conciliacion'] ?? 0);
            $this->model->cerrarSiCorresponde($id, (int)($_SESSION['id'] ?? 0));
            $this->logEvento('conciliacion_cierre', 'Conciliación cerrada ID ' . $id);
            redirect('conciliacion/index?id=' . $id . '&ok=1');
        } catch (Throwable $e) {
            redirect('conciliacion/index?error=' . urlencode($e->getMessage()));
        }
    }

    private function logEvento(string $evento, string $descripcion): void
    {
        $this->usuariosModel->insertar_bitacora(
            (int)($_SESSION['id'] ?? 0),
            $evento,
            $descripcion,
            (string)($_SERVER['REMOTE_ADDR'] ?? ''),
            (string)($_SERVER['HTTP_USER_AGENT'] ?? 'CLI')
        );
    }
}
