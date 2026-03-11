<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class CentroCostoController extends Controlador
{
    private CentroCostoModel $centroCostoModel;

    public function __construct()
    {
        parent::__construct();
        // Solo inicializamos el modelo que este controlador necesita
        $this->centroCostoModel = new CentroCostoModel();
    }

    // Antes era centros_costo()
    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');

        $this->render('contabilidad/centros_costo', [
            'ruta_actual' => 'contabilidad/centros_costo',
            'centros' => $this->centroCostoModel->listar(),
        ]);
    }

    // Antes era guardar_centro_costo()
    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.centros_costo.gestionar');
        
        try {
            $this->centroCostoModel->guardar($_POST, $this->uid());
            redirect('contabilidad/centros_costo?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/centros_costo?error=' . urlencode($e->getMessage()));
        }
    }

    // Mantenemos la función de ayuda para obtener el ID del usuario
    private function uid(): int
    {
        return (int)($_SESSION['id'] ?? 0);
    }
}