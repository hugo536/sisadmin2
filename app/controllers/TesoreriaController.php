<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaMovimientoModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaCuentaModel.php';

class TesoreriaController extends Controlador
{
    private TesoreriaCxcModel $cxcModel;
    private TesoreriaCxpModel $cxpModel;
    private TesoreriaMovimientoModel $movModel;
    private TesoreriaCuentaModel $cuentaModel;
    private ContaCuentaModel $planContableModel;

    public function __construct()
    {
        parent::__construct();
        $this->cxcModel = new TesoreriaCxcModel();
        $this->cxpModel = new TesoreriaCxpModel();
        $this->movModel = new TesoreriaMovimientoModel();
        $this->cuentaModel = new TesoreriaCuentaModel();
        $this->planContableModel = new ContaCuentaModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');
        redirect('tesoreria/cuentas');
    }

    // ========================================================================
    // MÓDULO: CUENTAS (CAJA/BANCO/BILLETERA)
    // ========================================================================
    public function cuentas(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        $idEditar = (int) ($_GET['id'] ?? 0);

        $this->render('tesoreria/tesoreria_cuentas', [
            'ruta_actual' => 'tesoreria/cuentas',
            'cuentas' => $this->cuentaModel->listarGestion(),
            'bancos' => $this->cuentaModel->listarBancosConfigurados(),
            'cuentaEditar' => $idEditar > 0 ? $this->cuentaModel->obtenerPorId($idEditar) : null,
        ]);
    }

    public function guardar_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/cuentas');
        }

        try {
            $payload = [
                'id' => (int) ($_POST['id'] ?? 0),
                'codigo' => trim((string) ($_POST['codigo'] ?? '')),
                'nombre' => trim((string) ($_POST['nombre'] ?? '')),
                'tipo' => strtoupper(trim((string) ($_POST['tipo'] ?? 'CAJA'))),
                'moneda' => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'config_banco_id' => (int) ($_POST['config_banco_id'] ?? 0),
                'titular' => trim((string) ($_POST['titular'] ?? '')),
                'tipo_cuenta' => trim((string) ($_POST['tipo_cuenta'] ?? '')),
                'numero_cuenta' => trim((string) ($_POST['numero_cuenta'] ?? '')),
                'cci' => trim((string) ($_POST['cci'] ?? '')),
                'permite_cobros' => isset($_POST['permite_cobros']) ? 1 : 0,
                'permite_pagos' => isset($_POST['permite_pagos']) ? 1 : 0,
                'saldo_inicial' => (float) ($_POST['saldo_inicial'] ?? 0),
                'fecha_saldo_inicial' => trim((string) ($_POST['fecha_saldo_inicial'] ?? '')),
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            if (array_key_exists('estado', $_POST)) {
                $payload['estado'] = ((int) $_POST['estado'] === 1) ? 1 : 0;
            }

            $this->cuentaModel->guardar($payload, $this->obtenerUsuarioId());
            $action = ((int) $payload['id'] > 0) ? 'updated' : 'created';
            redirect("tesoreria/cuentas?ok=1&action={$action}");

        } catch (Throwable $e) {
            $errorUrl = 'tesoreria/cuentas?error=' . urlencode($e->getMessage());
            if ((int) ($_POST['id'] ?? 0) > 0) {
                $errorUrl .= '&id=' . (int) $_POST['id'];
            }
            redirect($errorUrl);
        }
    }

    public function eliminar_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/cuentas');
        }

        try {
            $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
            $this->cuentaModel->eliminar($idCuenta, $this->obtenerUsuarioId());
            redirect('tesoreria/cuentas?ok=1&action=deleted');
        } catch (Throwable $e) {
            redirect('tesoreria/cuentas?error=' . urlencode($e->getMessage()));
        }
    }

    public function cambiar_estado_cuenta(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/cuentas');
        }

        try {
            $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
            $estado = isset($_POST['estado']) ? 1 : 0;
            $this->cuentaModel->cambiarEstado($idCuenta, $estado, $this->obtenerUsuarioId());
            redirect('tesoreria/cuentas?ok=1&action=updated');
        } catch (Throwable $e) {
            redirect('tesoreria/cuentas?error=' . urlencode($e->getMessage()));
        }
    }

    // ========================================================================
    // MÓDULO: CUENTAS POR COBRAR (CXC)
    // ========================================================================
    public function cxc(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxc.ver');

        $filtros = [
            'estado'      => trim((string) ($_GET['estado'] ?? '')),
            'moneda'      => trim((string) ($_GET['moneda'] ?? '')),
            'vencimiento' => trim((string) ($_GET['vencimiento'] ?? '')),
        ];

        // FILTRO: Solo cuentas con vinculación contable (sin advertencia amarilla)
        $cuentasVinculadas = array_filter($this->cuentaModel->listarActivas(), function($cta) {
            return !empty($cta['id_cuenta_contable'])
                && (int)$cta['id_cuenta_contable'] > 0
                && (int)($cta['permite_cobros'] ?? 0) === 1;
        });

        $this->render('tesoreria/tesoreria_cxc', [
            'ruta_actual' => 'tesoreria/cxc',
            'registros'   => $this->cxcModel->listar($filtros),
            'filtros'     => $filtros,
            'cuentas'     => $cuentasVinculadas,
            'metodos'     => $this->listarMetodosPago(),
            'clientes'    => $this->listarClientesActivos(),
        ]);
    }

    public function registrar_cobro(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('tesoreria.cobros.registrar');
            $this->registrarMovimientoDesdePost('CXC', 'COBRO', 'tesoreria/cxc');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error al acceder a Registrar Cobro");
        }
    }

    public function registrar_cobro_manual(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('tesoreria.cobros.registrar');
            $this->registrarMovimientoManualDesdePost('CXC', 'COBRO', 'tesoreria/cxc');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error al acceder a Cobro Manual");
        }
    }

    // ========================================================================
    // MÓDULO: CUENTAS POR PAGAR (CXP)
    // ========================================================================
    public function cxp(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        $filtros = [
            'estado'      => trim((string) ($_GET['estado'] ?? '')),
            'moneda'      => trim((string) ($_GET['moneda'] ?? '')),
            'vencimiento' => trim((string) ($_GET['vencimiento'] ?? '')),
        ];

        // FILTRO: Solo cuentas con vinculación contable (sin advertencia amarilla)
        $cuentasVinculadas = array_filter($this->cuentaModel->listarActivas(), function($cta) {
            return !empty($cta['id_cuenta_contable'])
                && (int)$cta['id_cuenta_contable'] > 0
                && (int)($cta['permite_pagos'] ?? 0) === 1;
        });

        $this->render('tesoreria/tesoreria_cxp', [
            'ruta_actual' => 'tesoreria/cxp',
            'registros'   => $this->cxpModel->listar($filtros),
            'filtros'     => $filtros,
            'cuentas'     => $cuentasVinculadas,
            'metodos'     => $this->listarMetodosPago(),
            'proveedores' => $this->listarProveedoresActivos(),
        ]);
    }

    public function registrar_pago(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('tesoreria.pagos.registrar');
            $this->registrarMovimientoDesdePost('CXP', 'PAGO', 'tesoreria/cxp');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error al acceder a Registrar Pago");
        }
    }

    public function registrar_pago_manual(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('tesoreria.pagos.registrar');
            $this->registrarMovimientoManualDesdePost('CXP', 'PAGO', 'tesoreria/cxp');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error al acceder a Pago Manual");
        }
    }

    // ========================================================================
    // MÓDULO: HISTORIAL DE MOVIMIENTOS
    // ========================================================================
    public function movimientos(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        $filtros = [
            'origen'     => strtoupper(trim((string) ($_GET['origen'] ?? ''))),
            'id_origen'  => (int) ($_GET['id_origen'] ?? 0),
            'id_tercero' => (int) ($_GET['id_tercero'] ?? 0),
        ];

        $this->render('tesoreria/tesoreria_movimientos', [
            'ruta_actual'    => 'tesoreria/movimientos',
            'movimientos'    => $this->movModel->listarRecientes($filtros, 100),
            'resumenCuentas' => $this->movModel->resumenPorCuenta(),
            'filtros'        => $filtros,
        ]);
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
            $idOrigen     = (int) ($_POST['id_origen'] ?? 0);
            $origen       = strtoupper(trim((string) ($_POST['origen'] ?? '')));
            $userId       = $this->obtenerUsuarioId();

            if ($idMovimiento <= 0 || !in_array($origen, ['CXC', 'CXP'], true)) {
                throw new RuntimeException('Datos inválidos para anular el movimiento.');
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

    // ========================================================================
    // FUNCIONES PRIVADAS DE APOYO
    // ========================================================================
    private function registrarMovimientoDesdePost(string $origen, string $tipo, string $redirectRuta): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            die("<h2 style='color:red;'>El formulario no se envió por el método POST.</h2>");
        }

        try {
            $idOrigen = (int) ($_POST['id_origen'] ?? 0);
            $monto    = (float) ($_POST['monto'] ?? 0);
            
            if ($idOrigen <= 0) {
                throw new RuntimeException('ID Origen llegó como 0.');
            }

            $payload = [
                'tipo'           => $tipo,
                'origen'         => $origen,
                'id_origen'      => $idOrigen,
                'id_cuenta'      => (int) ($_POST['id_cuenta'] ?? 0),
                'id_metodo_pago' => (int) ($_POST['id_metodo_pago'] ?? 0),
                'fecha'          => trim((string) ($_POST['fecha'] ?? date('Y-m-d'))),
                'moneda'         => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'monto'          => round($monto, 4),
                'referencia'     => trim((string) ($_POST['referencia'] ?? '')),
                'observaciones'  => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            $this->validarPermisoOperacionCuenta((int) $payload['id_cuenta'], $origen);

            $userId = $this->obtenerUsuarioId();
            $this->movModel->registrar($payload, $userId);
            
            if ($origen === 'CXC') {
                $this->cxcModel->recalcularEstado($idOrigen, $userId);
            } else {
                $this->cxpModel->recalcularEstado($idOrigen, $userId);
            }

            redirect($redirectRuta . '?ok=1');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error guardando el movimiento");
        }
    }

    private function registrarMovimientoManualDesdePost(string $origen, string $tipo, string $redirectRuta): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
             die("<h2 style='color:red;'>El formulario manual no se envió por el método POST.</h2>");
        }

        try {
            $idTercero = (int) ($_POST['id_tercero'] ?? 0);
            $monto = round((float) ($_POST['monto'] ?? 0), 4);
            $moneda = strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN')));

            if ($idTercero <= 0) throw new RuntimeException('Tercero no válido.');
            if ($monto <= 0) throw new RuntimeException('El monto debe ser mayor a cero.');

            $idsOrigen = $origen === 'CXC'
                ? $this->cxcModel->listarPendientesPorAntiguedad($idTercero, $moneda)
                : $this->cxpModel->listarPendientesPorAntiguedad($idTercero, $moneda);

            if (empty($idsOrigen)) {
                throw new RuntimeException('No hay documentos pendientes en la moneda indicada.');
            }

            $idCuenta = (int) ($_POST['id_cuenta'] ?? 0);
            $this->validarPermisoOperacionCuenta($idCuenta, $origen);

            $this->movModel->registrarDistribuido([
                'tipo'           => $tipo,
                'origen'         => $origen,
                'id_tercero'     => $idTercero,
                'id_cuenta'      => $idCuenta,
                'id_metodo_pago' => (int) ($_POST['id_metodo_pago'] ?? 0),
                'fecha'          => trim((string) ($_POST['fecha'] ?? date('Y-m-d'))),
                'moneda'         => $moneda,
                'monto'          => $monto,
                'referencia'     => trim((string) ($_POST['referencia'] ?? '')),
                'observaciones'  => trim((string) ($_POST['observaciones'] ?? '')),
            ], $idsOrigen, $this->obtenerUsuarioId());

            redirect($redirectRuta . '?ok=1');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error en pago manual distribuido");
        }
    }

    private function listarMetodosPago(): array
    {
        $sql = 'SELECT id, nombre FROM tesoreria_metodos_pago WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listarClientesActivos(): array
    {
        $sql = 'SELECT id, nombre_completo FROM terceros WHERE estado = 1 AND es_cliente = 1 AND deleted_at IS NULL ORDER BY nombre_completo ASC';
        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function listarProveedoresActivos(): array
    {
        $sql = 'SELECT id, nombre_completo FROM terceros WHERE estado = 1 AND es_proveedor = 1 AND deleted_at IS NULL ORDER BY nombre_completo ASC';
        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerUsuarioId(): int
    {
        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) throw new RuntimeException('Sesión expirada.');
        return $id;
    }

    private function mostrarErrorFatal(Throwable $e, string $contexto): void 
    {
        die("
        <div style='padding:30px; background-color:#ffebee; color:#b71c1c; border: 2px solid #c62828; border-radius: 8px; font-family: sans-serif; margin: 20px;'>
            <h1 style='margin-top:0;'>❌ Error 500 Capturado</h1>
            <h3 style='color:#c62828;'>Contexto: {$contexto}</h3>
            <p><strong>Mensaje Técnico:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
            <p><strong>Archivo que falló:</strong> " . $e->getFile() . "</p>
            <p><strong>Línea del error:</strong> " . $e->getLine() . "</p>
            <hr>
            <p style='font-size: 14px; color:#555;'><em>Copia este mensaje para solucionarlo.</em></p>
        </div>");
    }

    private function validarPermisoOperacionCuenta(int $idCuenta, string $origen): void
    {
        if ($idCuenta <= 0) {
            throw new RuntimeException('Debe seleccionar una cuenta de tesorería válida.');
        }

        $cuenta = $this->cuentaModel->obtenerPorId($idCuenta);
        if (!$cuenta || (int) ($cuenta['estado'] ?? 0) !== 1) {
            throw new RuntimeException('La cuenta de tesorería seleccionada está inactiva o no existe.');
        }

        if ($origen === 'CXC' && (int) ($cuenta['permite_cobros'] ?? 0) !== 1) {
            throw new RuntimeException('La cuenta seleccionada no está habilitada para cobros.');
        }

        if ($origen === 'CXP' && (int) ($cuenta['permite_pagos'] ?? 0) !== 1) {
            throw new RuntimeException('La cuenta seleccionada no está habilitada para pagos.');
        }
    }
}
