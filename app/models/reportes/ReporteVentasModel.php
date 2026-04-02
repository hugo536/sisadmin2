<?php
declare(strict_types=1);

class ReporteVentasModel extends Modelo
{
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
        if ($f['estado'] !== '' && $f['estado'] !== null) { $where[] = 'v.estado = :estado'; $params['estado'] = (int) $f['estado']; }
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
        if ($f['estado'] !== '' && $f['estado'] !== null) { $where[] = 'v.estado = :estado'; $params['estado'] = (int) $f['estado']; }
        $w = implode(' AND ', $where);

        $sql = "SELECT i.nombre AS producto,
                       ROUND(SUM(d.cantidad),2) AS total_cantidad,
                       ROUND(SUM(d.subtotal),2) AS total_monto
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
        $w = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM ventas_documentos v WHERE {$w}");
        $count->execute($params);

        $sql = "SELECT v.codigo AS documento, t.nombre_completo AS cliente,
                       ROUND(COALESCE(SUM(d.cantidad - d.cantidad_despachada),0),2) AS saldo_despachar,
                       a.nombre AS almacen,
                       DATEDIFF(CURDATE(), DATE(v.fecha_emision)) AS dias_desde_emision
                FROM ventas_documentos v
                INNER JOIN terceros t ON t.id = v.id_cliente
                INNER JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id AND d.deleted_at IS NULL
                LEFT JOIN almacenes a ON a.id = v.id_almacen
                WHERE {$w}
                GROUP BY v.id, v.codigo, t.nombre_completo, a.nombre, v.fecha_emision
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
}