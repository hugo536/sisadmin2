<?php
declare(strict_types=1);

class ReporteProduccionModel extends Modelo
{
    public function contarEnProceso(): int
    {
        $sql = "SELECT COUNT(*) FROM produccion_ordenes WHERE deleted_at IS NULL AND estado IN (0,1)";
        return (int) $this->db()->query($sql)->fetchColumn();
    }

    public function produccionPorProducto(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ['pi.deleted_at IS NULL', 'DATE(pi.created_at) BETWEEN :fd AND :fh'];
        if (!empty($f['id_item'])) { $where[] = 'pi.id_item = :id_item'; $params['id_item'] = (int) $f['id_item']; }
        $w = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(DISTINCT pi.id_item) FROM produccion_ingresos pi WHERE {$w}");
        $count->execute($params);

        $sql = "SELECT i.nombre AS producto,
                       ROUND(SUM(pi.cantidad),2) AS cantidad_producida,
                       ROUND(AVG(pi.costo_unitario_calculado),4) AS costo_unitario_promedio,
                       MIN(DATE(pi.created_at)) AS primer_registro,
                       MAX(DATE(pi.created_at)) AS ultimo_registro
                FROM produccion_ingresos pi
                INNER JOIN items i ON i.id = pi.id_item
                WHERE {$w}
                GROUP BY pi.id_item, i.nombre
                ORDER BY cantidad_producida DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function consumoInsumos(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];

        $count = $this->db()->prepare('SELECT COUNT(DISTINCT pc.id_item) FROM produccion_consumos pc WHERE pc.deleted_at IS NULL AND DATE(pc.created_at) BETWEEN :fd AND :fh');
        $count->execute($params);

        $sql = "SELECT i.nombre AS insumo,
                       ROUND(SUM(pc.cantidad),2) AS cantidad_consumida,
                       ROUND(SUM(pc.cantidad * pc.costo_unitario),2) AS costo_total
                FROM produccion_consumos pc
                INNER JOIN items i ON i.id = pc.id_item
                WHERE pc.deleted_at IS NULL
                  AND DATE(pc.created_at) BETWEEN :fd AND :fh
                GROUP BY pc.id_item, i.nombre
                ORDER BY cantidad_consumida DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':fd', $params['fd']);
        $stmt->bindValue(':fh', $params['fh']);
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }
}
