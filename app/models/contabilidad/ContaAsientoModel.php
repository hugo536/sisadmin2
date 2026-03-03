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
        if ($localTx) {
            $db->beginTransaction();
        }
        try {
            $stmt = $db->prepare('SELECT * FROM conta_asientos WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $stmt->execute(['id' => $idAsiento]);
            $asiento = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$asiento) {
                throw new RuntimeException('Asiento no encontrado.');
            }
            if ((string)$asiento['estado'] === 'ANULADO') {
                throw new RuntimeException('El asiento ya está anulado.');
            }

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

            $codigoRev = $this->siguienteCodigo($db, 'RV');
            $idReversion = $this->crearAsiento($db, [
                'codigo' => $codigoRev,
                'fecha' => (string)$asiento['fecha'],
                'id_periodo' => (int)$asiento['id_periodo'],
                'glosa' => 'Reversión de asiento ' . (string)$asiento['codigo'],
                'origen_modulo' => 'MANUAL',
                'id_origen' => null,
                'estado' => 'REGISTRADO',
            ], $lineas, $userId);

            $upd = $db->prepare('UPDATE conta_asientos SET estado = "ANULADO", updated_by = :user, updated_at = NOW() WHERE id = :id');
            $upd->execute(['id' => $idAsiento, 'user' => $userId]);

            if ($localTx) {
                $db->commit();
            }
            return $idReversion;
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarAutomaticoTesoreria(PDO $db, array $movimiento, int $userId): int
    {
        $paramModel = new ContaParametrosModel();
        $mapa = $paramModel->obtenerMapa();

        $req = ['CTA_CAJA_DEFECTO'];
        if ((string)$movimiento['tipo'] === 'COBRO') {
            $req[] = 'CTA_CXC';
        } else {
            $req[] = 'CTA_CXP';
        }
        foreach ($req as $clave) {
            if (!isset($mapa[$clave])) {
                $idCuentaResuelta = $this->resolverCuentaParametroFaltante($db, $clave);
                if ($idCuentaResuelta !== null) {
                    $paramModel->guardar($clave, $idCuentaResuelta, $userId);
                    $mapa[$clave] = $idCuentaResuelta;
                    continue;
                }

                throw new RuntimeException('Falta parametrización contable obligatoria: ' . $clave . '. Configure este parámetro en Contabilidad > Plan Contable > Parámetros.');
            }
        }

        $periodoModel = new ContaPeriodoModel();
        $periodo = $periodoModel->obtenerPeriodoPorFecha((string)$movimiento['fecha']);
        if (!$periodo || (string)$periodo['estado'] !== 'ABIERTO') {
            throw new RuntimeException('Periodo contable cerrado o inexistente para la fecha del movimiento.');
        }

        $monto = round((float)$movimiento['monto'], 4);
        $lineas = [];
        if ((string)$movimiento['tipo'] === 'COBRO') {
            $lineas[] = ['id_cuenta' => $mapa['CTA_CAJA_DEFECTO'], 'debe' => $monto, 'haber' => 0];
            $lineas[] = ['id_cuenta' => $mapa['CTA_CXC'], 'debe' => 0, 'haber' => $monto];
        } else {
            $lineas[] = ['id_cuenta' => $mapa['CTA_CXP'], 'debe' => $monto, 'haber' => 0];
            $lineas[] = ['id_cuenta' => $mapa['CTA_CAJA_DEFECTO'], 'debe' => 0, 'haber' => $monto];
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

    private function resolverCuentaParametroFaltante(PDO $db, string $clave): ?int
    {
        $condiciones = match ($clave) {
            'CTA_CAJA_DEFECTO' => [
                'prioridad' => 'CASE
                    WHEN UPPER(c.nombre) LIKE "%CAJA%" THEN 0
                    WHEN UPPER(c.nombre) LIKE "%EFECTIVO%" THEN 0
                    WHEN UPPER(c.nombre) LIKE "%BANCO%" THEN 1
                    WHEN c.codigo LIKE "10%" THEN 2
                    ELSE 3
                END',
                'where' => '(UPPER(c.nombre) LIKE "%CAJA%" OR UPPER(c.nombre) LIKE "%EFECTIVO%" OR UPPER(c.nombre) LIKE "%BANCO%" OR c.codigo LIKE "10%")',
                'tipo_fallback' => 'ACTIVO',
            ],
            'CTA_CXC' => [
                'prioridad' => 'CASE
                    WHEN UPPER(c.nombre) LIKE "%CUENTAS POR COBRAR%" THEN 0
                    WHEN UPPER(c.nombre) LIKE "%CLIENT%" THEN 1
                    WHEN c.codigo LIKE "12%" THEN 2
                    ELSE 3
                END',
                'where' => '(UPPER(c.nombre) LIKE "%CUENTAS POR COBRAR%" OR UPPER(c.nombre) LIKE "%CLIENT%" OR c.codigo LIKE "12%")',
                'tipo_fallback' => 'ACTIVO',
            ],
            'CTA_CXP' => [
                'prioridad' => 'CASE
                    WHEN UPPER(c.nombre) LIKE "%CUENTAS POR PAGAR%" THEN 0
                    WHEN UPPER(c.nombre) LIKE "%PROVEEDOR%" THEN 1
                    WHEN c.codigo LIKE "42%" THEN 2
                    ELSE 3
                END',
                'where' => '(UPPER(c.nombre) LIKE "%CUENTAS POR PAGAR%" OR UPPER(c.nombre) LIKE "%PROVEEDOR%" OR c.codigo LIKE "42%")',
                'tipo_fallback' => 'PASIVO',
            ],
            default => null,
        };

        if ($condiciones === null) {
            return null;
        }

        $sql = 'SELECT c.id
                FROM conta_cuentas c
                WHERE c.deleted_at IS NULL
                  AND c.estado = 1
                  AND c.permite_movimiento = 1
                  AND ' . $condiciones['where'] . '
                ORDER BY ' . $condiciones['prioridad'] . ', c.codigo ASC, c.id ASC
                LIMIT 1';

        $stmt = $db->query($sql);
        $idCuenta = (int)($stmt->fetchColumn() ?: 0);
        if ($idCuenta > 0) {
            return $idCuenta;
        }

        $sqlFallback = 'SELECT c.id
                        FROM conta_cuentas c
                        WHERE c.deleted_at IS NULL
                          AND c.estado = 1
                          AND c.permite_movimiento = 1
                          AND c.tipo = :tipo
                        ORDER BY c.codigo ASC, c.id ASC
                        LIMIT 1';
        $stmtFallback = $db->prepare($sqlFallback);
        $stmtFallback->execute(['tipo' => (string)$condiciones['tipo_fallback']]);
        $idCuenta = (int)($stmtFallback->fetchColumn() ?: 0);

        return $idCuenta > 0 ? $idCuenta : null;
    }

    private function crearAsiento(PDO $db, array $cabecera, array $lineas, int $userId): int
    {
        $localTx = !$db->inTransaction();
        if ($localTx) {
            $db->beginTransaction();
        }
        try {
            $this->validarLineas($db, $lineas);

            $periodo = (new ContaPeriodoModel())->obtenerPorId((int)$cabecera['id_periodo']);
            if (!$periodo) {
                throw new RuntimeException('Periodo contable inexistente.');
            }
            if ((string)$periodo['estado'] !== 'ABIERTO') {
                throw new RuntimeException('No se permiten asientos en periodos cerrados.');
            }
            $fecha = (string)$cabecera['fecha'];
            if ($fecha < (string)$periodo['fecha_inicio'] || $fecha > (string)$periodo['fecha_fin']) {
                throw new RuntimeException('La fecha del asiento no pertenece al periodo seleccionado.');
            }

            $stmt = $db->prepare('INSERT INTO conta_asientos (codigo, fecha, id_periodo, glosa, origen_modulo, id_origen, estado, created_by, updated_by, created_at, updated_at)
                                  VALUES (:codigo, :fecha, :id_periodo, :glosa, :origen_modulo, :id_origen, :estado, :user, :user, NOW(), NOW())');
            $stmt->execute([
                'codigo' => $cabecera['codigo'] ?? $this->siguienteCodigo($db, 'AS'),
                'fecha' => $cabecera['fecha'],
                'id_periodo' => (int)$cabecera['id_periodo'],
                'glosa' => $cabecera['glosa'],
                'origen_modulo' => $cabecera['origen_modulo'] ?? 'MANUAL',
                'id_origen' => $cabecera['id_origen'] ?? null,
                'estado' => $cabecera['estado'] ?? 'REGISTRADO',
                'user' => $userId,
            ]);
            $idAsiento = (int)$db->lastInsertId();

            $stmtDet = $db->prepare('INSERT INTO conta_asientos_detalle (id_asiento, id_cuenta, id_centro_costo, debe, haber, id_tercero, referencia, created_by, updated_by, created_at, updated_at)
                                     VALUES (:id_asiento, :id_cuenta, :id_centro_costo, :debe, :haber, :id_tercero, :referencia, :user, :user, NOW(), NOW())');
            foreach ($lineas as $l) {
                $stmtDet->execute([
                    'id_asiento' => $idAsiento,
                    'id_cuenta' => (int)$l['id_cuenta'],
                    'id_centro_costo' => !empty($l['id_centro_costo']) ? (int)$l['id_centro_costo'] : null,
                    'debe' => round((float)$l['debe'], 4),
                    'haber' => round((float)$l['haber'], 4),
                    'id_tercero' => !empty($l['id_tercero']) ? (int)$l['id_tercero'] : null,
                    'referencia' => $l['referencia'] ?? null,
                    'user' => $userId,
                ]);
            }
            if ($localTx) {
                $db->commit();
            }
            return $idAsiento;
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function validarLineas(PDO $db, array $lineas): void
    {
        if (count($lineas) < 2) {
            throw new RuntimeException('El asiento requiere al menos dos líneas.');
        }
        $sumDebe = 0.0;
        $sumHaber = 0.0;
        $stmt = $db->prepare('SELECT permite_movimiento, estado FROM conta_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        foreach ($lineas as $l) {
            $debe = round((float)$l['debe'], 4);
            $haber = round((float)$l['haber'], 4);
            if (($debe > 0 && $haber > 0) || ($debe <= 0 && $haber <= 0)) {
                throw new RuntimeException('Cada línea debe tener valor en Debe o Haber, pero no ambos.');
            }
            $stmt->execute(['id' => (int)$l['id_cuenta']]);
            $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cuenta || (int)$cuenta['estado'] !== 1 || (int)$cuenta['permite_movimiento'] !== 1) {
                throw new RuntimeException('Solo se permiten cuentas activas de movimiento en los asientos.');
            }
            $sumDebe += $debe;
            $sumHaber += $haber;
        }

        if (round($sumDebe, 4) !== round($sumHaber, 4)) {
            throw new RuntimeException('El asiento no está balanceado: Debe debe ser igual a Haber.');
        }
    }

    private function siguienteCodigo(PDO $db, string $prefijo): string
    {
        $anio = date('Y');
        $stmt = $db->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(codigo, "-", -1) AS UNSIGNED)), 0)
                              FROM conta_asientos
                              WHERE YEAR(fecha) = :anio AND codigo LIKE :like_codigo AND deleted_at IS NULL');
        $stmt->execute([
            'anio' => $anio,
            'like_codigo' => $prefijo . '-' . $anio . '-%',
        ]);
        $n = (int)$stmt->fetchColumn() + 1;
        return sprintf('%s-%s-%06d', $prefijo, $anio, $n);
    }
}
