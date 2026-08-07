<?php

declare(strict_types=1);

class TesoreriaCxcModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT c.*,
                       COALESCE(t.nombre_completo, "Cliente Eliminado/Desconocido") AS cliente,
                       TRIM(COALESCE(c.observaciones, "")) AS observacion_cxc,
                       TRIM(COALESCE(v.observaciones, "")) AS observacion_pedido,
                       TRIM(COALESCE(v.observaciones_despacho, "")) AS observacion_despacho
                FROM tesoreria_cxc c
                LEFT JOIN terceros t ON t.id = c.id_cliente
                LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
                LEFT JOIN distribuidores d ON d.id_tercero = t.id AND d.deleted_at IS NULL
                WHERE c.deleted_at IS NULL';

        $params = [];

        if (!empty($filtros['estado'])) {
            $sql .= ' AND c.estado = :estado';
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
            $sql .= ' AND DATE(c.fecha_vencimiento) >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(c.fecha_vencimiento) <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
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

        $stmtExiste = $db->prepare('SELECT id FROM tesoreria_cxc WHERE id_documento_venta = :id AND deleted_at IS NULL LIMIT 1');
        $stmtExiste->execute(['id' => $idDocumentoVenta]);
        $existe = (int) ($stmtExiste->fetchColumn() ?: 0);
        if ($existe > 0) {
            return $existe;
        }

        $stmtVenta = $db->prepare('SELECT v.id, v.id_cliente, v.fecha_emision, v.total, v.estado,
                                          COALESCE(tc.dias_credito, 0) AS dias_credito,
                                          UPPER(COALESCE(tc.condicion_pago, "CREDITO")) AS condicion_pago,
                                          v.moneda
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
        $fechaEmision = substr((string) ($venta['fecha_emision'] ?? date('Y-m-d')), 0, 10);
        $diasCredito = (int) ($venta['dias_credito'] ?? 0);
        $condicionPago = strtoupper((string) ($venta['condicion_pago'] ?? 'CREDITO'));
        if ($diasCredito < 0) {
            $diasCredito = 0;
        }

        $aplicaCredito = ($condicionPago === 'CREDITO' || $diasCredito > 0);
        $fechaVencimiento = $aplicaCredito
            ? date('Y-m-d', strtotime($fechaEmision . ' +' . $diasCredito . ' days'))
            : $fechaEmision;

        $moneda = in_array(strtoupper((string) ($venta['moneda'] ?? 'PEN')), ['PEN', 'USD'], true) ? strtoupper((string) $venta['moneda']) : 'PEN';

        $stmtInsert = $db->prepare('INSERT INTO tesoreria_cxc
            (id_cliente, id_documento_venta, fecha_emision, fecha_vencimiento, moneda, monto_total, monto_pagado, saldo, estado, created_by, updated_by, created_at, updated_at)
            VALUES
            (:id_cliente, :id_documento_venta, :fecha_emision, :fecha_vencimiento, :moneda, :monto_total, 0, :saldo, :estado, :created_by, :updated_by, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
            deleted_at = NULL,
            monto_pagado = 0,
            moneda = VALUES(moneda),
            monto_total = VALUES(monto_total),
            saldo = VALUES(saldo),
            estado = VALUES(estado),
            fecha_vencimiento = VALUES(fecha_vencimiento),
            updated_by = VALUES(updated_by),
            updated_at = NOW()');

        $stmtInsert->execute([
            'id_cliente' => $idCliente,
            'id_documento_venta' => $idDocumentoVenta,
            'fecha_emision' => $fechaEmision,
            'fecha_vencimiento' => $fechaVencimiento,
            'moneda' => $moneda, 
            'monto_total' => $total,
            'saldo' => $total,
            'estado' => $total > 0 ? 'PENDIENTE' : 'PAGADA', 
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $stmtRecuperarId = $db->prepare("SELECT id FROM tesoreria_cxc WHERE id_documento_venta = :id LIMIT 1");
        $stmtRecuperarId->execute(['id' => $idDocumentoVenta]);
        return (int) $stmtRecuperarId->fetchColumn();
    }

    public function recalcularEstado(int $id, int $userId): void
    {
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
              /* 👇 EXCLUIMOS NOTAS DE CRÉDITO PARA QUE EL BANCO NO LES ENVÍE EFECTIVO 👇 */
              AND COALESCE(tipo_documento, "") NOT IN ("NOTA_CREDITO", "ANTICIPO")
              AND deleted_at IS NULL
            ORDER BY fecha_emision ASC, fecha_vencimiento ASC, id ASC');
        $stmt->execute([
            'id_cliente' => $idCliente,
            'moneda' => strtoupper(trim($moneda)),
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    // =========================================================================
    // --- NUEVAS FUNCIONES PARA COBRO INMEDIATO DE VENTAS MULTI-PAGO ---
    // =========================================================================

    public function obtenerPorVenta(int $idDocumentoVenta): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM tesoreria_cxc WHERE id_documento_venta = :id AND deleted_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id' => $idDocumentoVenta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    // 👇 AÑADIDO: Parámetro $tipoCambio al final (por defecto 1.0)
    public function registrarCobroDirecto(int $idCxc, int $idCuenta, int $idMetodo, float $monto, string $fecha, string $observaciones, int $userId, float $tipoCambio = 1.0): void
    {
        if ($idCxc <= 0 || $idCuenta <= 0 || $idMetodo <= 0 || $monto <= 0) {
            throw new RuntimeException('Datos inválidos para registrar el cobro.');
        }

        $db = $this->db();

        $stmtCxc = $db->prepare('SELECT id, id_cliente, moneda, saldo FROM tesoreria_cxc WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE');
        $stmtCxc->execute(['id' => $idCxc]);
        $cxc = $stmtCxc->fetch(PDO::FETCH_ASSOC);
        if (!$cxc) {
            throw new RuntimeException('No se encontró la cuenta por cobrar para registrar el cobro.');
        }

        if ((float)$cxc['saldo'] < $monto - 0.0001) {
            throw new RuntimeException('El monto a cobrar supera el saldo pendiente de la deuda.');
        }

        // 👇 MAGIA BIMONETARIA 👇
        $stmtCuenta = $db->prepare('SELECT moneda FROM tesoreria_cuentas WHERE id = :id LIMIT 1');
        $stmtCuenta->execute(['id' => $idCuenta]);
        $monedaCuenta = strtoupper(trim((string) $stmtCuenta->fetchColumn() ?: 'PEN'));
        
        $monedaOrden = strtoupper(trim($cxc['moneda']));
        $montoParaMovimiento = $monto;
        
        // Si cobramos una orden en USD con una cuenta en PEN, aplicamos el TC a la entrada de caja
        if ($monedaOrden === 'USD' && $monedaCuenta === 'PEN') {
            $montoParaMovimiento = $monto * $tipoCambio;
        } 
        elseif ($monedaOrden === 'PEN' && $monedaCuenta === 'USD') {
            $montoParaMovimiento = $tipoCambio > 0 ? ($monto / $tipoCambio) : $monto;
        }

        // 1. REGISTRAMOS LA ENTRADA DE DINERO (INGRESO) USANDO LA MONEDA Y MONTO DE LA CAJA
        require_once BASE_PATH . '/app/models/tesoreria/TesoreriaMovimientoModel.php';
        $movimientoModel = new TesoreriaMovimientoModel();
        
        $observacionFinal = $observaciones;
        if ($tipoCambio !== 1.0) {
            $observacionFinal .= ' (T.C. aplicado: ' . $tipoCambio . ')';
        }

        $movimientoModel->registrar([
            'tipo' => 'COBRO',
            'origen' => 'CXC',
            'id_origen' => $idCxc,
            'id_cuenta' => $idCuenta,
            'id_metodo_pago' => $idMetodo,
            'fecha' => $fecha,
            'moneda' => $monedaCuenta,         // <-- Moneda real de la caja
            'monto' => $montoParaMovimiento,   // <-- Monto convertido a la moneda de la caja
            'naturaleza_pago' => 'DOCUMENTO',
            'monto_capital' => $montoParaMovimiento,
            'monto_interes' => 0,
            'observaciones' => $observacionFinal,
            'id_tercero' => $cxc['id_cliente'] // Para que se vincule al cliente
        ], $userId);

        $idMovimiento = (int) $db->lastInsertId();

        // 2. REGISTRAMOS EL HISTORIAL DE COBRO CRUZADO
        $stmtPago = $db->prepare('INSERT INTO tesoreria_cxc_cobros 
            (id_cxc, id_movimiento, monto_aplicado, created_by, updated_by, created_at, updated_at) 
            VALUES 
            (:id_cxc, :id_movimiento, :monto_aplicado, :created_by, :updated_by, NOW(), NOW())');
            
        $stmtPago->execute([
            'id_cxc'         => $idCxc,
            'id_movimiento'  => $idMovimiento,
            'monto_aplicado' => round($monto, 4), // <-- Monto puro de la factura original
            'created_by'     => $userId, 
            'updated_by'     => $userId  
        ]);

        // 3. DESCONTAMOS LA DEUDA EN CXC USANDO LA MONEDA ORIGINAL
        $stmtUpd = $db->prepare('UPDATE tesoreria_cxc 
            SET monto_pagado = monto_pagado + :monto, updated_by = :user, updated_at = NOW() 
            WHERE id = :id_cxc');
        $stmtUpd->execute([
            'monto'  => round($monto, 4), // <-- Descuenta puro 
            'user'   => $userId,
            'id_cxc' => $idCxc
        ]);

        $this->recalcularEstado($idCxc, $userId);
    }

    public function obtenerCuentasActivas(): array
    {
        $stmt = $this->db()->query('SELECT id, nombre, moneda FROM tesoreria_cuentas WHERE estado = 1 AND deleted_at IS NULL');
        
        if (!$stmt) {
            return []; 
        }
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerMetodosActivos(): array
    {
        $stmt = $this->db()->query('SELECT id, nombre FROM tesoreria_metodos_pago WHERE estado = 1 AND deleted_at IS NULL');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function convertirPagosASaldoFavor(int $idDocumentoVenta, int $userId): bool
    {
        $cxc = $this->obtenerPorVenta($idDocumentoVenta);
        
        if (!$cxc) {
            return false; 
        }

        $idCxc = (int) $cxc['id'];

        $sqlMovs = "UPDATE tesoreria_movimientos 
                    SET observaciones = CONCAT(COALESCE(observaciones, ''), ' [Convertido a Saldo a Favor por reversión de pedido]'),
                        updated_by = :user,
                        updated_at = NOW()
                    WHERE origen = 'CXC' AND id_origen = :id_cxc AND deleted_at IS NULL";
        
        $stmtMovs = $this->db()->prepare($sqlMovs);
        $stmtMovs->execute(['user' => $userId, 'id_cxc' => $idCxc]);

        $sqlCxc = "UPDATE tesoreria_cxc 
                   SET deleted_at = NOW() 
                   WHERE id = :id_cxc";
                   
        $stmtCxc = $this->db()->prepare($sqlCxc);
        return $stmtCxc->execute(['id_cxc' => $idCxc]);
    }

    public function obtenerDetallePagosVenta(int $idDocumentoVenta): array
    {
        $sql = "SELECT m.monto, tmp.nombre AS metodo
                FROM tesoreria_cxc c
                INNER JOIN tesoreria_movimientos m ON m.id_origen = c.id AND m.origen = 'CXC' AND m.deleted_at IS NULL
                INNER JOIN tesoreria_metodos_pago tmp ON tmp.id = m.id_metodo_pago
                WHERE c.id_documento_venta = :id_venta AND c.deleted_at IS NULL";
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_venta' => $idDocumentoVenta]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // 👇 NUEVO: FUNCIÓN PARA APLICAR SALDOS A FAVOR (NOTAS DE CRÉDITO) EN CADENA 👇
    public function aplicarCruceDeCuentas(int $idCxcDestino, float $montoACruzar, int $userId): void
    {
        $db = $this->db();
        
        // 1. Obtener la deuda que queremos cobrar
        $stmtDest = $db->prepare('SELECT id_cliente, moneda FROM tesoreria_cxc WHERE id = ? FOR UPDATE');
        $stmtDest->execute([$idCxcDestino]);
        $dest = $stmtDest->fetch(PDO::FETCH_ASSOC);

        if (!$dest || $montoACruzar <= 0) return;

        $restanteCruce = $montoACruzar;

        // 2. Buscar Notas de Crédito (Saldos a favor) del cliente ordenadas por las más antiguas
        $stmtNC = $db->prepare('SELECT id, saldo FROM tesoreria_cxc 
                                WHERE id_cliente = ? AND moneda = ? 
                                AND tipo_documento IN ("NOTA_CREDITO", "ANTICIPO") 
                                AND estado <> "ANULADA" AND saldo > 0 
                                ORDER BY id ASC FOR UPDATE');
        $stmtNC->execute([$dest['id_cliente'], $dest['moneda']]);
        $notas = $stmtNC->fetchAll(PDO::FETCH_ASSOC);

        foreach ($notas as $nc) {
            if ($restanteCruce <= 0) break;

            $saldoNC = (float) $nc['saldo'];
            $montoAplicar = min($saldoNC, $restanteCruce);

            // Descontar a la Nota de Crédito (Se cobra sola)
            $db->prepare('UPDATE tesoreria_cxc SET monto_pagado = monto_pagado + ?, saldo = saldo - ?, updated_at = NOW() WHERE id = ?')
               ->execute([$montoAplicar, $montoAplicar, $nc['id']]);
            $this->recalcularEstado((int)$nc['id'], $userId);

            // Descontar a la Factura Original
            $db->prepare('UPDATE tesoreria_cxc SET monto_pagado = monto_pagado + ?, saldo = saldo - ?, updated_at = NOW() WHERE id = ?')
               ->execute([$montoAplicar, $montoAplicar, $idCxcDestino]);
            
            // Insertar registro puente
            $db->prepare('INSERT INTO tesoreria_cxc_cobros (id_cxc, id_movimiento, monto_aplicado, created_by, updated_by, created_at, updated_at) 
                          VALUES (?, NULL, ?, ?, ?, NOW(), NOW())')
               ->execute([$idCxcDestino, $montoAplicar, $userId, $userId]);

            $restanteCruce -= $montoAplicar;
        }

        $this->recalcularEstado($idCxcDestino, $userId);
    }
}