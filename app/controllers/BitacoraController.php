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

        $this->render('bitacora', [
            'logs' => $this->bitacoraModel->listar(),
            'ruta_actual' => 'bitacora/index',
        ]);
    }
}
