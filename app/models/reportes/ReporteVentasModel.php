<?php
declare(strict_types=1);

class ReporteVentasModel extends Modelo
{
    private function aplicarFiltroTipoTercero(array &$where, array &$params, array $filtros, string $aliasDocumento = 'v'): void
    {
        $tipo = strtolower(trim((string) ($filtros['tipo_tercero'] ?? '')));
        if ($tipo === '') {
            return;
        }

        if ($tipo === 'cliente') {
            $where[] = "EXISTS (
                SELECT 1
                FROM terceros tft
                LEFT JOIN distribuidores dft
                    ON dft.id_tercero = tft.id
                   AND dft.deleted_at IS NULL
                WHERE tft.id = {$aliasDocumento}.id_cliente
                  AND tft.deleted_at IS NULL
                  AND tft.es_cliente = 1
                  AND dft.id_tercero IS NULL
            )";
            return;
        }

        if ($tipo === 'cliente_distribuidor') {
            $where[] = "EXISTS (
                SELECT 1
                FROM terceros tft
                INNER JOIN distribuidores dft
                    ON dft.id_tercero = tft.id
                   AND dft.deleted_at IS NULL
                WHERE tft.id = {$aliasDocumento}.id_cliente
                  AND tft.deleted_at IS NULL
                  AND tft.es_cliente = 1
            )";
            return;
        }

        if ($tipo === 'distribuidor') {
            $where[] = "EXISTS (
                SELECT 1
                FROM terceros tft
                INNER JOIN distribuidores dft
                    ON dft.id_tercero = tft.id
                   AND dft.deleted_at IS NULL
                WHERE tft.id = {$aliasDocumento}.id_cliente
                  AND tft.deleted_at IS NULL
                  AND COALESCE(tft.es_cliente, 0) = 0
            )";
        }
    }

    public function contarPorDespachar(): int
    {
        // Aquí NO filtramos donaciones, porque el almacén SÍ debe despacharlas.
        $sql = "SELECT COUNT(*)
                FROM ventas_documentos v
                WHERE v.deleted_at IS NULL AND v.estado IN (1,2)
                  AND EXISTS (
                    SELECT 1 FROM ventas_documentos_detalle d
                    WHERE d.id_documento_venta=v.id AND d.deleted_at IS NULL
                      AND (d.cantidad - d.cantidad_despachada) > 0
                  )";
        return (int) $this->db()->query($sql)->fetchColumn();
    }

    public function ventasPorCliente(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        
        // MODIFICACIÓN: Agregamos v.tipo_operacion = 'VENTA' para que no sume clientes a los que se les donó
        $where = ["v.tipo_operacion = 'VENTA'", 'v.deleted_at IS NULL', 'DATE(v.fecha_emision) BETWEEN :fd AND :fh'];
        
        if (!empty($f['id_cliente'])) { $where[] = 'v.id_cliente = :id_cliente'; $params['id_cliente'] = (int) $f['id_cliente']; }
        if (!empty($f['id_item'])) {
            $where[] = 'EXISTS (SELECT 1 FROM ventas_documentos_detalle d WHERE d.id_documento_venta = v.id AND d.id_item = :id_item AND d.deleted_at IS NULL)';
            $params['id_item'] = (int) $f['id_item'];
        }
        if ($f['estado'] !== '' && $f['estado'] !== null) { $where[] = 'v.estado = :estado'; $params['estado'] = (int) $f['estado']; }
        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');
        $w = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(DISTINCT v.id_cliente) FROM ventas_documentos v WHERE {$w}");
        $count->execute($params);

        $sql = "SELECT t.nombre_completo AS cliente,
                       ROUND(SUM(v.total),2) AS total_vendido,
                       ROUND(AVG(v.total),2) AS ticket_promedio,
                       COUNT(*) AS documentos
                FROM ventas_documentos v
                INNER JOIN terceros t ON t.id = v.id_cliente
                WHERE {$w}
                GROUP BY v.id_cliente, t.nombre_completo
                ORDER BY total_vendido DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function topProductos(array $f, int $limite = 10): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        
        // MODIFICACIÓN: Agregamos v.tipo_operacion = 'VENTA' para que la donación no infle el ranking de productos
        $where = ["v.tipo_operacion = 'VENTA'", 'v.deleted_at IS NULL', 'd.deleted_at IS NULL', 'DATE(v.fecha_emision) BETWEEN :fd AND :fh'];
        
        if (!empty($f['id_cliente'])) { $where[] = 'v.id_cliente = :id_cliente'; $params['id_cliente'] = (int) $f['id_cliente']; }
        if (!empty($f['id_item'])) { $where[] = 'd.id_item = :id_item'; $params['id_item'] = (int) $f['id_item']; }
        if ($f['estado'] !== '' && $f['estado'] !== null) { $where[] = 'v.estado = :estado'; $params['estado'] = (int) $f['estado']; }
        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');
        $w = implode(' AND ', $where);

        /* * MODIFICACIÓN APLICADA Y CONFIRMADA:
         * Usamos (d.cantidad * d.precio_unitario) basándonos en la estructura real de la tabla.
         */
        $sql = "SELECT i.nombre AS producto,
                       ROUND(SUM(d.cantidad),2) AS total_cantidad,
                       ROUND(SUM(d.cantidad * d.precio_unitario),2) AS total_monto 
                FROM ventas_documentos v
                INNER JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id
                INNER JOIN items i ON i.id = d.id_item
                WHERE {$w}
                GROUP BY d.id_item, i.nombre
                ORDER BY total_monto DESC
                LIMIT :limite";
                
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
    

    public function pendientesDespacho(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        
        // Aquí NO filtramos donaciones, porque el almacén SÍ debe despacharlas.
        $where = ['v.deleted_at IS NULL', 'DATE(v.fecha_emision) BETWEEN :fd AND :fh', 'v.estado IN (1,2)'];
        if (!empty($f['id_cliente'])) { $where[] = 'v.id_cliente = :id_cliente'; $params['id_cliente'] = (int) $f['id_cliente']; }
        if (!empty($f['id_item'])) {
            $where[] = 'EXISTS (SELECT 1 FROM ventas_documentos_detalle d2 WHERE d2.id_documento_venta = v.id AND d2.id_item = :id_item AND d2.deleted_at IS NULL)';
            $params['id_item'] = (int) $f['id_item'];
        }
        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');
        $w = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM ventas_documentos v WHERE {$w}");
        $count->execute($params);

        /* * MODIFICACIÓN APLICADA:
         * 1. Retiramos el LEFT JOIN a la tabla almacenes ya que el almacén se define al despachar.
         * 2. Enviamos el texto 'Por asignar' como valor de la columna `almacen`.
         * 3. Retiramos a.nombre del GROUP BY.
         */
        $sql = "SELECT v.codigo AS documento, t.nombre_completo AS cliente,
                       ROUND(COALESCE(SUM(d.cantidad - d.cantidad_despachada),0),2) AS saldo_despachar,
                       'Por asignar' AS almacen,
                       DATEDIFF(CURDATE(), DATE(v.fecha_emision)) AS dias_desde_emision
                FROM ventas_documentos v
                INNER JOIN terceros t ON t.id = v.id_cliente
                INNER JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
                WHERE {$w}
                GROUP BY v.id, v.codigo, t.nombre_completo, v.fecha_emision
                HAVING saldo_despachar > 0
                ORDER BY dias_desde_emision DESC
                LIMIT :limite OFFSET :offset";
                
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function ventasPorPeriodo(array $f, string $agrupacion = 'diaria', int $limite = 12): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ["v.tipo_operacion = 'VENTA'", 'v.deleted_at IS NULL', 'DATE(v.fecha_emision) BETWEEN :fd AND :fh'];

        if (!empty($f['id_cliente'])) {
            $where[] = 'v.id_cliente = :id_cliente';
            $params['id_cliente'] = (int) $f['id_cliente'];
        }
        if (!empty($f['id_item'])) {
            $where[] = 'EXISTS (SELECT 1 FROM ventas_documentos_detalle d WHERE d.id_documento_venta = v.id AND d.id_item = :id_item AND d.deleted_at IS NULL)';
            $params['id_item'] = (int) $f['id_item'];
        }
        if ($f['estado'] !== '' && $f['estado'] !== null) {
            $where[] = 'v.estado = :estado';
            $params['estado'] = (int) $f['estado'];
        }
        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');

        $w = implode(' AND ', $where);

        if ($agrupacion === 'semanal') {
            $sql = "SELECT YEAR(v.fecha_emision) AS periodo_anio,
                           WEEK(v.fecha_emision, 1) AS periodo_semana,
                           CONCAT(YEAR(v.fecha_emision), '-S', LPAD(WEEK(v.fecha_emision, 1), 2, '0')) AS etiqueta,
                           ROUND(SUM(v.total), 2) AS total_vendido,
                           COUNT(*) AS documentos
                    FROM ventas_documentos v
                    WHERE {$w}
                    GROUP BY YEAR(v.fecha_emision), WEEK(v.fecha_emision, 1)
                    ORDER BY periodo_anio DESC, periodo_semana DESC
                    LIMIT :limite";
        } else {
            $sql = "SELECT DATE(v.fecha_emision) AS periodo_fecha,
                           DATE_FORMAT(DATE(v.fecha_emision), '%Y-%m-%d') AS etiqueta,
                           ROUND(SUM(v.total), 2) AS total_vendido,
                           COUNT(*) AS documentos
                    FROM ventas_documentos v
                    WHERE {$w}
                    GROUP BY DATE(v.fecha_emision)
                    ORDER BY periodo_fecha DESC
                    LIMIT :limite";
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }
}
