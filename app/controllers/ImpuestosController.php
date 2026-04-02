<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/configuracion/ImpuestoModel.php';

class ImpuestosController extends Controlador
{
    private ImpuestoModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ImpuestoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.ver');

        $filtros = [
            'q' => (string) ($_GET['q'] ?? ''),
            'tipo' => (string) ($_GET['tipo'] ?? ''),
            'estado_filtro' => (string) ($_GET['estado_filtro'] ?? 'activos'),
        ];

        $this->render('configuracion/impuestos', [
            'ruta_actual' => 'impuestos/index',
            'registros' => $this->model->listarConFiltros($filtros),
            'filtros' => $filtros,
            'resumen' => $this->model->resumen(),
            'flash' => [
                'tipo' => (string) ($_GET['tipo_msg'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('impuestos');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $porcentaje = (float) ($_POST['porcentaje'] ?? 0);
        $tipo = strtoupper(trim((string) ($_POST['tipo'] ?? 'VENTA')));
        $esDefault = !empty($_POST['es_default']) ? 1 : 0;
        $estado = ((int) ($_POST['estado'] ?? 1)) === 1 ? 1 : 0;
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($codigo === '' || $nombre === '') {
            redirect('impuestos/index?tipo_msg=error&msg=Código y nombre son obligatorios.');
        }
        if (!preg_match('/^[A-Z0-9\-_]{2,20}$/', $codigo)) {
            redirect('impuestos/index?tipo_msg=error&msg=Código inválido (2-20 caracteres A-Z 0-9 - _).');
        }
        if ($porcentaje < 0 || $porcentaje > 100) {
            redirect('impuestos/index?tipo_msg=error&msg=El porcentaje debe estar entre 0 y 100.');
        }
        if (!in_array($tipo, ['VENTA', 'COMPRA', 'AMBOS'], true)) {
            redirect('impuestos/index?tipo_msg=error&msg=Tipo inválido.');
        }
        if ($this->model->existeCodigo($codigo, $id > 0 ? $id : null)) {
            redirect('impuestos/index?tipo_msg=error&msg=El código ya existe.');
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'porcentaje' => round($porcentaje, 4),
            'tipo' => $tipo,
            'es_default' => $esDefault,
            'estado' => $estado,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'user_id' => $userId,
        ];

        $ok = $id > 0 ? $this->model->actualizar($id, $payload) : $this->model->crear($payload);
        if (!$ok) {
            redirect('impuestos/index?tipo_msg=error&msg=No fue posible guardar el impuesto.');
        }

        redirect('impuestos/index?tipo_msg=success&msg=Impuesto guardado correctamente.');
    }

    public function eliminar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('impuestos/index?tipo_msg=error&msg=Registro inválido.');
        }

        if (!$this->model->eliminarLogico($id, $userId)) {
            redirect('impuestos/index?tipo_msg=error&msg=No se pudo eliminar el registro.');
        }

        redirect('impuestos/index?tipo_msg=success&msg=Impuesto eliminado correctamente.');
    }

    public function restaurar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('impuestos/index?tipo_msg=error&msg=Registro inválido.');
        }

        if (!$this->model->restaurar($id, $userId)) {
            redirect('impuestos/index?tipo_msg=error&msg=No se pudo restaurar el registro.');
        }

        redirect('impuestos/index?tipo_msg=success&msg=Impuesto restaurado correctamente.');
    }
}
