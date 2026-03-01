<?php

declare(strict_types=1);

class TesoreriaMovimientoModel extends Modelo
{
    public function listarPorOrigen(string $origen, int $idOrigen): array
    {
        $stmt = $this->db()->prepare('SELECT m.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre, mp.nombre AS metodo_nombre
                                      FROM tesoreria_movimientos m
                                      INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                                      INNER JOIN tesoreria_metodos_pago mp ON mp.id = m.id_metodo_pago
                                      WHERE m.origen = :origen AND m.id_origen = :id_origen
                                      ORDER BY m.fecha DESC, m.id DESC');
        $stmt->execute(['origen' => $origen, 'id_origen' => $idOrigen]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarRecientes(array $filtros = [], int $limite = 20): array
    {
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

        $sql = 'SELECT m.*, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre, t.nombre_completo AS tercero_nombre
                FROM tesoreria_movimientos m
                INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                INNER JOIN terceros t ON t.id = m.id_tercero
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
            $origen = (string) $data['origen'];
            $idOrigen = (int) $data['id_origen'];
            $monto = round((float) $data['monto'], 4);

            if ($monto <= 0) {
                throw new RuntimeException('El monto debe ser mayor a cero.');
            }

            if ($origen === 'CXC') {
                $stmtOrigen = $db->prepare('SELECT id, id_cliente AS id_tercero, moneda, saldo, estado FROM tesoreria_cxc WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            } else {
                $stmtOrigen = $db->prepare('SELECT id, id_proveedor AS id_tercero, moneda, saldo, estado FROM tesoreria_cxp WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
            }

            $stmtOrigen->execute(['id' => $idOrigen]);
            $origenRow = $stmtOrigen->fetch(PDO::FETCH_ASSOC);
            if (!$origenRow) {
                throw new RuntimeException('Documento de origen no encontrado.');
            }

            if ((string) ($origenRow['estado'] ?? '') === 'ANULADA') {
                throw new RuntimeException('No se puede operar sobre un documento anulado.');
            }

            $saldoActual = round((float) ($origenRow['saldo'] ?? 0), 4);
            if ($monto > $saldoActual) {
                throw new RuntimeException('El monto excede el saldo pendiente.');
            }

            if ((string) ($origenRow['moneda'] ?? 'PEN') !== (string) $data['moneda']) {
                throw new RuntimeException('La moneda del movimiento no coincide con el origen.');
            }

            $stmtCuenta = $db->prepare('SELECT id, moneda, estado FROM tesoreria_cuentas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
            $stmtCuenta->execute(['id' => (int) $data['id_cuenta']]);
            $cuenta = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            if (!$cuenta || (int) ($cuenta['estado'] ?? 0) !== 1) {
                throw new RuntimeException('Cuenta de tesorería inválida o inactiva.');
            }
            if ((string) ($cuenta['moneda'] ?? '') !== (string) $data['moneda']) {
                throw new RuntimeException('La moneda de la cuenta no coincide con la del movimiento.');
            }

            $stmtTercero = $db->prepare('SELECT id FROM terceros WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtTercero->execute(['id' => (int) $origenRow['id_tercero']]);
            if (!(bool) $stmtTercero->fetchColumn()) {
                throw new RuntimeException('El tercero asociado se encuentra inactivo.');
            }

            $stmtInsert = $db->prepare('INSERT INTO tesoreria_movimientos
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:tipo, :id_tercero, :origen, :id_origen, :id_cuenta, :id_metodo_pago, :fecha, :moneda, :monto, :referencia, :observaciones, "CONFIRMADO", :created_by, :updated_by, NOW(), NOW())');

            $stmtInsert->execute([
                'tipo' => $data['tipo'],
                'id_tercero' => (int) $origenRow['id_tercero'],
                'origen' => $origen,
                'id_origen' => $idOrigen,
                'id_cuenta' => (int) $data['id_cuenta'],
                'id_metodo_pago' => (int) $data['id_metodo_pago'],
                'fecha' => $data['fecha'],
                'moneda' => $data['moneda'],
                'monto' => $monto,
                'referencia' => $data['referencia'] ?: null,
                'observaciones' => $data['observaciones'] ?: null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ($origen === 'CXC') {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxc SET monto_pagado = ROUND(monto_pagado + :monto, 4), updated_by = :user, updated_at = NOW() WHERE id = :id');
            } else {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxp SET monto_pagado = ROUND(monto_pagado + :monto, 4), updated_by = :user, updated_at = NOW() WHERE id = :id');
            }
            $stmtUpd->execute(['monto' => $monto, 'user' => $userId, 'id' => $idOrigen]);

            $db->commit();
            return (int) $db->lastInsertId();
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
                throw new RuntimeException('Movimiento no encontrado.');
            }

            if ((string) ($mov['estado'] ?? '') === 'ANULADO') {
                throw new RuntimeException('El movimiento ya está anulado.');
            }

            $origen = (string) ($mov['origen'] ?? '');
            $idOrigen = (int) ($mov['id_origen'] ?? 0);
            $monto = round((float) ($mov['monto'] ?? 0), 4);

            if ($origen === 'CXC') {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxc
                                         SET monto_pagado = GREATEST(ROUND(monto_pagado - :monto, 4), 0),
                                             updated_by = :user,
                                             updated_at = NOW()
                                         WHERE id = :id');
            } else {
                $stmtUpd = $db->prepare('UPDATE tesoreria_cxp
                                         SET monto_pagado = GREATEST(ROUND(monto_pagado - :monto, 4), 0),
                                             updated_by = :user,
                                             updated_at = NOW()
                                         WHERE id = :id');
            }
            $stmtUpd->execute(['monto' => $monto, 'user' => $userId, 'id' => $idOrigen]);

            $stmtAnular = $db->prepare('UPDATE tesoreria_movimientos
                                        SET estado = "ANULADO", updated_by = :user, updated_at = NOW()
                                        WHERE id = :id');
            $stmtAnular->execute(['id' => $idMovimiento, 'user' => $userId]);

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
        $sql = 'SELECT c.id, c.codigo, c.nombre, c.moneda,
                       COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "COBRO" AND m.fecha = CURDATE() THEN m.monto ELSE 0 END), 0) AS ingresos,
                       COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "PAGO" AND m.fecha = CURDATE() THEN m.monto ELSE 0 END), 0) AS egresos,
                       COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "COBRO" AND m.fecha = CURDATE() THEN m.monto ELSE 0 END), 0)
                       - COALESCE(SUM(CASE WHEN m.estado = "CONFIRMADO" AND m.tipo = "PAGO" AND m.fecha = CURDATE() THEN m.monto ELSE 0 END), 0) AS saldo_teorico
                FROM tesoreria_cuentas c
                LEFT JOIN tesoreria_movimientos m ON m.id_cuenta = c.id AND m.deleted_at IS NULL
                WHERE c.deleted_at IS NULL
                GROUP BY c.id, c.codigo, c.nombre, c.moneda
                ORDER BY c.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
