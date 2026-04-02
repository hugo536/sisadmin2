<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/contabilidad/ContaPeriodoModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaParametrosModel.php';

class ContaAsientoModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $where = ['a.deleted_at IS NULL'];
        $params = [];
        if (!empty($filtros['id_periodo'])) {
            $where[] = 'a.id_periodo = :id_periodo';
            $params['id_periodo'] = (int)$filtros['id_periodo'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'a.fecha >= :fecha_desde';
            $params['fecha_desde'] = (string)$filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'a.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = (string)$filtros['fecha_hasta'];
        }

        $pagina = max(1, (int)($filtros['pagina'] ?? 1));
        $porPagina = max(1, min(200, (int)($filtros['por_pagina'] ?? 30)));
        $offset = ($pagina - 1) * $porPagina;

        $sql = 'SELECT a.*, p.anio, p.mes,
                       (SELECT COALESCE(SUM(d.debe),0) FROM conta_asientos_detalle d WHERE d.id_asiento = a.id AND d.deleted_at IS NULL) AS total_debe,
                       (SELECT COALESCE(SUM(d.haber),0) FROM conta_asientos_detalle d WHERE d.id_asiento = a.id AND d.deleted_at IS NULL) AS total_haber
                FROM conta_asientos a
                INNER JOIN conta_periodos p ON p.id = a.id_periodo
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY a.fecha DESC, a.id DESC
                LIMIT :limit OFFSET :offset';
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue('limit', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function contar(array $filtros = []): int
    {
        $where = ['a.deleted_at IS NULL'];
        $params = [];
        if (!empty($filtros['id_periodo'])) {
            $where[] = 'a.id_periodo = :id_periodo';
            $params['id_periodo'] = (int)$filtros['id_periodo'];
        }
        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'a.fecha >= :fecha_desde';
            $params['fecha_desde'] = (string)$filtros['fecha_desde'];
        }
        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'a.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = (string)$filtros['fecha_hasta'];
        }

        $stmt = $this->db()->prepare('SELECT COUNT(1) FROM conta_asientos a WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function obtenerDetalle(int $idAsiento): array
    {
        $stmt = $this->db()->prepare('SELECT d.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre, cc.codigo AS centro_costo_codigo
                                      FROM conta_asientos_detalle d
                                      INNER JOIN conta_cuentas c ON c.id = d.id_cuenta
                                      LEFT JOIN conta_centros_costo cc ON cc.id = d.id_centro_costo
                                      WHERE d.id_asiento = :id AND d.deleted_at IS NULL
                                      ORDER BY d.id ASC');
        $stmt->execute(['id' => $idAsiento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearManual(array $cabecera, array $lineas, int $userId): int
    {
        return $this->crearAsiento($this->db(), $cabecera, $lineas, $userId);
    }

    public function anularConReversion(int $idAsiento, int $userId): int
    {
        $db = $this->db();
        $localTx = !$db->inTransaction();
        if ($localTx) $db->beginTransaction();
        
        try {
            $stmt = $db->prepare('SELECT * FROM conta_asientos WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $idAsiento]);
            $asiento = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$asiento) throw new RuntimeException('Asiento no encontrado.');
            if ((string)$asiento['estado'] === 'ANULADO') throw new RuntimeException('El asiento ya está anulado.');

            $periodo = (new ContaPeriodoModel())->obtenerPeriodoPorFecha((string)$asiento['fecha']);
            if (!$periodo || (string)$periodo['estado'] !== 'ABIERTO') {
                throw new RuntimeException('No se puede anular un asiento de periodo cerrado.');
            }

            $detalle = $this->obtenerDetalle($idAsiento);
            $lineas = [];
            foreach ($detalle as $d) {
                $lineas[] = [
                    'id_cuenta' => (int)$d['id_cuenta'],
                    'debe' => (float)$d['haber'],
                    'haber' => (float)$d['debe'],
                    'id_tercero' => (int)($d['id_tercero'] ?? 0),
                    'referencia' => 'REV-' . $idAsiento,
                ];
            }

            $idReversion = $this->crearAsiento($db, [
                'codigo' => $this->siguienteCodigo($db, 'RV'),
                'fecha' => (string)$asiento['fecha'],
                'id_periodo' => (int)$asiento['id_periodo'],
                'glosa' => 'Reversión de asiento ' . (string)$asiento['codigo'],
                'origen_modulo' => 'MANUAL',
                'id_origen' => null,
                'estado' => 'REGISTRADO',
            ], $lineas, $userId);

            $db->prepare('UPDATE conta_asientos SET estado = "ANULADO", updated_by = :user, updated_at = NOW() WHERE id = :id')
               ->execute(['id' => $idAsiento, 'user' => $userId]);

            if ($localTx) $db->commit();
            return $idReversion;
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    /**
     * Registro automático optimizado para vinculación dinámica
     */
    public function registrarAutomaticoTesoreria(PDO $db, array $movimiento, int $userId): int
    {
        $paramModel = new ContaParametrosModel();
        $mapa = $paramModel->obtenerMapa();

        // 1. Identificar Cuenta de Tesorería vinculada desde Contabilidad > Plan Contable.
        $idCuentaTesoreria = 0;
        $idCuentaTesoreriaOp = (int)($movimiento['id_cuenta_tesoreria'] ?? 0);
        if ($idCuentaTesoreriaOp > 0) {
            $stmt = $db->prepare('SELECT id_cuenta_contable FROM tesoreria_cuentas WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmt->execute(['id' => $idCuentaTesoreriaOp]);
            $idCuentaTesoreria = (int)$stmt->fetchColumn();
        }

        if ($idCuentaTesoreria <= 0) {
            throw new RuntimeException('La cuenta de tesorería seleccionada no tiene cuenta contable vinculada. Configure la vinculación en Contabilidad > Plan Contable.');
        }

        // 2. Identificar Cuenta de Contrapartida (CXC o CXP)
        $claveContra = trim((string)($movimiento['clave_contra'] ?? ''));
        if ($claveContra === '') {
            $claveContra = ((string)$movimiento['tipo'] === 'COBRO') ? 'CTA_CXC' : 'CTA_CXP';
        }
        $idCuentaContra = (int)($mapa[$claveContra] ?? 0);

        if ($idCuentaContra <= 0) {
            $idCuentaContra = $this->resolverCuentaParametroFaltante($db, $claveContra);
            if ($idCuentaContra) $paramModel->guardar($claveContra, $idCuentaContra, $userId);
            else throw new RuntimeException("Falta parametrización contable: $claveContra");
        }

        // 3. Validar Periodo
        $periodo = (new ContaPeriodoModel())->obtenerPeriodoPorFecha((string)$movimiento['fecha']);
        if (!$periodo || (string)$periodo['estado'] !== 'ABIERTO') {
            throw new RuntimeException('Periodo contable cerrado o inexistente.');
        }

        $monto = round((float)$movimiento['monto'], 4);
        $idTercero = (int)($movimiento['id_tercero'] ?? 0);
        $lineas = [];
        $naturaleza = strtoupper(trim((string) ($movimiento['naturaleza_pago'] ?? 'DOCUMENTO')));
        $montoCapital = round((float) ($movimiento['monto_capital'] ?? $monto), 4);
        $montoInteres = round((float) ($movimiento['monto_interes'] ?? 0), 4);
        $idCentroCosto = (int) ($movimiento['id_centro_costo'] ?? 0);

        if ((string)$movimiento['tipo'] === 'COBRO') {
            // Ingresa Dinero (Debe) / Baja Deuda Cliente (Haber)
            $lineas[] = ['id_cuenta' => $idCuentaTesoreria, 'debe' => $monto, 'haber' => 0, 'id_tercero' => $idTercero];
            $lineas[] = ['id_cuenta' => $idCuentaContra, 'debe' => 0, 'haber' => $monto, 'id_tercero' => $idTercero];
        } else {
            if ($naturaleza === 'INTERES' || $naturaleza === 'MIXTO') {
                $idCuentaGastoFin = (int)($mapa['CTA_GASTO_FINANCIERO'] ?? 0);
                if ($idCuentaGastoFin <= 0) {
                    $idCuentaGastoFin = $this->resolverCuentaParametroFaltante($db, 'CTA_GASTO_FINANCIERO') ?? 0;
                    if ($idCuentaGastoFin > 0) {
                        $paramModel->guardar('CTA_GASTO_FINANCIERO', $idCuentaGastoFin, $userId);
                    }
                }
                if ($idCuentaGastoFin <= 0) {
                    throw new RuntimeException('Falta parametrización contable de CTA_GASTO_FINANCIERO para registrar intereses.');
                }

                if ($naturaleza === 'INTERES') {
                    $montoCapital = 0;
                    $montoInteres = $monto;
                } elseif ($montoInteres <= 0) {
                    $montoInteres = max(0, $monto - $montoCapital);
                }

                if ($montoCapital > 0) {
                    $lineas[] = ['id_cuenta' => $idCuentaContra, 'debe' => $montoCapital, 'haber' => 0, 'id_tercero' => $idTercero];
                }
                if ($montoInteres > 0) {
                    $lineas[] = ['id_cuenta' => $idCuentaGastoFin, 'debe' => $montoInteres, 'haber' => 0, 'id_tercero' => $idTercero, 'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : 0];
                }
                $lineas[] = ['id_cuenta' => $idCuentaTesoreria, 'debe' => 0, 'haber' => $monto, 'id_tercero' => $idTercero];
            } else {
                // Baja Deuda Proveedor (Debe) / Sale Dinero (Haber)
                $lineas[] = ['id_cuenta' => $idCuentaContra, 'debe' => $monto, 'haber' => 0, 'id_tercero' => $idTercero];
                $lineas[] = ['id_cuenta' => $idCuentaTesoreria, 'debe' => 0, 'haber' => $monto, 'id_tercero' => $idTercero];
            }
        }

        return $this->crearAsiento($db, [
            'codigo' => $this->siguienteCodigo($db, 'AS'),
            'fecha' => (string)$movimiento['fecha'],
            'id_periodo' => (int)$periodo['id'],
            'glosa' => ((string)$movimiento['tipo'] === 'COBRO' ? 'Cobro' : 'Pago') . ' tesorería #' . (int)$movimiento['id_movimiento'],
            'origen_modulo' => 'TESORERIA',
            'id_origen' => (int)$movimiento['id_movimiento'],
            'estado' => 'REGISTRADO',
        ], $lineas, $userId);
    }


    public function registrarAutomaticoProduccion(PDO $db, array $movimiento, int $userId): int
    {
        $idOrden = (int) ($movimiento['id_orden'] ?? 0);
        $fecha = (string) ($movimiento['fecha'] ?? date('Y-m-d'));
        $codigoOrden = trim((string) ($movimiento['codigo_orden'] ?? ''));
        $costoMd = round((float) ($movimiento['costo_md'] ?? 0), 4);
        $costoMod = round((float) ($movimiento['costo_mod'] ?? 0), 4);
        $costoCif = round((float) ($movimiento['costo_cif'] ?? 0), 4);
        $costoTotal = round((float) ($movimiento['costo_total'] ?? ($costoMd + $costoMod + $costoCif)), 4);
        
        // NUEVO: Atrapamos el Centro de Costo que viene de Producción
        $idCentroCosto = (int) ($movimiento['id_centro_costo'] ?? 0);

        if ($idOrden <= 0 || $fecha === '' || $costoTotal <= 0) {
            throw new RuntimeException('Datos insuficientes para registrar asiento automático de producción.');
        }

        $stmtExiste = $db->prepare('SELECT id FROM conta_asientos
                                    WHERE origen_modulo = "PRODUCCION"
                                      AND id_origen = :id_origen
                                      AND deleted_at IS NULL
                                    LIMIT 1');
        $stmtExiste->execute(['id_origen' => $idOrden]);
        $idExistente = (int) ($stmtExiste->fetchColumn() ?: 0);
        if ($idExistente > 0) {
            return $idExistente;
        }

        $periodo = (new ContaPeriodoModel())->obtenerPeriodoPorFecha($fecha);
        if (!$periodo || (string) ($periodo['estado'] ?? '') !== 'ABIERTO') {
            throw new RuntimeException('Periodo contable cerrado o inexistente para la fecha de cierre de producción.');
        }

        $paramModel = new ContaParametrosModel();
        $mapa = $paramModel->obtenerMapa();

        $idCtaInvPT = (int) ($mapa['CTA_INV_PRODUCTO_TERMINADO'] ?? 0);
        if ($idCtaInvPT <= 0) {
            $idCtaInvPT = $this->resolverCuentaParametroFaltante($db, 'CTA_INV_PRODUCTO_TERMINADO') ?? 0;
            if ($idCtaInvPT > 0) {
                $paramModel->guardar('CTA_INV_PRODUCTO_TERMINADO', $idCtaInvPT, $userId);
            }
        }

        $idCtaInvMP = (int) ($mapa['CTA_INV_MATERIA_PRIMA'] ?? 0);
        if ($idCtaInvMP <= 0) {
            $idCtaInvMP = $this->resolverCuentaParametroFaltante($db, 'CTA_INV_MATERIA_PRIMA') ?? 0;
            if ($idCtaInvMP > 0) {
                $paramModel->guardar('CTA_INV_MATERIA_PRIMA', $idCtaInvMP, $userId);
            }
        }

        $idCtaNomina = (int) ($mapa['CTA_NOMINA_POR_PAGAR'] ?? 0);
        if ($idCtaNomina <= 0) {
            $idCtaNomina = $this->resolverCuentaParametroFaltante($db, 'CTA_NOMINA_POR_PAGAR') ?? 0;
            if ($idCtaNomina > 0) {
                $paramModel->guardar('CTA_NOMINA_POR_PAGAR', $idCtaNomina, $userId);
            }
        }

        $idCtaCif = (int) ($mapa['CTA_CIF_POR_APLICAR'] ?? 0);
        if ($idCtaCif <= 0) {
            $idCtaCif = $this->resolverCuentaParametroFaltante($db, 'CTA_CIF_POR_APLICAR') ?? 0;
            if ($idCtaCif > 0) {
                $paramModel->guardar('CTA_CIF_POR_APLICAR', $idCtaCif, $userId);
            }
        }

        if ($idCtaInvPT <= 0 || $idCtaInvMP <= 0 || ($costoMod > 0 && $idCtaNomina <= 0) || ($costoCif > 0 && $idCtaCif <= 0)) {
            throw new RuntimeException('Falta parametrización contable para producción (inventarios/MOD/CIF).');
        }

        $lineas = [];
        // NUEVO: Asignamos el Centro de Costo a todas las líneas del asiento
        $lineas[] = ['id_cuenta' => $idCtaInvPT, 'debe' => $costoTotal, 'haber' => 0, 'id_centro_costo' => $idCentroCosto];

        if ($costoMd > 0) {
            $lineas[] = ['id_cuenta' => $idCtaInvMP, 'debe' => 0, 'haber' => $costoMd, 'id_centro_costo' => $idCentroCosto];
        }
        if ($costoMod > 0) {
            $lineas[] = ['id_cuenta' => $idCtaNomina, 'debe' => 0, 'haber' => $costoMod, 'id_centro_costo' => $idCentroCosto];
        }
        if ($costoCif > 0) {
            $lineas[] = ['id_cuenta' => $idCtaCif, 'debe' => 0, 'haber' => $costoCif, 'id_centro_costo' => $idCentroCosto];
        }

        $sumaHaber = 0.0;
        foreach ($lineas as $ln) {
            $sumaHaber += (float) ($ln['haber'] ?? 0);
        }
        $delta = round($costoTotal - $sumaHaber, 4);
        if (abs($delta) > 0.0001) {
            for ($i = count($lineas) - 1; $i >= 0; $i--) {
                if ((float) ($lineas[$i]['haber'] ?? 0) > 0) {
                    $lineas[$i]['haber'] = round((float) $lineas[$i]['haber'] + $delta, 4);
                    break;
                }
            }
        }

        return $this->crearAsiento($db, [
            'codigo' => $this->siguienteCodigo($db, 'PRD'),
            'fecha' => $fecha,
            'id_periodo' => (int) $periodo['id'],
            'glosa' => 'Cierre automático OP ' . ($codigoOrden !== '' ? $codigoOrden : ('#' . $idOrden)),
            'origen_modulo' => 'PRODUCCION',
            'id_origen' => $idOrden,
            'estado' => 'REGISTRADO',
        ], $lineas, $userId);
    }

    public function registrarAutomaticoGasto(PDO $db, array $movimiento, int $userId): int
    {
        $idGasto = (int) ($movimiento['id_gasto'] ?? 0);
        $fecha = (string) ($movimiento['fecha'] ?? date('Y-m-d'));
        $idProveedor = (int) ($movimiento['id_proveedor'] ?? 0);
        $total = round((float) ($movimiento['total'] ?? 0), 4);
        $idCuentaGasto = (int) ($movimiento['id_cuenta_gasto'] ?? 0);
        $idCentroCosto = (int) ($movimiento['id_centro_costo'] ?? 0);

        if ($idGasto <= 0 || $idProveedor <= 0 || $total <= 0 || $idCuentaGasto <= 0) {
            throw new RuntimeException('Datos insuficientes para registrar asiento automático de gasto.');
        }

        $stmtExiste = $db->prepare('SELECT id FROM conta_asientos WHERE origen_modulo = "GASTOS" AND id_origen = :id_origen AND deleted_at IS NULL LIMIT 1');
        $stmtExiste->execute(['id_origen' => $idGasto]);
        $idExistente = (int) ($stmtExiste->fetchColumn() ?: 0);
        if ($idExistente > 0) {
            return $idExistente;
        }

        $periodo = (new ContaPeriodoModel())->obtenerPeriodoPorFecha($fecha);
        if (!$periodo || (string) ($periodo['estado'] ?? '') !== 'ABIERTO') {
            throw new RuntimeException('Periodo contable cerrado o inexistente para el registro de gasto.');
        }

        $mapa = (new ContaParametrosModel())->obtenerMapa();
        $idCuentaCxp = (int) ($mapa['CTA_CXP'] ?? 0);
        if ($idCuentaCxp <= 0) {
            $idCuentaCxp = $this->resolverCuentaParametroFaltante($db, 'CTA_CXP') ?? 0;
        }
        if ($idCuentaCxp <= 0) {
            throw new RuntimeException('Falta parametrización contable de CTA_CXP para registrar el gasto.');
        }

        return $this->crearAsiento($db, [
            'codigo' => $this->siguienteCodigo($db, 'AS'),
            'fecha' => $fecha,
            'id_periodo' => (int) $periodo['id'],
            'glosa' => (string) ($movimiento['glosa'] ?? ('Gasto #' . $idGasto)),
            'origen_modulo' => 'GASTOS',
            'id_origen' => $idGasto,
            'estado' => 'REGISTRADO',
        ], [
            ['id_cuenta' => $idCuentaGasto, 'debe' => $total, 'haber' => 0, 'id_tercero' => $idProveedor, 'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : 0],
            ['id_cuenta' => $idCuentaCxp, 'debe' => 0, 'haber' => $total, 'id_tercero' => $idProveedor],
        ], $userId);
    }

    private function resolverCuentaParametroFaltante(PDO $db, string $clave): ?int
    {
        $condiciones = null;
        if ($clave === 'CTA_CAJA_DEFECTO') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%CAJA%" OR UPPER(c.nombre) LIKE "%EFECTIVO%" OR UPPER(c.nombre) LIKE "%BANCO%" OR c.codigo LIKE "10%")',
                'tipo_fallback' => 'ACTIVO',
            ];
        } elseif ($clave === 'CTA_CXC') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%CUENTAS POR COBRAR%" OR UPPER(c.nombre) LIKE "%CLIENT%" OR c.codigo LIKE "12%")',
                'tipo_fallback' => 'ACTIVO',
            ];
        } elseif ($clave === 'CTA_CXP') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%CUENTAS POR PAGAR%" OR UPPER(c.nombre) LIKE "%PROVEEDOR%" OR c.codigo LIKE "42%")',
                'tipo_fallback' => 'PASIVO',
            ];
        } elseif ($clave === 'CTA_NOMINA_POR_PAGAR') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%REMUNERACIONES POR PAGAR%" OR UPPER(c.nombre) LIKE "%SUELDOS POR PAGAR%" OR UPPER(c.nombre) LIKE "%PLANILLA POR PAGAR%" OR c.codigo LIKE "41%" OR c.codigo LIKE "46%")',
                'tipo_fallback' => 'PASIVO',
            ];
        } elseif ($clave === 'CTA_INV_PRODUCTO_TERMINADO') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%PRODUCTO TERMINADO%" OR UPPER(c.nombre) LIKE "%INVENTARIO PRODUCTO%" OR c.codigo LIKE "20%")',
                'tipo_fallback' => 'ACTIVO',
            ];
        } elseif ($clave === 'CTA_INV_MATERIA_PRIMA') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%MATERIA PRIMA%" OR UPPER(c.nombre) LIKE "%INVENTARIO MATERIA%" OR UPPER(c.nombre) LIKE "%INSUMO%" OR c.codigo LIKE "20%")',
                'tipo_fallback' => 'ACTIVO',
            ];
        } elseif ($clave === 'CTA_GASTO_FINANCIERO') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%GASTO FINANCIER%" OR UPPER(c.nombre) LIKE "%INTERES%" OR c.codigo LIKE "67%")',
                'tipo_fallback' => 'GASTO',
            ];
        } elseif ($clave === 'CTA_CIF_POR_APLICAR') {
            $condiciones = [
                'where' => '(UPPER(c.nombre) LIKE "%CIF%" OR UPPER(c.nombre) LIKE "%COSTO INDIRECTO%" OR UPPER(c.nombre) LIKE "%COSTOS INDIRECTOS%")',
                'tipo_fallback' => 'PASIVO',
            ];
        }

        if (!$condiciones) return null;

        $sql = "SELECT c.id FROM conta_cuentas c WHERE c.deleted_at IS NULL AND c.estado = 1 AND c.permite_movimiento = 1 AND {$condiciones['where']} ORDER BY c.codigo ASC LIMIT 1";
        $id = (int)$db->query($sql)->fetchColumn();
        
        if (!$id) {
            $stmt = $db->prepare("SELECT id FROM conta_cuentas WHERE deleted_at IS NULL AND estado = 1 AND permite_movimiento = 1 AND tipo = :tipo ORDER BY codigo ASC LIMIT 1");
            $stmt->execute(['tipo' => $condiciones['tipo_fallback']]);
            $id = (int)$stmt->fetchColumn();
        }

        return $id > 0 ? $id : null;
    }

    private function crearAsiento(PDO $db, array $cabecera, array $lineas, int $userId): int
    {
        $localTx = !$db->inTransaction();
        if ($localTx) $db->beginTransaction();
        try {
            $this->validarLineas($db, $lineas);

            // CORRECCIÓN 1: Marcadores únicos para :created_by y :updated_by
            $stmt = $db->prepare('INSERT INTO conta_asientos (codigo, fecha, id_periodo, glosa, origen_modulo, id_origen, estado, created_by, updated_by, created_at, updated_at)
                                  VALUES (:codigo, :fecha, :id_periodo, :glosa, :origen_modulo, :id_origen, :estado, :created_by, :updated_by, NOW(), NOW())');
            $stmt->execute([
                'codigo' => $cabecera['codigo'],
                'fecha' => $cabecera['fecha'],
                'id_periodo' => (int)$cabecera['id_periodo'],
                'glosa' => $cabecera['glosa'],
                'origen_modulo' => $cabecera['origen_modulo'],
                'id_origen' => $cabecera['id_origen'],
                'estado' => $cabecera['estado'],
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $idAsiento = (int)$db->lastInsertId();

            // CORRECCIÓN 2: Marcadores únicos en el detalle también
            $stmtDet = $db->prepare('INSERT INTO conta_asientos_detalle (id_asiento, id_cuenta, id_centro_costo, debe, haber, id_tercero, referencia, created_by, updated_by, created_at, updated_at)
                                     VALUES (:id_asiento, :id_cuenta, :id_centro_costo, :debe, :haber, :id_tercero, :referencia, :created_by, :updated_by, NOW(), NOW())');
            foreach ($lineas as $l) {
                $stmtDet->execute([
                    'id_asiento' => $idAsiento,
                    'id_cuenta' => (int)$l['id_cuenta'],
                    'id_centro_costo' => !empty($l['id_centro_costo']) ? (int)$l['id_centro_costo'] : null,
                    'debe' => round((float)$l['debe'], 4),
                    'haber' => round((float)$l['haber'], 4),
                    'id_tercero' => !empty($l['id_tercero']) ? (int)$l['id_tercero'] : null,
                    'referencia' => $cabecera['id_origen'] ? 'MOV-'.$cabecera['id_origen'] : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }
            if ($localTx) $db->commit();
            return $idAsiento;
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function validarLineas(PDO $db, array $lineas): void
    {
        if (count($lineas) < 2) throw new RuntimeException('El asiento requiere al menos dos líneas.');
        $sumDebe = 0.0;
        $sumHaber = 0.0;
        foreach ($lineas as $l) {
            $sumDebe += round((float)$l['debe'], 4);
            $sumHaber += round((float)$l['haber'], 4);
        }
        if (round($sumDebe, 4) !== round($sumHaber, 4)) throw new RuntimeException('Asiento descuadrado.');
    }

    private function siguienteCodigo(PDO $db, string $prefijo): string
    {
        $anio = date('Y');
        $stmt = $db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(codigo, "-", -1) AS UNSIGNED)), 0) FROM conta_asientos WHERE YEAR(fecha) = :anio AND codigo LIKE :like_codigo AND deleted_at IS NULL');
        $stmt->execute(['anio' => $anio, 'like_codigo' => $prefijo . '-' . $anio . '-%']);
        return sprintf('%s-%s-%06d', $prefijo, $anio, (int)$stmt->fetchColumn() + 1);
    }
}
