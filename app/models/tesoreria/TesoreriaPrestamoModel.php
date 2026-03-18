<?php

declare(strict_types=1);

class TesoreriaPrestamoModel extends Modelo
{
    private ?bool $columnaIdGastoDisponible = null;

    public function __construct()
    {
        parent::__construct();
        $this->ensureTable();
    }

    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT p.*, 
                       cxp.id AS id_cxp,
                       cxp.monto_total,
                       cxp.monto_pagado,
                       cxp.saldo,
                       cxp.moneda,
                       cxp.estado,
                       cxp.fecha_vencimiento,
                       COALESCE(t.nombre_completo, "Proveedor Eliminado/Desconocido") AS proveedor
                FROM tesoreria_prestamos p
                INNER JOIN tesoreria_cxp cxp ON cxp.id = p.id_cxp
                LEFT JOIN terceros t ON t.id = cxp.id_proveedor
                WHERE p.deleted_at IS NULL
                  AND cxp.deleted_at IS NULL';

        $params = [];
        $estado = strtoupper(trim((string) ($filtros['estado'] ?? '')));
        if ($estado !== '') {
            $sql .= ' AND cxp.estado = :estado';
            $params['estado'] = $estado;
        }

        $sql .= ' ORDER BY p.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crear(array $data, int $userId): int
    {
        $idProveedor = (int) ($data['id_proveedor'] ?? 0);
        $numeroContrato = trim((string) ($data['numero_contrato'] ?? ''));
        $entidad = trim((string) ($data['entidad_financiera'] ?? ''));
        $fechaDesembolso = trim((string) ($data['fecha_desembolso'] ?? date('Y-m-d')));
        $fechaPrimeraCuota = trim((string) ($data['fecha_primera_cuota'] ?? $fechaDesembolso));
        $monto = round((float) ($data['monto_total'] ?? 0), 4);
        $moneda = strtoupper(trim((string) ($data['moneda'] ?? 'PEN')));
        $tipoTasa = strtoupper(trim((string) ($data['tipo_tasa'] ?? 'FIJA')));
        $tasaAnual = round((float) ($data['tasa_anual'] ?? 0), 4);
        $numeroCuotas = (int) ($data['numero_cuotas'] ?? 1);
        $observaciones = trim((string) ($data['observaciones'] ?? ''));

        if ($idProveedor <= 0) {
            throw new RuntimeException('Debe seleccionar la entidad financiera (tercero proveedor).');
        }
        $stmtProveedor = $this->db()->prepare('SELECT id
            FROM terceros
            WHERE id = :id
              AND es_proveedor = 1
              AND estado = 1
              AND deleted_at IS NULL
            LIMIT 1');
        $stmtProveedor->execute(['id' => $idProveedor]);
        if (!(bool) $stmtProveedor->fetchColumn()) {
            throw new RuntimeException('La entidad financiera seleccionada no está activa como proveedor.');
        }
        if ($entidad === '') {
            throw new RuntimeException('La entidad financiera es obligatoria.');
        }
        if ($monto <= 0) {
            throw new RuntimeException('El monto del préstamo debe ser mayor a cero.');
        }
        if (!in_array($moneda, ['PEN', 'USD'], true)) {
            throw new RuntimeException('La moneda del préstamo es inválida.');
        }
        if (!in_array($tipoTasa, ['FIJA', 'VARIABLE'], true)) {
            throw new RuntimeException('El tipo de tasa es inválido.');
        }
        if ($tasaAnual < 0) {
            throw new RuntimeException('La tasa anual no puede ser negativa.');
        }
        if ($numeroCuotas <= 0) {
            throw new RuntimeException('El número de cuotas debe ser mayor a cero.');
        }

        $fechaVencimiento = date('Y-m-d', strtotime($fechaPrimeraCuota . ' +' . max(1, $numeroCuotas) . ' months'));

        $db = $this->db();
        $db->beginTransaction();
        try {
            $columnas = ['id_proveedor', 'id_orden_compra', 'id_recepcion'];
            $values = [':id_proveedor', 'NULL', 'NULL'];
            if ($this->tieneColumnaIdGasto()) {
                $columnas[] = 'id_gasto';
                $values[] = 'NULL';
            }
            $columnas = array_merge($columnas, [
                'fecha_emision',
                'fecha_vencimiento',
                'moneda',
                'monto_total',
                'monto_pagado',
                'saldo',
                'estado',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ]);
            $values = array_merge($values, [
                ':fecha_emision',
                ':fecha_vencimiento',
                ':moneda',
                ':monto_total',
                '0',
                ':saldo',
                '"PENDIENTE"',
                ':created_by',
                ':updated_by',
                'NOW()',
                'NOW()',
            ]);
            $stmtCxp = $db->prepare('INSERT INTO tesoreria_cxp (' . implode(', ', $columnas) . ')
                VALUES (' . implode(', ', $values) . ')');
            $stmtCxp->execute([
                'id_proveedor'      => $idProveedor,
                'fecha_emision'     => $fechaDesembolso,
                'fecha_vencimiento' => $fechaVencimiento,
                'moneda'            => $moneda,
                'monto_total'       => $monto,
                'saldo'             => $monto,
                'created_by'        => $userId,
                'updated_by'        => $userId,
            ]);
            $idCxp = (int) $db->lastInsertId();

            $stmtPrestamo = $db->prepare('INSERT INTO tesoreria_prestamos
                (id_cxp, numero_contrato, entidad_financiera, fecha_desembolso, fecha_primera_cuota, tipo_tasa, tasa_anual, numero_cuotas, observaciones, created_by, updated_by, created_at, updated_at)
                VALUES
                (:id_cxp, :numero_contrato, :entidad_financiera, :fecha_desembolso, :fecha_primera_cuota, :tipo_tasa, :tasa_anual, :numero_cuotas, :observaciones, :created_by, :updated_by, NOW(), NOW())');
            $stmtPrestamo->execute([
                'id_cxp'              => $idCxp,
                'numero_contrato'     => $numeroContrato !== '' ? $numeroContrato : null,
                'entidad_financiera'  => $entidad,
                'fecha_desembolso'    => $fechaDesembolso,
                'fecha_primera_cuota' => $fechaPrimeraCuota,
                'tipo_tasa'           => $tipoTasa,
                'tasa_anual'          => $tasaAnual,
                'numero_cuotas'       => $numeroCuotas,
                'observaciones'       => $observaciones !== '' ? $observaciones : null,
                'created_by'          => $userId,
                'updated_by'          => $userId,
            ]);
            $idPrestamo = (int) $db->lastInsertId();

            $db->commit();
            return $idPrestamo;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function ensureTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS tesoreria_prestamos (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            id_cxp INT NOT NULL,
            numero_contrato VARCHAR(80) NULL,
            entidad_financiera VARCHAR(160) NOT NULL,
            fecha_desembolso DATE NOT NULL,
            fecha_primera_cuota DATE NULL,
            tipo_tasa ENUM("FIJA","VARIABLE") NOT NULL DEFAULT "FIJA",
            tasa_anual DECIMAL(10,4) NOT NULL DEFAULT 0,
            numero_cuotas INT NOT NULL DEFAULT 1,
            observaciones VARCHAR(255) NULL,
            created_by INT NULL,
            updated_by INT NULL,
            deleted_by INT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            deleted_at DATETIME NULL,
            UNIQUE KEY uq_tesoreria_prestamos_id_cxp (id_cxp),
            KEY idx_tesoreria_prestamos_estado (deleted_at),
            CONSTRAINT fk_tesoreria_prestamos_cxp FOREIGN KEY (id_cxp) REFERENCES tesoreria_cxp(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

        $this->db()->exec($sql);
    }

    private function tieneColumnaIdGasto(): bool
    {
        if ($this->columnaIdGastoDisponible !== null) {
            return $this->columnaIdGastoDisponible;
        }

        $stmt = $this->db()->prepare('SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :tabla
              AND column_name = :columna');
        $stmt->execute([
            'tabla' => 'tesoreria_cxp',
            'columna' => 'id_gasto',
        ]);

        $this->columnaIdGastoDisponible = (int) $stmt->fetchColumn() > 0;
        return $this->columnaIdGastoDisponible;
    }
}
