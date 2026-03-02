<?php
declare(strict_types=1);

class ReporteInventarioModel extends Modelo
{
    public function contarStockCritico(): int
    {
        $sql = 'SELECT COUNT(*)
                FROM inventario_stock s
                INNER JOIN items i ON i.id = s.id_item
                WHERE s.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND s.stock_actual <= i.stock_minimo';
        return (int) $this->db()->query($sql)->fetchColumn();
    }

    public function stockActual(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $where = ['s.deleted_at IS NULL', 'i.deleted_at IS NULL', 'a.deleted_at IS NULL'];
        $params = [];

        if (!empty($f['id_almacen'])) { $where[] = 's.id_almacen = :id_almacen'; $params['id_almacen'] = (int) $f['id_almacen']; }
        if (!empty($f['id_categoria'])) { $where[] = 'i.id_categoria = :id_categoria'; $params['id_categoria'] = (int) $f['id_categoria']; }
        if (!empty($f['tipo_item'])) { $where[] = 'i.tipo_item = :tipo_item'; $params['tipo_item'] = (string) $f['tipo_item']; }
        if ($f['estado'] !== '' && $f['estado'] !== null) { $where[] = 'i.estado = :estado'; $params['estado'] = (int) $f['estado']; }
        if (!empty($f['solo_bajo_minimo'])) { $where[] = 's.stock_actual <= i.stock_minimo'; }

        $whereSql = implode(' AND ', $where);
        $count = $this->db()->prepare("SELECT COUNT(*) FROM inventario_stock s INNER JOIN items i ON i.id=s.id_item INNER JOIN almacenes a ON a.id=s.id_almacen WHERE {$whereSql}");
        $count->execute($params);

        $sql = "SELECT s.id_item, i.nombre AS item, a.nombre AS almacen, s.stock_actual, i.stock_minimo, i.unidad_base AS unidad, i.estado,
                       CASE WHEN s.stock_actual <= i.stock_minimo THEN 'CRITICO' ELSE 'OK' END AS alerta
                FROM inventario_stock s
                INNER JOIN items i ON i.id = s.id_item
                INNER JOIN almacenes a ON a.id = s.id_almacen
                WHERE {$whereSql}
                ORDER BY alerta DESC, i.nombre ASC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function kardex(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $where = ['m.deleted_at IS NULL', 'DATE(m.created_at) BETWEEN :fecha_desde AND :fecha_hasta'];
        $params = ['fecha_desde' => $f['fecha_desde'], 'fecha_hasta' => $f['fecha_hasta']];

        if (!empty($f['id_item'])) { $where[] = 'm.id_item = :id_item'; $params['id_item'] = (int) $f['id_item']; }
        if (!empty($f['id_almacen'])) { $where[] = '(m.id_almacen_origen = :id_almacen OR m.id_almacen_destino = :id_almacen)'; $params['id_almacen'] = (int) $f['id_almacen']; }
        if (!empty($f['tipo_movimiento'])) { $where[] = 'm.tipo_movimiento = :tipo'; $params['tipo'] = (string) $f['tipo_movimiento']; }

        $whereSql = implode(' AND ', $where);
        $count = $this->db()->prepare("SELECT COUNT(*) FROM inventario_movimientos m WHERE {$whereSql}");
        $count->execute($params);

        $sql = "SELECT DATE(m.created_at) AS fecha, m.tipo_movimiento AS tipo, m.cantidad, COALESCE(m.costo_unitario, 0) AS costo_unitario,
                       ROUND(m.cantidad * COALESCE(m.costo_unitario, 0), 4) AS costo_total, m.referencia,
                       COALESCE(u.usuario, 'Sistema') AS usuario
                FROM inventario_movimientos m
                LEFT JOIN usuarios u ON u.id = m.created_by
                WHERE {$whereSql}
                ORDER BY m.created_at DESC, m.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function vencimientos(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $dias = max(1, (int) ($f['dias'] ?? 30));
        $where = ['l.deleted_at IS NULL', 'l.fecha_vencimiento IS NOT NULL', 'l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL :dias DAY)'];
        $params = ['dias' => $dias];
        if (!empty($f['id_item'])) { $where[] = 'l.id_item = :id_item'; $params['id_item'] = (int) $f['id_item']; }
        if (!empty($f['id_almacen'])) { $where[] = 'l.id_almacen = :id_almacen'; $params['id_almacen'] = (int) $f['id_almacen']; }
        $whereSql = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM inventario_lotes l WHERE {$whereSql}");
        $count->execute($params);

        $sql = "SELECT i.nombre AS item, a.nombre AS almacen, l.lote, l.fecha_vencimiento, l.stock_lote,
                       CASE
                         WHEN l.fecha_vencimiento < CURDATE() THEN 'VENCIDO'
                         WHEN l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'PROXIMO'
                         ELSE 'OK'
                       END AS alerta
                FROM inventario_lotes l
                INNER JOIN items i ON i.id = l.id_item
                INNER JOIN almacenes a ON a.id = l.id_almacen
                WHERE {$whereSql}
                ORDER BY l.fecha_vencimiento ASC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, PDO::PARAM_INT); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }
}
