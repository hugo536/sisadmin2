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
                LEFT JOIN distribuidores d ON d.id_tercero = t.id AND d.deleted_at IS NULL
                WHERE p.deleted_at IS NULL';

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND p.estado = :estado';
            $params['estado'] = (string) $filtros['estado'];
        }

        if (!empty($filtros['tipo_tercero'])) {
            if ($filtros['tipo_tercero'] === 'cliente_distribuidor') {
                $sql .= ' AND COALESCE(t.es_cliente, 0) = 1 AND d.id_tercero IS NOT NULL';
            } elseif ($filtros['tipo_tercero'] === 'cliente') {
                $sql .= ' AND COALESCE(t.es_cliente, 0) = 1';
            } elseif ($filtros['tipo_tercero'] === 'distribuidor') {
                $sql .= ' AND d.id_tercero IS NOT NULL';
            }
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND DATE(p.fecha_vencimiento) >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(p.fecha_vencimiento) <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $sql .= ' ORDER BY p.fecha_vencimiento DESC, p.id DESC';

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
    
    // ====================================================================
    // --- NUEVO: FUNCIÓN PARA REGISTRAR PAGOS INMEDIATOS DESDE GASTOS ---
    // ====================================================================
    public function registrarPagoDirecto(int $idCxp, int $idCuenta, int $idMetodo, float $monto, string $fecha, string $observacion, int $userId): void
    {
        if ($idCxp <= 0 || $idCuenta <= 0 || $idMetodo <= 0 || $monto <= 0) {
            throw new RuntimeException('Datos inválidos para registrar el pago del gasto.');
        }

        $db = $this->db();

        // 1. Obtener datos de la Cuenta por Pagar para verificar saldo y moneda
        $stmtCxp = $db->prepare('SELECT id_proveedor, moneda, saldo FROM tesoreria_cxp WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
        $stmtCxp->execute(['id' => $idCxp]);
        $cxp = $stmtCxp->fetch(PDO::FETCH_ASSOC);

        if (!$cxp) {
            throw new RuntimeException('La Cuenta por Pagar no existe o fue eliminada.');
        }

        if ((float)$cxp['saldo'] < $monto - 0.0001) {
            throw new RuntimeException('El monto a pagar supera el saldo pendiente de la deuda.');
        }

        // 2. Registrar el Egreso en Tesorería (Salida de dinero de caja/bancos)
        $stmtMov = $db->prepare('INSERT INTO tesoreria_movimientos 
            (id_cuenta, id_metodo_pago, id_tercero, tipo, monto, moneda, fecha, observaciones, estado, created_by, updated_by, created_at, updated_at) 
            VALUES 
            (:id_cuenta, :id_metodo, :id_tercero, "EGRESO", :monto, :moneda, :fecha, :observacion, 1, :user, :user, NOW(), NOW())');
            
        $stmtMov->execute([
            'id_cuenta'   => $idCuenta,
            'id_metodo'   => $idMetodo,
            'id_tercero'  => $cxp['id_proveedor'], // Pagamos al proveedor
            'monto'       => round($monto, 4),
            'moneda'      => $cxp['moneda'],
            'fecha'       => $fecha,
            'observacion' => $observacion,
            'user'        => $userId
        ]);

        $idMovimiento = (int) $db->lastInsertId();

        // 3. Vincular el Movimiento con la CxP (Amortización/Pago)
        $stmtPago = $db->prepare('INSERT INTO tesoreria_cxp_pagos 
            (id_cxp, id_movimiento, monto_aplicado, created_by, updated_by, created_at, updated_at) 
            VALUES 
            (:id_cxp, :id_movimiento, :monto_aplicado, :user, :user, NOW(), NOW())');
            
        $stmtPago->execute([
            'id_cxp'         => $idCxp,
            'id_movimiento'  => $idMovimiento,
            'monto_aplicado' => round($monto, 4),
            'user'           => $userId
        ]);

        // 4. Actualizar Monto Pagado en la tabla Maestra de CxP
        $stmtUpd = $db->prepare('UPDATE tesoreria_cxp 
            SET monto_pagado = monto_pagado + :monto, updated_by = :user, updated_at = NOW() 
            WHERE id = :id_cxp');
        $stmtUpd->execute([
            'monto'  => round($monto, 4),
            'user'   => $userId,
            'id_cxp' => $idCxp
        ]);

        // 5. Recalcular el Estado Final (Pagado, Parcial)
        $this->recalcularEstado($idCxp, $userId);
    }
}