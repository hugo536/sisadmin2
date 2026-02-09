<?php

require_once __DIR__ . '/../middleware/AuthMiddleware.php';
require_once __DIR__ . '/../models/terceros/DistribuidoresModel.php';

class DistribuidoresController extends Controlador
{
    private DistribuidoresModel $distribuidoresModel;

    public function __construct()
    {
        if (!class_exists('Controlador')) {
            if (file_exists(__DIR__ . '/../core/Controlador.php')) {
                require_once __DIR__ . '/../core/Controlador.php';
            } elseif (file_exists(__DIR__ . '/../libs/Controlador.php')) {
                require_once __DIR__ . '/../libs/Controlador.php';
            }
        }

        $this->distribuidoresModel = new DistribuidoresModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.ver');

        $this->render('distribuidores/index', [
            'distribuidores' => $this->distribuidoresModel->listar(),
            'ruta_actual' => 'distribuidores'
        ]);
    }
}
