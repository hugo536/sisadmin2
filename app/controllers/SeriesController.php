<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/configuracion/SerieModel.php';

class SeriesController extends Controlador
{
    private SerieModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new SerieModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.ver');

        $filtros = [
            'q' => (string) ($_GET['q'] ?? ''),
            'modulo' => (string) ($_GET['modulo'] ?? ''),
            'estado_filtro' => (string) ($_GET['estado_filtro'] ?? 'activos'),
        ];

        $this->render('configuracion/series', [
            'ruta_actual' => 'series/index',
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
            redirect('series');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $modulo = strtoupper(trim((string) ($_POST['modulo'] ?? 'VENTAS')));
        $tipoDocumento = strtoupper(trim((string) ($_POST['tipo_documento'] ?? 'PEDIDO')));
        $codigoSerie = strtoupper(trim((string) ($_POST['codigo_serie'] ?? '')));
        $prefijo = strtoupper(trim((string) ($_POST['prefijo'] ?? '')));
        $correlativo = (int) ($_POST['correlativo_actual'] ?? 0);
        $longitud = (int) ($_POST['longitud_correlativo'] ?? 6);
        $predeterminada = !empty($_POST['predeterminada']) ? 1 : 0;
        $estado = ((int) ($_POST['estado'] ?? 1)) === 1 ? 1 : 0;
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
        $userId = (int) ($_SESSION['id'] ?? 0);

        if (!in_array($modulo, ['VENTAS', 'COMPRAS'], true)) {
            redirect('series/index?tipo_msg=error&msg=Módulo inválido.');
        }
        if ($tipoDocumento === '' || $codigoSerie === '' || $prefijo === '') {
            redirect('series/index?tipo_msg=error&msg=Tipo documento, serie y prefijo son obligatorios.');
        }
        if (!preg_match('/^[A-Z0-9\-_]{2,10}$/', $codigoSerie)) {
            redirect('series/index?tipo_msg=error&msg=Serie inválida (2-10 caracteres A-Z 0-9 - _).');
        }
        if (!preg_match('/^[A-Z0-9\-]{2,20}$/', $prefijo)) {
            redirect('series/index?tipo_msg=error&msg=Prefijo inválido (2-20 caracteres A-Z 0-9 -).');
        }
        if ($correlativo < 0 || $longitud < 3 || $longitud > 12) {
            redirect('series/index?tipo_msg=error&msg=Correlativo o longitud inválidos.');
        }
        if ($this->model->existeSerie($modulo, $tipoDocumento, $codigoSerie, $id > 0 ? $id : null)) {
            redirect('series/index?tipo_msg=error&msg=Ya existe esa serie para el documento.');
        }

        $payload = [
            'modulo' => $modulo,
            'tipo_documento' => $tipoDocumento,
            'codigo_serie' => $codigoSerie,
            'prefijo' => $prefijo,
            'correlativo_actual' => $correlativo,
            'longitud_correlativo' => $longitud,
            'predeterminada' => $predeterminada,
            'estado' => $estado,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'user_id' => $userId,
        ];

        $ok = $id > 0 ? $this->model->actualizar($id, $payload) : $this->model->crear($payload);
        if (!$ok) {
            redirect('series/index?tipo_msg=error&msg=No fue posible guardar la serie.');
        }

        redirect('series/index?tipo_msg=success&msg=Serie guardada correctamente.');
    }

    public function eliminar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('series/index?tipo_msg=error&msg=Registro inválido.');
        }

        if (!$this->model->eliminarLogico($id, $userId)) {
            redirect('series/index?tipo_msg=error&msg=No se pudo eliminar el registro.');
        }

        redirect('series/index?tipo_msg=success&msg=Serie eliminada correctamente.');
    }

    public function restaurar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('series/index?tipo_msg=error&msg=Registro inválido.');
        }

        if (!$this->model->restaurar($id, $userId)) {
            redirect('series/index?tipo_msg=error&msg=No se pudo restaurar el registro.');
        }

        redirect('series/index?tipo_msg=success&msg=Serie restaurada correctamente.');
    }
}
