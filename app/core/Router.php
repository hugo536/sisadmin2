<?php
declare(strict_types=1);

class Router
{
    public function dispatch(): void
    {
        $ruta = $_GET['ruta'] ?? 'login';

        switch ($ruta) {
            case 'login':
                require_once BASE_PATH . '/app/controllers/AuthController.php';
                (new AuthController())->login();
                break;

            default:
                http_response_code(404);
                echo "Ruta no encontrada: " . htmlspecialchars($ruta);
        }
    }
}
