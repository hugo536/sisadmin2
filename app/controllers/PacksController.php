<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/core/Controlador.php';
require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/items/PacksModel.php';

class PacksController extends Controlador
{
    private PacksModel $packsModel;

    public function __construct()
    {
        parent::__construct();
        $this->packsModel = new PacksModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $datos = [
            'titulo' => 'Packs y Combos Comerciales',
            'packs' => $this->packsModel->obtenerTodosLosPacks(),
            'csrf_token' => (string) $_SESSION['csrf_token'],
        ];

        $this->render('items/packs', $datos);
    }

    public function buscar_componentes(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Petición inválida.'], 400);
            return;
        }

        $termino = trim((string) ($_GET['q'] ?? ''));
        json_response([
            'ok' => true,
            'items' => $this->packsModel->buscarComponentes($termino),
        ]);
    }

    public function obtener_componentes(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.ver');

        if (!es_ajax()) {
            json_response(['ok' => false, 'mensaje' => 'Petición inválida.'], 400);
            return;
        }

        $idPack = (int) ($_GET['id_pack'] ?? 0);
        if ($idPack <= 0) {
            json_response(['ok' => false, 'mensaje' => 'Pack inválido.'], 422);
            return;
        }

        json_response([
            'ok' => true,
            'items' => $this->packsModel->obtenerComponentesPorPack($idPack),
        ]);
    }

    public function agregar_componente(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.editar');

        if (!es_ajax() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['ok' => false, 'mensaje' => 'Petición inválida.'], 400);
            return;
        }

        $csrf = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
            json_response(['ok' => false, 'mensaje' => 'Error de seguridad (CSRF).'], 403);
            return;
        }

        $payload = [
            'id_pack' => (int) ($_POST['id_pack'] ?? 0),
            'id_item' => (int) ($_POST['id_item'] ?? 0),
            'cantidad' => (float) str_replace(',', '.', (string) ($_POST['cantidad'] ?? 0)),
            'es_bonificacion' => in_array($_POST['es_bonificacion'] ?? 0, [1, '1', 'on', true], true) ? 1 : 0,
            'usuario_id' => (int) ($_SESSION['id'] ?? 0),
        ];

        try {
            $id = $this->packsModel->agregarComponente($payload);
            json_response(['ok' => true, 'mensaje' => 'Componente agregado correctamente.', 'id' => $id]);
        } catch (Throwable $e) {
            json_response(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        }
    }

    public function eliminar_componente(): void
    {
        AuthMiddleware::handle();
        require_permiso('items.editar');

        if (!es_ajax() || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            json_response(['ok' => false, 'mensaje' => 'Petición inválida.'], 400);
            return;
        }

        $csrf = (string) ($_POST['csrf_token'] ?? '');
        if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $csrf)) {
            json_response(['ok' => false, 'mensaje' => 'Error de seguridad (CSRF).'], 403);
            return;
        }

        $idDetalle = (int) ($_POST['id_detalle'] ?? 0);
        if ($idDetalle <= 0) {
            json_response(['ok' => false, 'mensaje' => 'Detalle inválido.'], 422);
            return;
        }

        $ok = $this->packsModel->eliminarComponente($idDetalle);
        json_response([
            'ok' => $ok,
            'mensaje' => $ok ? 'Componente eliminado.' : 'No se pudo eliminar el componente.',
        ], $ok ? 200 : 422);
    }
}
