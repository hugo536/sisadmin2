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
        require_permiso('terceros.ver');

        // Generar token CSRF si no existe
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $config = $this->model->obtenerConfiguracion();

        $this->render('rrhh/config_rrhh', [
            'ruta_actual' => 'rrhh/config_rrhh',
            'config' => $config,
            'csrf_token' => $_SESSION['csrf_token'],
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
            redirect('rrhh/config_rrhh');
            return;
        }

        // Validar CSRF
        $postToken = (string) ($_POST['csrf_token'] ?? '');
        $sessionToken = (string) ($_SESSION['csrf_token'] ?? '');
        if ($sessionToken === '' || !hash_equals($sessionToken, $postToken)) {
            redirect('rrhh/config_rrhh?tipo=error&msg=' . urlencode('Error de seguridad. Recarga la página e intenta de nuevo.'));
            return;
        }

        // Extraer y validar datos
        $minutosGracia = (int) ($_POST['minutos_gracia_salida'] ?? 15);
        $minutosMinimos = (int) ($_POST['minutos_minimos_extra'] ?? 30);
        $pagarSalidaTarde = isset($_POST['pagar_salida_tarde']) ? 1 : 0;

        // Validar rangos
        $minutosGracia = max(0, min(120, $minutosGracia));
        $minutosMinimos = max(1, min(480, $minutosMinimos));

        // Si se activan horas extras, el mínimo debe ser mayor a 0
        if ($pagarSalidaTarde === 1 && $minutosMinimos <= 0) {
            redirect('rrhh/config_rrhh?tipo=error&msg=' . urlencode('El tiempo mínimo para horas extras debe ser mayor a 0.'));
            return;
        }

        $datos = [
            'pagar_llegada_temprano' => isset($_POST['pagar_llegada_temprano']) ? 1 : 0,
            'pagar_salida_tarde' => isset($_POST['pagar_salida_tarde']) ? 1 : 0,
            'minutos_gracia_salida' => (int) ($_POST['minutos_gracia_salida'] ?? 5), // Agregado
            'tipo_calculo_horas_extras' => $_POST['tipo_calculo_horas_extras'] ?? 'EXACTO',
            'minutos_umbral_media_hora' => (int) ($_POST['minutos_umbral_media_hora'] ?? 15),
            'minutos_umbral_hora_completa' => (int) ($_POST['minutos_umbral_hora_completa'] ?? 45)
        ];

        $exito = $this->model->guardarConfiguracion($datos);

        if ($exito) {
            redirect('rrhh/config_rrhh?tipo=success&msg=' . urlencode('Políticas actualizadas exitosamente.'));
        } else {
            redirect('rrhh/config_rrhh?tipo=error&msg=' . urlencode('Hubo un error al guardar las políticas.'));
        }
    }
}
