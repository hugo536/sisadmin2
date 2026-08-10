<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/tesoreria/AdelantosModel.php';

class AdelantosController extends Controlador
{
    private AdelantosModel $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new AdelantosModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('tesoreria/adelantos', [
            'ruta_actual' => 'tesoreria/adelantos',
            'adelantos' => $this->model->listarAdelantos(),
            'empleados' => $this->model->listarEmpleadosActivos(),
            'cuentas' => $this->model->listarCuentasTesoreria(),
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
        require_permiso('tesoreria.pagos.registrar');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postToken = (string) ($_POST['csrf_token'] ?? '');
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $postToken)) {
                redirect('tesoreria/adelantos?tipo=error&msg=' . urlencode('Error de seguridad.'));
                return;
            }

            $exito = $this->model->registrarAdelanto($_POST, AuthMiddleware::getUserId());
            
            if ($exito) {
                redirect('tesoreria/adelantos?tipo=success&msg=' . urlencode('Adelanto registrado y descontado de tesorería.'));
            } else {
                redirect('tesoreria/adelantos?tipo=error&msg=' . urlencode('No se pudo registrar el adelanto. Verifique los saldos.'));
            }
        }
    }

    public function devolver(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.pagos.registrar');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $postToken = (string) ($_POST['csrf_token'] ?? '');
            if (!hash_equals($_SESSION['csrf_token'] ?? '', $postToken)) {
                redirect('tesoreria/adelantos?tipo=error&msg=' . urlencode('Error de seguridad.'));
                return;
            }

            $exito = $this->model->registrarDevolucionManual($_POST, AuthMiddleware::getUserId());
            
            if ($exito) {
                redirect('tesoreria/adelantos?tipo=success&msg=' . urlencode('Devolución procesada y dinero ingresado a cuenta.'));
            } else {
                redirect('tesoreria/adelantos?tipo=error&msg=' . urlencode('No se pudo procesar la devolución.'));
            }
        }
    }

    
}
