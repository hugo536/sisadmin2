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

    public function listarRecientes(array $filtros = [], int $limite = 20): array
    {
        // MEJORA: Usamos LEFT JOIN para cuentas y terceros por si alguna vez se borra una cuenta o tercero,
        // no perdamos el registro histórico del movimiento en pantalla.
        $where = ['m.deleted_at IS NULL'];
        $params = [];

        $origen = strtoupper(trim((string) ($filtros['origen'] ?? '')));
        if (in_array($origen, ['CXC', 'CXP'], true)) {
            $where[] = 'm.origen = :origen';
            $params['origen'] = $origen;
        }

        $idOrigen = (int) ($filtros['id_origen'] ?? 0);
        if ($idOrigen > 0) {
            $where[] = 'm.id_origen = :id_origen';
            $params['id_origen'] = $idOrigen;
        }

        $idTercero = (int) ($filtros['id_tercero'] ?? 0);
        if ($idTercero > 0) {
            $where[] = 'm.id_tercero = :id_tercero';
            $params['id_tercero'] = $idTercero;
        }

        $sql = 'SELECT m.*, 
                       COALESCE(c.codigo, "S/C") AS cuenta_codigo, 
                       COALESCE(c.nombre, "Cuenta Eliminada") AS cuenta_nombre, 
                       COALESCE(t.nombre_completo, "Tercero Eliminado") AS tercero_nombre
                FROM tesoreria_movimientos m
                LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                LEFT JOIN terceros t ON t.id = m.id_tercero
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY m.id DESC
                LIMIT :limite';

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function registrar(array $data, int $userId): int
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $origen = strtoupper(trim((string) $data['origen']));
            $idOrigen = (int) $data['id_origen'];
            $monto = round((float) $data['monto'], 4);

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
            
            if (!$origenRow) {
                throw new RuntimeException('Documento de origen no encontrado o eliminado.');
            }

            if ((string) ($origenRow['estado'] ?? '') === 'ANULADA') {
                throw new RuntimeException('No se puede registrar pagos/cobros sobre un documento anulado.');
            }

            $saldoActual = round((float) ($origenRow['saldo'] ?? 0), 4);
            if ($monto > $saldoActual) {
                throw new RuntimeException('El monto a registrar excede el saldo pendiente actual (' . $saldoActual . ').');
            }

            if ((string) ($origenRow['moneda'] ?? 'PEN') !== (string) $data['moneda']) {
                throw new RuntimeException('La moneda del movimiento no coincide con la moneda del documento origen.');
            }

            $stmtCuenta = $db->prepare('SELECT id, moneda, estado FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmtCuenta->execute(['id' => (int) $data['id_cuenta']]);
            $cuenta = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            
            if (!$cuenta || (int) ($cuenta['estado'] ?? 0) !== 1) {
                throw new RuntimeException('La cuenta de tesorería seleccionada es inválida o está inactiva.');
            }
            if ((string) ($cuenta['moneda'] ?? '') !== (string) $data['moneda']) {
                throw new RuntimeException('La moneda de la cuenta bancaria no coincide con la moneda de la transacción.');
            }

            $stmtTercero = $db->prepare('SELECT id FROM terceros WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtTercero->execute(['id' => (int) $origenRow['id_tercero']]);
            
            if (!(bool) $stmtTercero->fetchColumn()) {
                throw new RuntimeException('El cliente/proveedor asociado se encuentra inactivo en el sistema.');
            }

            $stmtInsert = $db->prepare('INSERT INTO tesoreria_movimientos
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:tipo, :id_tercero, :origen, :id_origen, :id_cuenta, :id_metodo_pago, :fecha, :moneda, :monto, :referencia, :observaciones, "CONFIRMADO", :created_by, :updated_by, NOW(), NOW())');

            $stmtInsert->execute([
                'tipo'           => strtoupper(trim((string)$data['tipo'])),
                'id_tercero'     => (int) $origenRow['id_tercero'],
                'origen'         => $origen,
                'id_origen'      => $idOrigen,
                'id_cuenta'      => (int) $data['id_cuenta'],
                'id_metodo_pago' => (int) $data['id_metodo_pago'],
                'fecha'          => $data['fecha'],
                'moneda'         => $data['moneda'],
                'monto'          => $monto,
                'referencia'     => $data['referencia'] ?: null,
                'observaciones'  => $data['observaciones'] ?: null,
                'created_by'     => $userId,
                'updated_by'     => $userId,
            ]);
            $idMovimiento = (int) $db->lastInsertId();

            if ($origen === 'CXC') {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxc SET monto_pagado = ROUND(monto_pagado + :monto, 4), updated_by = :user, updated_at = NOW() WHERE id = :id');
            } else {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxp SET monto_pagado = ROUND(monto_pagado + :monto, 4), updated_by = :user, updated_at = NOW() WHERE id = :id');
            }
            $stmtUpd->execute(['monto' => $monto, 'user' => $userId, 'id' => $idOrigen]);

            $contaModel = new ContaAsientoModel();
            $contaModel->registrarAutomaticoTesoreria($db, [
                'id_movimiento' => $idMovimiento,
                'tipo' => strtoupper(trim((string)$data['tipo'])),
                'fecha' => (string)$data['fecha'],
                'monto' => $monto,
            ], $userId);

            $db->commit();
            return $idMovimiento;
            
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }


    public function registrarDistribuido(array $data, array $idsOrigen, int $userId): array
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $origen = strtoupper(trim((string) ($data['origen'] ?? '')));
            $tipo = strtoupper(trim((string) ($data['tipo'] ?? '')));
            $idTercero = (int) ($data['id_tercero'] ?? 0);
            $montoTotal = round((float) ($data['monto'] ?? 0), 4);
            $moneda = strtoupper(trim((string) ($data['moneda'] ?? 'PEN')));

            if (!in_array($origen, ['CXC', 'CXP'], true)) {
                throw new RuntimeException('Origen de transacción inválido.');
            }

            if (!in_array($tipo, ['COBRO', 'PAGO'], true)) {
                throw new RuntimeException('Tipo de transacción inválido.');
            }

            if ($idTercero <= 0 || $montoTotal <= 0) {
                throw new RuntimeException('Datos inválidos para registrar el movimiento manual.');
            }

            if ($idsOrigen === []) {
                throw new RuntimeException('No existen documentos pendientes para aplicar el movimiento.');
            }

            $stmtCuenta = $db->prepare('SELECT id, moneda, estado FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmtCuenta->execute(['id' => (int) ($data['id_cuenta'] ?? 0)]);
            $cuenta = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            if (!$cuenta || (int) ($cuenta['estado'] ?? 0) !== 1) {
                throw new RuntimeException('La cuenta de tesorería seleccionada es inválida o está inactiva.');
            }
            if ((string) ($cuenta['moneda'] ?? '') !== $moneda) {
                throw new RuntimeException('La moneda de la cuenta bancaria no coincide con la moneda de la transacción.');
            }

            $stmtMetodo = $db->prepare('SELECT id FROM tesoreria_metodos_pago WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtMetodo->execute(['id' => (int) ($data['id_metodo_pago'] ?? 0)]);
            if (!(bool) $stmtMetodo->fetchColumn()) {
                throw new RuntimeException('El método de pago seleccionado no es válido.');
            }

            $stmtTercero = $db->prepare('SELECT id FROM terceros WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtTercero->execute(['id' => $idTercero]);
            if (!(bool) $stmtTercero->fetchColumn()) {
                throw new RuntimeException('El cliente/proveedor asociado se encuentra inactivo en el sistema.');
            }

            $tabla = $origen === 'CXC' ? 'tesoreria_cxc' : 'tesoreria_cxp';
            $campoTercero = $origen === 'CXC' ? 'id_cliente' : 'id_proveedor';
            $stmtOrigen = $db->prepare('SELECT id, saldo, moneda, estado
                FROM ' . $tabla . '
                WHERE id = :id
                  AND ' . $campoTercero . ' = :id_tercero
                  AND deleted_at IS NULL
                LIMIT 1 FOR UPDATE');

            $stmtInsert = $db->prepare('INSERT INTO tesoreria_movimientos
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:tipo, :id_tercero, :origen, :id_origen, :id_cuenta, :id_metodo_pago, :fecha, :moneda, :monto, :referencia, :observaciones, "CONFIRMADO", :created_by, :updated_by, NOW(), NOW())');

            $stmtUpd = $db->prepare('UPDATE ' . $tabla . '
                SET monto_pagado = ROUND(monto_pagado + :monto_a, 4),
                    saldo = GREATEST(ROUND(monto_total - ROUND(monto_pagado + :monto_b, 4), 4), 0),
                    estado = CASE
                        WHEN estado = "ANULADA" THEN "ANULADA"
                        WHEN ROUND(monto_total - ROUND(monto_pagado + :monto_c, 4), 4) <= 0 THEN "PAGADA"
                        WHEN DATE(fecha_vencimiento) < CURDATE() THEN "VENCIDA"
                        WHEN ROUND(monto_pagado + :monto_d, 4) > 0 THEN "PARCIAL"
                        ELSE "ABIERTA"
                    END,
                    updated_by = :user,
                    updated_at = NOW()
                WHERE id = :id');

            $restante = $montoTotal;
            $movimientos = 0;
            $idsAplicados = [];
            $contaModel = new ContaAsientoModel();

            foreach ($idsOrigen as $idOrigen) {
                if ($restante <= 0) {
                    break;
                }

                $stmtOrigen->execute(['id' => (int) $idOrigen, 'id_tercero' => $idTercero]);
                $row = $stmtOrigen->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    continue;
                }

                if ((string) ($row['estado'] ?? '') === 'ANULADA') {
                    continue;
                }

                if ((string) ($row['moneda'] ?? 'PEN') !== $moneda) {
                    throw new RuntimeException('La moneda de uno de los documentos no coincide con la moneda del movimiento.');
                }

                $saldo = round((float) ($row['saldo'] ?? 0), 4);
                if ($saldo <= 0) {
                    continue;
                }

                $aplicar = min($restante, $saldo);
                $aplicar = round($aplicar, 4);
                if ($aplicar <= 0) {
                    continue;
                }

                $stmtInsert->execute([
                    'tipo' => $tipo,
                    'id_tercero' => $idTercero,
                    'origen' => $origen,
                    'id_origen' => (int) $row['id'],
                    'id_cuenta' => (int) ($data['id_cuenta'] ?? 0),
                    'id_metodo_pago' => (int) ($data['id_metodo_pago'] ?? 0),
                    'fecha' => (string) ($data['fecha'] ?? date('Y-m-d')),
                    'moneda' => $moneda,
                    'monto' => $aplicar,
                    'referencia' => !empty($data['referencia']) ? (string) $data['referencia'] : null,
                    'observaciones' => !empty($data['observaciones']) ? (string) $data['observaciones'] : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
                $idMovimiento = (int) $db->lastInsertId();

                $stmtUpd->execute([
                    'monto_a' => $aplicar,
                    'monto_b' => $aplicar,
                    'monto_c' => $aplicar,
                    'monto_d' => $aplicar,
                    'user' => $userId,
                    'id' => (int) $row['id'],
                ]);

                $contaModel->registrarAutomaticoTesoreria($db, [
                    'id_movimiento' => $idMovimiento,
                    'tipo' => $tipo,
                    'fecha' => (string) ($data['fecha'] ?? date('Y-m-d')),
                    'monto' => $aplicar,
                ], $userId);

                $restante = round($restante - $aplicar, 4);
                $movimientos++;
                $idsAplicados[] = (int) $row['id'];
            }

            if ($restante > 0) {
                throw new RuntimeException('El monto excede la deuda pendiente del tercero para la moneda seleccionada.');
            }

            $db->commit();
            return ['movimientos' => $movimientos, 'origenes' => $idsAplicados];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
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
            
            if (!$mov) {
                throw new RuntimeException('Movimiento no encontrado o eliminado previamente.');
            }

            if ((string) ($mov['estado'] ?? '') === 'ANULADO') {
                throw new RuntimeException('El movimiento ya se encuentra anulado.');
            }

            $origen = (string) ($mov['origen'] ?? '');
            $idOrigen = (int) ($mov['id_origen'] ?? 0);
            $monto = round((float) ($mov['monto'] ?? 0), 4);

            // Devolver el saldo al documento original
            if ($origen === 'CXC') {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxc
                                         SET monto_pagado = GREATEST(ROUND(monto_pagado - :monto, 4), 0),
                                             updated_by = :user,
                                             updated_at = NOW()
                                         WHERE id = :id');
            } elseif ($origen === 'CXP') {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxp
                                         SET monto_pagado = GREATEST(ROUND(monto_pagado - :monto, 4), 0),
                                             updated_by = :user,
                                             updated_at = NOW()
                                         WHERE id = :id');
            } else {
                throw new RuntimeException('Origen de documento desconocido. No se pudo anular.');
            }
            $stmtUpd->execute(['monto' => $monto, 'user' => $userId, 'id' => $idOrigen]);

            // Marcar el movimiento como anulado
            $stmtAnular = $db->prepare('UPDATE tesoreria_movimientos
                                        SET estado = "ANULADO", updated_by = :user, updated_at = NOW()
                                        WHERE id = :id');
            $stmtAnular->execute(['id' => $idMovimiento, 'user' => $userId]);

            $stmtAs = $db->prepare('SELECT id FROM conta_asientos WHERE origen_modulo = "TESORERIA" AND id_origen = :id_mov AND estado = "REGISTRADO" AND deleted_at IS NULL LIMIT 1');
            $stmtAs->execute(['id_mov' => $idMovimiento]);
            $idAsiento = (int)$stmtAs->fetchColumn();
            if ($idAsiento > 0) {
                $contaModel = new ContaAsientoModel();
                $contaModel->anularConReversion($idAsiento, $userId);
            }

            $db->commit();
            return true;
            
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function resumenPorCuenta(): array
    {
        // OPTIMIZACIÓN: Se usa una subconsulta para agrupar primero y luego calcular el saldo teórico.
        // Esto evita que MySQL tenga que repetir los CASE WHEN largos múltiples veces.
        $sql = 'SELECT 
                    res.id, res.codigo, res.nombre, res.moneda, 
                    res.ingresos, 
                    res.egresos, 
                    (res.ingresos - res.egresos) AS saldo_teorico
                FROM (
                    SELECT c.id, c.codigo, c.nombre, c.moneda,
                           COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "COBRO" AND DATE(m.fecha) = CURDATE() THEN m.monto ELSE 0 END), 0) AS ingresos,
                           COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "PAGO" AND DATE(m.fecha) = CURDATE() THEN m.monto ELSE 0 END), 0) AS egresos
                    FROM tesoreria_cuentas c
                    LEFT JOIN tesoreria_movimientos m ON m.id_cuenta = c.id AND m.deleted_at IS NULL
                    WHERE c.deleted_at IS NULL AND c.estado = 1
                    GROUP BY c.id, c.codigo, c.nombre, c.moneda
                ) res
                ORDER BY res.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}