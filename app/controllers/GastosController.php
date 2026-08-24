<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/gastos/GastoConceptoModel.php';
require_once BASE_PATH . '/app/models/gastos/GastoRegistroModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';
require_once BASE_PATH . '/app/models/gastos/GastoProveedorModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';

class GastosController extends Controlador
{
    private GastoConceptoModel $conceptoModel;
    private GastoRegistroModel $registroModel;
    private CentroCostoModel $centroCostoModel;
    private GastoProveedorModel $proveedorModel;

    public function __construct()
    {
        parent::__construct();
        $this->conceptoModel = new GastoConceptoModel();
        $this->registroModel = new GastoRegistroModel();
        $this->centroCostoModel = new CentroCostoModel();
        $this->proveedorModel = new GastoProveedorModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');
        redirect('gastos/conceptos');
    }

    public function conceptos(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        $filtros = [
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'q' => trim((string) ($_GET['q'] ?? '')),
        ];

        $this->render('gastos/conceptos_gasto', [
            'ruta_actual' => 'gastos/conceptos',
            'registros' => $this->conceptoModel->listar($filtros),
            'filtros' => $filtros,
            'centrosCosto' => $this->centroCostoModel->listarActivos(),
            'codigoSugerido' => $this->conceptoModel->siguienteCodigo(),
        ]);
    }

    public function actualizar_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $payload = [
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
                'es_recurrente' => isset($_POST['es_recurrente']) ? 1 : 0,
                'dia_vencimiento' => (int) ($_POST['dia_vencimiento'] ?? 0),
                'dias_anticipacion' => (int) ($_POST['dias_anticipacion'] ?? 0),
            ];

            $this->conceptoModel->actualizar($id, $payload, $this->uid());
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'mensaje' => 'Concepto actualizado correctamente.']);
            exit;
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    public function toggle_estado_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $estado = (int) ($_POST['estado'] ?? 0) === 1 ? 1 : 0;
            $this->conceptoModel->cambiarEstado($id, $estado, $this->uid());
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'mensaje' => 'Estado actualizado correctamente.']);
            exit;
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    public function eliminar_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->conceptoModel->eliminar($id, $this->uid());
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'mensaje' => 'Concepto eliminado correctamente.']);
            exit;
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    public function guardar_concepto(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/conceptos');
        }

        try {
            $payload = [
                'codigo' => trim((string) ($_POST['codigo'] ?? '')),
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
                'es_recurrente' => isset($_POST['es_recurrente']) ? 1 : 0,
                'dia_vencimiento' => (int) ($_POST['dia_vencimiento'] ?? 0),
                'dias_anticipacion' => (int) ($_POST['dias_anticipacion'] ?? 0),
            ];
            $this->conceptoModel->crear($payload, $this->uid());
            
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'mensaje' => 'Concepto registrado correctamente.']);
            exit;
        } catch (Throwable $e) {
            header('Content-Type: application/json');
            http_response_code(400);
            echo json_encode(['status' => 'error', 'mensaje' => $e->getMessage()]);
            exit;
        }
    }

    public function registros(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        // 👇 NUEVO: Definir rangos por defecto (El mes actual)
        $fechaDesdeDefault = date('Y-m-01');
        $fechaHastaDefault = date('Y-m-t');

        $filtros = [
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? $fechaDesdeDefault)),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? $fechaHastaDefault)),
        ];

        $tesoreriaModel = new TesoreriaCxcModel();
        $cuentaModel = new TesoreriaCuentaModel(); // <--- INSTANCIAMOS EL MODELO CORRECTO

        $this->render('gastos/registro_gastos', [
            'ruta_actual' => 'gastos/registros',
            'registros' => $this->registroModel->listar($filtros),
            'proveedores' => $this->proveedorModel->listarActivos(),
            'conceptos' => $this->conceptoModel->listarActivos(),
            'centrosCosto' => $this->centroCostoModel->listarActivos(),
            'filtros' => $filtros,
            'cuentas' => $cuentaModel->listarActivas(), // <--- AHORA TRAE EL SALDO CALCULADO
            'metodos' => $tesoreriaModel->obtenerMetodosActivos(),
        ]);
    }

    public function guardar_registro(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/registros');
        }

        try {
            // 1. Capturar el flag de pago inmediato
            $pagoInmediato = isset($_POST['pago_inmediato']) ? 1 : 0;
            $detallesPago = [];

            // 2. Si el switch está activo, procesamos los arrays de las filas generadas por JS
            if ($pagoInmediato === 1) {
                $cuentasPOST = $_POST['pago_cuenta'] ?? [];
                $metodosPOST = $_POST['pago_metodo'] ?? [];
                $montosPOST  = $_POST['pago_monto'] ?? [];

                foreach ($cuentasPOST as $index => $idCuenta) {
                    $montoPago = (float) ($montosPOST[$index] ?? 0);
                    
                    // Solo agregamos pagos con monto válido
                    if ($montoPago > 0) {
                        $detallesPago[] = [
                            'id_cuenta' => (int) $idCuenta,
                            'id_metodo' => (int) ($metodosPOST[$index] ?? 0),
                            'monto'     => $montoPago
                        ];
                    }
                }
            }

            // 3. Ensamblamos el payload final
            $payload = [
                'fecha' => trim((string) ($_POST['fecha'] ?? date('Y-m-d'))),
                'id_proveedor' => (int) ($_POST['id_proveedor'] ?? 0),
                'id_concepto' => (int) ($_POST['id_concepto'] ?? 0),
                'monto' => (float) ($_POST['monto'] ?? 0),
                'impuesto_tipo' => trim((string) ($_POST['impuesto_tipo'] ?? 'NINGUNO')),
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
                'observacion' => trim((string) ($_POST['observacion'] ?? '')),
                'pago_inmediato' => $pagoInmediato,
                'pagos_detalle'  => $detallesPago
            ];

            // Insertamos
            $this->registroModel->crear($payload, $this->uid());
            
            // 👇 NUEVO: RESPUESTA JSON DE ÉXITO
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'success',
                'mensaje' => 'El registro de gasto se guardó correctamente.'
            ]);
            exit;

        } catch (Throwable $e) {
            // 👇 NUEVO: RESPUESTA JSON DE ERROR
            header('Content-Type: application/json');
            http_response_code(400); // Opcional: marca como error para el navegador
            echo json_encode([
                'status' => 'error',
                'mensaje' => $e->getMessage()
            ]);
            exit;
        }
    }

    public function anular_registro(): void
    {
        AuthMiddleware::handle();
        require_permiso('compras.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('gastos/registros');
        }

        try {
            $id = (int) ($_POST['id'] ?? 0);
            $this->registroModel->anular($id, $this->uid());
            redirect('gastos/registros?ok=1');
        } catch (Throwable $e) {
            redirect('gastos/registros?error=' . urlencode($e->getMessage()));
        }
    }

    private function uid(): int
    {
        return (int)($_SESSION['usuario_id'] ?? $_SESSION['id'] ?? 1);
    }
}
