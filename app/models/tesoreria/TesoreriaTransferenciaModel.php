<?php

declare(strict_types=1);

class TesoreriaTransferenciaModel extends Modelo
{
    public function __construct()
    {
        parent::__construct();
        $this->ensureTable();
    }

    public function registrar(array $payload, int $userId): int
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $idCuentaOrigen = (int) ($payload['id_cuenta_origen'] ?? 0);
            $idCuentaDestino = (int) ($payload['id_cuenta_destino'] ?? 0);
            $fecha = trim((string) ($payload['fecha'] ?? ''));
            $moneda = strtoupper(trim((string) ($payload['moneda'] ?? 'PEN')));
            $monto = round((float) ($payload['monto'] ?? 0), 4);
            $referencia = trim((string) ($payload['referencia'] ?? ''));
            $observaciones = trim((string) ($payload['observaciones'] ?? ''));

            if ($idCuentaOrigen <= 0 || $idCuentaDestino <= 0) {
                throw new RuntimeException('Debe seleccionar cuentas origen y destino válidas.');
            }
            if ($idCuentaOrigen === $idCuentaDestino) {
                throw new RuntimeException('La cuenta origen y destino no pueden ser la misma.');
            }
            if ($fecha === '') {
                throw new RuntimeException('La fecha es obligatoria.');
            }
            if (!in_array($moneda, ['PEN', 'USD'], true)) {
                throw new RuntimeException('Moneda inválida.');
            }
            if ($monto <= 0) {
                throw new RuntimeException('El monto debe ser mayor a cero.');
            }

            $stmtCuenta = $db->prepare('SELECT id, nombre, moneda, estado
                                        FROM tesoreria_cuentas
                                        WHERE id = :id AND deleted_at IS NULL
                                        LIMIT 1
                                        FOR UPDATE');

            $stmtCuenta->execute(['id' => $idCuentaOrigen]);
            $cuentaOrigen = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            if (!$cuentaOrigen || (int) ($cuentaOrigen['estado'] ?? 0) !== 1) {
                throw new RuntimeException('La cuenta origen no existe o está inactiva.');
            }

            $stmtCuenta->execute(['id' => $idCuentaDestino]);
            $cuentaDestino = $stmtCuenta->fetch(PDO::FETCH_ASSOC);
            if (!$cuentaDestino || (int) ($cuentaDestino['estado'] ?? 0) !== 1) {
                throw new RuntimeException('La cuenta destino no existe o está inactiva.');
            }

            if (strtoupper((string) $cuentaOrigen['moneda']) !== $moneda || strtoupper((string) $cuentaDestino['moneda']) !== $moneda) {
                throw new RuntimeException('La moneda de transferencia debe coincidir con la moneda de ambas cuentas.');
            }

            $saldoOrigen = $this->obtenerSaldoCuentaTx($db, $idCuentaOrigen);
            if ($saldoOrigen < $monto) {
                throw new RuntimeException('Saldo insuficiente en la cuenta origen.');
            }

            $stmtInsert = $db->prepare('INSERT INTO tesoreria_transferencias
                (fecha, id_cuenta_origen, id_cuenta_destino, moneda, monto, referencia, observaciones, estado, created_by, updated_by, created_at, updated_at)
                VALUES
                (:fecha, :id_cuenta_origen, :id_cuenta_destino, :moneda, :monto, :referencia, :observaciones, "CONFIRMADA", :created_by, :updated_by, NOW(), NOW())');

            $stmtInsert->execute([
                'fecha' => $fecha,
                'id_cuenta_origen' => $idCuentaOrigen,
                'id_cuenta_destino' => $idCuentaDestino,
                'moneda' => $moneda,
                'monto' => $monto,
                'referencia' => $referencia !== '' ? $referencia : null,
                'observaciones' => $observaciones !== '' ? $observaciones : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $id = (int) $db->lastInsertId();
            $db->commit();

            return $id;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function obtenerSaldoCuentaTx(PDO $db, int $idCuenta): float
    {
        $stmt = $db->prepare('SELECT
                COALESCE(c.saldo_inicial, 0)
                + COALESCE((
                    SELECT SUM(CASE
                        WHEN m.estado = "CONFIRMADO" AND m.tipo = "COBRO" THEN m.monto
                        WHEN m.estado = "CONFIRMADO" AND m.tipo = "PAGO" THEN -m.monto
                        ELSE 0
                    END)
                    FROM tesoreria_movimientos m
                    WHERE m.id_cuenta = c.id AND m.deleted_at IS NULL
                ), 0)
                + COALESCE((
                    SELECT SUM(CASE
                        WHEN t.estado = "CONFIRMADA" AND t.id_cuenta_destino = c.id THEN t.monto
                        WHEN t.estado = "CONFIRMADA" AND t.id_cuenta_origen = c.id THEN -t.monto
                        ELSE 0
                    END)
                    FROM tesoreria_transferencias t
                    WHERE (t.id_cuenta_origen = c.id OR t.id_cuenta_destino = c.id)
                      AND t.deleted_at IS NULL
                ), 0) AS saldo_actual
            FROM tesoreria_cuentas c
            WHERE c.id = :id
            LIMIT 1');
        $stmt->execute(['id' => $idCuenta]);

        return round((float) $stmt->fetchColumn(), 4);
    }

    private function ensureTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS tesoreria_transferencias (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            fecha DATE NOT NULL,
            id_cuenta_origen INT NOT NULL,
            id_cuenta_destino INT NOT NULL,
            moneda ENUM("PEN", "USD") NOT NULL DEFAULT "PEN",
            monto DECIMAL(14,4) NOT NULL,
            referencia VARCHAR(120) NULL,
            observaciones VARCHAR(255) NULL,
            estado ENUM("CONFIRMADA", "ANULADA") NOT NULL DEFAULT "CONFIRMADA",
            created_by INT NULL,
            updated_by INT NULL,
            deleted_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            KEY idx_tes_trf_fecha (fecha),
            KEY idx_tes_trf_origen (id_cuenta_origen, estado),
            KEY idx_tes_trf_destino (id_cuenta_destino, estado),
            CONSTRAINT fk_tes_trf_cuenta_origen FOREIGN KEY (id_cuenta_origen) REFERENCES tesoreria_cuentas(id),
            CONSTRAINT fk_tes_trf_cuenta_destino FOREIGN KEY (id_cuenta_destino) REFERENCES tesoreria_cuentas(id),
            CONSTRAINT chk_tes_trf_monto CHECK (monto > 0),
            CONSTRAINT chk_tes_trf_cuentas_distintas CHECK (id_cuenta_origen <> id_cuenta_destino)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->db()->exec($sql);
    }
}
