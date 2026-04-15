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
                    t.es_distribuidor
                FROM terceros t
                WHERE t.deleted_at IS NULL
                  AND t.estado = 1
                  AND (t.es_cliente = 1 OR t.es_distribuidor = 1)
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
        $offset = ($pagina - 1) * $tamano;
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
                ORDER BY dias_atraso DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function agingCxp(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
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
                ORDER BY dias_atraso DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function flujoPorCuenta(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $whereTercero = '';
        if (!empty($f['id_tercero'])) {
            $whereTercero = " AND (
                (m.origen = 'CXC' AND EXISTS (
                    SELECT 1
                    FROM tesoreria_cxc cxc
                    WHERE cxc.id = m.id_origen
                      AND cxc.id_cliente = :id_tercero
                      AND cxc.deleted_at IS NULL
                ))
                OR
                (m.origen = 'CXP' AND EXISTS (
                    SELECT 1
                    FROM tesoreria_cxp cxp
                    WHERE cxp.id = m.id_origen
                      AND cxp.id_proveedor = :id_tercero
                      AND cxp.deleted_at IS NULL
                ))
            )";
            $params['id_tercero'] = (int) $f['id_tercero'];
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
        $cantidadExpr = $this->cantidadVentasDetalleExpr('d', '1');
        $cantidadExprZero = $this->cantidadVentasDetalleExpr('d', '0');
        $precioExprZero = $this->precioUnitarioVentasDetalleExpr('d', '0');
        
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
                CAST({$cantidadExpr} AS DECIMAL(14,2)) AS cantidad,
                CAST(COALESCE({$precioExprZero}, c.monto_total) AS DECIMAL(14,4)) AS precio_unitario,
                CAST(
                    CASE
                        WHEN d.id IS NULL THEN c.monto_total
                        ELSE ({$cantidadExprZero} * {$precioExprZero})
                    END
                AS DECIMAL(14,2)) AS monto_transaccion,
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
        $offset = ($pagina - 1) * $tamano;
        $cantidadExpr = $this->cantidadComprasDetalleExpr('d', '1');
        $cantidadExprZero = $this->cantidadComprasDetalleExpr('d', '0');
        [$where, $params] = $this->buildEstadoCuentaProveedoresWhere($f);

        $cte = "
            WITH TargetCXP AS (
                SELECT c.*, t.nombre_completo AS proveedor_nombre
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                WHERE {$where}
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
            FROM TargetCXP c
            LEFT JOIN compras_ordenes co ON co.id = c.id_orden_compra AND co.deleted_at IS NULL
            LEFT JOIN compras_ordenes_detalle d ON d.id_orden = co.id AND d.deleted_at IS NULL
            LEFT JOIN items i ON i.id = d.id_item

            UNION ALL

            SELECT
                'ABONO' AS tipo_transaccion,
                DATE(m.fecha) AS fecha_atencion,
                c.proveedor_nombre AS proveedor,
                CONCAT('PAGO REF: ', COALESCE(NULLIF(TRIM(m.referencia), ''), m.id)) AS documento,
                'Pago / Abono al proveedor' AS producto,
                1.00 AS cantidad,
                CAST(m.monto AS DECIMAL(14,4)) AS precio_unitario,
                CAST(m.monto AS DECIMAL(14,2)) AS monto_transaccion,
                c.estado
            FROM tesoreria_movimientos m
            INNER JOIN TargetCXP c ON c.id = m.id_origen AND m.origen = 'CXP'
            WHERE m.tipo = 'PAGO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL

            ORDER BY fecha_atencion DESC, tipo_transaccion ASC
            LIMIT :limite OFFSET :offset
        ";

        $countSql = $cte . "
            SELECT SUM(conteos) FROM (
                SELECT COUNT(*) AS conteos
                FROM TargetCXP c
                LEFT JOIN compras_ordenes_detalle d ON d.id_orden = c.id_orden_compra AND d.deleted_at IS NULL

                UNION ALL

                SELECT COUNT(*) AS conteos
                FROM tesoreria_movimientos m
                INNER JOIN TargetCXP c ON c.id = m.id_origen AND m.origen = 'CXP'
                WHERE m.tipo = 'PAGO' AND m.estado = 'CONFIRMADO' AND m.deleted_at IS NULL
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
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta'],
        ];

        $whereBase = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) BETWEEN :fd AND :fh',
            // NUEVO: Filtro para los cálculos de la cabecera
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v WHERE v.id = c.id_documento_venta AND v.tipo_operacion = "DONACION")'
        ];
        
        $whereAnterior = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) < :fd_anterior',
            // NUEVO: Filtro para que las donaciones no alteren el saldo histórico
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v WHERE v.id = c.id_documento_venta AND v.tipo_operacion = "DONACION")'
        ];
        $params['fd_anterior'] = $f['fecha_desde'];

        if (!empty($f['cliente'])) {
            $condicionCliente = "COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :cliente";
            $whereBase[] = $condicionCliente;
            $whereAnterior[] = $condicionCliente;
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
        $whereAnt = implode(' AND ', $whereAnterior);

        // 1. OBTENEMOS EL RESUMEN DEL PERIODO ACTUAL
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
            if ($k !== 'fd_anterior') {
                $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_facturado' => 0,
            'total_pagado' => 0,
            'total_saldo' => 0,
            'total_documentos' => 0,
        ];

        // 2. OBTENEMOS EL SALDO ANTERIOR A LA FECHA 'DESDE'
        $sqlAnterior = "
            SELECT 
                (COALESCE(SUM(c.monto_total), 0) - COALESCE(SUM(c.monto_pagado), 0)) AS saldo_anterior
            FROM tesoreria_cxc c
            INNER JOIN terceros t ON t.id = c.id_cliente
            WHERE {$whereAnt}
        ";
        
        $stmtAnt = $this->db()->prepare($sqlAnterior);
        foreach ($params as $k => $v) {
            if (in_array($k, ['fd_anterior', 'cliente'])) {
                $stmtAnt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $stmtAnt->execute();
        $saldoAnt = (float) $stmtAnt->fetchColumn();

        $resumen['saldo_anterior'] = $saldoAnt;

        return $resumen;
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
            // NUEVO: Candado maestro para ocultar cualquier donación que se haya colado a CxC
            'NOT EXISTS (SELECT 1 FROM ventas_documentos v WHERE v.id = c.id_documento_venta AND v.tipo_operacion = "DONACION")'
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

    private function resumenEstadoCuentaProveedores(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta'],
        ];

        $whereBase = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) BETWEEN :fd AND :fh',
        ];

        $whereAnterior = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) < :fd_anterior',
        ];
        $params['fd_anterior'] = $f['fecha_desde'];

        if (!empty($f['proveedor'])) {
            $condicionProveedor = "COALESCE(NULLIF(TRIM(t.nombre_completo), ''), '') LIKE :proveedor";
            $whereBase[] = $condicionProveedor;
            $whereAnterior[] = $condicionProveedor;
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

        $where = implode(' AND ', $whereBase);
        $whereAnt = implode(' AND ', $whereAnterior);

        $sql = "SELECT
                    CAST(ROUND(SUM(c.monto_total), 2) AS DECIMAL(14,2)) AS total_facturado,
                    CAST(ROUND(SUM(c.monto_pagado), 2) AS DECIMAL(14,2)) AS total_pagado,
                    CAST(ROUND(SUM(c.saldo), 2) AS DECIMAL(14,2)) AS total_saldo,
                    COUNT(*) AS total_documentos
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                WHERE {$where}";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            if ($k !== 'fd_anterior') {
                $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $stmt->execute();
        $resumen = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
            'total_facturado' => 0,
            'total_pagado' => 0,
            'total_saldo' => 0,
            'total_documentos' => 0,
        ];

        $sqlAnterior = "
            SELECT
                (COALESCE(SUM(c.monto_total), 0) - COALESCE(SUM(c.monto_pagado), 0)) AS saldo_anterior
            FROM tesoreria_cxp c
            INNER JOIN terceros t ON t.id = c.id_proveedor
            WHERE {$whereAnt}
        ";

        $stmtAnt = $this->db()->prepare($sqlAnterior);
        foreach ($params as $k => $v) {
            if (in_array($k, ['fd_anterior', 'proveedor'], true)) {
                $stmtAnt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $stmtAnt->execute();
        $saldoAnt = (float) $stmtAnt->fetchColumn();

        $resumen['saldo_anterior'] = $saldoAnt;

        return $resumen;
    }

    private function buildEstadoCuentaProveedoresWhere(array $f): array
    {
        $params = [
            'fd' => $f['fecha_desde'],
            'fh' => $f['fecha_hasta'],
        ];

        $where = [
            'c.deleted_at IS NULL',
            'DATE(c.fecha_emision) BETWEEN :fd AND :fh',
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
        $offset = ($pagina - 1) * $tamano;
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

        // 2. Consulta principal (Hacemos LEFT JOIN con CxC para obtener el cliente si el pago viene de una factura)
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
                ORDER BY m.fecha DESC, m.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Sumar el total de dinero de esos depósitos (Para mostrarlo en la tabla y en el PDF)
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
}
