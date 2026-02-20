<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/configuracion/CajasBancosModel.php';

class CajasBancosController extends Controlador
{
    private CajasBancosModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new CajasBancosModel();
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

        $this->render('configuracion/cajas_bancos', [
            'ruta_actual' => 'cajas_bancos/index',
            'registros' => $this->model->listarConFiltros($filtros),
            'filtros' => $filtros,
            'flash' => [
                'tipo' => (string) ($_GET['tipo_msg'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    public function toggle_estado(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['ok' => false, 'mensaje' => 'Método inválido.'], 405);
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $estado = ((int) ($_POST['estado'] ?? 0) === 1) ? 1 : 0;
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            json_response(['ok' => false, 'mensaje' => 'ID inválido.'], 400);
            return;
        }

        if (!$this->model->actualizarEstado($id, $estado, $userId)) {
            json_response(['ok' => false, 'mensaje' => 'No se pudo actualizar el estado.'], 422);
            return;
        }

        json_response(['ok' => true, 'mensaje' => 'Estado actualizado correctamente.']);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('cajas_bancos');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $tipo = strtoupper(trim((string) ($_POST['tipo'] ?? 'BANCO')));
        $entidad = trim((string) ($_POST['entidad'] ?? ''));
        $tipoCuenta = trim((string) ($_POST['tipo_cuenta'] ?? ''));
        $moneda = strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN')));
        $titular = trim((string) ($_POST['titular'] ?? ''));
        $numeroCuenta = trim((string) ($_POST['numero_cuenta'] ?? ''));
        $permiteCobros = !empty($_POST['permite_cobros']) ? 1 : 0;
        $permitePagos = !empty($_POST['permite_pagos']) ? 1 : 0;
        $estado = ((int) ($_POST['estado'] ?? 1)) === 1 ? 1 : 0;
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($codigo === '' || $nombre === '') {
            redirect('cajas_bancos/index?tipo_msg=error&msg=Código y nombre son obligatorios.');
        }
        if (!preg_match('/^[A-Z0-9\-_]{3,30}$/', $codigo)) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=El código debe tener 3 a 30 caracteres (A-Z, 0-9, - o _).');
        }
        if (!in_array($tipo, ['CAJA', 'BANCO', 'BILLETERA', 'OTROS'], true)) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=Tipo inválido.');
        }
        if (!in_array($moneda, ['PEN', 'USD'], true)) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=Moneda inválida.');
        }
        if ($this->model->existeCodigo($codigo, $id > 0 ? $id : null)) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=El código ya está registrado.');
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'entidad' => $entidad !== '' ? $entidad : null,
            'tipo_cuenta' => $tipoCuenta !== '' ? $tipoCuenta : null,
            'moneda' => $moneda,
            'titular' => $titular !== '' ? $titular : null,
            'numero_cuenta' => $numeroCuenta !== '' ? $numeroCuenta : null,
            'permite_cobros' => $permiteCobros,
            'permite_pagos' => $permitePagos,
            'estado' => $estado,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'user_id' => $userId,
        ];

        $ok = $id > 0 ? $this->model->actualizar($id, $payload) : $this->model->crear($payload);
        if (!$ok) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=No fue posible guardar el registro.');
        }

        redirect('cajas_bancos/index?tipo_msg=success&msg=Registro guardado correctamente.');
    }

    public function eliminar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=Registro inválido.');
        }

        if (!$this->model->eliminarLogico($id, $userId)) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=No se pudo eliminar el registro.');
        }

        redirect('cajas_bancos/index?tipo_msg=success&msg=Registro eliminado correctamente.');
    }

    public function restaurar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=Registro inválido.');
        }

        if (!$this->model->restaurar($id, $userId)) {
            redirect('cajas_bancos/index?tipo_msg=error&msg=No se pudo restaurar el registro.');
        }

        redirect('cajas_bancos/index?tipo_msg=success&msg=Registro restaurado correctamente.');
    }
}
