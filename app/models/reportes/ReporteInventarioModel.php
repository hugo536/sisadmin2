<?php
declare(strict_types=1);

class ReporteInventarioModel extends Modelo
{
    private function costoUnitarioExpr(string $stockAlias = 's', string $itemAlias = 'i'): string
    {
        return "COALESCE(NULLIF({$stockAlias}.costo_promedio, 0), NULLIF({$itemAlias}.ultimo_costo_compra, 0), NULLIF({$itemAlias}.costo_referencial, 0), 0)";
    }

    public function resumenValorizacionDashboard(int $limiteTop = 8): array
    {
        $limiteTop = max(3, min(20, $limiteTop));
        $costoExpr = $this->costoUnitarioExpr('s', 'i');

        $totalInventario = (float) $this->db()->query(
            'SELECT COALESCE(SUM(
                        CASE
                            WHEN s.stock_actual > 0 THEN s.stock_actual * ' . $costoExpr . '
                            ELSE 0
                        END
                    ), 0)
             FROM inventario_stock s
             INNER JOIN items i ON i.id = s.id_item
             LEFT JOIN almacenes a ON a.id = s.id_almacen
             WHERE s.deleted_at IS NULL
               AND i.deleted_at IS NULL
               AND (a.id IS NULL OR a.deleted_at IS NULL)'
        )->fetchColumn();

        $sqlItems = 'SELECT i.id,
                            i.sku,
                            i.nombre,
                            SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual ELSE 0 END) AS stock_total,
                            ' . $costoExpr . ' AS costo_ref,
                            SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * ' . $costoExpr . ' ELSE 0 END) AS valor_total
                     FROM inventario_stock s
                     INNER JOIN items i ON i.id = s.id_item
                     LEFT JOIN almacenes a ON a.id = s.id_almacen
                     WHERE s.deleted_at IS NULL
                       AND i.deleted_at IS NULL
                       AND (a.id IS NULL OR a.deleted_at IS NULL)
                     GROUP BY i.id, i.sku, i.nombre, i.costo_referencial
                     HAVING valor_total > 0
                     ORDER BY valor_total DESC
                     LIMIT :limite';
        $stmtItems = $this->db()->prepare($sqlItems);
        $stmtItems->bindValue(':limite', $limiteTop, PDO::PARAM_INT);
        $stmtItems->execute();
        $topItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sqlAlmacenes = 'SELECT COALESCE(a.id, 0) AS id_almacen,
                                COALESCE(a.nombre, "Sin Ubicación Física") AS almacen,
                                SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual ELSE 0 END) AS stock_total,
                                SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * ' . $costoExpr . ' ELSE 0 END) AS valor_total
                         FROM inventario_stock s
                         INNER JOIN items i ON i.id = s.id_item
                         LEFT JOIN almacenes a ON a.id = s.id_almacen
                         WHERE s.deleted_at IS NULL
                           AND i.deleted_at IS NULL
                           AND (a.id IS NULL OR a.deleted_at IS NULL)
                         GROUP BY a.id, a.nombre
                         HAVING valor_total > 0
                         ORDER BY valor_total DESC';
        $almacenes = $this->db()->query($sqlAlmacenes)->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $itemsValorizados = (int) $this->db()->query(
            'SELECT COUNT(*)
             FROM (
                SELECT i.id
                FROM inventario_stock s
                INNER JOIN items i ON i.id = s.id_item
                LEFT JOIN almacenes a ON a.id = s.id_almacen
                WHERE s.deleted_at IS NULL
                  AND i.deleted_at IS NULL
                  AND (a.id IS NULL OR a.deleted_at IS NULL)
                GROUP BY i.id
                HAVING SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * ' . $costoExpr . ' ELSE 0 END) > 0
             ) t'
        )->fetchColumn();

        return [
            'total_inventario' => $totalInventario,
            'top_items' => $topItems,
            'almacenes' => $almacenes,
            'items_valorizados' => $itemsValorizados,
            'almacenes_valorizados' => count($almacenes),
        ];
    }

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
        $costoExpr = $this->costoUnitarioExpr('s', 'i');
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
                       ROUND({$costoExpr}, 4) AS costo_unitario,
                       ROUND(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * {$costoExpr} ELSE 0 END, 4) AS valor_total,
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

        $sqlTotales = "SELECT ROUND(SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * {$costoExpr} ELSE 0 END), 4) AS valor_total,
                              COUNT(*) AS registros
                       FROM inventario_stock s
                       INNER JOIN items i ON i.id=s.id_item
                       INNER JOIN almacenes a ON a.id=s.id_almacen
                       WHERE {$whereSql}";
        $stmtTotales = $this->db()->prepare($sqlTotales);
        $stmtTotales->execute($params);
        $totales = $stmtTotales->fetch(PDO::FETCH_ASSOC) ?: ['valor_total' => 0, 'registros' => 0];

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => (int) $count->fetchColumn(),
            'valor_total' => (float) ($totales['valor_total'] ?? 0),
        ];
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
