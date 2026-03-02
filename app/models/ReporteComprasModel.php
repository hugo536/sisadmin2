<?php
declare(strict_types=1);

class ReporteComprasModel extends Modelo
{
    public function contarPendientes(): int
    {
        $sql = "SELECT COUNT(*) FROM compras_ordenes WHERE deleted_at IS NULL AND estado IN (1,2)";
        return (int) $this->db()->query($sql)->fetchColumn();
    }

    public function comprasPorProveedor(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ['r.deleted_at IS NULL', 'DATE(r.fecha_recepcion) BETWEEN :fd AND :fh'];
        if (!empty($f['id_proveedor'])) { $where[] = 'o.id_proveedor = :id_proveedor'; $params['id_proveedor'] = (int) $f['id_proveedor']; }
        if (!empty($f['id_almacen'])) { $where[] = 'r.id_almacen = :id_almacen'; $params['id_almacen'] = (int) $f['id_almacen']; }
        $w = implode(' AND ', $where);

        $countSql = "SELECT COUNT(DISTINCT o.id_proveedor) FROM compras_recepciones r INNER JOIN compras_ordenes o ON o.id=r.id_orden_compra WHERE {$w}";
        $c = $this->db()->prepare($countSql); $c->execute($params);

        $sql = "SELECT t.nombre_completo AS proveedor,
                       COUNT(DISTINCT r.id) AS recepciones,
                       ROUND(SUM(rd.cantidad_recibida * rd.costo_unitario_real), 2) AS total_recibido,
                       ROUND(AVG(rd.costo_unitario_real), 4) AS costo_promedio_item
                FROM compras_recepciones r
                INNER JOIN compras_ordenes o ON o.id = r.id_orden_compra AND o.deleted_at IS NULL
                INNER JOIN terceros t ON t.id = o.id_proveedor AND t.deleted_at IS NULL
                INNER JOIN compras_recepciones_detalle rd ON rd.id_recepcion = r.id AND rd.deleted_at IS NULL
                WHERE {$w}
                GROUP BY o.id_proveedor, t.nombre_completo
                ORDER BY total_recibido DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $c->fetchColumn()];
    }

    public function ocCumplimiento(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ['o.deleted_at IS NULL', 'DATE(o.fecha_emision) BETWEEN :fd AND :fh'];
        if (!empty($f['id_proveedor'])) { $where[] = 'o.id_proveedor = :id_proveedor'; $params['id_proveedor'] = (int) $f['id_proveedor']; }
        $w = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM compras_ordenes o WHERE {$w}");
        $count->execute($params);

        $sql = "SELECT o.codigo,
                       t.nombre_completo AS proveedor,
                       ROUND(COALESCE(SUM(od.cantidad_solicitada),0),2) AS solicitado,
                       ROUND(COALESCE(SUM(od.cantidad_recibida),0),2) AS recibido,
                       ROUND(CASE WHEN SUM(od.cantidad_solicitada) > 0 THEN (SUM(od.cantidad_recibida) / SUM(od.cantidad_solicitada)) * 100 ELSE 0 END,2) AS pct_cumplimiento,
                       CASE WHEN o.fecha_entrega_estimada IS NOT NULL AND DATE(o.fecha_entrega_estimada) < CURDATE() AND o.estado IN (1,2) THEN 1 ELSE 0 END AS retrasada
                FROM compras_ordenes o
                INNER JOIN terceros t ON t.id = o.id_proveedor
                LEFT JOIN compras_ordenes_detalle od ON od.id_orden = o.id AND od.deleted_at IS NULL
                WHERE {$w}
                GROUP BY o.id, o.codigo, t.nombre_completo, o.fecha_entrega_estimada, o.estado
                ORDER BY o.id DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }
}
