<?php
declare(strict_types=1);

class AdelantosModel extends Modelo
{
    public function listarAdelantos(): array
    {
        $sql = "SELECT a.id, a.fecha, t.nombre_completo AS empleado, t.numero_documento, 
                       c.nombre AS cuenta_origen, a.monto, a.saldo_pendiente, a.estado, a.observacion
                FROM rrhh_adelantos a
                INNER JOIN terceros t ON t.id = a.id_tercero
                LEFT JOIN tesoreria_cuentas c ON c.id = a.id_cuenta_tesoreria
                ORDER BY a.fecha DESC, a.id DESC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarEmpleadosActivos(): array
    {
        $sql = "SELECT id, nombre_completo, numero_documento 
                FROM terceros 
                WHERE es_empleado = 1 AND estado = 1 AND deleted_at IS NULL 
                ORDER BY nombre_completo ASC";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarCuentasTesoreria(): array
    {
        $sql = "SELECT id, nombre, saldo_actual, moneda 
                FROM tesoreria_cuentas 
                WHERE estado = 1 AND deleted_at IS NULL";
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function registrarAdelanto(array $datos, int $userId): bool
    {
        $db = $this->db();
        try {
            $db->beginTransaction();

            $idTercero = (int) $datos['id_tercero'];
            $idCuenta = (int) $datos['id_cuenta'];
            $monto = (float) $datos['monto'];
            $fecha = $datos['fecha'];
            $obs = trim($datos['observacion'] ?? '');

            // 1. Registrar la deuda en RRHH
            $sqlAdelanto = "INSERT INTO rrhh_adelantos (id_tercero, id_cuenta_tesoreria, monto, saldo_pendiente, fecha, observacion, estado, created_by) 
                            VALUES (:id_tercero, :id_cuenta, :monto, :saldo, :fecha, :obs, 'PENDIENTE', :uid)";
            $db->prepare($sqlAdelanto)->execute([
                'id_tercero' => $idTercero, 'id_cuenta' => $idCuenta, 
                'monto' => $monto, 'saldo' => $monto, 'fecha' => $fecha, 
                'obs' => $obs, 'uid' => $userId
            ]);

            // 2. Registrar el Egreso en Tesorería
            $sqlMovimiento = "INSERT INTO tesoreria_movimientos (id_cuenta, tipo, monto, concepto, fecha, created_by) 
                              VALUES (:id_cuenta, 'EGRESO', :monto, :concepto, :fecha, :uid)";
            $conceptoTesoreria = "Adelanto de sueldo a personal (ID: {$idTercero}) - " . $obs;
            $db->prepare($sqlMovimiento)->execute([
                'id_cuenta' => $idCuenta, 'monto' => $monto, 
                'concepto' => $conceptoTesoreria, 'fecha' => $fecha, 'uid' => $userId
            ]);

            // 3. Descontar el saldo de la cuenta
            $db->prepare("UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual - :monto WHERE id = :id_cuenta")
               ->execute(['monto' => $monto, 'id_cuenta' => $idCuenta]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            error_log("Error al registrar adelanto: " . $e->getMessage());
            return false;
        }
    }

    public function registrarDevolucionManual(array $datos, int $userId): bool
    {
        $db = $this->db();
        try {
            $db->beginTransaction();

            $idAdelanto = (int) $datos['id_adelanto'];
            $idCuenta = (int) $datos['id_cuenta_destino'];
            $montoDevuelto = (float) $datos['monto_devuelto'];

            // Obtener info del adelanto
            $stmt = $db->prepare("SELECT id_tercero, saldo_pendiente FROM rrhh_adelantos WHERE id = :id");
            $stmt->execute(['id' => $idAdelanto]);
            $adelanto = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$adelanto || $montoDevuelto <= 0 || $montoDevuelto > $adelanto['saldo_pendiente']) {
                throw new Exception("Monto de devolución inválido.");
            }

            // 1. Actualizar deuda
            $sqlUpd = "UPDATE rrhh_adelantos 
                       SET saldo_pendiente = saldo_pendiente - :monto,
                           estado = IF(saldo_pendiente - :monto <= 0, 'PAGADO', 'PENDIENTE')
                       WHERE id = :id";
            $db->prepare($sqlUpd)->execute(['monto' => $montoDevuelto, 'id' => $idAdelanto]);

            // 2. Registrar Ingreso en Tesorería
            $sqlMovimiento = "INSERT INTO tesoreria_movimientos (id_cuenta, tipo, monto, concepto, fecha, created_by) 
                              VALUES (:id_cuenta, 'INGRESO', :monto, :concepto, CURDATE(), :uid)";
            $conceptoTesoreria = "Devolución manual de adelanto - Personal ID: {$adelanto['id_tercero']}";
            $db->prepare($sqlMovimiento)->execute([
                'id_cuenta' => $idCuenta, 'monto' => $montoDevuelto, 
                'concepto' => $conceptoTesoreria, 'uid' => $userId
            ]);

            // 3. Sumar el saldo a la cuenta
            $db->prepare("UPDATE tesoreria_cuentas SET saldo_actual = saldo_actual + :monto WHERE id = :id_cuenta")
               ->execute(['monto' => $montoDevuelto, 'id_cuenta' => $idCuenta]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}