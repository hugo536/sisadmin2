<?php

declare(strict_types=1);

class TesoreriaCxcModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        // MEJORA: Usamos LEFT JOIN para no perder la deuda si el cliente fue "soft-deleted"
        $sql = 'SELECT c.*, COALESCE(t.nombre_completo, "Cliente Eliminado/Desconocido") AS cliente
                FROM tesoreria_cxc c
                LEFT JOIN terceros t ON t.id = c.id_cliente
                WHERE c.deleted_at IS NULL';

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND c.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['moneda'])) {
            $sql .= ' AND c.moneda = :moneda';
            $params['moneda'] = (string) $filtros['moneda'];
        }

        if (!empty($filtros['vencimiento']) && $filtros['vencimiento'] === 'vencidas') {
            // MEJORA: DATE() asegura que la comparación sea estrictamente por día, ignorando horas si existieran
            $sql .= ' AND c.saldo > 0 AND DATE(c.fecha_vencimiento) < CURDATE()';
        }

        $sql .= ' ORDER BY c.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtener(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tesoreria_cxc WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearDesdeVenta(int $idDocumentoVenta, int $userId): ?int
    {
        $db = $this->db();

        $stmtExiste = $db->prepare('SELECT id FROM tesoreria_cxc WHERE id_documento_venta = :id LIMIT 1');
        $stmtExiste->execute(['id' => $idDocumentoVenta]);
        $existe = (int) ($stmtExiste->fetchColumn() ?: 0);
        if ($existe > 0) {
            return $existe;
        }

        $stmtVenta = $db->prepare('SELECT v.id, v.id_cliente, v.fecha_emision, v.total, v.estado,
                                          COALESCE(tc.dias_credito, 0) AS dias_credito,
                                          UPPER(COALESCE(tc.condicion_pago, "CREDITO")) AS condicion_pago
                                   FROM ventas_documentos v
                                   LEFT JOIN terceros_clientes tc ON tc.id_tercero = v.id_cliente
                                   WHERE v.id = :id AND v.deleted_at IS NULL
                                   LIMIT 1');
        $stmtVenta->execute(['id' => $idDocumentoVenta]);
        $venta = $stmtVenta->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            return null;
        }

        $estadoVenta = (int) ($venta['estado'] ?? 0);
        if (!in_array($estadoVenta, [2, 3], true)) {
            return null;
        }

        $idCliente = (int) ($venta['id_cliente'] ?? 0);
        $stmtCliente = $db->prepare('SELECT id FROM terceros WHERE id = :id AND es_cliente = 1 AND estado = 1 AND deleted_at IS NULL LIMIT 1');
        $stmtCliente->execute(['id' => $idCliente]);
        if (!(bool) $stmtCliente->fetchColumn()) {
            return null;
        }

        $total = round((float) ($venta['total'] ?? 0), 4);
        $fechaEmision = (string) ($venta['fecha_emision'] ?? date('Y-m-d'));
        $diasCredito = (int) ($venta['dias_credito'] ?? 0);
        $condicionPago = strtoupper((string) ($venta['condicion_pago'] ?? 'CREDITO'));
        if ($diasCredito < 0) {
            $diasCredito = 0;
        }

        $aplicaCredito = ($condicionPago === 'CREDITO' || $diasCredito > 0);
        $fechaVencimiento = $aplicaCredito
            ? date('Y-m-d', strtotime($fechaEmision . ' +' . $diasCredito . ' days'))
            : $fechaEmision;

        $stmtInsert = $db->prepare('INSERT INTO tesoreria_cxc
            (id_cliente, id_documento_venta, fecha_emision, fecha_vencimiento, moneda, monto_total, monto_pagado, saldo, estado, created_by, updated_by, created_at, updated_at)
            VALUES
            (:id_cliente, :id_documento_venta, :fecha_emision, :fecha_vencimiento, :moneda, :monto_total, 0, :saldo, :estado, :created_by, :updated_by, NOW(), NOW())');

        $stmtInsert->execute([
            'id_cliente' => $idCliente,
            'id_documento_venta' => $idDocumentoVenta,
            'fecha_emision' => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'moneda' => 'PEN', 
            'monto_total' => $total,
            'saldo' => $total,
            // CAMBIO: Ahora el estado inicial es PENDIENTE si el total es mayor a 0
            'estado' => $total > 0 ? 'PENDIENTE' : 'PAGADA', 
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $db->lastInsertId();
    }

    public function recalcularEstado(int $id, int $userId): void
    {
        // CAMBIO: Se actualizó la lógica del CASE según las reglas solicitadas
        $stmt = $this->db()->prepare('UPDATE tesoreria_cxc
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
    }

    public function listarPendientesPorAntiguedad(int $idCliente, string $moneda): array
    {
        $stmt = $this->db()->prepare('SELECT id
            FROM tesoreria_cxc
            WHERE id_cliente = :id_cliente
              AND moneda = :moneda
              AND estado <> "ANULADA"
              AND saldo > 0
              AND deleted_at IS NULL
            ORDER BY fecha_emision ASC, fecha_vencimiento ASC, id ASC');
        $stmt->execute([
            'id_cliente' => $idCliente,
            'moneda' => strtoupper(trim($moneda)),
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

}