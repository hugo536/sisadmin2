<?php
declare(strict_types=1);

class DashboardController extends Controlador
{
    public function index(): void
    {
        if (!isset($_SESSION['id'], $_SESSION['usuario'])) {
            header('Location: ?ruta=login/index');
            exit;
        }

        $this->render('dashboard', [
            'usuario' => (string) $_SESSION['usuario'],
            'idRol' => (int) ($_SESSION['id_rol'] ?? 0),
        ]);
    }
}
