<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/rrhh/ConfigRrhhModel.php';

class ConfigRrhhController extends Controlador
{
    private ConfigRrhhModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new ConfigRrhhModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        // Nota: Asegúrate de que el rol de RRHH tenga el permiso terceros.ver
        require_permiso('terceros.ver');

        $config = $this->model->obtenerConfiguracion();

        $this->render('rrhh/config_rrhh', [
            'ruta_actual' => 'rrhh/config_rrhh', // <-- Ajustado aquí
            'config' => $config,
            'flash' => [
                'tipo' => (string) ($_GET['tipo'] ?? ''),
                'texto' => (string) ($_GET['msg'] ?? ''),
            ],
        ]);
    }

    public function guardar(): void
    {
        AuthMiddleware::handle();
        require_permiso('terceros.editar');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('rrhh/config_rrhh'); // <-- Ajustado aquí
            return;
        }

        $datos = [
            'pagar_llegada_temprano' => isset($_POST['pagar_llegada_temprano']) ? 1 : 0,
            'pagar_salida_tarde' => isset($_POST['pagar_salida_tarde']) ? 1 : 0,
            'minutos_gracia_salida' => (int) ($_POST['minutos_gracia_salida'] ?? 15),
            'minutos_minimos_extra' => (int) ($_POST['minutos_minimos_extra'] ?? 30)
        ];

        $exito = $this->model->guardarConfiguracion($datos);

        if ($exito) {
            redirect('rrhh/config_rrhh?tipo=success&msg=' . urlencode('Políticas actualizadas exitosamente.')); // <-- Ajustado aquí
        } else {
            redirect('rrhh/config_rrhh?tipo=error&msg=' . urlencode('Hubo un error al guardar las políticas.')); // <-- Ajustado aquí
        }
    }
}