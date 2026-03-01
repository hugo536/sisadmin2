<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/TesoreriaMovimientoModel.php';
require_once BASE_PATH . '/app/models/TesoreriaCuentaModel.php';

class TesoreriaController extends Controlador
{
    private TesoreriaCxcModel $cxcModel;
    private TesoreriaCxpModel $cxpModel;
    private TesoreriaMovimientoModel $movModel;
    private TesoreriaCuentaModel $cuentaModel;

    public function __construct()
    {
        parent::__construct();
        $this->cxcModel = new TesoreriaCxcModel();
        $this->cxpModel = new TesoreriaCxpModel();
        $this->movModel = new TesoreriaMovimientoModel();
        $this->cuentaModel = new TesoreriaCuentaModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');
        redirect('tesoreria/cxc');
    }

    public function cxc(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxc.ver');

        $filtros = [
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'moneda' => trim((string) ($_GET['moneda'] ?? '')),
            'vencimiento' => trim((string) ($_GET['vencimiento'] ?? '')),
        ];

        $this->render('tesoreria_cxc', [
            'ruta_actual' => 'tesoreria/cxc',
            'registros' => $this->cxcModel->listar($filtros),
            'filtros' => $filtros,
            'cuentas' => $this->cuentaModel->listarActivas(),
            'metodos' => $this->listarMetodosPago(),
        ]);
    }

    public function cxp(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        $filtros = [
            'estado' => trim((string) ($_GET['estado'] ?? '')),
            'moneda' => trim((string) ($_GET['moneda'] ?? '')),
            'vencimiento' => trim((string) ($_GET['vencimiento'] ?? '')),
        ];

        $this->render('tesoreria_cxp', [
            'ruta_actual' => 'tesoreria/cxp',
            'registros' => $this->cxpModel->listar($filtros),
            'filtros' => $filtros,
            'cuentas' => $this->cuentaModel->listarActivas(),
            'metodos' => $this->listarMetodosPago(),
        ]);
    }

    public function movimientos(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        $filtros = [
            'origen' => strtoupper(trim((string) ($_GET['origen'] ?? ''))),
            'id_origen' => (int) ($_GET['id_origen'] ?? 0),
            'id_tercero' => (int) ($_GET['id_tercero'] ?? 0),
        ];

        $this->render('tesoreria_movimientos', [
            'ruta_actual' => 'tesoreria/movimientos',
            'movimientos' => $this->movModel->listarRecientes($filtros, 100),
            'resumenCuentas' => $this->movModel->resumenPorCuenta(),
            'filtros' => $filtros,
        ]);
    }

    public function registrar_cobro(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cobros.registrar');
        $this->registrarMovimientoDesdePost('CXC', 'COBRO', 'tesoreria/cxc');
    }

    public function registrar_pago(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.pagos.registrar');
        $this->registrarMovimientoDesdePost('CXP', 'PAGO', 'tesoreria/cxp');
    }

    public function anular_movimiento(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.movimientos.anular');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/movimientos');
        }

        try {
            $idMovimiento = (int) ($_POST['id_movimiento'] ?? 0);
            $idOrigen = (int) ($_POST['id_origen'] ?? 0);
            $origen = strtoupper(trim((string) ($_POST['origen'] ?? '')));
            $userId = $this->obtenerUsuarioId();

            if ($idMovimiento <= 0 || !in_array($origen, ['CXC', 'CXP'], true)) {
                throw new RuntimeException('Datos inválidos para anular movimiento.');
            }

            $this->movModel->anular($idMovimiento, $userId);
            if ($origen === 'CXC') {
                $this->cxcModel->recalcularEstado($idOrigen, $userId);
            } else {
                $this->cxpModel->recalcularEstado($idOrigen, $userId);
            }

            redirect('tesoreria/movimientos?ok=1');
        } catch (Throwable $e) {
            redirect('tesoreria/movimientos?error=' . urlencode($e->getMessage()));
        }
    }

    public function generar_cxc_desde_venta(int $idDocumentoVenta, int $userId): ?int
    {
        return $this->cxcModel->crearDesdeVenta($idDocumentoVenta, $userId);
    }

    public function generar_cxp_desde_recepcion(int $idRecepcion, int $userId): ?int
    {
        return $this->cxpModel->crearDesdeRecepcion($idRecepcion, $userId);
    }

    private function registrarMovimientoDesdePost(string $origen, string $tipo, string $redirectRuta): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect($redirectRuta);
        }

        try {
            $idOrigen = (int) ($_POST['id_origen'] ?? 0);
            $monto = (float) ($_POST['monto'] ?? 0);
            $payload = [
                'tipo' => $tipo,
                'origen' => $origen,
                'id_origen' => $idOrigen,
                'id_cuenta' => (int) ($_POST['id_cuenta'] ?? 0),
                'id_metodo_pago' => (int) ($_POST['id_metodo_pago'] ?? 0),
                'fecha' => (string) ($_POST['fecha'] ?? date('Y-m-d')),
                'moneda' => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'monto' => round($monto, 4),
                'referencia' => trim((string) ($_POST['referencia'] ?? '')),
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            if ($idOrigen <= 0) {
                throw new RuntimeException('Origen inválido.');
            }

            $userId = $this->obtenerUsuarioId();
            $this->movModel->registrar($payload, $userId);
            if ($origen === 'CXC') {
                $this->cxcModel->recalcularEstado($idOrigen, $userId);
            } else {
                $this->cxpModel->recalcularEstado($idOrigen, $userId);
            }

            redirect($redirectRuta . '?ok=1');
        } catch (Throwable $e) {
            redirect($redirectRuta . '?error=' . urlencode($e->getMessage()));
        }
    }

    private function listarMetodosPago(): array
    {
        $sql = 'SELECT id, nombre FROM tesoreria_metodos_pago WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerUsuarioId(): int
    {
        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Sesión inválida.');
        }
        return $id;
    }
}
