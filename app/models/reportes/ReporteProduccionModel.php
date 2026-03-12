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
                       ROUND(COALESCE(SUM(pi.cantidad * pi.costo_unitario_calculado) / NULLIF(SUM(pi.cantidad), 0), 0),4) AS costo_unitario_promedio,
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
    public function costosPorOrden(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];

        $count = $this->db()->prepare('SELECT COUNT(*)
                                      FROM produccion_ordenes o
                                      WHERE o.deleted_at IS NULL
                                        AND o.estado = 2
                                        AND DATE(COALESCE(o.fecha_fin, o.updated_at, o.created_at)) BETWEEN :fd AND :fh');
        $count->execute($params);

        $sql = "SELECT o.id, o.codigo, o.fecha_programada, o.estado,
                       i.nombre AS producto,
                       o.cantidad_planificada, o.cantidad_producida,
                       COALESCE(o.costo_teorico_unitario_snapshot, 0) AS costo_teorico_unitario_snapshot,
                       COALESCE(o.costo_teorico_total_snapshot, 0) AS costo_teorico_total_snapshot,
                       COALESCE(o.costo_real_unitario, o.costo_unitario_real, 0) AS costo_real_unitario,
                       COALESCE(o.costo_real_total, 0) AS costo_real_total,
                       (COALESCE(r.costo_md_teorico, 0) * COALESCE(o.cantidad_planificada, 0)) AS md_teorico_total,
                       (COALESCE(r.costo_mod_teorico, 0) * COALESCE(o.cantidad_planificada, 0)) AS mod_teorico_total,
                       (COALESCE(r.costo_cif_teorico, 0) * COALESCE(o.cantidad_planificada, 0)) AS cif_teorico_total,
                       COALESCE(o.total_md_real, o.costo_md_real, 0) AS md_real_total,
                       COALESCE(o.total_mod_real, o.costo_mod_real, 0) AS mod_real_total,
                       COALESCE(o.total_cif_real, o.costo_cif_real, 0) AS cif_real_total,
                       ROUND(COALESCE(o.costo_real_total, 0) - COALESCE(o.costo_teorico_total_snapshot, 0), 4) AS variacion_total,
                       ROUND(
                           CASE
                               WHEN COALESCE(o.costo_teorico_total_snapshot, 0) <= 0 THEN 0
                               ELSE ((COALESCE(o.costo_real_total, 0) - o.costo_teorico_total_snapshot) / o.costo_teorico_total_snapshot) * 100
                           END
                       , 2) AS variacion_pct
                FROM produccion_ordenes o
                LEFT JOIN items i ON i.id = o.id_producto_snapshot
                LEFT JOIN produccion_recetas r ON r.id = o.id_receta
                WHERE o.deleted_at IS NULL
                  AND o.estado = 2
                  AND DATE(COALESCE(o.fecha_fin, o.updated_at, o.created_at)) BETWEEN :fd AND :fh
                ORDER BY o.id DESC
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
