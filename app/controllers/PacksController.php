<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/PacksModel.php';

class PacksController
{
    private PacksModel $modelo;

    public function __construct()
    {
        // Seguridad: Verificar si el usuario está logueado
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['usuario_id'])) {
            header('Location: ' . route_url('login'));
            exit;
        }

        // Seguridad: Verificar permiso
        if (!tiene_permiso('items.ver')) {
            require BASE_PATH . '/app/views/403.php';
            exit;
        }

        // Instanciar el modelo (lo crearemos en el siguiente paso)
        $this->modelo = new PacksModel();
    }

    /**
     * Muestra la pantalla principal de Packs y Combos
     */
    public function index(): void
    {
        // 1. Obtener la lista de ítems que están configurados como "Pack"
        $packs = $this->modelo->obtenerTodosLosPacks();
        
        $titulo = 'Packs y Combos Comerciales';

        // 2. Cargar la vista pasándole las variables
        $vista = BASE_PATH . '/app/views/items/packs.php';
        require BASE_PATH . '/app/views/layout.php';
    }
}