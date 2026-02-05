<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/BitacoraModel.php';

class BitacoraController extends Controlador
{
    private BitacoraModel $bitacoraModel;

    public function __construct()
    {
        $this->bitacoraModel = new BitacoraModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('bitacora.ver');

        $filtros = [
            'usuario' => (string) ($_GET['usuario'] ?? ''),
            'evento' => trim((string) ($_GET['evento'] ?? '')),
        ];

        $this->render('bitacora', [
            'logs' => $this->bitacoraModel->listar($filtros),
            'usuariosFiltro' => $this->bitacoraModel->usuarios_para_filtro(),
            'filtros' => $filtros,
            'ruta_actual' => 'bitacora/index',
        ]);
    }
}
