<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/middleware/AuthMiddleware.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxcModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCxpModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaMovimientoModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaCuentaModel.php';
require_once BASE_PATH . '/app/models/tesoreria/TesoreriaTransferenciaModel.php';
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
    private TesoreriaTransferenciaModel $transferenciaModel;
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
        $this->transferenciaModel = new TesoreriaTransferenciaModel();
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
            'cuentasActivas' => $this->cuentaModel->listarActivas(),
            'bancos'       => $this->cuentaModel->listarBancosConfigurados(),
            'cuentaEditar' => $cuentaEditar,
        ]);
    }

    public function registrar_transferencia_interna(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.pagos.registrar'); // OJO: Si tienes un permiso específico como 'tesoreria.transferencias.registrar', cámbialo aquí.

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            redirect('tesoreria/cuentas');
        }

        try {
            $idOrigen = (int) ($_POST['id_cuenta_origen'] ?? 0);
            $idDestino = (int) ($_POST['id_cuenta_destino'] ?? 0);
            $monto = round((float) ($_POST['monto'] ?? 0), 4);

            // --- INICIO DE VALIDACIONES BACKEND DE SEGURIDAD ---
            if ($idOrigen <= 0 || $idDestino <= 0) {
                throw new RuntimeException('Debe seleccionar la cuenta origen y destino.');
            }
            if ($idOrigen === $idDestino) {
                throw new RuntimeException('La cuenta de origen y destino no pueden ser la misma.');
            }
            if ($monto <= 0) {
                throw new RuntimeException('El monto de la transferencia debe ser mayor a cero.');
            }
            // --- FIN DE VALIDACIONES ---

            $this->transferenciaModel->registrar([
                'id_cuenta_origen' => $idOrigen,
                'id_cuenta_destino' => $idDestino,
                'fecha' => trim((string) ($_POST['fecha'] ?? '')),
                'moneda' => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'monto' => $monto,
                'referencia' => trim((string) ($_POST['referencia'] ?? '')),
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ], $this->obtenerUsuarioId());

            redirect('tesoreria/cuentas?ok=1&action=transfer');
        } catch (Throwable $e) {
            redirect('tesoreria/cuentas?error=' . urlencode($e->getMessage()));
        }
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

        $hoy = date('Y-m-d');
        $fechaDesdeDefault = date('Y-m-d', strtotime('-6 days'));
        $tipoTercero = trim((string) ($_GET['tipo_tercero'] ?? ''));
        if (!in_array($tipoTercero, ['', 'cliente', 'cliente_distribuidor', 'distribuidor'], true)) {
            $tipoTercero = '';
        }

        $filtros = [
            'estado'      => trim((string) ($_GET['estado'] ?? '')),
            'tipo_tercero' => $tipoTercero,
            'fecha_desde' => trim((string) ($_GET['fecha_desde'] ?? $fechaDesdeDefault)),
            'fecha_hasta' => trim((string) ($_GET['fecha_hasta'] ?? $hoy)),
        ];

        if (!$this->esFechaValida($filtros['fecha_desde'])) {
            $filtros['fecha_desde'] = $fechaDesdeDefault;
        }

        if (!$this->esFechaValida($filtros['fecha_hasta'])) {
            $filtros['fecha_hasta'] = $hoy;
        }

        if ($filtros['fecha_desde'] > $filtros['fecha_hasta']) {
            [$filtros['fecha_desde'], $filtros['fecha_hasta']] = [$filtros['fecha_hasta'], $filtros['fecha_desde']];
        }

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
        
        $sql = "SELECT i.id,
                       i.sku,
                       i.nombre,
                       i.descripcion,
                       i.precio_venta,
                       i.unidad_base
                FROM items i
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL";

        $params = [];
        if ($busqueda !== '') {
            $sql .= ' AND (
                        i.nombre LIKE :q
                        OR i.sku LIKE :q
                        OR COALESCE(i.descripcion, \'\') LIKE :q
                        OR COALESCE(i.marca, \'\') LIKE :q
                        OR COALESCE(i.unidad_base, \'\') LIKE :q
                        OR COALESCE(i.tipo_item, \'\') LIKE :q
                      )';
            $params['q'] = '%' . $busqueda . '%';
        }

        $sql .= ' ORDER BY i.nombre ASC LIMIT 30';
        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'ok' => true,
            'items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function ajax_item_unidades_saldos(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver');
        header('Content-Type: application/json; charset=utf-8');

        $idItem = (int) ($_GET['id_item'] ?? 0);
        if ($idItem <= 0) {
            echo json_encode(['ok' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $sql = "SELECT u.id,
                       u.nombre,
                       u.factor_conversion
                FROM items_unidades u
                INNER JOIN items i ON i.id = u.id_item
                WHERE u.id_item = :id_item
                  AND u.estado = 1
                  AND u.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                ORDER BY u.nombre ASC";

        $stmt = Conexion::get()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

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
            $modoRegistro = strtoupper(trim((string) ($_POST['modo_registro'] ?? 'DETALLE')));
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
            if (!in_array($modoRegistro, ['DETALLE', 'MANUAL'], true)) {
                throw new RuntimeException('Modo de registro inválido.');
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
            $subtotales = $_POST['detalle_subtotal'] ?? [];
            $fechasDetalle = $_POST['detalle_fecha'] ?? [];
            $idsUnidad = $_POST['detalle_item_unidad_id'] ?? [];
            $nombresUnidad = $_POST['detalle_item_unidad_nombre'] ?? [];
            $factoresUnidad = $_POST['detalle_item_unidad_factor'] ?? [];
            
            $detalleJson = [];
            $sumaCalculada = 0;
            $amortizacionesLocales = [];

            $amortFecha = $_POST['amortizacion_local_fecha'] ?? [];
            $amortReferencia = $_POST['amortizacion_local_referencia'] ?? [];
            $amortMetodo = $_POST['amortizacion_local_metodo'] ?? [];
            $amortMonto = $_POST['amortizacion_local_monto'] ?? [];

            if (is_array($amortFecha)) {
                foreach ($amortFecha as $idx => $fechaAmort) {
                    $fechaAmort = trim((string) $fechaAmort);
                    $montoAmort = round((float) ($amortMonto[$idx] ?? 0), 4);
                    if ($fechaAmort === '' || $montoAmort <= 0) {
                        continue;
                    }
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaAmort)) {
                        continue;
                    }
                    $amortizacionesLocales[] = [
                        'fecha' => $fechaAmort,
                        'referencia' => trim((string) ($amortReferencia[$idx] ?? '')),
                        'metodo' => trim((string) ($amortMetodo[$idx] ?? '')),
                        'monto' => $montoAmort,
                    ];
                }
            }

            if ($modoRegistro === 'DETALLE' && !empty($idsItems) && is_array($idsItems)) {
                foreach ($idsItems as $index => $idItem) {
                    $cant = (float) ($cantidades[$index] ?? 0);
                    $subtotal = (float) ($subtotales[$index] ?? 0);
                    $fechaDetalle = trim((string) ($fechasDetalle[$index] ?? $fechaEmision));
                    $idUnidad = (int) ($idsUnidad[$index] ?? 0);
                    $nombreUnidad = trim((string) ($nombresUnidad[$index] ?? ''));
                    $factorUnidad = round((float) ($factoresUnidad[$index] ?? 1), 4);
                    
                    if ($fechaDetalle === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDetalle)) {
                        $fechaDetalle = $fechaEmision;
                    }

                    if ($cant > 0 && $subtotal >= 0) {
                        $sumaCalculada += $subtotal;
                        $detalleJson[] = [
                            'id_item' => (int) $idItem,
                            'nombre' => trim((string) ($nombresItems[$index] ?? '')),
                            'fecha' => $fechaDetalle,
                            'cantidad' => $cant,
                            'id_item_unidad' => $idUnidad > 0 ? $idUnidad : null,
                            'unidad_nombre' => $nombreUnidad !== '' ? $nombreUnidad : null,
                            'factor_conversion' => $factorUnidad > 0 ? $factorUnidad : 1,
                            'subtotal' => $subtotal
                        ];
                    }
                }
            }

            // Si el usuario llenó la tabla, el monto total DEBE SER la suma exacta de la tabla.
            if ($modoRegistro === 'DETALLE') {
                if (empty($detalleJson)) {
                    throw new RuntimeException('Debe agregar al menos un ítem al detalle o cambiar a "Monto directo".');
                }
                $monto = round($sumaCalculada, 4);
                if ($monto <= 0) {
                    throw new RuntimeException('El monto calculado del detalle debe ser mayor a cero.');
                }
                
                // Guardamos el JSON dentro de las observaciones
                $observacionesFinal = json_encode([
                    'modo_registro' => $modoRegistro,
                    'nota_manual' => $observacionesText,
                    'detalle_items' => $detalleJson,
                    'amortizaciones_previas' => $amortizacionesLocales
                ], JSON_UNESCAPED_UNICODE);
            } else {
                // Si no hay tabla de detalle, validamos el monto digitado manualmente
                if ($monto <= 0) {
                    throw new RuntimeException('El monto debe ser mayor a cero.');
                }
                $observacionesFinal = json_encode([
                    'modo_registro' => $modoRegistro,
                    'nota_manual' => $observacionesText,
                    'amortizaciones_previas' => $amortizacionesLocales
                ], JSON_UNESCAPED_UNICODE);
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

            $existente = $this->saldosModel->obtenerSaldoInicialPorTercero($tipo, $idTercero);

            if ($tipo === 'CLIENTE') {
                if ($existente) {
                    $montoPagado = round((float) ($existente['monto_pagado'] ?? 0), 4);
                    $payload['saldo'] = max(0, round((float) $payload['monto_total'] - $montoPagado, 4));
                    $this->saldosModel->actualizarSaldoCxc((int) $existente['id'], $payload, $userId);
                } else {
                    $this->saldosModel->crearSaldoCxc($payload, $userId);
                }
            } else {
                if ($existente) {
                    $montoPagado = round((float) ($existente['monto_pagado'] ?? 0), 4);
                    $payload['saldo'] = max(0, round((float) $payload['monto_total'] - $montoPagado, 4));
                    $this->saldosModel->actualizarSaldoCxp((int) $existente['id'], $payload, $userId);
                } else {
                    $this->saldosModel->crearSaldoCxp($payload, $userId);
                }
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

            // CORRECCIÓN: Agregamos 'TRANSFERENCIA' a los orígenes permitidos
            if ($idMovimiento <= 0 || !in_array($origen, ['CXC', 'CXP', 'TRANSFERENCIA'], true)) {
                throw new RuntimeException('Datos inválidos para anular el movimiento.');
            }

            // Anulamos el movimiento en el ledger principal
            $this->movModel->anular($idMovimiento, $userId);
            
            // Recalculamos estados dependiendo del origen
            if ($origen === 'CXC') {
                $this->cxcModel->recalcularEstado($idOrigen, $userId);
            } elseif ($origen === 'CXP') {
                $this->cxpModel->recalcularEstado($idOrigen, $userId);
            } elseif ($origen === 'TRANSFERENCIA') {
                // NOTA: Si en tu 'transferenciaModel' tienes un método para cambiar el estado 
                // de la transferencia a 'ANULADO', deberías llamarlo aquí.
                if (method_exists($this->transferenciaModel, 'anular')) {
                    $this->transferenciaModel->anular($idOrigen, $userId);
                }
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
            $montoTotal = round((float) ($_POST['monto'] ?? 0), 4);

            if ($idOrigen <= 0) {
                throw new RuntimeException('ID Origen llegó como 0.');
            }

            $naturalezaPago = strtoupper(trim((string) ($_POST['naturaleza_pago'] ?? 'DOCUMENTO')));
            $montoCapitalTotal = round((float) ($_POST['monto_capital'] ?? 0), 4);
            $montoInteresTotal = round((float) ($_POST['monto_interes'] ?? 0), 4);

            $payloadBase = [
                'tipo'            => $tipo,
                'origen'          => $origen,
                'id_origen'       => $idOrigen,
                'id_cuenta'       => (int) ($_POST['id_cuenta'] ?? 0),
                'fecha'           => trim((string) ($_POST['fecha'] ?? date('Y-m-d'))),
                'moneda'          => strtoupper(trim((string) ($_POST['moneda'] ?? 'PEN'))),
                'monto'           => $montoTotal,
                'referencia'      => trim((string) ($_POST['referencia'] ?? '')),
                'observaciones'   => trim((string) ($_POST['observaciones'] ?? '')),
                'naturaleza_pago' => $naturalezaPago,
                'monto_capital'   => $montoCapitalTotal,
                'monto_interes'   => $montoInteresTotal,
                'id_centro_costo' => (int) ($_POST['id_centro_costo'] ?? 0),
            ];

            $cuentaDestinoIds = $_POST['cuenta_destino_ids'] ?? [];
            $metodoIds = $_POST['metodo_pago_ids'] ?? [];
            $metodoMontos = $_POST['metodo_montos'] ?? [];
            $distribucion = [];

            if (is_array($cuentaDestinoIds) && is_array($metodoIds) && is_array($metodoMontos) && count($metodoIds) > 0) {
                $totalDistribuido = 0.0;
                foreach ($metodoIds as $idx => $idMetodoRaw) {
                    $idCuenta = (int) ($cuentaDestinoIds[$idx] ?? 0);
                    $idMetodo = (int) $idMetodoRaw;
                    $montoMetodo = round((float) ($metodoMontos[$idx] ?? 0), 4);
                    if ($idCuenta <= 0 || $idMetodo <= 0 || $montoMetodo <= 0) {
                        continue;
                    }
                    $distribucion[] = [
                        'id_cuenta'      => $idCuenta,
                        'id_metodo_pago' => $idMetodo,
                        'monto'          => $montoMetodo
                    ];
                    $totalDistribuido += $montoMetodo;
                }

                if (!empty($distribucion) && abs(round($totalDistribuido, 4) - $montoTotal) > 0.0001) {
                    throw new RuntimeException('La suma de la distribución por cuenta y método debe coincidir con el monto total del cobro.');
                }
            }

            if (empty($distribucion)) {
                $idCuentaUnica = (int) ($_POST['id_cuenta'] ?? 0);
                $idMetodoUnico = (int) ($_POST['id_metodo_pago'] ?? 0);
                if ($idCuentaUnica <= 0 || $idMetodoUnico <= 0) {
                    throw new RuntimeException('Debe seleccionar al menos una cuenta destino y un método de cobro.');
                }
                $distribucion[] = [
                    'id_cuenta'      => $idCuentaUnica,
                    'id_metodo_pago' => $idMetodoUnico,
                    'monto'          => $montoTotal
                ];
            }

            $cuentasValidadas = [];
            foreach ($distribucion as $itemDistribucion) {
                $idCuentaDistribucion = (int) ($itemDistribucion['id_cuenta'] ?? 0);
                if ($idCuentaDistribucion <= 0 || isset($cuentasValidadas[$idCuentaDistribucion])) {
                    continue;
                }
                $this->validarPermisoOperacionCuenta($idCuentaDistribucion, $origen);
                $cuentasValidadas[$idCuentaDistribucion] = true;
            }

            $userId = $this->obtenerUsuarioId();
            $db = Conexion::get();
            $db->beginTransaction();

            $montoRegistrado = 0.0;
            foreach ($distribucion as $idx => $item) {
                $esUltimo = ($idx === count($distribucion) - 1);
                $montoParcial = $esUltimo
                    ? round($montoTotal - $montoRegistrado, 4)
                    : round((float) $item['monto'], 4);

                if ($montoParcial <= 0) {
                    throw new RuntimeException('El monto parcial del método de cobro no es válido.');
                }

                $payload = $payloadBase;
                $payload['id_cuenta'] = (int) $item['id_cuenta'];
                $payload['id_metodo_pago'] = (int) $item['id_metodo_pago'];
                $payload['monto'] = $montoParcial;

                if ($naturalezaPago === 'MIXTO') {
                    if ($montoTotal <= 0) {
                        throw new RuntimeException('Monto total inválido para cobro mixto.');
                    }
                    if ($esUltimo) {
                        $montoCapitalParcial = round($montoCapitalTotal - ($payloadBase['_capital_asignado'] ?? 0), 4);
                        $montoInteresParcial = round($montoInteresTotal - ($payloadBase['_interes_asignado'] ?? 0), 4);
                    } else {
                        $proporcion = $montoParcial / $montoTotal;
                        $montoCapitalParcial = round($montoCapitalTotal * $proporcion, 4);
                        $montoInteresParcial = round($montoInteresTotal * $proporcion, 4);
                        $payloadBase['_capital_asignado'] = round(($payloadBase['_capital_asignado'] ?? 0) + $montoCapitalParcial, 4);
                        $payloadBase['_interes_asignado'] = round(($payloadBase['_interes_asignado'] ?? 0) + $montoInteresParcial, 4);
                    }
                    $payload['monto_capital'] = $montoCapitalParcial;
                    $payload['monto_interes'] = $montoInteresParcial;
                } elseif ($naturalezaPago === 'CAPITAL') {
                    $payload['monto_capital'] = $montoParcial;
                    $payload['monto_interes'] = 0;
                } elseif ($naturalezaPago === 'INTERES') {
                    $payload['monto_capital'] = 0;
                    $payload['monto_interes'] = $montoParcial;
                } else {
                    $payload['monto_capital'] = 0;
                    $payload['monto_interes'] = 0;
                }

                $this->movModel->registrar($payload, $userId);
                $montoRegistrado += $montoParcial;
            }

            if (abs(round($montoRegistrado, 4) - $montoTotal) > 0.0001) {
                throw new RuntimeException('No se pudo distribuir correctamente el monto entre los métodos de cobro.');
            }

            if ($origen === 'CXC') {
                $this->cxcModel->recalcularEstado($idOrigen, $userId);
            } else {
                $this->cxpModel->recalcularEstado($idOrigen, $userId);
            }

            $db->commit();
            redirect($redirectRuta . '?ok=1');
        } catch (Throwable $e) {
            if (Conexion::get()->inTransaction()) {
                Conexion::get()->rollBack();
            }
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

    private function esFechaValida(string $fecha): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $fecha));
        return checkdate($month, $day, $year);
    }

    private function listarClientesActivos(): array
    {
        $sql = 'SELECT DISTINCT t.id, t.nombre_completo
                FROM terceros t
                LEFT JOIN distribuidores d
                  ON d.id_tercero = t.id
                 AND d.deleted_at IS NULL
                WHERE t.estado = 1
                  AND t.deleted_at IS NULL
                  AND (t.es_cliente = 1 OR d.id_tercero IS NOT NULL)
                ORDER BY t.nombre_completo ASC';
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

    // ========================================================================
    // NUEVA FUNCIÓN: VERIFICAR SI EL TERCERO YA TIENE CUENTA Y SALDO DE AMORTIZACIONES
    // ========================================================================
    public function ajax_verificar_cuenta_tercero(): void
    {
        AuthMiddleware::handle();
        require_permiso('tesoreria.cxp.ver'); // O el permiso adecuado según tu lógica

        header('Content-Type: application/json; charset=utf-8');

        $idTercero = (int) ($_GET['id'] ?? 0);
        $tipo = strtoupper(trim((string) ($_GET['tipo'] ?? 'CLIENTE'))); // CLIENTE (cxc) o PROVEEDOR (cxp)

        if ($idTercero <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'ID inválido']);
            exit;
        }

        try {
            // Evaluamos a qué tabla mirar dependiendo de la naturaleza
            $tabla = $tipo === 'CLIENTE' ? 'tesoreria_cxc' : 'tesoreria_cxp';
            $columnaTercero = $tipo === 'CLIENTE' ? 'id_cliente' : 'id_proveedor';
            
            // 1. Verificamos si existe el registro de "SALDO INICIAL" (documento_referencia = Saldo inicial)
            // Asumimos que los saldos iniciales guardan un estado o una referencia particular.
            // Si en tu modelo usas un motivo corto específico para identificar el saldo inicial (ej: observacion contiene 'saldo migrado' o doc_ref), 
            // ajusta esta consulta. Por defecto, asumiremos que solo hay un gran registro consolidado o buscamos cualquiera abierto.
            
            $sqlCuenta = "SELECT id, monto_total, observaciones 
                          FROM {$tabla} 
                          WHERE {$columnaTercero} = :id_tercero
                          AND origen = 'MIGRACION'
                          AND deleted_at IS NULL 
                          ORDER BY id DESC
                          LIMIT 1";
            
            $stmt = Conexion::get()->prepare($sqlCuenta);
            $stmt->execute(['id_tercero' => $idTercero]);
            $cuentaExiste = $stmt->fetch(PDO::FETCH_ASSOC);

            $tieneCuenta = $cuentaExiste ? true : false;
            $totalAmortizaciones = 0;
            $detalleItems = [];
            $amortizaciones = [];
            $modoRegistro = 'DETALLE';
            $montoBaseReferencial = 0;

            if ($tieneCuenta) {
                $montoBaseReferencial = round((float) ($cuentaExiste['monto_total'] ?? 0), 4);
                // 2. Si tiene cuenta, buscamos cuánto ha pagado (amortizado) hasta ahora.
                $origenMov = $tipo === 'CLIENTE' ? 'CXC' : 'CXP';
                $tipoMov = $tipo === 'CLIENTE' ? 'COBRO' : 'PAGO';
                
                $sqlAmortizaciones = "SELECT COALESCE(SUM(monto), 0) as total_pagado 
                                      FROM tesoreria_movimientos 
                                      WHERE origen = :origen 
                                        AND id_origen = :id_cuenta
                                        AND tipo = :tipo
                                        AND estado = 1 
                                        AND deleted_at IS NULL";
                
                $stmtAmort = Conexion::get()->prepare($sqlAmortizaciones);
                $stmtAmort->execute([
                    'origen' => $origenMov,
                    'id_cuenta' => $cuentaExiste['id'],
                    'tipo' => $tipoMov
                ]);
                $totalAmortizaciones = (float) $stmtAmort->fetchColumn();

                $sqlDetalleAmort = "SELECT m.fecha,
                                           COALESCE(m.referencia, CONCAT('MOV-', m.id)) AS referencia,
                                           COALESCE(mp.nombre, 'N/D') AS metodo,
                                           m.monto
                                    FROM tesoreria_movimientos m
                                    LEFT JOIN tesoreria_metodos_pago mp ON mp.id = m.id_metodo_pago
                                    WHERE m.origen = :origen
                                      AND m.id_origen = :id_cuenta
                                      AND m.tipo = :tipo
                                      AND m.estado = 1
                                      AND m.deleted_at IS NULL
                                    ORDER BY m.fecha DESC, m.id DESC";

                $stmtDetalleAmort = Conexion::get()->prepare($sqlDetalleAmort);
                $stmtDetalleAmort->execute([
                    'origen' => $origenMov,
                    'id_cuenta' => $cuentaExiste['id'],
                    'tipo' => $tipoMov
                ]);
                $amortizaciones = $stmtDetalleAmort->fetchAll(PDO::FETCH_ASSOC) ?: [];

                // 3. Intentamos extraer los ítems guardados previamente si es que existen en el JSON de observaciones
                if (!empty($cuentaExiste['observaciones'])) {
                    $obsDecoded = json_decode($cuentaExiste['observaciones'], true);
                    if (is_array($obsDecoded)) {
                        if (!empty($obsDecoded['modo_registro']) && strtoupper((string) $obsDecoded['modo_registro']) === 'MANUAL') {
                            $modoRegistro = 'MANUAL';
                        }
                        if (isset($obsDecoded['detalle_items']) && is_array($obsDecoded['detalle_items'])) {
                            $detalleItems = $obsDecoded['detalle_items'];
                            if ($detalleItems !== []) {
                                $modoRegistro = 'DETALLE';
                            }
                        }
                        if (!empty($obsDecoded['amortizaciones_previas']) && is_array($obsDecoded['amortizaciones_previas'])) {
                            foreach ($obsDecoded['amortizaciones_previas'] as $amortLocal) {
                                $montoLocal = (float) ($amortLocal['monto'] ?? 0);
                                if ($montoLocal <= 0) {
                                    continue;
                                }
                                $totalAmortizaciones += $montoLocal;
                                $amortizaciones[] = [
                                    'fecha' => $amortLocal['fecha'] ?? '-',
                                    'referencia' => $amortLocal['referencia'] ?? '-',
                                    'metodo' => $amortLocal['metodo'] ?? '-',
                                    'monto' => $montoLocal,
                                ];
                            }
                        }
                    }
                }

                if ($modoRegistro === 'MANUAL') {
                    $montoBaseReferencial = round($montoBaseReferencial + $totalAmortizaciones, 4);
                }
            }

            echo json_encode([
                'ok' => true,
                'tiene_cuenta' => $tieneCuenta,
                'total_amortizaciones' => $totalAmortizaciones,
                'modo_registro' => $modoRegistro,
                'monto_base_referencial' => $montoBaseReferencial,
                'items_guardados' => $detalleItems,
                'amortizaciones' => $amortizaciones
            ]);

        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }
}
