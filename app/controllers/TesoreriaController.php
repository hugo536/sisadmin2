<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaMovimientoModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';

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
                'principal' => isset($_POST['principal']) ? 1 : 0,
                'estado' => isset($_POST['estado']) ? 1 : 0,
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            $id = $this->cuentaModel->guardar($payload, $this->obtenerUsuarioId());
            redirect('tesoreria/cuentas?ok=1&id=' . $id);
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

        // Saneamiento de filtros para el modelo y la vista
        $filtros = [
            'estado'      => trim((string) ($_GET['estado'] ?? '')),
            'moneda'      => trim((string) ($_GET['moneda'] ?? '')),
            'vencimiento' => trim((string) ($_GET['vencimiento'] ?? '')),
        ];

        // Nota: Si esta petición viene de AJAX (JS), la vista renderizará todo
        // y nuestro JS extraerá solo la tabla y el badge de forma silenciosa.
        $this->render('tesoreria/tesoreria_cxc', [
            'ruta_actual' => 'tesoreria/cxc',
            'registros'   => $this->cxcModel->listar($filtros),
            'filtros'     => $filtros,
            'cuentas'     => $this->cuentaModel->listarActivas(),
            'metodos'     => $this->listarMetodosPago(),
        ]);
    }

    public function registrar_cobro(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cobros.registrar');
        
        $this->registrarMovimientoDesdePost('CXC', 'COBRO', 'tesoreria/cxc');
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

        $this->render('tesoreria/tesoreria_cxp', [
            'ruta_actual' => 'tesoreria/cxp',
            'registros'   => $this->cxpModel->listar($filtros),
            'filtros'     => $filtros,
            'cuentas'     => $this->cuentaModel->listarActivas(),
            'metodos'     => $this->listarMetodosPago(),
        ]);
    }

    public function registrar_pago(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.pagos.registrar');
        
        $this->registrarMovimientoDesdePost('CXP', 'PAGO', 'tesoreria/cxp');
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

            // 1. Anular el movimiento en la base de datos
            $this->movModel->anular($idMovimiento, $userId);
            
            // 2. Recalcular el saldo y estado del documento origen
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
    // MÉTODOS INTEGRADORES (Llamados desde otros módulos como Ventas/Compras)
    // ========================================================================
    public function generar_cxc_desde_venta(int $idDocumentoVenta, int $userId): ?int
    {
        return $this->cxcModel->crearDesdeVenta($idDocumentoVenta, $userId);
    }

    public function generar_cxp_desde_recepcion(int $idRecepcion, int $userId): ?int
    {
        return $this->cxpModel->crearDesdeRecepcion($idRecepcion, $userId);
    }

    // ========================================================================
    // FUNCIONES PRIVADAS DE APOYO
    // ========================================================================
    private function registrarMovimientoDesdePost(string $origen, string $tipo, string $redirectRuta): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect($redirectRuta);
        }

        try {
            $idOrigen = (int) ($_POST['id_origen'] ?? 0);
            $monto    = (float) ($_POST['monto'] ?? 0);
            
            if ($idOrigen <= 0) {
                throw new RuntimeException('Origen de documento inválido.');
            }

            $payload = [
                'tipo'           => $tipo, // 'COBRO' o 'PAGO'
                'origen'         => $origen, // 'CXC' o 'CXP'
                'id_origen'      => $idOrigen,
                'id_cuenta'      => (int) ($_POST['id_cuenta'] ?? 0),
                'id_metodo_pago' => (int) ($_POST['id_metodo_pago'] ?? 0),
                'fecha'          => trim((string) ($_POST['fecha'] ?? date('Y-m-d'))),
                'moneda'         => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'monto'          => round($monto, 4),
                'referencia'     => trim((string) ($_POST['referencia'] ?? '')),
                'observaciones'  => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            $userId = $this->obtenerUsuarioId();
            
            // 1. Registrar el ingreso/salida de dinero
            $this->movModel->registrar($payload, $userId);
            
            // 2. Recalcular el saldo del documento origen (Factura/Boleta/Guía)
            if ($origen === 'CXC') {
                $this->cxcModel->recalcularEstado($idOrigen, $userId);
            } else {
                $this->cxpModel->recalcularEstado($idOrigen, $userId);
            }

            redirect($redirectRuta . '?ok=1');
        } catch (Throwable $e) {
            // Pasamos el error por URL de forma segura para que la vista lo atrape
            redirect($redirectRuta . '?error=' . urlencode($e->getMessage()));
        }
    }

    private function listarMetodosPago(): array
    {
        $sql = 'SELECT id, nombre 
                FROM tesoreria_metodos_pago 
                WHERE estado = 1 AND deleted_at IS NULL 
                ORDER BY nombre ASC';
                
        return Conexion::get()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerUsuarioId(): int
    {
        $id = (int) ($_SESSION['id'] ?? 0);
        if ($id <= 0) {
            throw new RuntimeException('Sesión inválida o expirada. Por favor, inicie sesión nuevamente.');
        }
        return $id;
    }
}