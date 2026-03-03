<?php

declare(strict_types=1);

class TesoreriaCxpModel extends Modelo
{
    public function listarProveedoresConSaldoPendiente(): array
    {
        $sql = 'SELECT p.id_proveedor,
                       p.moneda,
                       ROUND(SUM(p.saldo), 4) AS saldo_total,
                       COALESCE(t.nombre_completo, "Proveedor Eliminado/Desconocido") AS proveedor
                FROM tesoreria_cxp p
                LEFT JOIN terceros t ON t.id = p.id_proveedor
                WHERE p.deleted_at IS NULL
                  AND p.estado <> "ANULADA"
                  AND p.saldo > 0
                GROUP BY p.id_proveedor, p.moneda, t.nombre_completo
                ORDER BY t.nombre_completo ASC, p.moneda ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

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

        $sql .= ' ORDER BY p.fecha_vencimiento ASC, p.id DESC';

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
            'moneda'            => 'PEN', // Igual que en CXC, podrías heredar la moneda de la orden de compra en el futuro
            'monto_total'       => $total,
            'saldo'             => $total,
            'estado'            => $total > 0 ? 'ABIERTA' : 'PAGADA',
            'created_by'        => $userId,
            'updated_by'        => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    public function recalcularEstado(int $id, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE tesoreria_cxp
            SET saldo = GREATEST(ROUND(monto_total - monto_pagado, 4), 0),
                estado = CASE
                    WHEN estado = "ANULADA" THEN "ANULADA"
                    WHEN ROUND(monto_total - monto_pagado, 4) <= 0 THEN "PAGADA"
                    WHEN DATE(fecha_vencimiento) < CURDATE() THEN "VENCIDA"
                    WHEN monto_pagado > 0 THEN "PARCIAL"
                    ELSE "ABIERTA"
                END,
                updated_by = :user,
                updated_at = NOW()
            WHERE id = :id');
        $stmt->execute(['id' => $id, 'user' => $userId]);
    }
}
