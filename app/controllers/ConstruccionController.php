<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';

class ConstruccionController extends Controlador
{
    public function index(): void
    {
        AuthMiddleware::handle();

        $this->render('shared/construccion', [
            'destino' => (string) ($_GET['destino'] ?? ''),
            'ruta_actual' => 'construccion/index',
        ]);
    }
}
