<?php
declare(strict_types=1);

class ReporteTesoreriaModel extends Modelo
{
    public function contarCxcVencida(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM tesoreria_cxc WHERE deleted_at IS NULL AND saldo > 0 AND fecha_vencimiento < CURDATE()")->fetchColumn();
    }

    public function contarCxpVencida(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM tesoreria_cxp WHERE deleted_at IS NULL AND saldo > 0 AND fecha_vencimiento < CURDATE()")->fetchColumn();
    }

    public function agingCxc(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $count = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_cxc c WHERE c.deleted_at IS NULL AND c.fecha_emision BETWEEN :fd AND :fh');
        $count->execute($params);

        $sql = "SELECT t.nombre_completo AS cliente, c.saldo, c.fecha_vencimiento,
                       GREATEST(DATEDIFF(CURDATE(), c.fecha_vencimiento), 0) AS dias_atraso,
                       CASE
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 0 AND 7 THEN '0-7'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 8 AND 30 THEN '8-30'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 31 AND 60 THEN '31-60'
                         ELSE '61+'
                       END AS bucket
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                WHERE c.deleted_at IS NULL
                  AND c.fecha_emision BETWEEN :fd AND :fh
                  AND c.saldo > 0
                ORDER BY dias_atraso DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':fd', $params['fd']);
        $stmt->bindValue(':fh', $params['fh']);
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function agingCxp(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $count = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_cxp c WHERE c.deleted_at IS NULL AND c.fecha_emision BETWEEN :fd AND :fh');
        $count->execute($params);

        $sql = "SELECT t.nombre_completo AS proveedor, c.saldo, c.fecha_vencimiento,
                       GREATEST(DATEDIFF(CURDATE(), c.fecha_vencimiento), 0) AS dias_atraso,
                       CASE
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 0 AND 7 THEN '0-7'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 8 AND 30 THEN '8-30'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 31 AND 60 THEN '31-60'
                         ELSE '61+'
                       END AS bucket
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                WHERE c.deleted_at IS NULL
                  AND c.fecha_emision BETWEEN :fd AND :fh
                  AND c.saldo > 0
                ORDER BY dias_atraso DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':fd', $params['fd']);
        $stmt->bindValue(':fh', $params['fh']);
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function flujoPorCuenta(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $whereCuenta = '';
        if (!empty($f['id_cuenta'])) {
            $whereCuenta = ' AND m.id_cuenta = :id_cuenta';
            $params['id_cuenta'] = (int) $f['id_cuenta'];
        }

        $count = $this->db()->prepare('SELECT COUNT(DISTINCT m.id_cuenta) FROM tesoreria_movimientos m WHERE m.deleted_at IS NULL AND m.fecha BETWEEN :fd AND :fh' . $whereCuenta);
        $count->execute($params);

        $sql = "SELECT c.nombre AS cuenta,
                       ROUND(SUM(CASE WHEN m.tipo='COBRO' THEN m.monto ELSE 0 END),2) AS total_ingresos,
                       ROUND(SUM(CASE WHEN m.tipo='PAGO' THEN m.monto ELSE 0 END),2) AS total_egresos,
                       ROUND(SUM(CASE WHEN m.tipo='COBRO' THEN m.monto ELSE -m.monto END),2) AS saldo_neto
                FROM tesoreria_movimientos m
                INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                WHERE m.deleted_at IS NULL
                  AND m.fecha BETWEEN :fd AND :fh {$whereCuenta}
                GROUP BY m.id_cuenta, c.nombre
                ORDER BY saldo_neto DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function estadoCuentaClientes(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        [$where, $params] = $this->buildEstadoCuentaWhere($f);

        $countSql = "SELECT COUNT(*)
                     FROM tesoreria_cxc c
                     INNER JOIN terceros t ON t.id = c.id_cliente
                     LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
                     LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
                     LEFT JOIN items i ON i.id = d.id_item
                     WHERE {$where}";
        $count = $this->db()->prepare($countSql);
        $count->execute($params);

        $sql = "SELECT
                    c.id AS cxc_id,
                    c.id_cliente,
                    COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Cliente #', c.id_cliente)) AS cliente,
                    DATE(COALESCE(v.fecha_emision, c.fecha_emision)) AS fecha_atencion,
                    COALESCE(NULLIF(TRIM(v.codigo), ''), NULLIF(TRIM(c.documento_referencia), ''), CONCAT('CXC-', c.id)) AS documento,
                    i.id AS id_item,
                    i.nombre AS producto,
                    CAST(COALESCE(d.cantidad, 0) AS DECIMAL(14,2)) AS cantidad,
                    CAST(COALESCE(d.precio_unitario, 0) AS DECIMAL(14,4)) AS precio_unitario,
                    CAST(COALESCE(d.total_linea, c.monto_total) AS DECIMAL(14,2)) AS subtotal_linea,
                    CAST(c.monto_total AS DECIMAL(14,2)) AS monto_documento,
                    CAST(COALESCE(pagos.total_depositos, 0) AS DECIMAL(14,2)) AS depositos_documento,
                    CAST(c.saldo AS DECIMAL(14,2)) AS saldo_documento,
                    c.estado
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
                LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
                LEFT JOIN items i ON i.id = d.id_item
                LEFT JOIN (
                    SELECT m.id_origen AS cxc_id, ROUND(SUM(m.monto), 2) AS total_depositos
                    FROM tesoreria_movimientos m
                    WHERE m.origen = 'CXC'
                      AND m.tipo = 'COBRO'
                      AND m.estado = 'CONFIRMADO'
                      AND m.deleted_at IS NULL
                    GROUP BY m.id_origen
                ) pagos ON pagos.cxc_id = c.id
                WHERE {$where}
                ORDER BY fecha_atencion DESC, c.id DESC, d.id ASC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => (int) $count->fetchColumn(),
            'resumen' => $this->resumenEstadoCuenta($f),
        ];
    }

    // ==========================================
    // NUEVO MÉTODO HISTORIAL (EL QUE FALTABA)
    // ==========================================
    public function historialEstadoCuenta(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        
        // Obtenemos el WHERE original y los parámetros
        [$where, $params] = $this->buildEstadoCuentaWhere($f);
        
        // Creamos la tabla temporal (CTE) llamada TargetCXC.
        $cte = "
            WITH TargetCXC AS (
                SELECT c.*, t.nombre_completo AS cliente_nombre
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                WHERE {$where}
            )
        ";

        // Consulta principal que une Cargos (Productos) y Abonos (Depósitos)
        $sql = $cte . "
            SELECT 
                'CARGO' AS tipo_transaccion,
                DATE(COALESCE(v.fecha_emision, c.fecha_emision)) AS fecha_atencion,
                c.cliente_nombre AS cliente,
                COALESCE(NULLIF(TRIM(v.codigo), ''), NULLIF(TRIM(c.documento_referencia), ''), CONCAT('CXC-', c.id)) AS documento,
                COALESCE(i.nombre, 'Sin detalle de producto') AS producto,
                CAST(COALESCE(d.cantidad, 1) AS DECIMAL(14,2)) AS cantidad,
                CAST(COALESCE(d.precio_unitario, c.monto_total) AS DECIMAL(14,4)) AS precio_unitario,
                CAST(COALESCE(d.total_linea, c.monto_total) AS DECIMAL(14,2)) AS monto_transaccion,
                c.estado
            FROM TargetCXC c
            LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
            LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
            LEFT JOIN items i ON i.id = d.id_item

            UNION ALL

            SELECT 
                'ABONO' AS tipo_transaccion,
                DATE(m.fecha) AS fecha_atencion,
                c.cliente_nombre AS cliente,
                CONCAT('PAGO REF: ', COALESCE(NULLIF(TRIM(m.referencia), ''), m.id)) AS documento,
                'Abono / Depósito del cliente' AS producto,
                1.00 AS cantidad,
                CAST(m.monto AS DECIMAL(14,4)) AS precio_unitario,
                CAST(m.monto AS DECIMAL(14,2)) AS monto_transaccion,
                c.estado
            FROM tesoreria_movimientos m
            INNER JOIN TargetCXC c ON c.id = m.id_origen AND m.origen = 'CXC'
            WHERE m.tipo = 'COBRO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
            
            ORDER BY fecha_atencion DESC, tipo_transaccion ASC
            LIMIT :limite OFFSET :offset
        ";

        $countSql = $cte . "
            SELECT SUM(conteos) FROM (
                SELECT COUNT(*) AS conteos 
                FROM TargetCXC c
                LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = c.id_documento_venta AND d.deleted_at IS NULL
                
                UNION ALL
                
                SELECT COUNT(*) AS conteos 
                FROM tesoreria_movimientos m
                INNER JOIN TargetCXC c ON c.id = m.id_origen AND m.origen = 'CXC'
                WHERE m.tipo = 'COBRO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
            ) AS total
        ";

        $countStmt = $this->db()->prepare($countSql);
        foreach ($params as $k => $v) {
            $countStmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $countStmt->execute();
        $totalRows = (int) $countStmt->fetchColumn();

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $totalRows,
            'resumen' => $this->resumenEstadoCuenta($f),
        ];
    }
    // ==========================================

    public function estadoCuentaPorProducto(array $f, int $limite = 200): array
    {
        [$where, $params] = $this->buildEstadoCuentaWhere($f);

        $sql = "SELECT
                    COALESCE(i.nombre, 'Sin producto asociado') AS producto,
                    CAST(ROUND(SUM(COALESCE(d.cantidad, 0)), 2) AS DECIMAL(14,2)) AS total_cantidad,
                    CAST(ROUND(SUM(COALESCE(d.total_linea, c.monto_total)), 2) AS DECIMAL(14,2)) AS total_facturado,
                    CAST(ROUND(SUM(
                        CASE
                            WHEN COALESCE(dt.total_subtotal, 0) > 0 AND d.id IS NOT NULL THEN c.saldo * (COALESCE(d.total_linea, 0) / dt.total_subtotal)
                            ELSE c.saldo
                        END
                    ), 2) AS DECIMAL(14,2)) AS total_saldo
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
                LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
                LEFT JOIN (
                    SELECT dd.id_documento_venta, SUM(COALESCE(dd.total_linea, 0)) AS total_subtotal
                    FROM ventas_documentos_detalle dd
                    WHERE dd.deleted_at IS NULL
                    GROUP BY dd.id_documento_venta
                ) dt ON dt.id_documento_venta = v.id
                LEFT JOIN items i ON i.id = d.id_item
                WHERE {$where}
                GROUP BY i.id, i.nombre
                ORDER BY total_saldo DESC
                LIMIT :limite";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarClientesEstadoCuenta(int $limite = 1000): array
    {
        $sql = "SELECT DISTINCT
                    COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Cliente #', c.id_cliente)) AS cliente
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                WHERE c.deleted_at IS NULL
                ORDER BY cliente ASC
                LIMIT :limite";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private function resumenEstadoCuenta(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta'],
        ];

        $whereBase = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) BETWEEN :fd AND :fh',
        ];

        if (!empty($f['cliente'])) {
            $whereBase[] = "COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :cliente";
            $params['cliente'] = '%' . (string) $f['cliente'] . '%';
        }

        if (!empty($f['estado'])) {
            $whereBase[] = 'c.estado = :estado';
            $params['estado'] = (string) $f['estado'];
        }

        if (!empty($f['producto'])) {
            $whereBase[] = 'EXISTS (
                SELECT 1
                FROM ventas_documentos_detalle d2
                INNER JOIN items i2 ON i2.id = d2.id_item
                WHERE d2.id_documento_venta = c.id_documento_venta
                  AND d2.deleted_at IS NULL
                  AND COALESCE(NULLIF(TRIM(i2.nombre), \'\'), \'\') LIKE :producto
            )';
            $params['producto'] = '%' . (string) $f['producto'] . '%';
        }

        $where = implode(' AND ', $whereBase);
        $sql = "SELECT
                    CAST(ROUND(SUM(c.monto_total), 2) AS DECIMAL(14,2)) AS total_facturado,
                    CAST(ROUND(SUM(c.monto_pagado), 2) AS DECIMAL(14,2)) AS total_pagado,
                    CAST(ROUND(SUM(c.saldo), 2) AS DECIMAL(14,2)) AS total_saldo,
                    COUNT(*) AS total_documentos
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                WHERE {$where}";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_facturado' => 0,
            'total_pagado' => 0,
            'total_saldo' => 0,
            'total_documentos' => 0,
        ];
    }

    private function buildEstadoCuentaWhere(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta'],
        ];

        $where = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) BETWEEN :fd AND :fh',
        ];

        if (!empty($f['cliente'])) {
            $where[] = "COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :cliente";
            $params['cliente'] = '%' . (string) $f['cliente'] . '%';
        }

        if (!empty($f['estado'])) {
            $where[] = 'c.estado = :estado';
            $params['estado'] = (string) $f['estado'];
        }

        if (!empty($f['producto'])) {
            $where[] = "COALESCE(NULLIF(TRIM(i.nombre), ''), '') LIKE :producto";
            $params['producto'] = '%' . (string) $f['producto'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}
