<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaCuentaModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaParametrosModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
require_once BASE_PATH . '/app/models/gastos/GastoConceptoModel.php';

class ContabilidadController extends Controlador
{
    private ContaCuentaModel $cuentaModel;
    private ContaPeriodoModel $periodoModel;
    private ContaAsientoModel $asientoModel;
    private ContaParametrosModel $paramModel;
    private CentroCostoModel $centroCostoModel;
    private TesoreriaCuentaModel $tesoreriaCuentaModel;
    private GastoConceptoModel $gastoConceptoModel;

    public function __construct()
    {
        parent::__construct();
        $this->cuentaModel = new ContaCuentaModel();
        $this->periodoModel = new ContaPeriodoModel();
        $this->asientoModel = new ContaAsientoModel();
        $this->paramModel = new ContaParametrosModel();
        $this->centroCostoModel = new CentroCostoModel();
        $this->tesoreriaCuentaModel = new TesoreriaCuentaModel();
        $this->gastoConceptoModel = new GastoConceptoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.ver');
        redirect('contabilidad/plan');
    }

    public function plan(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.ver');

        $this->render('contabilidad/conta_plan', [
            'ruta_actual' => 'contabilidad/plan',
            'cuentas' => $this->cuentaModel->listar(),
            'parametros' => $this->paramModel->listar(),
            'cuentasMovimiento' => $this->cuentaModel->listarMovimientoActivas(),
            'cuentasTesoreria' => $this->tesoreriaCuentaModel->listarGestion(),
            'conceptosGasto' => $this->gastoConceptoModel->listarActivos(),
        ]);
    }

    public function guardar_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('contabilidad/plan');
        }

        try {
            $this->cuentaModel->guardar($_POST, $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function inactivar_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        try {
            $this->cuentaModel->inactivar((int)($_POST['id_cuenta'] ?? 0), $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function cambiar_estado_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');

        // Verificamos que sea POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect('contabilidad/plan');
        }

        try {
            // Capturamos los datos del formulario
            $idCuenta = (int)($_POST['id_cuenta'] ?? 0);
            $estado = (int)($_POST['estado'] ?? 0);

            if ($idCuenta > 0) {
                $this->cuentaModel->cambiarEstado($idCuenta, $estado, $this->uid());
                redirect('contabilidad/plan?ok=1');
            } else {
                throw new Exception("ID de cuenta no válido.");
            }
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function guardar_parametro(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('contabilidad/plan');
        }
        
        try {
            $clave = trim((string)($_POST['clave'] ?? ''));
            $idCuentaContable = (int)($_POST['id_cuenta'] ?? 0);
            $userId = $this->uid();

            // 1. Guardar en con_parametros (Para que aparezca en la ventana unificada azul)
            $this->paramModel->guardar($clave, $idCuentaContable, $userId);

            // 2. Sincronización de Tesorería: Buscar si la clave es el código de una caja/banco
            $cuentasTesoreria = $this->tesoreriaCuentaModel->listarGestion();
            foreach ($cuentasTesoreria as $ct) {
                if ((string)$ct['codigo'] === $clave) {
                    // Si coincide, vinculamos en la tabla de tesorería para no romper el resto del sistema
                    $this->tesoreriaCuentaModel->vincularCuentaContable((int)$ct['id'], $idCuentaContable, $userId);
                    break; // Salimos del bucle, ya lo encontramos
                }
            }

            // 3. Sincronización de Gastos: vincula el concepto si es una clave dinámica.
            $this->gastoConceptoModel->vincularCuentaContablePorClave($clave, $idCuentaContable, $userId);

            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function eliminar_parametro(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.plan_contable.gestionar');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('contabilidad/plan');
        }

        try {
            $this->paramModel->eliminar((int)($_POST['id_parametro'] ?? 0), $this->uid());
            redirect('contabilidad/plan?ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/plan?error=' . urlencode($e->getMessage()));
        }
    }

    public function periodos(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.periodos.ver');

        $anio = (int)($_GET['anio'] ?? date('Y'));
        $this->render('contabilidad/conta_periodos', [
            'ruta_actual' => 'contabilidad/periodos',
            'anio' => $anio,
            'periodos' => $this->periodoModel->listarPorAnio($anio),
        ]);
    }

    public function crear_periodo(): void
    {
        AuthMiddleware::handle();
        require_permiso('conta.periodos.cerrar');
        try {
            $anio = (int)($_POST['anio'] ?? date('Y'));
            $mes = (int)($_POST['mes'] ?? 0);
            $this->periodoModel->crearSiNoExiste($anio, $mes, $this->uid());
            redirect('contabilidad/periodos?anio=' . $anio . '&ok=1');
        } catch (Throwable $e) {
            redirect('contabilidad/periodos?error=' . urlencode($e->getMessage()));
        }
    }

    public function cerrar_periodo(): void
    {
        try {
            // 1. Metemos todo dentro del try para que NADA se escape
            AuthMiddleware::handle();
            require_permiso('conta.periodos.cerrar');

            // 2. Verificamos si realmente llegan datos POST
            if (empty($_POST)) {
                die("<h1 style='color:red;'>Error: El formulario no envió datos por POST. ¿Falta la ruta en tu Router?</h1>");
            }

            $idPeriodo = (int)($_POST['id_periodo'] ?? 0);
            if ($idPeriodo === 0) {
                die("<h1 style='color:red;'>Error: El id_periodo llegó vacío o es 0.</h1>");
            }

            // 3. Ejecutamos el modelo
            $this->periodoModel->cambiarEstado($idPeriodo, 'CERRADO', $this->uid());
            
            // 4. Pausamos la redirección para asegurar que la base de datos pasó la prueba
            die("<h1 style='color:green;'>¡ÉXITO! El periodo se cerró en la base de datos. <a href='" . route_url('contabilidad/periodos') . "'>Volver a la vista</a></h1>");

        } catch (Throwable $e) {
            // 5. Imprimimos el error gigante en pantalla en vez del F12
            die("<div style='padding:20px; background:#ffebee; color:#c62828; font-family:sans-serif;'>
                    <h2>❌ Error Fatal de PHP Atrapado:</h2>
                    <p><b>Mensaje:</b> " . $e->getMessage() . "</p>
                    <p><b>Archivo:</b> " . $e->getFile() . "</p>
                    <p><b>Línea:</b> " . $e->getLine() . "</p>
                 </div>");
        }
    }

    public function abrir_periodo(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('conta.periodos.cerrar');

            if (empty($_POST)) {
                die("<h1 style='color:red;'>Error: El formulario no envió datos por POST.</h1>");
            }

            $idPeriodo = (int)($_POST['id_periodo'] ?? 0);
            
            $this->periodoModel->cambiarEstado($idPeriodo, 'ABIERTO', $this->uid());
            
            die("<h1 style='color:green;'>¡ÉXITO! El periodo se reabrió. <a href='" . route_url('contabilidad/periodos') . "'>Volver a la vista</a></h1>");

        } catch (Throwable $e) {
            die("<div style='padding:20px; background:#ffebee; color:#c62828; font-family:sans-serif;'>
                    <h2>❌ Error Fatal de PHP Atrapado:</h2>
                    <p><b>Mensaje:</b> " . $e->getMessage() . "</p>
                    <p><b>Archivo:</b> " . $e->getFile() . "</p>
                    <p><b>Línea:</b> " . $e->getLine() . "</p>
                 </div>");
        }
    }

    private function uid(): int
    {
        $id = (int)($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Sesión expirada. Inicie sesión nuevamente.');
        }
        return $id;
    }
}
