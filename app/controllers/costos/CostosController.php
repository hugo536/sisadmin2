<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/costos/CostosModel.php';

class CostosController extends Controlador
{
    private CostosModel $costosModel;

    public function __construct()
    {
        $this->costosModel = new CostosModel();
    }

    public function configuracion(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.produccion.ver'); 

        $flash = $this->obtenerFlash();

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            $accion = $_POST['accion'] ?? '';

            if ($accion === 'actualizar_tarifas_planta') {
                try {
                    $idPlanta = (int) ($_POST['id_planta'] ?? 0);
                    $tarifaMod = (float) ($_POST['tarifa_mod'] ?? 0);
                    $tarifaCif = (float) ($_POST['tarifa_cif'] ?? 0);

                    if ($idPlanta <= 0) {
                        throw new Exception('Planta no válida.');
                    }

                    // Usamos el modelo para guardar
                    $this->costosModel->actualizarTarifasPlanta($idPlanta, $tarifaMod, $tarifaCif);

                    $this->setFlash('success', 'Las tarifas de la planta se actualizaron correctamente.');
                    
                    header('Location: ?ruta=costos/configuracion');
                    exit;

                } catch (Exception $e) {
                    $flash = ['tipo' => 'error', 'texto' => $e->getMessage()];
                }
            }
        }

        $plantas = $this->costosModel->obtenerPlantas();

        $this->render('costos/configuracion', [
            'ruta_actual' => 'costos/configuracion',
            'plantas' => $plantas,
            'flash' => $flash
        ]);
    }

    public function alertas(): void
    {
        AuthMiddleware::handle();
        require_permiso('reportes.produccion.ver');
        $this->render('costos/alertas', ['ruta_actual' => 'costos/alertas']);
    }

    private function setFlash(string $tipo, string $texto): void
    {
        $_SESSION['costos_flash'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    private function obtenerFlash(): array
    {
        $flash = ['tipo' => '', 'texto' => ''];
        if (isset($_SESSION['costos_flash']) && is_array($_SESSION['costos_flash'])) {
            $flash = [
                'tipo' => (string) ($_SESSION['costos_flash']['tipo'] ?? ''),
                'texto' => (string) ($_SESSION['costos_flash']['texto'] ?? ''),
            ];
            unset($_SESSION['costos_flash']);
        }
        return $flash;
    }
}