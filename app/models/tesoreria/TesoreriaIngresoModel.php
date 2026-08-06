<?php

declare(strict_types=1);

class TesoreriaIngresoModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $where = ['i.deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(i.concepto LIKE :q OR i.referencia LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $cuenta = (int) ($filtros['cuenta'] ?? 0);
        if ($cuenta > 0) {
            $where[] = 'i.id_cuenta = :cuenta';
            $params['cuenta'] = $cuenta;
        }

        if (!empty($filtros['fecha_desde'])) {
            $where[] = 'i.fecha >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $where[] = 'i.fecha <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $sql = 'SELECT i.*, c.nombre AS cuenta_nombre, c.codigo AS cuenta_codigo
                FROM tesoreria_ingresos_extraordinarios i
                INNER JOIN tesoreria_cuentas c ON c.id = i.id_cuenta
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY i.fecha DESC, i.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data, int $userId): int
    {
        $fecha = trim((string) ($data['fecha'] ?? ''));
        $idCuenta = (int) ($data['id_cuenta'] ?? 0);
        $monto = round((float) ($data['monto'] ?? 0), 4);
        $concepto = trim((string) ($data['concepto'] ?? ''));
        $referencia = trim((string) ($data['referencia'] ?? ''));

        if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('La fecha del ingreso es inválida.');
        }
        if ($idCuenta <= 0) {
            throw new RuntimeException('Debe seleccionar la cuenta destino.');
        }
        if ($monto <= 0) {
            throw new RuntimeException('El monto debe ser mayor a cero.');
        }
        if ($concepto === '') {
            throw new RuntimeException('Debe ingresar el concepto del ingreso.');
        }

        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmtCuenta = $db->prepare('SELECT id, moneda FROM tesoreria_cuentas WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
            $stmtCuenta->execute(['id' => $idCuenta]);
            $cuenta = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            if (!$cuenta) {
                throw new RuntimeException('La cuenta destino no existe o está inactiva.');
            }

            $stmtIngreso = $db->prepare('INSERT INTO tesoreria_ingresos_extraordinarios
                (id_cuenta, fecha, moneda, monto, concepto, referencia, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:id_cuenta, :fecha, :moneda, :monto, :concepto, :referencia, 1, :created_by, :updated_by, NOW(), NOW())');
            $stmtIngreso->execute([
                'id_cuenta' => $idCuenta,
                'fecha' => $fecha,
                'moneda' => (string) ($cuenta['moneda'] ?? 'PEN'),
                'monto' => $monto,
                'concepto' => $concepto,
                'referencia' => $referencia !== '' ? $referencia : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $idIngreso = (int) $db->lastInsertId();

            $metodoId = $this->obtenerMetodoIngreso($db);
            $stmtMov = $db->prepare('INSERT INTO tesoreria_movimientos
                (tipo, id_tercero, origen, id_origen, id_cuenta, id_metodo_pago, fecha, moneda, monto, tipo_cambio, naturaleza_pago, monto_capital, monto_interes, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES (\'INGRESO\', NULL, \'INGRESO_EXTRA\', :id_origen, :id_cuenta, :id_metodo_pago, :fecha, :moneda, :monto, 1, \'DOCUMENTO\', :monto_capital, 0, :referencia, :observaciones, \'CONFIRMADO\', :created_by, :updated_by, NOW(), NOW())');
            $stmtMov->execute([
                'id_origen' => $idIngreso,
                'id_cuenta' => $idCuenta,
                'id_metodo_pago' => $metodoId,
                'fecha' => $fecha,
                'moneda' => (string) ($cuenta['moneda'] ?? 'PEN'),
                'monto' => $monto,
                'monto_capital' => $monto,
                'referencia' => $referencia !== '' ? $referencia : null,
                'observaciones' => $concepto,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $db->commit();
            return $idIngreso;
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    public function anular(int $id, int $userId): void
    {
        if ($id <= 0) throw new RuntimeException('Ingreso inválido.');
        $db = $this->db();
        $db->beginTransaction();
        try {
            $stmt = $db->prepare('UPDATE tesoreria_ingresos_extraordinarios SET estado = 0, updated_by = :user, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(['id' => $id, 'user' => $userId]);
            if ($stmt->rowCount() === 0) throw new RuntimeException('No se encontró el ingreso a anular.');

            $db->prepare('UPDATE tesoreria_movimientos SET estado = \'ANULADO\', updated_by = :user, updated_at = NOW() WHERE origen = \'INGRESO_EXTRA\' AND id_origen = :id AND deleted_at IS NULL')
                ->execute(['id' => $id, 'user' => $userId]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
    }

    private function obtenerMetodoIngreso(PDO $db): int
    {
        $stmt = $db->query("SELECT id FROM tesoreria_metodos_pago WHERE estado = 1 ORDER BY id ASC LIMIT 1");
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id <= 0) {
            throw new RuntimeException('Debe existir al menos un método de pago activo.');
        }
        return $id;
    }
}
