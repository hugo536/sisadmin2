<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';

class ComercialController extends Controlador
{
    public function index(): void
    {
        AuthMiddleware::handle();
        $this->presentaciones();
    }

    public function presentaciones(): void
    {
        AuthMiddleware::handle();
        $this->render('shared/construccion', [
            'ruta_actual' => 'comercial/presentaciones',
            'destino' => 'Gestión Comercial / Presentaciones y Packs',
        ]);
    }

    public function listas(): void
    {
        AuthMiddleware::handle();
        $this->render('shared/construccion', [
            'ruta_actual' => 'comercial/listas',
            'destino' => 'Gestión Comercial / Listas de Precios',
        ]);
    }

    public function asignacion(): void
    {
        AuthMiddleware::handle();
        $this->render('shared/construccion', [
            'ruta_actual' => 'comercial/asignacion',
            'destino' => 'Gestión Comercial / Asignación Masiva',
        ]);
    }
}
