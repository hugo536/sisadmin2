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

        // Capturar los nuevos parámetros de tiempo efectivo
        $metaDiaria = (float) ($_POST['meta_horas_diarias'] ?? 8.0);
        $bloqueMinutos = (int) ($_POST['bloque_minutos'] ?? 30);
        $minutosTolerancia = (int) ($_POST['minutos_tolerancia'] ?? 14);

        // Validación estricta en el backend
        if ($minutosTolerancia >= $bloqueMinutos) {
            redirect('rrhh/config_rrhh?tipo=error&msg=' . urlencode('El umbral de corte (tolerancia) debe ser estrictamente menor al tamaño del bloque.'));
            return;
        }

        // Armar arreglo con los nuevos campos de la BD
        $datos = [
            'meta_horas_diarias' => $metaDiaria,
            'bloque_minutos' => $bloqueMinutos,
            'minutos_tolerancia' => $minutosTolerancia
        ];

        $exito = $this->model->guardarConfiguracion($datos);

        if ($exito) {
            redirect('rrhh/config_rrhh?tipo=success&msg=' . urlencode('Políticas actualizadas exitosamente.'));
        } else {
            redirect('rrhh/config_rrhh?tipo=error&msg=' . urlencode('Hubo un error al guardar las políticas.'));
        }
    }
}