<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/AlmacenModel.php';

class AlmacenesController extends Controlador
{
    private AlmacenModel $almacenModel;

    public function __construct()
    {
        parent::__construct();
        $this->almacenModel = new AlmacenModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.ver');

        $filtros = [
            'q' => (string) ($_GET['q'] ?? ''),
            'estado_filtro' => (string) ($_GET['estado_filtro'] ?? 'activos'),
            'fecha_desde' => (string) ($_GET['fecha_desde'] ?? ''),
            'fecha_hasta' => (string) ($_GET['fecha_hasta'] ?? ''),
            'orden' => (string) ($_GET['orden'] ?? 'nombre_asc'),
        ];

        $this->render('almacenes', [
            'ruta_actual' => 'almacenes/index',
            'almacenes' => $this->almacenModel->listarConFiltros($filtros),
            'filtros' => $filtros,
            'resumen' => $this->almacenModel->resumen(),
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('almacenes');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $descripcion = trim((string) ($_POST['descripcion'] ?? ''));
        $estado = ((int) ($_POST['estado'] ?? 1)) === 1 ? 1 : 0;
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($codigo === '' || $nombre === '') {
            redirect('almacenes/index?tipo=error&msg=Código y nombre son obligatorios.');
        }

        if (!preg_match('/^[A-Z0-9\-_]{3,30}$/', $codigo)) {
            redirect('almacenes/index?tipo=error&msg=El código debe tener 3 a 30 caracteres (A-Z, 0-9, - o _).');
        }

        if ($this->almacenModel->existeCodigo($codigo, $id > 0 ? $id : null)) {
            redirect('almacenes/index?tipo=error&msg=El código ya está registrado en otro almacén.');
        }

        $payload = [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado' => $estado,
            'user_id' => $userId,
        ];

        $ok = $id > 0
            ? $this->almacenModel->actualizar($id, $payload)
            : $this->almacenModel->crear($payload);

        if (!$ok) {
            redirect('almacenes/index?tipo=error&msg=No fue posible guardar el almacén.');
        }

        redirect('almacenes/index?tipo=success&msg=Almacén guardado correctamente.');
    }

    public function cambiarEstado(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $estado = ((int) ($_POST['estado'] ?? 0)) === 1 ? 1 : 0;
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('almacenes/index?tipo=error&msg=Almacén inválido.');
        }

        if ($estado === 0 && $this->almacenModel->estaEnUso($id)) {
            redirect('almacenes/index?tipo=error&msg=No se puede desactivar: el almacén tiene stock o movimientos asociados.');
        }

        if (!$this->almacenModel->cambiarEstado($id, $estado, $userId)) {
            redirect('almacenes/index?tipo=error&msg=No se pudo actualizar el estado.');
        }

        redirect('almacenes/index?tipo=success&msg=Estado actualizado correctamente.');
    }

    public function eliminar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('almacenes/index?tipo=error&msg=Almacén inválido.');
        }

        if ($this->almacenModel->estaEnUso($id)) {
            redirect('almacenes/index?tipo=error&msg=No se puede eliminar: el almacén está en uso.');
        }

        if (!$this->almacenModel->eliminarLogico($id, $userId)) {
            redirect('almacenes/index?tipo=error&msg=No se pudo eliminar el almacén.');
        }

        redirect('almacenes/index?tipo=success&msg=Almacén eliminado correctamente.');
    }

    public function restaurar(): void
    {
        AuthMiddleware::handle();
        require_permiso('config.editar');

        $id = (int) ($_POST['id'] ?? 0);
        $userId = (int) ($_SESSION['id'] ?? 0);

        if ($id <= 0) {
            redirect('almacenes/index?tipo=error&msg=Almacén inválido.');
        }

        if (!$this->almacenModel->restaurar($id, $userId)) {
            redirect('almacenes/index?tipo=error&msg=No se pudo restaurar el almacén.');
        }

        redirect('almacenes/index?tipo=success&msg=Almacén restaurado correctamente.');
    }
}
