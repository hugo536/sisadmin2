<?php

declare(strict_types=1);

require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';

class TesoreriaMovimientoModel extends Modelo
{
    public function listarPorOrigen(string $origen, int $idOrigen): array
    {
        $stmt = $this->db()->prepare('SELECT m.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre, mp.nombre AS metodo_nombre
                                      FROM tesoreria_movimientos m
                                      INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                                      INNER JOIN tesoreria_metodos_pago mp ON mp.id = m.id_metodo_pago
                                      WHERE m.origen = :origen AND m.id_origen = :id_origen AND m.deleted_at IS NULL
                                      ORDER BY m.fecha DESC, m.id DESC');
        $stmt->execute(['origen' => strtoupper(trim($origen)), 'id_origen' => $idOrigen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarRecientes(array $filtros = [], int $limite = 50): array
    {
        $origenFilter = strtoupper(trim((string) ($filtros['origen'] ?? '')));
        $idOrigenFilter = (int) ($filtros['id_origen'] ?? 0);
        $idTerceroFilter = (int) ($filtros['id_tercero'] ?? 0);

        $params = [];
        
        // --- QUERY 1: Movimientos Normales ---
        $whereMov = ['m.deleted_at IS NULL'];
        if (in_array($origenFilter, ['CXC', 'CXP'], true)) {
            $whereMov[] = 'm.origen = :origen_mov';
            $params['origen_mov'] = $origenFilter;
        }
        if ($idOrigenFilter > 0) {
            $whereMov[] = 'm.id_origen = :id_origen_mov';
            $params['id_origen_mov'] = $idOrigenFilter;
        }
        if ($idTerceroFilter > 0) {
            $whereMov[] = 'm.id_tercero = :id_tercero_mov';
            $params['id_tercero_mov'] = $idTerceroFilter;
        }

        $sqlMov = 'SELECT m.id, m.fecha, m.tipo, m.origen, m.id_origen, m.monto, m.estado, 
                          COALESCE(c.codigo, "S/C") AS cuenta_codigo, 
                          COALESCE(c.nombre, "Cuenta Eliminada") AS cuenta_nombre, 
                          COALESCE(t.nombre_completo, "Tercero Eliminado") AS tercero_nombre,
                          m.created_at
                   FROM tesoreria_movimientos m
                   LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                   LEFT JOIN terceros t ON t.id = m.id_tercero
                   WHERE ' . implode(' AND ', $whereMov);

        // --- QUERY 2: Transferencias (Salidas y Entradas) ---
        $whereTrf = ['trf.deleted_at IS NULL'];
        $addTransfers = true;
        
        if ($origenFilter !== '' && $origenFilter !== 'TRANSFERENCIA') {
            $addTransfers = false; // Solo quieren ver CXC o CXP
        }
        if ($idOrigenFilter > 0) {
            $whereTrf[] = 'trf.id = :id_origen_trf';
            $params['id_origen_trf'] = $idOrigenFilter;
        }
        if ($idTerceroFilter > 0) {
            $addTransfers = false; // Las transferencias son internas, no tienen tercero
        }

        $paramsFinal = $params;
        if ($addTransfers) {
            // Un egreso por la cuenta origen
            $sqlTrfOut = 'SELECT trf.id, trf.fecha, "PAGO" AS tipo, "TRANSFERENCIA" AS origen, trf.id AS id_origen, trf.monto, trf.estado,
                                 COALESCE(co.codigo, "S/C") AS cuenta_codigo,
                                 COALESCE(co.nombre, "Cuenta Eliminada") AS cuenta_nombre,
                                 "Cuentas Propias" AS tercero_nombre,
                                 trf.created_at
                          FROM tesoreria_transferencias trf
                          LEFT JOIN tesoreria_cuentas co ON co.id = trf.id_cuenta_origen
                          WHERE ' . implode(' AND ', $whereTrf);

            // Un ingreso por la cuenta destino
            $sqlTrfIn = 'SELECT trf.id, trf.fecha, "COBRO" AS tipo, "TRANSFERENCIA" AS origen, trf.id AS id_origen, trf.monto, trf.estado,
                                 COALESCE(cd.codigo, "S/C") AS cuenta_codigo,
                                 COALESCE(cd.nombre, "Cuenta Eliminada") AS cuenta_nombre,
                                 "Cuentas Propias" AS tercero_nombre,
                                 trf.created_at
                          FROM tesoreria_transferencias trf
                          LEFT JOIN tesoreria_cuentas cd ON cd.id = trf.id_cuenta_destino
                          WHERE ' . implode(' AND ', $whereTrf);

            $sqlFinal = "($sqlMov) UNION ALL ($sqlTrfOut) UNION ALL ($sqlTrfIn) ORDER BY fecha DESC, created_at DESC LIMIT :limite";
        } else {
            unset($paramsFinal['id_origen_trf']);
            $sqlFinal = "$sqlMov ORDER BY fecha DESC, created_at DESC LIMIT :limite";
        }

        $stmt = $this->db()->prepare($sqlFinal);
        foreach ($paramsFinal as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function registrar(array $data, int $userId): int
    {
        $db = $this->db();
        $localTx = !$db->inTransaction();
        if ($localTx) {
            $db->beginTransaction();
        }

        try {
            $origen = strtoupper(trim((string) $data['origen']));
            $idOrigen = (int) $data['id_origen'];
            $monto = round((float) $data['monto'], 4);
            $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
            $naturalezaPago = strtoupper(trim((string) ($data['naturaleza_pago'] ?? 'DOCUMENTO')));
            $montoCapital = round((float) ($data['monto_capital'] ?? $monto), 4);
            $montoInteres = round((float) ($data['monto_interes'] ?? 0), 4);
            $idCentroCosto = (int) ($data['id_centro_costo'] ?? 0);

            if ($monto <= 0) {
                throw new RuntimeException('El monto de la transacción debe ser mayor a cero.');
            }

            if ($origen === 'CXC') {
                $stmtOrigen = $db->prepare('SELECT id, id_cliente AS id_tercero, moneda, saldo, estado FROM tesoreria_cxc WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            } elseif ($origen === 'CXP') {
                $stmtOrigen = $db->prepare('SELECT id, id_proveedor AS id_tercero, moneda, saldo, estado FROM tesoreria_cxp WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            } else {
                throw new RuntimeException('Origen de transacción inválido.');
            }

            $stmtOrigen->execute(['id' => $idOrigen]);
            $origenRow = $stmtOrigen->fetch(PDO::FETCH_ASSOC);
            
            if (!$origenRow) throw new RuntimeException('Documento de origen no encontrado.');
            if (($origenRow['estado'] ?? '') === 'ANULADA') throw new RuntimeException('El documento está anulado.');

            $monedaOrigen = strtoupper(trim((string) ($origenRow['moneda'] ?? '')));
            if ($monedaOrigen === '') {
                throw new RuntimeException('La moneda del documento de origen es inválida.');
            }
            // La moneda real del movimiento siempre viene del documento de origen
            // para evitar inconsistencias por manipulación del formulario.
            $data['moneda'] = $monedaOrigen;

            $montoAplicaOrigen = $monto;
            if (($origen === 'CXP' && $tipo === 'PAGO') || ($origen === 'CXC' && $tipo === 'COBRO')) {
                if (!in_array($naturalezaPago, ['DOCUMENTO', 'CAPITAL', 'INTERES', 'MIXTO'], true)) {
                    throw new RuntimeException('Naturaleza de pago inválida.');
                }
                if ($naturalezaPago === 'INTERES') {
                    $montoCapital = 0;
                    $montoInteres = $monto;
                    $montoAplicaOrigen = 0;
                } elseif ($naturalezaPago === 'CAPITAL') {
                    $montoInteres = 0;
                    $montoCapital = $monto;
                    $montoAplicaOrigen = $montoCapital;
                } elseif ($naturalezaPago === 'MIXTO') {
                    if ($montoCapital <= 0 || $montoInteres <= 0) {
                        throw new RuntimeException('Para pago mixto debe indicar montos de capital e interés mayores a cero.');
                    }
                    if (round($montoCapital + $montoInteres, 4) !== $monto) {
                        throw new RuntimeException('La suma capital + interés debe ser igual al monto total del pago.');
                    }
                    $montoAplicaOrigen = $montoCapital;
                }
                $saldoActual = round((float) ($origenRow['saldo'] ?? 0), 4);
                if ($montoAplicaOrigen > $saldoActual) {
                    throw new RuntimeException('El componente de capital no puede exceder el saldo pendiente de la obligación.');
                }
            }

            $stmtCuenta = $db->prepare('SELECT id, moneda, estado FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmtCuenta->execute(['id' => (int) $data['id_cuenta']]);
            $cuentaTes = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuentaTes || (int)$cuentaTes['estado'] !== 1) throw new RuntimeException('La cuenta de tesorería está inactiva.');
            $monedaCuenta = strtoupper(trim((string) ($cuentaTes['moneda'] ?? '')));
            if ($monedaCuenta !== '' && $monedaCuenta !== $monedaOrigen) {
                throw new RuntimeException('La moneda de la cuenta no coincide con la moneda del documento.');
            }

            $stmtInsert = $db->prepare('INSERT INTO tesoreria_movimientos
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, naturaleza_pago, monto_capital, monto_interes, id_centro_costo, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:tipo, :id_tercero, :origen, :id_origen, :id_cuenta, :id_metodo_pago, :fecha, :moneda, :monto, :naturaleza_pago, :monto_capital, :monto_interes, :id_centro_costo, :referencia, :observaciones, "CONFIRMADO", :created_by, :updated_by, NOW(), NOW())');

            $stmtInsert->execute([
                'tipo'           => $tipo,
                'id_tercero'     => (int) $origenRow['id_tercero'],
                'origen'         => $origen,
                'id_origen'      => $idOrigen,
                'id_cuenta'      => (int) $data['id_cuenta'],
                'id_metodo_pago' => (int) $data['id_metodo_pago'],
                'fecha'          => $data['fecha'],
                'moneda'         => $data['moneda'],
                'monto'          => $monto,
                'naturaleza_pago' => $naturalezaPago,
                'monto_capital' => $montoCapital,
                'monto_interes' => $montoInteres,
                'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : null,
                'referencia'     => $data['referencia'] ?: null,
                'observaciones'  => $data['observaciones'] ?: null,
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ]);
            $idMovimiento = (int) $db->lastInsertId();

            $tablaOrigen = ($origen === 'CXC') ? 'tesoreria_cxc' : 'tesoreria_cxp';
            $stmtUpd = $db->prepare("UPDATE $tablaOrigen SET monto_pagado = ROUND(monto_pagado + :monto_aplica, 4), updated_by = :user, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['monto_aplica' => $montoAplicaOrigen, 'user' => $userId, 'id' => $idOrigen]);

            $contaModel = new ContaAsientoModel();
            $contaModel->registrarAutomaticoTesoreria($db, [
                'id_movimiento'      => $idMovimiento,
                'tipo'               => strtoupper(trim((string)$data['tipo'])), 
                'fecha'              => (string)$data['fecha'],
                'monto'              => $monto,
                'id_cuenta_tesoreria' => (int)$data['id_cuenta'],
                'id_tercero'         => (int)$origenRow['id_tercero'],
                'naturaleza_pago'    => $naturalezaPago,
                'monto_capital'      => $montoCapital,
                'monto_interes'      => $montoInteres,
                'id_centro_costo'    => $idCentroCosto
            ], $userId);

            if ($localTx) {
                $db->commit();
            }
            return $idMovimiento;

        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarDistribuido(array $data, array $idsOrigen, int $userId): array
    {
        $db = $this->db();
        $localTx = !$db->inTransaction();
        if ($localTx) {
            $db->beginTransaction();
        }

        try {
            $origen = strtoupper(trim((string) ($data['origen'] ?? '')));
            $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
            $montoTotal = round((float) ($data['monto'] ?? 0), 4);
            $moneda = strtoupper(trim((string) ($data['moneda'] ?? 'PEN')));

            $restante = $montoTotal;
            $movimientosCount = 0;
            $idsAplicados = [];

            foreach ($idsOrigen as $idOrigen) {
                if ($restante <= 0) {
                    break;
                }

                $tabla = ($origen === 'CXC') ? 'tesoreria_cxc' : 'tesoreria_cxp';
                $stmtSaldo = $db->prepare("SELECT saldo FROM $tabla WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE");
                $stmtSaldo->execute(['id' => $idOrigen]);
                $saldoDocumento = round((float) $stmtSaldo->fetchColumn(), 4);

                if ($saldoDocumento <= 0) {
                    continue;
                }

                $montoAPagarAqui = min($restante, $saldoDocumento);

                $pagoAqui = $this->registrar([
                    'tipo'           => $tipo,
                    'origen'         => $origen,
                    'id_origen'      => (int)$idOrigen,
                    'id_cuenta'      => (int)$data['id_cuenta'],
                    'id_metodo_pago' => (int)$data['id_metodo_pago'],
                    'fecha'          => $data['fecha'],
                    'moneda'         => $moneda,
                    'monto'          => $montoAPagarAqui,
                    'referencia'     => $data['referencia'],
                    'observaciones'  => $data['observaciones']
                ], $userId);

                $restante = round($restante - $montoAPagarAqui, 4);
                $movimientosCount++;
                $idsAplicados[] = (int)$idOrigen;
            }

            if ($restante > 0) {
                throw new RuntimeException('El monto excede el saldo pendiente disponible para aplicar en modo FIFO.');
            }

            if ($localTx) {
                $db->commit();
            }
            
            return ['movimientos' => $movimientosCount, 'origenes' => $idsAplicados];
        } catch (Throwable $e) {
            if ($localTx && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function anular(int $idMovimiento, int $userId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmtMov = $db->prepare('SELECT * FROM tesoreria_movimientos WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            $stmtMov->execute(['id' => $idMovimiento]);
            $mov = $stmtMov->fetch(PDO::FETCH_ASSOC);
            
            if (!$mov) throw new RuntimeException('Movimiento no encontrado.');
            if (($mov['estado'] ?? '') === 'ANULADO') throw new RuntimeException('Ya está anulado.');

            $origen = (string)$mov['origen'];
            $idOrigen = (int)$mov['id_origen'];
            $monto = (float)$mov['monto'];
            $naturaleza = strtoupper((string)($mov['naturaleza_pago'] ?? 'DOCUMENTO'));
            $montoCapital = (float)($mov['monto_capital'] ?? 0);
            $montoAplicaOrigen = $monto;
            if ($origen === 'CXP') {
                if ($naturaleza === 'INTERES') {
                    $montoAplicaOrigen = 0;
                } elseif ($naturaleza === 'MIXTO') {
                    $montoAplicaOrigen = max(0, $montoCapital);
                }
            }

            $tabla = ($origen === 'CXC') ? 'tesoreria_cxc' : 'tesoreria_cxp';
            $stmtUpd = $db->prepare("UPDATE $tabla SET monto_pagado = GREATEST(ROUND(monto_pagado - :monto_aplica, 4), 0), updated_by = :user, updated_at = NOW() WHERE id = :id");
            $stmtUpd->execute(['monto_aplica' => $montoAplicaOrigen, 'user' => $userId, 'id' => $idOrigen]);

            $db->prepare('UPDATE tesoreria_movimientos SET estado = "ANULADO", updated_by = :user, updated_at = NOW() WHERE id = :id')
               ->execute(['id' => $idMovimiento, 'user' => $userId]);

            $stmtAs = $db->prepare('SELECT id FROM conta_asientos WHERE origen_modulo = "TESORERIA" AND id_origen = :id_mov AND estado = "REGISTRADO" LIMIT 1');
            $stmtAs->execute(['id_mov' => $idMovimiento]);
            $idAsiento = (int)$stmtAs->fetchColumn();
            
            if ($idAsiento > 0) {
                $contaModel = new ContaAsientoModel();
                $contaModel->anularConReversion($idAsiento, $userId);
            }

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function resumenPorCuenta(): array
    {
        $sql = 'SELECT c.id, c.codigo, c.nombre, c.moneda,
                (
                    COALESCE(c.saldo_inicial, 0)
                    + COALESCE((SELECT SUM(CASE WHEN m.tipo = "COBRO" THEN m.monto WHEN m.tipo = "PAGO" THEN -m.monto ELSE 0 END)
                                FROM tesoreria_movimientos m
                                WHERE m.id_cuenta = c.id AND m.estado = "CONFIRMADO" AND m.deleted_at IS NULL), 0)
                    + COALESCE((SELECT SUM(CASE WHEN t.id_cuenta_destino = c.id THEN t.monto WHEN t.id_cuenta_origen = c.id THEN -t.monto ELSE 0 END)
                                FROM tesoreria_transferencias t
                                WHERE (t.id_cuenta_origen = c.id OR t.id_cuenta_destino = c.id)
                                  AND t.estado = "CONFIRMADA"
                                  AND t.deleted_at IS NULL), 0)
                ) AS saldo_actual,
                (
                    SELECT u.tipo
                    FROM (
                        SELECT m.fecha, m.created_at, m.id, m.tipo, m.monto
                        FROM tesoreria_movimientos m
                        WHERE m.id_cuenta = c.id
                          AND m.estado = "CONFIRMADO"
                          AND m.deleted_at IS NULL
                        UNION ALL
                        SELECT t.fecha, t.created_at, t.id, "COBRO" AS tipo, t.monto
                        FROM tesoreria_transferencias t
                        WHERE t.id_cuenta_destino = c.id
                          AND t.estado = "CONFIRMADA"
                          AND t.deleted_at IS NULL
                        UNION ALL
                        SELECT t.fecha, t.created_at, t.id, "PAGO" AS tipo, t.monto
                        FROM tesoreria_transferencias t
                        WHERE t.id_cuenta_origen = c.id
                          AND t.estado = "CONFIRMADA"
                          AND t.deleted_at IS NULL
                    ) u
                    ORDER BY u.fecha DESC, u.created_at DESC, u.id DESC
                    LIMIT 1
                ) AS ultimo_tipo,
                (
                    SELECT u.monto
                    FROM (
                        SELECT m.fecha, m.created_at, m.id, m.tipo, m.monto
                        FROM tesoreria_movimientos m
                        WHERE m.id_cuenta = c.id
                          AND m.estado = "CONFIRMADO"
                          AND m.deleted_at IS NULL
                        UNION ALL
                        SELECT t.fecha, t.created_at, t.id, "COBRO" AS tipo, t.monto
                        FROM tesoreria_transferencias t
                        WHERE t.id_cuenta_destino = c.id
                          AND t.estado = "CONFIRMADA"
                          AND t.deleted_at IS NULL
                        UNION ALL
                        SELECT t.fecha, t.created_at, t.id, "PAGO" AS tipo, t.monto
                        FROM tesoreria_transferencias t
                        WHERE t.id_cuenta_origen = c.id
                          AND t.estado = "CONFIRMADA"
                          AND t.deleted_at IS NULL
                    ) u
                    ORDER BY u.fecha DESC, u.created_at DESC, u.id DESC
                    LIMIT 1
                ) AS ultimo_monto,
                (
                    SELECT u.fecha
                    FROM (
                        SELECT m.fecha, m.created_at, m.id, m.tipo, m.monto
                        FROM tesoreria_movimientos m
                        WHERE m.id_cuenta = c.id
                          AND m.estado = "CONFIRMADO"
                          AND m.deleted_at IS NULL
                        UNION ALL
                        SELECT t.fecha, t.created_at, t.id, "COBRO" AS tipo, t.monto
                        FROM tesoreria_transferencias t
                        WHERE t.id_cuenta_destino = c.id
                          AND t.estado = "CONFIRMADA"
                          AND t.deleted_at IS NULL
                        UNION ALL
                        SELECT t.fecha, t.created_at, t.id, "PAGO" AS tipo, t.monto
                        FROM tesoreria_transferencias t
                        WHERE t.id_cuenta_origen = c.id
                          AND t.estado = "CONFIRMADA"
                          AND t.deleted_at IS NULL
                    ) u
                    ORDER BY u.fecha DESC, u.created_at DESC, u.id DESC
                    LIMIT 1
                ) AS ultimo_fecha
                FROM tesoreria_cuentas c
                WHERE c.deleted_at IS NULL AND c.estado = 1
                ORDER BY c.nombre ASC';
                
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
