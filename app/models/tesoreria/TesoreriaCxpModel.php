<?php

declare(strict_types=1);

class TesoreriaCxpModel extends Modelo
{
    private ?bool $columnaIdGastoDisponible = null;

    public function listar(array $filtros = []): array
    {
        // MEJORA: Usamos LEFT JOIN para evitar perder el registro si el proveedor fue eliminado (soft-delete)
        $sql = 'SELECT p.*, COALESCE(t.nombre_completo, "Proveedor Eliminado/Desconocido") AS proveedor
                FROM tesoreria_cxp p
                LEFT JOIN terceros t ON t.id = p.id_proveedor
                WHERE p.deleted_at IS NULL';

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND p.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['moneda'])) {
            $sql .= ' AND p.moneda = :moneda';
            $params['moneda'] = (string) $filtros['moneda'];
        }

        if (!empty($filtros['vencimiento']) && $filtros['vencimiento'] === 'vencidas') {
            // MEJORA: DATE() asegura que la comparación sea estricta por día
            $sql .= ' AND p.saldo > 0 AND DATE(p.fecha_vencimiento) < CURDATE()';
        }

        // NUEVO CÓDIGO (Los más recientes siempre arriba):
        $sql .= ' ORDER BY p.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tesoreria_cxp WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearDesdeRecepcion(int $idRecepcion, int $userId): ?int
    {
        $db = $this->db();

        $stmtExiste = $db->prepare('SELECT id FROM tesoreria_cxp WHERE id_recepcion = :id LIMIT 1');
        $stmtExiste->execute(['id' => $idRecepcion]);
        $existe = (int) ($stmtExiste->fetchColumn() ?: 0);
        if ($existe > 0) {
            return $existe;
        }

        $stmtRecepcion = $db->prepare('SELECT r.id, r.id_orden_compra, o.id_proveedor, o.total, r.fecha_recepcion,
                                              COALESCE(tp.dias_credito, 0) AS dias_credito,
                                              UPPER(COALESCE(tp.condicion_pago, "CREDITO")) AS condicion_pago
                                       FROM compras_recepciones r
                                       INNER JOIN compras_ordenes o ON o.id = r.id_orden_compra AND o.deleted_at IS NULL
                                       LEFT JOIN terceros_proveedores tp ON tp.id_tercero = o.id_proveedor
                                       WHERE r.id = :id AND r.deleted_at IS NULL
                                       LIMIT 1');
        $stmtRecepcion->execute(['id' => $idRecepcion]);
        $rec = $stmtRecepcion->fetch(PDO::FETCH_ASSOC);

        if (!$rec) {
            return null;
        }

        $idProveedor = (int) ($rec['id_proveedor'] ?? 0);
        $stmtProveedor = $db->prepare('SELECT id FROM terceros WHERE id = :id AND es_proveedor = 1 AND estado = 1 AND deleted_at IS NULL LIMIT 1');
        $stmtProveedor->execute(['id' => $idProveedor]);
        if (!(bool) $stmtProveedor->fetchColumn()) {
            return null;
        }

        $fechaEmision = substr((string) ($rec['fecha_recepcion'] ?? date('Y-m-d')), 0, 10);
        $diasCredito = (int) ($rec['dias_credito'] ?? 0);
        $condicionPago = strtoupper((string) ($rec['condicion_pago'] ?? 'CREDITO'));
        if ($diasCredito < 0) {
            $diasCredito = 0;
        }

        $aplicaCredito = ($condicionPago === 'CREDITO' || $diasCredito > 0);
        $fechaVencimiento = $aplicaCredito
            ? date('Y-m-d', strtotime($fechaEmision . ' +' . $diasCredito . ' days'))
            : $fechaEmision;
        $total = round((float) ($rec['total'] ?? 0), 4);

        $stmtInsert = $db->prepare('INSERT INTO tesoreria_cxp
            (id_proveedor, id_orden_compra, id_recepcion, fecha_emision, fecha_vencimiento, moneda, monto_total, monto_pagado, saldo, estado, created_by, updated_by, created_at, updated_at)
            VALUES
            (:id_proveedor, :id_orden_compra, :id_recepcion, :fecha_emision, :fecha_vencimiento, :moneda, :monto_total, 0, :saldo, :estado, :created_by, :updated_by, NOW(), NOW())');

        $stmtInsert->execute([
            'id_proveedor'      => $idProveedor,
            'id_orden_compra'   => (int) ($rec['id_orden_compra'] ?? 0),
            'id_recepcion'      => $idRecepcion,
            'fecha_emision'     => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'moneda'            => 'PEN',
            'monto_total'       => $total,
            'saldo'             => $total,
            // CAMBIO: Estado inicial pasa a ser PENDIENTE
            'estado'            => $total > 0 ? 'PENDIENTE' : 'PAGADA',
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    public function recalcularEstado(int $id, int $userId): void
    {
        // CAMBIO: Nueva lógica de estados (PAGADA, PARCIAL, PENDIENTE, VENCIDA)
        $stmt = $this->db()->prepare('UPDATE tesoreria_cxp
            SET saldo = GREATEST(ROUND(monto_total - monto_pagado, 4), 0),
                estado = CASE
                    WHEN estado = "ANULADA" THEN "ANULADA"
                    WHEN ROUND(monto_total - monto_pagado, 4) <= 0 THEN "PAGADA"
                    WHEN monto_pagado > 0 AND ROUND(monto_total - monto_pagado, 4) > 0 THEN "PARCIAL"
                    WHEN monto_pagado <= 0 AND DATE(fecha_vencimiento) >= CURDATE() THEN "PENDIENTE"
                    WHEN monto_pagado <= 0 AND DATE(fecha_vencimiento) < CURDATE() THEN "VENCIDA"
                    ELSE "PENDIENTE"
                END,
                updated_by = :user,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute(['id' => $id, 'user' => $userId]);

        $idGasto = 0;
        $estadoCxp = 'PENDIENTE';

        if ($this->tieneColumnaIdGasto()) {
            $stmtRel = $this->db()->prepare('SELECT id_gasto, estado FROM tesoreria_cxp WHERE id = :id LIMIT 1');
            $stmtRel->execute(['id' => $id]);
            $rel = $stmtRel->fetch(PDO::FETCH_ASSOC) ?: null;
            $idGasto = (int) ($rel['id_gasto'] ?? 0);
            $estadoCxp = strtoupper((string) ($rel['estado'] ?? 'PENDIENTE'));
        } else {
            $stmtRel = $this->db()->prepare('SELECT id, estado FROM tesoreria_cxp WHERE id = :id LIMIT 1');
            $stmtRel->execute(['id' => $id]);
            $rel = $stmtRel->fetch(PDO::FETCH_ASSOC) ?: null;
            $estadoCxp = strtoupper((string) ($rel['estado'] ?? 'PENDIENTE'));

            $stmtGasto = $this->db()->prepare('SELECT id FROM gastos_registros WHERE id_cxp = :id_cxp AND deleted_at IS NULL LIMIT 1');
            $stmtGasto->execute(['id_cxp' => $id]);
            $idGasto = (int) ($stmtGasto->fetchColumn() ?: 0);
        }

        if ($idGasto > 0) {
            $estadoGasto = 'PENDIENTE';
            
            // Sincronización exacta de estados
            if ($estadoCxp === 'PAGADA') {
                $estadoGasto = 'PAGADO';
            } elseif ($estadoCxp === 'PARCIAL') {
                $estadoGasto = 'PARCIAL';
            } elseif ($estadoCxp === 'ANULADA') {
                $estadoGasto = 'ANULADO';
            }

            $stmtG = $this->db()->prepare('UPDATE gastos_registros
                SET estado = :estado, updated_by = :user, updated_at = NOW()
                WHERE id = :id_gasto AND deleted_at IS NULL');
            $stmtG->execute([
                'estado' => $estadoGasto,
                'user' => $userId,
                'id_gasto' => $idGasto,
            ]);
        }
    }

    public function crearDesdeGasto(int $idGasto, int $userId): int
    {
        $db = $this->db();

        // 1. Verificamos si ya existe para no duplicar
        $stmtExiste = $db->prepare('SELECT id_cxp FROM gastos_registros WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmtExiste->execute(['id' => $idGasto]);
        $idCxpExistente = (int) ($stmtExiste->fetchColumn() ?: 0);
        if ($idCxpExistente > 0) {
            return $idCxpExistente;
        }

        // 2. Obtenemos los datos del gasto recién creado
        $stmtGasto = $db->prepare('SELECT id, fecha, id_proveedor, total
                                   FROM gastos_registros
                                   WHERE id = :id AND deleted_at IS NULL
                                   LIMIT 1');
        $stmtGasto->execute(['id' => $idGasto]);
        $gasto = $stmtGasto->fetch(PDO::FETCH_ASSOC);
        
        if (!$gasto) {
            throw new RuntimeException('No se encontró el gasto para generar CxP.');
        }

        $fecha = substr((string) ($gasto['fecha'] ?? date('Y-m-d')), 0, 10);
        $total = round((float) ($gasto['total'] ?? 0), 4);

        // 3. Insertamos directamente la Cuenta por Pagar
        $stmtInsert = $db->prepare('INSERT INTO tesoreria_cxp
            (id_proveedor, fecha_emision, fecha_vencimiento, moneda, monto_total, monto_pagado, saldo, estado, created_by, updated_by, created_at, updated_at)
            VALUES
            (:id_proveedor, :fecha_emision, :fecha_vencimiento, :moneda, :monto_total, 0, :saldo, :estado, :created_by, :updated_by, NOW(), NOW())');

        $exito = $stmtInsert->execute([
            'id_proveedor'      => (int) ($gasto['id_proveedor'] ?? 0),
            'fecha_emision'     => $fecha,
            'fecha_vencimiento' => $fecha,
            'moneda'            => 'PEN',
            'monto_total'       => $total,
            'saldo'             => $total,
            'estado'            => $total > 0 ? 'PENDIENTE' : 'PAGADA',
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);

        if (!$exito) {
            throw new RuntimeException('Fallo al insertar la Cuenta por Pagar.');
        }

        $nuevoId = (int) $db->lastInsertId();
        
        if ($nuevoId === 0) {
             throw new RuntimeException('La Cuenta por Pagar se insertó pero no devolvió un ID válido.');
        }

        return $nuevoId;
    }

    private function asegurarColumnaIdGasto(): void
    {
        if ($this->tieneColumnaIdGasto()) {
            return;
        }

        try {
            $this->db()->exec('ALTER TABLE tesoreria_cxp ADD COLUMN id_gasto INT NULL AFTER id_recepcion');
            $this->db()->exec('ALTER TABLE tesoreria_cxp ADD KEY idx_tesoreria_cxp_id_gasto (id_gasto)');
            $this->columnaIdGastoDisponible = true;
        } catch (Throwable $e) {
            // Si no se puede alterar la tabla, mantenemos compatibilidad usando el vínculo gastos_registros.id_cxp.
            $this->columnaIdGastoDisponible = $this->consultarExistenciaColumna('id_gasto');
        }
    }

    private function tieneColumnaIdGasto(): bool
    {
        if ($this->columnaIdGastoDisponible !== null) {
            return $this->columnaIdGastoDisponible;
        }

        $this->columnaIdGastoDisponible = $this->consultarExistenciaColumna('id_gasto');
        return $this->columnaIdGastoDisponible;
    }

    private function consultarExistenciaColumna(string $columna): bool
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :tabla
              AND column_name = :columna');
        $stmt->execute([
            'tabla' => 'tesoreria_cxp',
            'columna' => $columna,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listarPendientesPorAntiguedad(int $idProveedor, string $moneda): array
    {
        $stmt = $this->db()->prepare('SELECT id
            FROM tesoreria_cxp
            WHERE id_proveedor = :id_proveedor
              AND moneda = :moneda
              AND estado <> "ANULADA"
              AND saldo > 0
              AND deleted_at IS NULL
            ORDER BY fecha_emision ASC, fecha_vencimiento ASC, id ASC');
        $stmt->execute([
            'id_proveedor' => $idProveedor,
            'moneda' => strtoupper(trim($moneda)),
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

}
