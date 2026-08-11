<?php
declare(strict_types=1);

class AdelantosModel extends Modelo
{
    private string $ultimoError = '';

    public function obtenerUltimoError(): string
    {
        return $this->ultimoError;
    }

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
        $this->ultimoError = '';
        try {
            $this->asegurarEsquemaMovimientos($db);
            $db->beginTransaction();

            $idTercero = (int) $datos['id_tercero'];
            $idCuenta = (int) $datos['id_cuenta'];
            $monto = (float) $datos['monto'];
            $fecha = $datos['fecha'];

            if ($idTercero <= 0 || $idCuenta <= 0 || $monto <= 0) {
                throw new InvalidArgumentException('Los datos del adelanto no son válidos.');
            }

            $cuenta = $this->obtenerCuentaConSaldo($db, $idCuenta);
            if (!$cuenta || (float) $cuenta['saldo_actual'] + 0.0001 < $monto) {
                throw new RuntimeException('La cuenta seleccionada no tiene saldo suficiente.');
            }

            $idMetodoPago = $this->obtenerMetodoPago($db, (string) $cuenta['tipo']);
            
            // 1. Manejo seguro de la observación
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

            $idAdelanto = (int) $db->lastInsertId();

            // 3. Registrar el egreso en Tesorería
            $sqlMovimiento = "INSERT INTO tesoreria_movimientos
                                (id_cuenta, id_metodo_pago, id_tercero, tipo, origen, id_origen,
                                 moneda, monto, observaciones, fecha, estado, created_by, updated_by,
                                 created_at, updated_at)
                              VALUES
                                (:id_cuenta, :id_metodo_pago, :id_tercero, 'EGRESO', 'ADELANTO', :id_origen,
                                 :moneda, :monto, :observaciones, :fecha, 'CONFIRMADO', :created_by, :updated_by,
                                 NOW(), NOW())";
            
            $conceptoTesoreria = "Adelanto de sueldo a personal (ID: {$idTercero})";
            if ($obs) {
                $conceptoTesoreria .= " - " . $obs;
            }

            $db->prepare($sqlMovimiento)->execute([
                'id_cuenta' => $idCuenta, 
                'id_metodo_pago' => $idMetodoPago,
                'id_tercero' => $idTercero,
                'id_origen' => $idAdelanto,
                'moneda' => $cuenta['moneda'],
                'monto' => $monto, 
                'observaciones' => $conceptoTesoreria, 
                'fecha' => $fecha, 
                'created_by' => $userId,
                'updated_by' => $userId
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->ultimoError = $this->mensajeSeguro($e);
            error_log("Error al registrar adelanto: " . $e->getMessage());
            return false;
        }
    }

    public function registrarDevolucionManual(array $datos, int $userId): bool
    {
        $db = $this->db();
        $this->ultimoError = '';
        try {
            $this->asegurarEsquemaMovimientos($db);
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
            $nuevoSaldo = (float)$adelanto['saldo_pendiente'] - $montoDevuelto;
            $nuevoEstado = $nuevoSaldo <= 0.001 ? 'PAGADO' : 'PENDIENTE'; 

            $sqlUpd = "UPDATE rrhh_adelantos 
                       SET saldo_pendiente = :nuevoSaldo,
                           estado = :nuevoEstado
                       WHERE id = :id";
            $db->prepare($sqlUpd)->execute([
                'nuevoSaldo' => $nuevoSaldo,
                'nuevoEstado' => $nuevoEstado,
                'id' => $idAdelanto
            ]);

            $cuenta = $this->obtenerCuentaConSaldo($db, $idCuenta);
            if (!$cuenta) {
                throw new RuntimeException('La cuenta de destino no existe o está inactiva.');
            }
            $idMetodoPago = $this->obtenerMetodoPago($db, (string) $cuenta['tipo']);

            // 2. Registrar el ingreso en Tesorería
            $sqlMovimiento = "INSERT INTO tesoreria_movimientos
                                (id_cuenta, id_metodo_pago, id_tercero, tipo, origen, id_origen,
                                 moneda, monto, observaciones, fecha, estado, created_by, updated_by,
                                 created_at, updated_at)
                              VALUES
                                (:id_cuenta, :id_metodo_pago, :id_tercero, 'INGRESO', 'ADELANTO', :id_origen,
                                 :moneda, :monto, :observaciones, CURDATE(), 'CONFIRMADO', :created_by, :updated_by,
                                 NOW(), NOW())";
            
            $conceptoTesoreria = "Devolución manual de adelanto - Personal ID: {$adelanto['id_tercero']}";
            
            $db->prepare($sqlMovimiento)->execute([
                'id_cuenta' => $idCuenta, 
                'id_metodo_pago' => $idMetodoPago,
                'id_tercero' => (int) $adelanto['id_tercero'],
                'id_origen' => $idAdelanto,
                'moneda' => $cuenta['moneda'],
                'monto' => $montoDevuelto, 
                'observaciones' => $conceptoTesoreria, 
                'created_by' => $userId,
                'updated_by' => $userId
            ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $this->ultimoError = $this->mensajeSeguro($e);
            error_log("Error al registrar devolución de adelanto: " . $e->getMessage());
            return false;
        }
    }

    public function obtenerHistorialAdelanto(int $idAdelanto): array
    {
        try {
            $sql = "SELECT 
                        DATE_FORMAT(fecha_raw, '%d/%m/%Y') AS fecha,
                        origen,
                        monto
                    FROM (
                        -- 1. Devoluciones manuales en caja/banco
                        SELECT 
                            m.fecha AS fecha_raw,
                            CONCAT('Devolución en ', COALESCE(c.nombre, 'Caja')) AS origen,
                            m.monto
                        FROM tesoreria_movimientos m
                        LEFT JOIN tesoreria_cuentas c ON m.id_cuenta = c.id
                        WHERE m.origen = 'ADELANTO' 
                        AND m.tipo = 'INGRESO' 
                        AND m.id_origen = :id_adelanto_tesoreria
                        AND m.deleted_at IS NULL
                        
                        UNION ALL
                        
                        -- 2. Descuentos automáticos o manuales por planilla
                        SELECT 
                            n.fecha_fin AS fecha_raw,
                            CONCAT('Planilla Cerrada (Ref: ', COALESCE(n.referencia, 'S/R'), ')') AS origen,
                            nc.monto
                        FROM rrhh_nominas_conceptos nc
                        INNER JOIN rrhh_nominas_detalles nd ON nd.id = nc.id_detalle_nomina
                        INNER JOIN rrhh_nominas n ON n.id = nd.id_nomina
                        WHERE nc.id_adelanto_ref = :id_adelanto_planilla
                        AND n.estado = 'APROBADO'
                    ) AS historial
                    ORDER BY fecha_raw DESC";

            $stmt = $this->db()->prepare($sql);
            
            // CORRECCIÓN 1: Se añadieron los ':' en las claves
            $stmt->execute([
                ':id_adelanto_tesoreria' => $idAdelanto,
                ':id_adelanto_planilla' => $idAdelanto
            ]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            
        } catch (PDOException $e) {
            // CORRECCIÓN 2: Si falla la BD, registramos el error real para poder leerlo
            error_log("❌ Error SQL en obtenerHistorialAdelanto: " . $e->getMessage());
            return []; 
        }
    }

    private function asegurarEsquemaMovimientos(PDO $db): void
    {
        $stmt = $db->query("SELECT COLUMN_NAME, COLUMN_TYPE
                            FROM information_schema.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE()
                              AND TABLE_NAME = 'tesoreria_movimientos'
                              AND COLUMN_NAME IN ('tipo', 'origen')");
        $columnas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $columna) {
            $columnas[(string) $columna['COLUMN_NAME']] = strtoupper((string) $columna['COLUMN_TYPE']);
        }

        if (!isset($columnas['tipo'], $columnas['origen'])) {
            throw new RuntimeException('La estructura del ledger de tesorería está incompleta.');
        }

        $tipoCompatible = str_contains($columnas['tipo'], "'INGRESO'")
            && str_contains($columnas['tipo'], "'EGRESO'");
        $origenCompatible = str_contains($columnas['origen'], "'ADELANTO'");
        if ($tipoCompatible && $origenCompatible) {
            return;
        }

        $db->exec("ALTER TABLE tesoreria_movimientos
                   MODIFY COLUMN tipo ENUM('COBRO','PAGO','INGRESO','EGRESO') NOT NULL,
                   MODIFY COLUMN origen ENUM('CXC','CXP','ADELANTO') NOT NULL");
    }

    private function mensajeSeguro(Throwable $error): string
    {
        if ($error instanceof InvalidArgumentException || $error instanceof RuntimeException) {
            return $error->getMessage();
        }

        return 'No se pudo completar la operación. Revise el registro técnico del servidor.';
    }

    private function obtenerCuentaConSaldo(PDO $db, int $idCuenta): ?array
    {
        $sql = "SELECT c.id, c.tipo, c.moneda,
                       (COALESCE(c.saldo_inicial, 0)
                        + COALESCE((SELECT SUM(CASE
                            WHEN m.estado = 'CONFIRMADO' AND m.tipo IN ('COBRO', 'INGRESO') THEN m.monto
                            WHEN m.estado = 'CONFIRMADO' AND m.tipo IN ('PAGO', 'EGRESO') THEN -m.monto
                            ELSE 0 END)
                          FROM tesoreria_movimientos m
                          WHERE m.id_cuenta = c.id AND m.deleted_at IS NULL), 0)
                        + COALESCE((SELECT SUM(x.delta) FROM (
                            SELECT t.id_cuenta_destino AS cuenta_id, t.monto_destino AS delta
                            FROM tesoreria_transferencias t
                            WHERE t.deleted_at IS NULL AND t.estado = 'CONFIRMADA'
                            UNION ALL
                            SELECT t.id_cuenta_origen AS cuenta_id, -t.monto_origen AS delta
                            FROM tesoreria_transferencias t
                            WHERE t.deleted_at IS NULL AND t.estado = 'CONFIRMADA'
                          ) x WHERE x.cuenta_id = c.id), 0)) AS saldo_actual
                FROM tesoreria_cuentas c
                WHERE c.id = :id AND c.estado = 1 AND c.deleted_at IS NULL
                LIMIT 1 FOR UPDATE";
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $idCuenta]);
        $cuenta = $stmt->fetch(PDO::FETCH_ASSOC);
        return $cuenta ?: null;
    }

    private function obtenerMetodoPago(PDO $db, string $tipoCuenta): int
    {
        $nombre = strtoupper($tipoCuenta) === 'BANCO' ? 'Transferencia' : 'Efectivo';
        $stmt = $db->prepare('SELECT id FROM tesoreria_metodos_pago WHERE nombre = :nombre AND estado = 1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['nombre' => $nombre]);
        $idMetodo = (int) $stmt->fetchColumn();
        if ($idMetodo <= 0) {
            throw new RuntimeException("No existe un método de pago activo para {$nombre}.");
        }
        return $idMetodo;
    }
}