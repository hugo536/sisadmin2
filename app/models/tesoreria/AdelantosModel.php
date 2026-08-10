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
        $sql = "SELECT c.id, c.nombre, c.moneda,
                       (COALESCE(c.saldo_inicial, 0)
                        + COALESCE(mov.saldo_delta, 0)
                        + COALESCE(trf.saldo_delta, 0)) AS saldo_actual
                FROM tesoreria_cuentas c
                LEFT JOIN (
                    SELECT id_cuenta,
                           SUM(CASE
                               WHEN estado = 'CONFIRMADO' AND tipo IN ('COBRO', 'INGRESO') THEN monto
                               WHEN estado = 'CONFIRMADO' AND tipo IN ('PAGO', 'EGRESO') THEN -monto
                               ELSE 0
                           END) AS saldo_delta
                    FROM tesoreria_movimientos
                    WHERE deleted_at IS NULL
                    GROUP BY id_cuenta
                ) mov ON mov.id_cuenta = c.id
                LEFT JOIN (
                    SELECT cuenta_id, SUM(delta) AS saldo_delta
                    FROM (
                        SELECT id_cuenta_destino AS cuenta_id, monto_destino AS delta
                        FROM tesoreria_transferencias
                        WHERE deleted_at IS NULL AND estado = 'CONFIRMADA'
                        UNION ALL
                        SELECT id_cuenta_origen AS cuenta_id, -monto_origen AS delta
                        FROM tesoreria_transferencias
                        WHERE deleted_at IS NULL AND estado = 'CONFIRMADA'
                    ) transferencias
                    GROUP BY cuenta_id
                ) trf ON trf.cuenta_id = c.id
                WHERE c.estado = 1 AND c.deleted_at IS NULL
                ORDER BY c.nombre ASC";
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
            
            // 1. Manejo seguro de la observación para evitar errores de MySQL Strict Mode
            $obs = trim($datos['observacion'] ?? '');
            $obs = $obs !== '' ? $obs : null;

            // 2. Registrar la deuda en RRHH
            $sqlAdelanto = "INSERT INTO rrhh_adelantos (id_tercero, id_cuenta_tesoreria, monto, saldo_pendiente, fecha, observacion, estado, created_by) 
                            VALUES (:id_tercero, :id_cuenta, :monto, :saldo, :fecha, :obs, 'PENDIENTE', :uid)";
            
            $db->prepare($sqlAdelanto)->execute([
                'id_tercero' => $idTercero, 
                'id_cuenta' => $idCuenta, 
                'monto' => $monto, 
                'saldo' => $monto, 
                'fecha' => $fecha, 
                'obs' => $obs, 
                'uid' => $userId
            ]);

            // 3. Registrar el Egreso en Tesorería (Corregido a 'observaciones')
            $sqlMovimiento = "INSERT INTO tesoreria_movimientos (id_cuenta, tipo, monto, observaciones, fecha, estado, created_by) 
                              VALUES (:id_cuenta, 'EGRESO', :monto, :observaciones, :fecha, 'CONFIRMADO', :uid)";
            
            $conceptoTesoreria = "Adelanto de sueldo a personal (ID: {$idTercero})";
            if ($obs) {
                $conceptoTesoreria .= " - " . $obs;
            }

            $db->prepare($sqlMovimiento)->execute([
                'id_cuenta' => $idCuenta, 
                'monto' => $monto, 
                'observaciones' => $conceptoTesoreria, 
                'fecha' => $fecha, 
                'uid' => $userId
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            // Restauramos el log normal para que no vuelva a salir la pantalla roja
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

            // 2. Registrar Ingreso en Tesorería (Corregido a 'observaciones' y 'CONFIRMADO')
            $sqlMovimiento = "INSERT INTO tesoreria_movimientos (id_cuenta, tipo, monto, observaciones, fecha, estado, created_by) 
                              VALUES (:id_cuenta, 'INGRESO', :monto, :observaciones, CURDATE(), 'CONFIRMADO', :uid)";
            
            $conceptoTesoreria = "Devolución manual de adelanto - Personal ID: {$adelanto['id_tercero']}";
            
            $db->prepare($sqlMovimiento)->execute([
                'id_cuenta' => $idCuenta, 
                'monto' => $montoDevuelto, 
                'observaciones' => $conceptoTesoreria, 
                'uid' => $userId
            ]);

            $db->commit();
            return true;
        } catch (Exception $e) {
            $db->rollBack();
            return false;
        }
    }
}
