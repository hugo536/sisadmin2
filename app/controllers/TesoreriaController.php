<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaMovimientoModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaPrestamoModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaSaldosModel.php'; // <-- Nuevo modelo
require_once BASE_PATH . '/app/models/contabilidad/ContaCuentaModel.php';
require_once BASE_PATH . '/app/models/contabilidad/CentroCostoModel.php';

class TesoreriaController extends Controlador
{
    private TesoreriaCxcModel $cxcModel;
    private TesoreriaCxpModel $cxpModel;
    private TesoreriaMovimientoModel $movModel;
    private TesoreriaCuentaModel $cuentaModel;
    private TesoreriaPrestamoModel $prestamoModel;
    private TesoreriaSaldosModel $saldosModel; // <-- Nueva propiedad
    private ContaCuentaModel $planContableModel;
    private CentroCostoModel $centroCostoModel;

    public function __construct()
    {
        parent::__construct();
        $this->cxcModel = new TesoreriaCxcModel();
        $this->cxpModel = new TesoreriaCxpModel();
        $this->movModel = new TesoreriaMovimientoModel();
        $this->cuentaModel = new TesoreriaCuentaModel();
        $this->prestamoModel = new TesoreriaPrestamoModel();
        $this->saldosModel = new TesoreriaSaldosModel(); // <-- Inicialización
        $this->planContableModel = new ContaCuentaModel();
        $this->centroCostoModel = new CentroCostoModel();
    }

    public function index(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');
        redirect('tesoreria/cuentas');
    }

    // ========================================================================
    // MÓDULO: CUENTAS DE TESORERÍA
    // ========================================================================
    public function cuentas(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.ver');

        $idEditar = (int) ($_GET['editar'] ?? 0);
        $cuentaEditar = null;
        if ($idEditar > 0) {
            $cuentaEditar = $this->cuentaModel->obtenerPorId($idEditar);
        }

        $this->render('tesoreria/tesoreria_cuentas', [
            'ruta_actual'  => 'tesoreria/cuentas',
            'cuentas'      => $this->cuentaModel->listarGestion(),
            'bancos'       => $this->cuentaModel->listarBancosConfigurados(),
            'cuentaEditar' => $cuentaEditar,
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
            $idGuardado = $this->cuentaModel->guardar($_POST, $this->obtenerUsuarioId());
            $esEdicion = (int) ($_POST['id'] ?? 0) > 0;
            $action = $esEdicion ? 'updated' : 'created';
            redirect('tesoreria/cuentas?ok=1&action=' . $action . '&id=' . $idGuardado);
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
            redirect('tesoreria/cuentas?ok=1');
        } catch (Throwable $e) {
            redirect('tesoreria/cuentas?error=' . urlencode($e->getMessage()));
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

        $cuentasVinculadas = array_filter($this->cuentaModel->listarActivas(), function ($cta) {
            return !empty($cta['id_cuenta_contable'])
                && (int) $cta['id_cuenta_contable'] > 0
                && (int) ($cta['permite_cobros'] ?? 0) === 1;
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
            $this->mostrarErrorFatal($e, 'Error al registrar cobro');
        }
    }

    public function registrar_cobro_manual(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('tesoreria.cobros.registrar');
            $this->registrarMovimientoManualDesdePost('CXC', 'COBRO', 'tesoreria/cxc');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, 'Error al registrar cobro manual');
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
            'centros_costo' => $this->centroCostoModel->listarActivos(),
        ]);
    }

    // ========================================================================
    // MÓDULO: SALDOS INICIALES (MIGRACIÓN / CUTOVER)
    // ========================================================================
    public function saldos_iniciales(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        $this->render('tesoreria/tesoreria_saldos_iniciales', [
            'ruta_actual' => 'tesoreria/saldos_iniciales',
        ]);
    }

    public function ajax_terceros_saldos(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        header('Content-Type: application/json; charset=utf-8');

        $tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'CLIENTE')));
        $busqueda = trim((string) ($_GET['q'] ?? ''));

        if ($tipo === 'PROVEEDOR') {
            $sql = "SELECT t.id,
                           COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Tercero #', t.id)) AS nombre_completo
                    FROM terceros t
                    WHERE t.estado = 1
                      AND t.deleted_at IS NULL
                      AND t.es_proveedor = 1";
        } else {
            $sql = "SELECT DISTINCT t.id,
                           COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Tercero #', t.id)) AS nombre_completo
                    FROM terceros t
                    LEFT JOIN distribuidores d
                      ON d.id_tercero = t.id
                     AND d.deleted_at IS NULL
                    WHERE t.estado = 1
                      AND t.deleted_at IS NULL
                      AND (t.es_cliente = 1 OR d.id_tercero IS NOT NULL)";
        }

        $params = [];
        if ($busqueda !== '') {
            $sql .= ' AND t.nombre_completo LIKE :q';
            $params['q'] = '%' . $busqueda . '%';
        }

        $sql .= ' ORDER BY t.nombre_completo ASC LIMIT 30';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'ok' => true,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function ajax_items_saldos(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        header('Content-Type: application/json; charset=utf-8');

        $busqueda = trim((string) ($_GET['q'] ?? ''));
        
        $sql = "SELECT id, sku, nombre, precio_venta, unidad_base
                FROM items
                WHERE estado = 1 AND deleted_at IS NULL";

        $params = [];
        if ($busqueda !== '') {
            $sql .= ' AND (nombre LIKE :q OR sku LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        $sql .= ' ORDER BY nombre ASC LIMIT 30';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'ok' => true,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function guardar_saldo_inicial(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/saldos_iniciales');
        }

        try {
            $tipo = strtoupper(trim((string) ($_POST['tipo_deuda'] ?? 'CLIENTE')));
            $idTercero = (int) ($_POST['id_tercero'] ?? 0);
            $moneda = strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN')));
            $monto = round((float) ($_POST['monto_total'] ?? 0), 4);
            $fechaEmision = trim((string) ($_POST['fecha_emision'] ?? ''));
            $docRef = trim((string) ($_POST['documento_referencia'] ?? ''));
            $observacionesText = trim((string) ($_POST['observaciones'] ?? ''));
            $userId = $this->obtenerUsuarioId();

            if (!in_array($tipo, ['CLIENTE', 'PROVEEDOR'], true)) {
                throw new RuntimeException('Tipo de deuda inválido.');
            }
            if ($idTercero <= 0) {
                throw new RuntimeException('Debe seleccionar un tercero válido.');
            }
            if (!in_array($moneda, ['PEN', 'USD'], true)) {
                throw new RuntimeException('La moneda debe ser PEN o USD.');
            }
            if ($docRef === '') {
                throw new RuntimeException('El documento de referencia es obligatorio.');
            }

            $hoy = date('Y-m-d');
            if ($fechaEmision === '') {
                $fechaEmision = $hoy;
            }

            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
                throw new RuntimeException('Las fechas deben tener formato YYYY-MM-DD.');
            }

            // === NUEVA LÓGICA: CAPTURAR EL DETALLE DE ÍTEMS ===
            $idsItems = $_POST['detalle_item_id'] ?? [];
            $nombresItems = $_POST['detalle_item_nombre'] ?? [];
            $cantidades = $_POST['detalle_cantidad'] ?? [];
            $precios = $_POST['detalle_precio'] ?? [];
            
            $detalleJson = [];
            $sumaCalculada = 0;

            if (!empty($idsItems) && is_array($idsItems)) {
                foreach ($idsItems as $index => $idItem) {
                    $cant = (float) ($cantidades[$index] ?? 0);
                    $prec = (float) ($precios[$index] ?? 0);
                    $subtotal = $cant * $prec;
                    
                    if ($cant > 0 && $prec >= 0) {
                        $sumaCalculada += $subtotal;
                        $detalleJson[] = [
                            'id_item' => (int) $idItem,
                            'nombre' => trim((string) ($nombresItems[$index] ?? '')),
                            'cantidad' => $cant,
                            'precio_unitario' => $prec,
                            'subtotal' => $subtotal
                        ];
                    }
                }
            }

            // Si el usuario llenó la tabla, el monto total DEBE SER la suma exacta de la tabla.
            if (!empty($detalleJson)) {
                $monto = round($sumaCalculada, 4);
                if ($monto <= 0) {
                    throw new RuntimeException('El monto calculado del detalle debe ser mayor a cero.');
                }
                
                // Guardamos el JSON dentro de las observaciones
                $observacionesFinal = json_encode([
                    'nota_manual' => $observacionesText,
                    'detalle_items' => $detalleJson
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // Si no hay tabla de detalle, validamos el monto digitado manualmente
                if ($monto <= 0) {
                    throw new RuntimeException('El monto debe ser mayor a cero.');
                }
                $observacionesFinal = $observacionesText;
            }

            // Armamos el payload con la data limpia para enviarlo al modelo
            $payload = [
                'id_tercero'           => $idTercero,
                'documento_referencia' => $docRef,
                'fecha_emision'        => $fechaEmision,
                'fecha_vencimiento'    => $fechaEmision, // Según requerimiento, no se usa vencimiento extra
                'moneda'               => $moneda,
                'monto_total'          => $monto,
                'estado'               => 'ABIERTA', // Como quitamos Vencimiento, siempre nace ABIERTA
                'observaciones'        => $observacionesFinal,
            ];

            // 🚀 El controlador delega la inyección en base de datos al nuevo modelo
            if ($tipo === 'CLIENTE') {
                $this->saldosModel->crearSaldoCxc($payload, $userId);
            } else {
                $this->saldosModel->crearSaldoCxp($payload, $userId);
            }

            redirect('tesoreria/saldos_iniciales?ok=1');
        } catch (Throwable $e) {
            redirect('tesoreria/saldos_iniciales?error=' . urlencode($e->getMessage()));
        }
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
    // MÓDULO: PRÉSTAMOS BANCARIOS
    // ========================================================================
    public function prestamos(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        $filtros = [
            'estado' => strtoupper(trim((string) ($_GET['estado'] ?? ''))),
        ];

        $cuentasVinculadas = array_filter($this->cuentaModel->listarActivas(), function ($cta) {
            return !empty($cta['id_cuenta_contable'])
                && (int) $cta['id_cuenta_contable'] > 0
                && (int) ($cta['permite_pagos'] ?? 0) === 1;
        });

        $this->render('tesoreria/tesoreria_prestamos', [
            'ruta_actual'    => 'tesoreria/prestamos',
            'registros'      => $this->prestamoModel->listar($filtros),
            'filtros'        => $filtros,
            'cuentas'        => $cuentasVinculadas,
            'metodos'        => $this->listarMetodosPago(),
            'proveedores'    => $this->listarProveedoresActivos(),
            'entidades_catalogo' => $this->listarEntidadesFinancierasCatalogo(),
            'centros_costo'  => $this->centroCostoModel->listarActivos(),
        ]);
    }

    public function guardar_prestamo(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/prestamos');
        }

        try {
            $payload = [
                'id_proveedor'        => (int) ($_POST['id_proveedor'] ?? 0),
                'numero_contrato'     => trim((string) ($_POST['numero_contrato'] ?? '')),
                'entidad_financiera'  => trim((string) ($_POST['entidad_financiera'] ?? '')),
                'fecha_desembolso'    => trim((string) ($_POST['fecha_desembolso'] ?? date('Y-m-d'))),
                'fecha_primera_cuota' => trim((string) ($_POST['fecha_primera_cuota'] ?? date('Y-m-d'))),
                'monto_total'         => round((float) ($_POST['monto_total'] ?? 0), 4),
                'moneda'              => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'tipo_tasa'           => strtoupper(trim((string) ($_POST['tipo_tasa'] ?? 'FIJA'))),
                'tasa_anual'          => round((float) ($_POST['tasa_anual'] ?? 0), 4),
                'numero_cuotas'       => (int) ($_POST['numero_cuotas'] ?? 1),
                'observaciones'       => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            $this->prestamoModel->crear($payload, $this->obtenerUsuarioId());
            redirect('tesoreria/prestamos?ok=1');
        } catch (Throwable $e) {
            redirect('tesoreria/prestamos?error=' . urlencode($e->getMessage()));
        }
    }

    public function registrar_pago_prestamo(): void
    {
        try {
            AuthMiddleware::handle();
            require_permiso('tesoreria.pagos.registrar');
            $this->registrarMovimientoDesdePost('CXP', 'PAGO', 'tesoreria/prestamos');
        } catch (Throwable $e) {
            $this->mostrarErrorFatal($e, "Error al registrar pago de préstamo");
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
                'naturaleza_pago' => strtoupper(trim((string) ($_POST['naturaleza_pago'] ?? 'DOCUMENTO'))),
                'monto_capital' => round((float) ($_POST['monto_capital'] ?? 0), 4),
                'monto_interes' => round((float) ($_POST['monto_interes'] ?? 0), 4),
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
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

            // 1. Guardamos el resultado del FIFO (qué facturas se afectaron)
            $resultado = $this->movModel->registrarDistribuido([
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
                'naturaleza_pago' => 'DOCUMENTO',
            ], $idsOrigen, $this->obtenerUsuarioId());

            // 2. Sincronizamos los estados de esas facturas
            $userId = $this->obtenerUsuarioId();
            if (!empty($resultado['origenes'])) {
                foreach ($resultado['origenes'] as $idDocAfectado) {
                    if ($origen === 'CXC') {
                        $this->cxcModel->recalcularEstado($idDocAfectado, $userId);
                    } else {
                        $this->cxpModel->recalcularEstado($idDocAfectado, $userId);
                    }
                }
            }

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

    private function listarEntidadesFinancierasCatalogo(): array
    {
        $sql = 'SELECT id, codigo, nombre, tipo
                FROM configuracion_cajas_bancos
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY tipo ASC, nombre ASC';

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
