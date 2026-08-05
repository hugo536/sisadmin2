<?php
declare(strict_types=1);

class ReporteTesoreriaModel extends Modelo
{
    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    public function contarCxcVencida(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM tesoreria_cxc WHERE deleted_at IS NULL AND saldo > 0 AND fecha_vencimiento < CURDATE()")->fetchColumn();
    }

    public function contarCxpVencida(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM tesoreria_cxp WHERE deleted_at IS NULL AND saldo > 0 AND fecha_vencimiento < CURDATE()")->fetchColumn();
    }

    public function listarTercerosFiltroTesoreria(): array
    {
        $sql = "SELECT
                    t.id,
                    COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Tercero #', t.id)) AS nombre,
                    t.es_cliente,
                    CASE WHEN d.id_tercero IS NULL THEN 0 ELSE 1 END AS es_distribuidor
                FROM terceros t
                LEFT JOIN distribuidores d ON d.id_tercero = t.id AND d.deleted_at IS NULL
                WHERE t.deleted_at IS NULL
                  AND t.estado = 1
                  AND (t.es_cliente = 1 OR d.id_tercero IS NOT NULL)
                ORDER BY nombre ASC";
        $rows = $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $esCliente = (int) ($row['es_cliente'] ?? 0) === 1;
            $esDistribuidor = (int) ($row['es_distribuidor'] ?? 0) === 1;
            $row['tipo_label'] = $esCliente && $esDistribuidor
                ? 'Cliente / Distribuidor'
                : ($esDistribuidor ? 'Distribuidor' : 'Cliente');
        }
        unset($row);

        return $rows;
    }

    public function agingCxc(array $f, int $pagina, int $tamano): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $whereTercero = '';
        if (!empty($f['id_tercero'])) {
            $whereTercero = ' AND c.id_cliente = :id_tercero';
            $params['id_tercero'] = (int) $f['id_tercero'];
        }
        $count = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_cxc c WHERE c.deleted_at IS NULL AND c.fecha_emision BETWEEN :fd AND :fh' . $whereTercero);
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
                  {$whereTercero}
                ORDER BY dias_atraso DESC";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function agingCxp(array $f, int $pagina, int $tamano): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $whereTercero = '';
        if (!empty($f['id_tercero'])) {
            $whereTercero = ' AND c.id_proveedor = :id_tercero';
            $params['id_tercero'] = (int) $f['id_tercero'];
        }
        $count = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_cxp c WHERE c.deleted_at IS NULL AND c.fecha_emision BETWEEN :fd AND :fh' . $whereTercero);
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
                  {$whereTercero}
                ORDER BY dias_atraso DESC";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function flujoPorCuenta(array $f, int $pagina, int $tamano): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $whereTercero = '';
        if (!empty($f['id_tercero'])) {
            $whereTercero = " AND (
                (m.origen = 'CXC' AND EXISTS (
                    SELECT 1
                    FROM tesoreria_cxc cxc
                    WHERE cxc.id = m.id_origen
                      AND cxc.id_cliente = :id_tercero_cxc
                      AND cxc.deleted_at IS NULL
                ))
                OR
                (m.origen = 'CXP' AND EXISTS (
                    SELECT 1
                    FROM tesoreria_cxp cxp
                    WHERE cxp.id = m.id_origen
                      AND cxp.id_proveedor = :id_tercero_cxp
                      AND cxp.deleted_at IS NULL
                ))
            )";
            $params['id_tercero_cxc'] = (int) $f['id_tercero'];
            $params['id_tercero_cxp'] = (int) $f['id_tercero'];
        }

        $count = $this->db()->prepare('SELECT COUNT(DISTINCT m.id_cuenta) FROM tesoreria_movimientos m WHERE m.deleted_at IS NULL AND m.fecha BETWEEN :fd AND :fh' . $whereTercero);
        $count->execute($params);

        $sql = "SELECT c.nombre AS cuenta,
                       ROUND(SUM(CASE WHEN m.tipo='COBRO' THEN m.monto ELSE 0 END),2) AS total_ingresos,
                       ROUND(SUM(CASE WHEN m.tipo='PAGO' THEN m.monto ELSE 0 END),2) AS total_egresos,
                       ROUND(SUM(CASE WHEN m.tipo='COBRO' THEN m.monto ELSE -m.monto END),2) AS saldo_neto
                FROM tesoreria_movimientos m
                INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                WHERE m.deleted_at IS NULL
                  AND m.fecha BETWEEN :fd AND :fh {$whereTercero}
                GROUP BY m.id_cuenta, c.nombre
                ORDER BY saldo_neto DESC";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function estadoCuentaClientes(array $f, int $pagina, int $tamano): array
    {
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
                ORDER BY fecha_atencion DESC, c.id DESC, d.id ASC";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => (int) $count->fetchColumn(),
            'resumen' => $this->resumenEstadoCuenta($f),
        ];
    }

    // ==========================================
    // MÉTODO HISTORIAL CORREGIDO (CLIENTES) - AGRUPANDO PAGOS
    // ==========================================
    public function historialEstadoCuenta(array $f, int $pagina, int $tamano): array
    {
        $cantidadExpr = $this->cantidadVentasDetalleExpr('d', '1');
        $cantidadExprZero = $this->cantidadVentasDetalleExpr('d', '0');
        $precioExprZero = $this->precioUnitarioVentasDetalleExpr('d', '0');
        
        $params = [
            'fd1' => $f['fecha_desde'],
            'fh1' => $f['fecha_hasta'],
            'fd2' => $f['fecha_desde'],
            'fh2' => $f['fecha_hasta']
        ];
        
        $whereBase = [
            'c.deleted_at IS NULL',
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v WHERE v.id = c.id_documento_venta AND v.tipo_operacion = "DONACION")',
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v2 WHERE v2.id = c.id_documento_venta AND v2.estado IN ("BORRADOR", "ANULADA"))'
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
                SELECT 1 FROM ventas_documentos_detalle d2
                INNER JOIN items i2 ON i2.id = d2.id_item
                WHERE d2.id_documento_venta = c.id_documento_venta AND d2.deleted_at IS NULL
                  AND COALESCE(NULLIF(TRIM(i2.nombre), \'\'), \'\') LIKE :producto
            )';
            $params['producto'] = '%' . (string) $f['producto'] . '%';
        }
        
        $whereCte = implode(' AND ', $whereBase);

        $cte = "
            WITH BaseCXC AS (
                SELECT c.*, t.nombre_completo AS cliente_nombre
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                WHERE {$whereCte}
            )
        ";

        $sql = $cte . "
            SELECT 
                'CARGO' AS tipo_transaccion,
                DATE(COALESCE(v.fecha_emision, c.fecha_emision)) AS fecha_atencion,
                c.cliente_nombre AS cliente,
                COALESCE(NULLIF(TRIM(v.codigo), ''), NULLIF(TRIM(c.documento_referencia), ''), CONCAT('CXC-', c.id)) AS documento,
                COALESCE(i.nombre, pp.nombre, 'Sin detalle de producto') AS producto,
                CAST({$cantidadExpr} AS DECIMAL(14,2)) AS cantidad,
                CAST(COALESCE({$precioExprZero}, c.monto_total) AS DECIMAL(14,4)) AS precio_unitario,
                CAST(
                    CASE
                        WHEN d.id IS NULL THEN c.monto_total
                        ELSE ({$cantidadExprZero} * {$precioExprZero})
                    END
                AS DECIMAL(14,2)) AS monto_transaccion,
                c.estado
            FROM BaseCXC c
            LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
            LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
            LEFT JOIN items i ON i.id = d.id_item
            LEFT JOIN precios_presentaciones pp ON pp.id = d.id_presentacion
            WHERE DATE(COALESCE(v.fecha_emision, c.fecha_emision)) BETWEEN :fd1 AND :fh1

            UNION ALL

            -- MAGIA: Agrupamos los Abonos para fusionar pagos de una misma fecha/cuenta/referencia
            SELECT 
                'ABONO' AS tipo_transaccion,
                DATE(m.fecha) AS fecha_atencion,
                MAX(c.cliente_nombre) AS cliente,
                CONCAT('PAGO REF: ', COALESCE(NULLIF(TRIM(m.referencia), ''), DATE_FORMAT(m.fecha, '%d%m%Y'))) AS documento,
                CONCAT('Abono en ', COALESCE(MAX(tc.nombre), 'Caja General')) AS producto,
                1.00 AS cantidad,
                CAST(SUM(m.monto) AS DECIMAL(14,4)) AS precio_unitario,
                CAST(SUM(m.monto) AS DECIMAL(14,2)) AS monto_transaccion,
                'CONFIRMADO' AS estado
            FROM tesoreria_movimientos m
            INNER JOIN BaseCXC c ON c.id = m.id_origen AND m.origen = 'CXC'
            LEFT JOIN tesoreria_cuentas tc ON tc.id = m.id_cuenta
            WHERE m.tipo = 'COBRO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
              AND DATE(m.fecha) BETWEEN :fd2 AND :fh2
            GROUP BY DATE(m.fecha), c.id_cliente, m.id_cuenta, m.referencia
            
            ORDER BY fecha_atencion DESC, tipo_transaccion ASC
        ";

        $countSql = $cte . "
            SELECT SUM(conteos) FROM (
                SELECT COUNT(*) AS conteos 
                FROM BaseCXC c
                LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
                LEFT JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
                WHERE DATE(COALESCE(v.fecha_emision, c.fecha_emision)) BETWEEN :fd1 AND :fh1
                
                UNION ALL
                
                -- MAGIA DEL CONTEO: Contamos los grupos unicos para no descuadrar la paginación
                SELECT COUNT(DISTINCT CONCAT(DATE(m.fecha), '_', c.id_cliente, '_', COALESCE(m.id_cuenta, 0), '_', COALESCE(TRIM(m.referencia), ''))) AS conteos 
                FROM tesoreria_movimientos m
                INNER JOIN BaseCXC c ON c.id = m.id_origen AND m.origen = 'CXC'
                WHERE m.tipo = 'COBRO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
                  AND DATE(m.fecha) BETWEEN :fd2 AND :fh2
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
        $cantidadExprZero = $this->cantidadVentasDetalleExpr('d', '0');
        $precioExprZero = $this->precioUnitarioVentasDetalleExpr('d', '0');

        $sql = "SELECT
                    COALESCE(i.nombre, 'Sin producto asociado') AS producto,
                    CAST(ROUND(SUM({$cantidadExprZero}), 2) AS DECIMAL(14,2)) AS total_cantidad,
                    CAST(ROUND(SUM(
                        CASE
                            WHEN d.id IS NULL THEN c.monto_total
                            ELSE ({$cantidadExprZero} * {$precioExprZero})
                        END
                    ), 2) AS DECIMAL(14,2)) AS total_facturado,
                    CAST(ROUND(SUM(
                        CASE
                            WHEN COALESCE(dt.total_subtotal, 0) > 0 AND d.id IS NOT NULL THEN c.saldo * (
                                ({$cantidadExprZero} * {$precioExprZero}) / dt.total_subtotal
                            )
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

    public function historialEstadoCuentaProveedores(array $f, int $pagina, int $tamano): array
    {
        $cantidadExpr = $this->cantidadComprasDetalleExpr('d', '1');
        $cantidadExprZero = $this->cantidadComprasDetalleExpr('d', '0');
        
        $params = [
            'fd1' => $f['fecha_desde'],
            'fh1' => $f['fecha_hasta'],
            'fd2' => $f['fecha_desde'],
            'fh2' => $f['fecha_hasta']
        ];
        
        $whereBase = [
            'c.deleted_at IS NULL',
            'NOT EXISTS (SELECT 1 FROM compras_ordenes co WHERE co.id = c.id_orden_compra AND co.estado IN ("BORRADOR", "ANULADA"))'
        ];

        if (!empty($f['proveedor'])) {
            $whereBase[] = "COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :proveedor";
            $params['proveedor'] = '%' . (string) $f['proveedor'] . '%';
        }

        if (!empty($f['estado'])) {
            $whereBase[] = 'c.estado = :estado';
            $params['estado'] = (string) $f['estado'];
        }

        if (!empty($f['producto'])) {
            $whereBase[] = 'EXISTS (
                SELECT 1
                FROM compras_ordenes_detalle d2
                INNER JOIN items i2 ON i2.id = d2.id_item
                WHERE d2.id_orden = c.id_orden_compra
                  AND d2.deleted_at IS NULL
                  AND COALESCE(NULLIF(TRIM(i2.nombre), \'\'), \'\') LIKE :producto
            )';
            $params['producto'] = '%' . (string) $f['producto'] . '%';
        }

        $whereCte = implode(' AND ', $whereBase);

        $cte = "
            WITH BaseCXP AS (
                SELECT c.*, t.nombre_completo AS proveedor_nombre
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                WHERE {$whereCte}
            )
        ";

        $sql = $cte . "
            SELECT
                'CARGO' AS tipo_transaccion,
                DATE(COALESCE(co.fecha_emision, c.fecha_emision)) AS fecha_atencion,
                c.proveedor_nombre AS proveedor,
                COALESCE(NULLIF(TRIM(co.codigo), ''), NULLIF(TRIM(c.documento_referencia), ''), CONCAT('CXP-', c.id)) AS documento,
                COALESCE(i.nombre, 'Sin detalle de producto') AS producto,
                CAST({$cantidadExpr} AS DECIMAL(14,2)) AS cantidad,
                CAST(COALESCE(d.costo_unitario_pactado, c.monto_total) AS DECIMAL(14,4)) AS precio_unitario,
                CAST(
                    CASE
                        WHEN d.id IS NULL THEN c.monto_total
                        ELSE ({$cantidadExprZero} * COALESCE(d.costo_unitario_pactado, 0))
                    END
                AS DECIMAL(14,2)) AS monto_transaccion,
                c.estado
            FROM BaseCXP c
            LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra AND co.deleted_at IS NULL
            LEFT JOIN compras_ordenes_detalle d ON d.id_orden = co.id AND d.deleted_at IS NULL
            LEFT JOIN items i ON i.id = d.id_item
            WHERE DATE(COALESCE(co.fecha_emision, c.fecha_emision)) BETWEEN :fd1 AND :fh1

            UNION ALL

            -- MAGIA: Agrupamos los Abonos también para los proveedores
            SELECT
                'ABONO' AS tipo_transaccion,
                DATE(m.fecha) AS fecha_atencion,
                MAX(c.proveedor_nombre) AS proveedor,
                CONCAT('PAGO REF: ', COALESCE(NULLIF(TRIM(m.referencia), ''), DATE_FORMAT(m.fecha, '%d%m%Y'))) AS documento,
                CONCAT('Pago desde ', COALESCE(MAX(tc.nombre), 'Caja General')) AS producto,
                1.00 AS cantidad,
                CAST(SUM(m.monto) AS DECIMAL(14,4)) AS precio_unitario,
                CAST(SUM(m.monto) AS DECIMAL(14,2)) AS monto_transaccion,
                'CONFIRMADO' AS estado
            FROM tesoreria_movimientos m
            INNER JOIN BaseCXP c ON c.id = m.id_origen AND m.origen = 'CXP'
            LEFT JOIN tesoreria_cuentas tc ON tc.id = m.id_cuenta
            WHERE m.tipo = 'PAGO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
              AND DATE(m.fecha) BETWEEN :fd2 AND :fh2
            GROUP BY DATE(m.fecha), c.id_proveedor, m.id_cuenta, m.referencia

            ORDER BY fecha_atencion DESC, tipo_transaccion ASC
        ";

        $countSql = $cte . "
            SELECT SUM(conteos) FROM (
                SELECT COUNT(*) AS conteos
                FROM BaseCXP c
                LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra AND co.deleted_at IS NULL
                LEFT JOIN compras_ordenes_detalle d ON d.id_orden = co.id AND d.deleted_at IS NULL
                WHERE DATE(COALESCE(co.fecha_emision, c.fecha_emision)) BETWEEN :fd1 AND :fh1

                UNION ALL

                SELECT COUNT(DISTINCT CONCAT(DATE(m.fecha), '_', c.id_proveedor, '_', COALESCE(m.id_cuenta, 0), '_', COALESCE(TRIM(m.referencia), ''))) AS conteos
                FROM tesoreria_movimientos m
                INNER JOIN BaseCXP c ON c.id = m.id_origen AND m.origen = 'CXP'
                WHERE m.tipo = 'PAGO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
                  AND DATE(m.fecha) BETWEEN :fd2 AND :fh2
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
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => $totalRows,
            'resumen' => $this->resumenEstadoCuentaProveedores($f),
        ];
    }

    public function estadoCuentaProveedoresPorProducto(array $f, int $limite = 200): array
    {
        [$where, $params] = $this->buildEstadoCuentaProveedoresWhere($f);
        $cantidadExprZero = $this->cantidadComprasDetalleExpr('d', '0');
        $cantidadExprZeroDetalle = $this->cantidadComprasDetalleExpr('dd', '0');

        $sql = "SELECT
                    COALESCE(i.nombre, 'Sin producto asociado') AS producto,
                    CAST(ROUND(SUM({$cantidadExprZero}), 2) AS DECIMAL(14,2)) AS total_cantidad,
                    CAST(ROUND(SUM(
                        CASE
                            WHEN d.id IS NULL THEN c.monto_total
                            ELSE ({$cantidadExprZero} * COALESCE(d.costo_unitario_pactado, 0))
                        END
                    ), 2) AS DECIMAL(14,2)) AS total_facturado,
                    CAST(ROUND(SUM(
                        CASE
                            WHEN COALESCE(dt.total_subtotal, 0) > 0 AND d.id IS NOT NULL THEN c.saldo * (
                                ({$cantidadExprZero} * COALESCE(d.costo_unitario_pactado, 0)) / dt.total_subtotal
                            )
                            ELSE c.saldo
                        END
                    ), 2) AS DECIMAL(14,2)) AS total_saldo
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra AND co.deleted_at IS NULL
                LEFT JOIN compras_ordenes_detalle d ON d.id_orden = co.id AND d.deleted_at IS NULL
                LEFT JOIN (
                    SELECT dd.id_orden, SUM({$cantidadExprZeroDetalle} * COALESCE(dd.costo_unitario_pactado, 0)) AS total_subtotal
                    FROM compras_ordenes_detalle dd
                    WHERE dd.deleted_at IS NULL
                    GROUP BY dd.id_orden
                ) dt ON dt.id_orden = co.id
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

    public function listarProveedoresEstadoCuenta(int $limite = 1000): array
    {
        $sql = "SELECT DISTINCT
                    COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Proveedor #', c.id_proveedor)) AS proveedor
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                WHERE c.deleted_at IS NULL
                ORDER BY proveedor ASC
                LIMIT :limite";

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }


    private function precioUnitarioVentasDetalleExpr(string $alias, string $default = '0'): string
    {
        $hasPrecioUnitario = $this->tableColumnExists('ventas_documentos_detalle', 'precio_unitario');
        $hasCostoPactado = $this->tableColumnExists('ventas_documentos_detalle', 'costo_unitario_pactado');

        if ($hasPrecioUnitario && $hasCostoPactado) {
            return "COALESCE({$alias}.precio_unitario, {$alias}.costo_unitario_pactado, {$default})";
        }
        if ($hasPrecioUnitario) {
            return "COALESCE({$alias}.precio_unitario, {$default})";
        }
        if ($hasCostoPactado) {
            return "COALESCE({$alias}.costo_unitario_pactado, {$default})";
        }

        return $default;
    }

    private function cantidadVentasDetalleExpr(string $alias, string $default = '0'): string
    {
        $hasCantidadConversion = $this->tableColumnExists('ventas_documentos_detalle', 'cantidad_conversion');
        $hasCantidadSolicitada = $this->tableColumnExists('ventas_documentos_detalle', 'cantidad_solicitada');
        $hasCantidad = $this->tableColumnExists('ventas_documentos_detalle', 'cantidad');

        if ($hasCantidadConversion && $hasCantidadSolicitada && $hasCantidad) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad_solicitada, {$alias}.cantidad, {$default})";
        }
        if ($hasCantidadConversion && $hasCantidadSolicitada) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad_solicitada, {$default})";
        }
        if ($hasCantidadConversion && $hasCantidad) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad, {$default})";
        }
        if ($hasCantidadSolicitada && $hasCantidad) {
            return "COALESCE({$alias}.cantidad_solicitada, {$alias}.cantidad, {$default})";
        }
        if ($hasCantidadConversion) {
            return "COALESCE({$alias}.cantidad_conversion, {$default})";
        }
        if ($hasCantidadSolicitada) {
            return "COALESCE({$alias}.cantidad_solicitada, {$default})";
        }
        if ($hasCantidad) {
            return "COALESCE({$alias}.cantidad, {$default})";
        }

        return $default;
        if ($this->tableColumnExists('ventas_documentos_detalle', 'cantidad_conversion')) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad_solicitada, {$default})";
        }

        return "COALESCE({$alias}.cantidad_solicitada, {$default})";
    }

    private function cantidadComprasDetalleExpr(string $alias, string $default = '0'): string
    {
        $hasCantidadConversion = $this->tableColumnExists('compras_ordenes_detalle', 'cantidad_conversion');
        $hasCantidadSolicitada = $this->tableColumnExists('compras_ordenes_detalle', 'cantidad_solicitada');
        $hasCantidad = $this->tableColumnExists('compras_ordenes_detalle', 'cantidad');

        if ($hasCantidadConversion && $hasCantidadSolicitada && $hasCantidad) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad_solicitada, {$alias}.cantidad, {$default})";
        }
        if ($hasCantidadConversion && $hasCantidadSolicitada) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad_solicitada, {$default})";
        }
        if ($hasCantidadConversion && $hasCantidad) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad, {$default})";
        }
        if ($hasCantidadSolicitada && $hasCantidad) {
            return "COALESCE({$alias}.cantidad_solicitada, {$alias}.cantidad, {$default})";
        }
        if ($hasCantidadConversion) {
            return "COALESCE({$alias}.cantidad_conversion, {$default})";
        }
        if ($hasCantidadSolicitada) {
            return "COALESCE({$alias}.cantidad_solicitada, {$default})";
        }
        if ($hasCantidad) {
            return "COALESCE({$alias}.cantidad, {$default})";
        }

        return $default;
        if ($this->tableColumnExists('compras_ordenes_detalle', 'cantidad_conversion')) {
            return "COALESCE({$alias}.cantidad_conversion, {$alias}.cantidad_solicitada, {$default})";
        }

        return "COALESCE({$alias}.cantidad_solicitada, {$default})";
    }

    private function tableColumnExists(string $table, string $column): bool
    {
        $cacheKey = $table . '.' . $column;
        if (array_key_exists($cacheKey, $this->columnExistsCache)) {
            return $this->columnExistsCache[$cacheKey];
        }

        $sql = "SELECT COUNT(*) FROM information_schema.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                  AND TABLE_NAME = :table
                  AND COLUMN_NAME = :column";
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'table' => $table,
            'column' => $column,
        ]);

        $this->columnExistsCache[$cacheKey] = ((int) $stmt->fetchColumn()) > 0;
        return $this->columnExistsCache[$cacheKey];
    }

    private function resumenEstadoCuenta(array $f): array
    {
        $paramsAnt = ['fd' => $f['fecha_desde']];
        $paramsPer = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $filtroCliente = "";

        if (!empty($f['cliente'])) {
            $filtroCliente = " AND COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :cliente";
            $paramsAnt['cliente'] = '%' . (string) $f['cliente'] . '%';
            $paramsPer['cliente'] = '%' . (string) $f['cliente'] . '%';
        }

        // =======================================================
        // 1. SALDO INICIAL (Todo lo ocurrido ANTES de la fecha_desde)
        // =======================================================
        
        $sqlCargosAnt = "
            SELECT COALESCE(SUM(c.monto_total), 0) 
            FROM tesoreria_cxc c
            INNER JOIN terceros t ON t.id = c.id_cliente
            LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta
            WHERE c.deleted_at IS NULL 
              AND DATE(COALESCE(v.fecha_emision, c.fecha_emision)) < :fd
              AND NOT EXISTS (SELECT 1 FROM ventas_documentos v2 WHERE v2.id = c.id_documento_venta AND v2.tipo_operacion = 'DONACION')
              AND NOT EXISTS (SELECT 1 FROM ventas_documentos v3 WHERE v3.id = c.id_documento_venta AND v3.estado IN ('BORRADOR', 'ANULADA'))
              {$filtroCliente}
        ";
        $stmt = $this->db()->prepare($sqlCargosAnt);
        $stmt->execute($paramsAnt);
        $totalCargosAnt = (float) $stmt->fetchColumn();

        $sqlAbonosAnt = "
            SELECT COALESCE(SUM(m.monto), 0) 
            FROM tesoreria_movimientos m
            INNER JOIN tesoreria_cxc c ON c.id = m.id_origen AND m.origen = 'CXC'
            INNER JOIN terceros t ON t.id = c.id_cliente
            WHERE m.deleted_at IS NULL 
              AND m.tipo = 'COBRO' 
              AND m.estado = 'CONFIRMADO'
              AND DATE(m.fecha) < :fd
              {$filtroCliente}
        ";
        $stmt = $this->db()->prepare($sqlAbonosAnt);
        $stmt->execute($paramsAnt);
        $totalAbonosAnt = (float) $stmt->fetchColumn();

        $saldoInicial = $totalCargosAnt - $totalAbonosAnt;

        // =======================================================
        // 2. MOVIMIENTOS DEL PERIODO (Entre fecha_desde y fecha_hasta)
        // =======================================================
        
        $sqlCargosPer = "
            SELECT COALESCE(SUM(c.monto_total), 0) AS total, COUNT(c.id) AS cant
            FROM tesoreria_cxc c
            INNER JOIN terceros t ON t.id = c.id_cliente
            LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta
            WHERE c.deleted_at IS NULL 
              AND DATE(COALESCE(v.fecha_emision, c.fecha_emision)) BETWEEN :fd AND :fh
              AND NOT EXISTS (SELECT 1 FROM ventas_documentos v2 WHERE v2.id = c.id_documento_venta AND v2.tipo_operacion = 'DONACION')
              AND NOT EXISTS (SELECT 1 FROM ventas_documentos v3 WHERE v3.id = c.id_documento_venta AND v3.estado IN ('BORRADOR', 'ANULADA'))
              {$filtroCliente}
        ";
        $stmt = $this->db()->prepare($sqlCargosPer);
        $stmt->execute($paramsPer);
        $resCargosPer = $stmt->fetch(PDO::FETCH_ASSOC);

        $sqlAbonosPer = "
            SELECT COALESCE(SUM(m.monto), 0) AS total, COUNT(m.id) AS cant
            FROM tesoreria_movimientos m
            INNER JOIN tesoreria_cxc c ON c.id = m.id_origen AND m.origen = 'CXC'
            INNER JOIN terceros t ON t.id = c.id_cliente
            WHERE m.deleted_at IS NULL 
              AND m.tipo = 'COBRO' 
              AND m.estado = 'CONFIRMADO'
              AND DATE(m.fecha) BETWEEN :fd AND :fh
              {$filtroCliente}
        ";
        $stmt = $this->db()->prepare($sqlAbonosPer);
        $stmt->execute($paramsPer);
        $resAbonosPer = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalFacturado = (float) ($resCargosPer['total'] ?? 0);
        $totalPagado = (float) ($resAbonosPer['total'] ?? 0);

        return [
            'saldo_inicial'    => $saldoInicial,
            'total_facturado'  => $totalFacturado,
            'total_pagado'     => $totalPagado,
            'total_saldo'      => $saldoInicial + $totalFacturado - $totalPagado,
            'total_documentos' => (int)($resCargosPer['cant'] ?? 0) + (int)($resAbonosPer['cant'] ?? 0)
        ];
    }

    private function resumenEstadoCuentaProveedores(array $f): array
    {
        $paramsAnt = ['fd' => $f['fecha_desde']];
        $paramsPer = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $filtroProveedor = "";

        if (!empty($f['proveedor'])) {
            $filtroProveedor = " AND COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :proveedor";
            $paramsAnt['proveedor'] = '%' . (string) $f['proveedor'] . '%';
            $paramsPer['proveedor'] = '%' . (string) $f['proveedor'] . '%';
        }

        // 1. SALDO INICIAL (ANTES de fecha_desde)
        $sqlCargosAnt = "
            SELECT COALESCE(SUM(c.monto_total), 0) 
            FROM tesoreria_cxp c
            INNER JOIN terceros t ON t.id = c.id_proveedor
            LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra
            WHERE c.deleted_at IS NULL 
              AND DATE(COALESCE(co.fecha_emision, c.fecha_emision)) < :fd
              AND NOT EXISTS (SELECT 1 FROM compras_ordenes co2 WHERE co2.id = c.id_orden_compra AND co2.estado IN ('BORRADOR', 'ANULADA'))
              {$filtroProveedor}
        ";
        $stmt = $this->db()->prepare($sqlCargosAnt);
        $stmt->execute($paramsAnt);
        $totalCargosAnt = (float) $stmt->fetchColumn();

        $sqlAbonosAnt = "
            SELECT COALESCE(SUM(m.monto), 0) 
            FROM tesoreria_movimientos m
            INNER JOIN tesoreria_cxp c ON c.id = m.id_origen AND m.origen = 'CXP'
            INNER JOIN terceros t ON t.id = c.id_proveedor
            WHERE m.deleted_at IS NULL 
              AND m.tipo = 'PAGO' 
              AND m.estado = 'CONFIRMADO'
              AND DATE(m.fecha) < :fd
              {$filtroProveedor}
        ";
        $stmt = $this->db()->prepare($sqlAbonosAnt);
        $stmt->execute($paramsAnt);
        $totalAbonosAnt = (float) $stmt->fetchColumn();

        $saldoInicial = $totalCargosAnt - $totalAbonosAnt;

        // 2. MOVIMIENTOS DEL PERIODO
        $sqlCargosPer = "
            SELECT COALESCE(SUM(c.monto_total), 0) AS total, COUNT(c.id) AS cant
            FROM tesoreria_cxp c
            INNER JOIN terceros t ON t.id = c.id_proveedor
            LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra
            WHERE c.deleted_at IS NULL 
              AND DATE(COALESCE(co.fecha_emision, c.fecha_emision)) BETWEEN :fd AND :fh
              AND NOT EXISTS (SELECT 1 FROM compras_ordenes co2 WHERE co2.id = c.id_orden_compra AND co2.estado IN ('BORRADOR', 'ANULADA'))
              {$filtroProveedor}
        ";
        $stmt = $this->db()->prepare($sqlCargosPer);
        $stmt->execute($paramsPer);
        $resCargosPer = $stmt->fetch(PDO::FETCH_ASSOC);

        $sqlAbonosPer = "
            SELECT COALESCE(SUM(m.monto), 0) AS total, COUNT(m.id) AS cant
            FROM tesoreria_movimientos m
            INNER JOIN tesoreria_cxp c ON c.id = m.id_origen AND m.origen = 'CXP'
            INNER JOIN terceros t ON t.id = c.id_proveedor
            WHERE m.deleted_at IS NULL 
              AND m.tipo = 'PAGO' 
              AND m.estado = 'CONFIRMADO'
              AND DATE(m.fecha) BETWEEN :fd AND :fh
              {$filtroProveedor}
        ";
        $stmt = $this->db()->prepare($sqlAbonosPer);
        $stmt->execute($paramsPer);
        $resAbonosPer = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalFacturado = (float) ($resCargosPer['total'] ?? 0);
        $totalPagado = (float) ($resAbonosPer['total'] ?? 0);

        return [
            'saldo_inicial'    => $saldoInicial,
            'total_facturado'  => $totalFacturado,
            'total_pagado'     => $totalPagado,
            'total_saldo'      => $saldoInicial + $totalFacturado - $totalPagado,
            'total_documentos' => (int)($resCargosPer['cant'] ?? 0) + (int)($resAbonosPer['cant'] ?? 0)
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
            'DATE(COALESCE(v.fecha_emision, c.fecha_emision)) BETWEEN :fd AND :fh',
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v WHERE v.id = c.id_documento_venta AND v.tipo_operacion = "DONACION")',
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v2 WHERE v2.id = c.id_documento_venta AND v2.estado IN ("BORRADOR", "ANULADA"))'
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

    private function buildEstadoCuentaProveedoresWhere(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta'],
        ];

        $where = [
            'c.deleted_at IS NULL',
            'DATE(COALESCE(co.fecha_emision, c.fecha_emision)) BETWEEN :fd AND :fh',
            'NOT EXISTS (SELECT 1 FROM compras_ordenes co2 WHERE co2.id = c.id_orden_compra AND co2.estado IN ("BORRADOR", "ANULADA"))'
        ];

        if (!empty($f['proveedor'])) {
            $where[] = "COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :proveedor";
            $params['proveedor'] = '%' . (string) $f['proveedor'] . '%';
        }

        if (!empty($f['estado'])) {
            $where[] = 'c.estado = :estado';
            $params['estado'] = (string) $f['estado'];
        }

        if (!empty($f['producto'])) {
            $where[] = "EXISTS (
                SELECT 1
                FROM compras_ordenes_detalle d2
                INNER JOIN items i2 ON i2.id = d2.id_item
                WHERE d2.id_orden = c.id_orden_compra
                  AND d2.deleted_at IS NULL
                  AND COALESCE(NULLIF(TRIM(i2.nombre), ''), '') LIKE :producto
            )";
            $params['producto'] = '%' . (string) $f['producto'] . '%';
        }

        return [implode(' AND ', $where), $params];
    }

    // ==========================================
    // REPORTE DE DEPÓSITOS / INGRESOS
    // ==========================================
    public function reporteDepositos(array $f, int $pagina, int $tamano): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];

        // Filtramos solo los ingresos/cobros que no estén eliminados
        $where = [
            'm.deleted_at IS NULL', 
            "m.tipo = 'COBRO'", 
            'DATE(m.fecha) BETWEEN :fd AND :fh'
        ];

        // Si filtran por una cuenta bancaria específica
        if (!empty($f['id_tercero'])) {
            $where[] = "cxc.id_cliente = :id_tercero";
            $params['id_tercero'] = (int) $f['id_tercero'];
        }

        $whereSql = implode(' AND ', $where);

        // 1. Contar el total de registros para la paginación
        $countSql = "SELECT COUNT(*)
                     FROM tesoreria_movimientos m
                     LEFT JOIN tesoreria_cxc cxc ON cxc.id = m.id_origen AND m.origen = 'CXC'
                     WHERE {$whereSql}";
        $countStmt = $this->db()->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // 2. Consulta principal
        $sql = "SELECT 
                    m.id, 
                    m.fecha, 
                    c.nombre AS cuenta, 
                    m.referencia, 
                    m.monto, 
                    m.estado, 
                    m.origen,
                    COALESCE(t.nombre_completo, 'Ingreso General / Otros') AS cliente_origen
                FROM tesoreria_movimientos m
                LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                LEFT JOIN tesoreria_cxc cxc ON cxc.id = m.id_origen AND m.origen = 'CXC'
                LEFT JOIN terceros t ON t.id = cxc.id_cliente
                WHERE {$whereSql}
                ORDER BY m.fecha DESC, m.id DESC";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Sumar el total de dinero de esos depósitos
        $sqlTotal = "SELECT ROUND(SUM(m.monto), 2)
                     FROM tesoreria_movimientos m
                     LEFT JOIN tesoreria_cxc cxc ON cxc.id = m.id_origen AND m.origen = 'CXC'
                     WHERE {$whereSql} AND m.estado = 'CONFIRMADO'";
        $stmtTotal = $this->db()->prepare($sqlTotal);
        $stmtTotal->execute($params);
        $sumaTotal = (float) $stmtTotal->fetchColumn();

        return [
            'rows' => $rows,
            'total' => $total,
            'suma_total' => $sumaTotal
        ];
    }

    // ==========================================
    // REPORTE GLOBAL CXC (AGRUPADO Y DETALLADO)
    // ==========================================
    public function obtenerCarteraMacroCxC(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta']
        ];

        $where = [
            'c.deleted_at IS NULL',
            'c.saldo > 0',
            'DATE(COALESCE(v.fecha_emision, c.fecha_emision)) BETWEEN :fd AND :fh'
        ];

        // Filtro por Cliente (búsqueda de texto)
        if (!empty($f['cliente'])) {
            $where[] = 'COALESCE(NULLIF(TRIM(t.nombre_completo), ""), "") LIKE :cliente';
            $params['cliente'] = '%' . (string) $f['cliente'] . '%';
        }

        // Filtro por Estado de Factura
        if (isset($f['estado_factura'])) {
            if ($f['estado_factura'] === 'vencida') {
                $where[] = 'c.fecha_vencimiento < CURDATE()';
            } elseif ($f['estado_factura'] === 'corriente') {
                $where[] = 'c.fecha_vencimiento >= CURDATE()';
            }
        }

        // Filtro por Tipo de Tercero
        if (isset($f['tipo_tercero'])) {
            if ($f['tipo_tercero'] === 'cliente') {
                $where[] = 't.es_cliente = 1';
            } elseif ($f['tipo_tercero'] === 'distribuidor') {
                $where[] = 'd.id_tercero IS NOT NULL';
            }
        }

        $whereSql = implode(' AND ', $where);

        // 1. Consulta AGRUPADA (Usada para los KPIs en tiempo real y la vista web)
        $sqlAgrupados = "SELECT
            t.id AS id_cliente,
            COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Cliente #', c.id_cliente)) AS cliente,
            SUM(c.saldo) AS total_deuda,
            SUM(CASE WHEN c.fecha_vencimiento >= CURDATE() THEN c.saldo ELSE 0 END) AS por_vencer,
            SUM(CASE WHEN c.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 1 AND 30 THEN c.saldo ELSE 0 END) AS mora_30,
            SUM(CASE WHEN c.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 31 AND 60 THEN c.saldo ELSE 0 END) AS mora_60,
            SUM(CASE WHEN c.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), c.fecha_vencimiento) > 60 THEN c.saldo ELSE 0 END) AS mora_mas_60
        FROM tesoreria_cxc c
        INNER JOIN terceros t ON t.id = c.id_cliente
        LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
        LEFT JOIN distribuidores d ON d.id_tercero = t.id AND d.deleted_at IS NULL
        WHERE {$whereSql}
        GROUP BY t.id, t.nombre_completo
        ORDER BY total_deuda DESC";

        $stmtAgrupados = $this->db()->prepare($sqlAgrupados);
        $stmtAgrupados->execute($params);
        $agrupados = $stmtAgrupados->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Consulta DETALLADA (Usada para exportar el CSV y Excel Nativos)
        $sqlDetallados = "SELECT
            COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Cliente #', c.id_cliente)) AS cliente,
            COALESCE(NULLIF(TRIM(v.codigo), ''), NULLIF(TRIM(c.documento_referencia), ''), CONCAT('CXC-', c.id)) AS documento_referencia,
            DATE(COALESCE(v.fecha_emision, c.fecha_emision)) AS fecha_emision,
            c.fecha_vencimiento,
            c.monto_total,
            c.saldo,
            CASE
                WHEN c.fecha_vencimiento < CURDATE() THEN 'Vencida'
                ELSE 'Corriente'
            END AS estado
        FROM tesoreria_cxc c
        INNER JOIN terceros t ON t.id = c.id_cliente
        LEFT JOIN ventas_documentos v ON v.id = c.id_documento_venta AND v.deleted_at IS NULL
        LEFT JOIN distribuidores d ON d.id_tercero = t.id AND d.deleted_at IS NULL
        WHERE {$whereSql}
        ORDER BY cliente ASC, fecha_emision DESC";

        $stmtDetallados = $this->db()->prepare($sqlDetallados);
        $stmtDetallados->execute($params);
        $detallados = $stmtDetallados->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'agrupados' => $agrupados,
            'detallados' => $detallados
        ];
    }

    // ==========================================
    // REPORTE GLOBAL CXP (AGRUPADO Y DETALLADO)
    // ==========================================
    public function obtenerCarteraMacroCxP(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta']
        ];

        $where = [
            'c.deleted_at IS NULL',
            'c.saldo > 0',
            'DATE(COALESCE(co.fecha_emision, c.fecha_emision)) BETWEEN :fd AND :fh'
        ];

        // Filtro por Proveedor (búsqueda de texto)
        if (!empty($f['proveedor'])) {
            $where[] = 'COALESCE(NULLIF(TRIM(t.nombre_completo), ""), "") LIKE :proveedor';
            $params['proveedor'] = '%' . (string) $f['proveedor'] . '%';
        }

        // Filtro por Estado de Factura
        if (isset($f['estado_factura'])) {
            if ($f['estado_factura'] === 'vencida') {
                $where[] = 'c.fecha_vencimiento < CURDATE()';
            } elseif ($f['estado_factura'] === 'corriente') {
                $where[] = 'c.fecha_vencimiento >= CURDATE()';
            }
        }

        $whereSql = implode(' AND ', $where);

        // 1. Consulta AGRUPADA (Usada para los KPIs en tiempo real y la vista web)
        $sqlAgrupados = "SELECT
            t.id AS id_proveedor,
            COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Proveedor #', c.id_proveedor)) AS proveedor,
            SUM(c.saldo) AS total_deuda,
            SUM(CASE WHEN c.fecha_vencimiento >= CURDATE() THEN c.saldo ELSE 0 END) AS por_vencer,
            SUM(CASE WHEN c.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 1 AND 30 THEN c.saldo ELSE 0 END) AS mora_30,
            SUM(CASE WHEN c.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 31 AND 60 THEN c.saldo ELSE 0 END) AS mora_60,
            SUM(CASE WHEN c.fecha_vencimiento < CURDATE() AND DATEDIFF(CURDATE(), c.fecha_vencimiento) > 60 THEN c.saldo ELSE 0 END) AS mora_mas_60
        FROM tesoreria_cxp c
        INNER JOIN terceros t ON t.id = c.id_proveedor
        LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra AND co.deleted_at IS NULL
        WHERE {$whereSql}
        GROUP BY t.id, t.nombre_completo
        ORDER BY total_deuda DESC";

        $stmtAgrupados = $this->db()->prepare($sqlAgrupados);
        $stmtAgrupados->execute($params);
        $agrupados = $stmtAgrupados->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 2. Consulta DETALLADA (Usada para exportar el CSV y Excel Nativos)
        $sqlDetallados = "SELECT
            COALESCE(NULLIF(TRIM(t.nombre_completo), ''), CONCAT('Proveedor #', c.id_proveedor)) AS proveedor,
            COALESCE(NULLIF(TRIM(co.codigo), ''), NULLIF(TRIM(c.documento_referencia), ''), CONCAT('CXP-', c.id)) AS documento_referencia,
            DATE(COALESCE(co.fecha_emision, c.fecha_emision)) AS fecha_emision,
            c.fecha_vencimiento,
            c.monto_total,
            c.saldo,
            CASE
                WHEN c.fecha_vencimiento < CURDATE() THEN 'Vencida'
                ELSE 'Corriente'
            END AS estado
        FROM tesoreria_cxp c
        INNER JOIN terceros t ON t.id = c.id_proveedor
        LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra AND co.deleted_at IS NULL
        WHERE {$whereSql}
        ORDER BY proveedor ASC, fecha_emision DESC";

        $stmtDetallados = $this->db()->prepare($sqlDetallados);
        $stmtDetallados->execute($params);
        $detallados = $stmtDetallados->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'agrupados' => $agrupados,
            'detallados' => $detallados
        ];
    }
}