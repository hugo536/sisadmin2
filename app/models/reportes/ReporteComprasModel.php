<?php
declare(strict_types=1);

class ReporteComprasModel extends Modelo
{
    /**
     * Función auxiliar para filtrar compras por categoría o ítem específico
     * de manera optimizada usando EXISTS.
     */
    private function aplicarFiltroItemCompras(array &$where, array &$params, array $f, string $alias = 'o'): void
    {
        $condiciones = ["od.id_orden = {$alias}.id", "od.deleted_at IS NULL"];

        if (!empty($f['id_categoria'])) {
            $condiciones[] = 'i.id_categoria = :id_categoria';
            $params['id_categoria'] = (int) $f['id_categoria'];
        }

        if (!empty($f['id_item'])) {
            $condiciones[] = 'od.id_item = :id_item';
            $params['id_item'] = (int) $f['id_item'];
        }

        // Si hay filtros aplicados (más de las 2 condiciones base), inyectamos la subconsulta
        if (count($condiciones) > 2) {
            $wDetalle = implode(' AND ', $condiciones);
            $where[] = "EXISTS (
                SELECT 1 
                FROM compras_ordenes_detalle od 
                INNER JOIN items i ON i.id = od.id_item 
                WHERE {$wDetalle}
            )";
        }
    }

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

    // =========================================================================
    // NUEVOS MÉTODOS AÑADIDOS PARA HOMOLOGAR CON VENTAS
    // =========================================================================

    public function comprasPorPeriodo(array $f, string $agrupacion = 'diaria', int $limite = 12): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ['o.deleted_at IS NULL', 'DATE(o.fecha_emision) BETWEEN :fd AND :fh'];

        if (!empty($f['id_proveedor'])) {
            $where[] = 'o.id_proveedor = :id_proveedor';
            $params['id_proveedor'] = (int) $f['id_proveedor'];
        }

        // Aplicamos filtro inteligente de ítem/categoría si existe
        $this->aplicarFiltroItemCompras($where, $params, $f, 'o');

        $w = implode(' AND ', $where);

        if ($agrupacion === 'semanal') {
            $sql = "SELECT YEAR(o.fecha_emision) AS periodo_anio,
                           WEEK(o.fecha_emision, 1) AS periodo_semana,
                           CONCAT(
                               'S', LPAD(WEEK(MIN(o.fecha_emision), 1), 2, '0'), '-', YEAR(MIN(o.fecha_emision)),
                               ' (', DATE_FORMAT(DATE_ADD(MIN(o.fecha_emision), INTERVAL -WEEKDAY(MIN(o.fecha_emision)) DAY), '%d/%m'),
                               ' al ', DATE_FORMAT(DATE_ADD(MIN(o.fecha_emision), INTERVAL 6 - WEEKDAY(MIN(o.fecha_emision)) DAY), '%d/%m'), ')'
                           ) AS etiqueta,
                           ROUND(SUM(o.total), 2) AS total_comprado,
                           COUNT(*) AS documentos
                    FROM compras_ordenes o
                    WHERE {$w}
                    GROUP BY YEAR(o.fecha_emision), WEEK(o.fecha_emision, 1)
                    ORDER BY periodo_anio DESC, periodo_semana DESC
                    LIMIT :limite";
        } elseif ($agrupacion === 'mensual') {
            $sql = "SELECT DATE_FORMAT(o.fecha_emision, '%Y-%m') AS periodo_mes,
                           DATE_FORMAT(o.fecha_emision, '%m-%Y') AS etiqueta,
                           ROUND(SUM(o.total), 2) AS total_comprado,
                           COUNT(*) AS documentos
                    FROM compras_ordenes o
                    WHERE {$w}
                    GROUP BY DATE_FORMAT(o.fecha_emision, '%Y-%m')
                    ORDER BY periodo_mes DESC
                    LIMIT :limite";
        } else {
            $sql = "SELECT DATE(o.fecha_emision) AS periodo_fecha,
                           DATE_FORMAT(DATE(o.fecha_emision), '%Y-%m-%d') AS etiqueta,
                           ROUND(SUM(o.total), 2) AS total_comprado,
                           COUNT(*) AS documentos
                    FROM compras_ordenes o
                    WHERE {$w}
                    GROUP BY DATE(o.fecha_emision)
                    ORDER BY periodo_fecha DESC
                    LIMIT :limite";
        }

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function topInsumos(array $f, int $limite = 10): array
    {
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ['o.deleted_at IS NULL', 'od.deleted_at IS NULL', 'DATE(o.fecha_emision) BETWEEN :fd AND :fh'];

        if (!empty($f['id_categoria'])) { 
            $where[] = 'i.id_categoria = :id_categoria'; 
            $params['id_categoria'] = (int) $f['id_categoria']; 
        }
        if (!empty($f['id_proveedor'])) { 
            $where[] = 'o.id_proveedor = :id_proveedor'; 
            $params['id_proveedor'] = (int) $f['id_proveedor']; 
        }
        if (!empty($f['id_item'])) { 
            $where[] = 'od.id_item = :id_item'; 
            $params['id_item'] = (int) $f['id_item']; 
        }

        $w = implode(' AND ', $where);

        $sql = "SELECT i.nombre AS producto,
                       ROUND(SUM(od.cantidad_solicitada),2) AS total_cantidad,
                       ROUND(SUM(od.cantidad_solicitada * od.costo_unitario_pactado),2) AS total_monto
                FROM compras_ordenes o
                INNER JOIN compras_ordenes_detalle od ON od.id_orden = o.id
                INNER JOIN items i ON i.id = od.id_item
                WHERE {$w}
                GROUP BY od.id_item, i.nombre
                ORDER BY total_monto DESC
                LIMIT :limite";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function variacionCostos(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $where = ['r.deleted_at IS NULL', 'rd.deleted_at IS NULL', 'DATE(r.fecha_recepcion) BETWEEN :fd AND :fh'];

        if (!empty($f['id_categoria'])) { 
            $where[] = 'i.id_categoria = :id_categoria'; 
            $params['id_categoria'] = (int) $f['id_categoria']; 
        }
        if (!empty($f['id_item'])) { 
            $where[] = 'rd.id_item = :id_item'; 
            $params['id_item'] = (int) $f['id_item']; 
        }

        $w = implode(' AND ', $where);

        $countSql = "SELECT COUNT(DISTINCT rd.id_item)
                     FROM compras_recepciones r
                     INNER JOIN compras_recepciones_detalle rd ON rd.id_recepcion = r.id
                     INNER JOIN items i ON i.id = rd.id_item
                     WHERE {$w}";
        $c = $this->db()->prepare($countSql); $c->execute($params);

        // Aproximación de variación: Usamos el mínimo costo y el máximo costo del periodo
        // para encontrar fluctuaciones (inflación/deflación de precios) dentro del rango buscado.
        $sql = "SELECT i.nombre AS producto,
                       ROUND(MIN(rd.costo_unitario_real), 4) AS costo_anterior,
                       ROUND(MAX(rd.costo_unitario_real), 4) AS costo_actual
                FROM compras_recepciones r
                INNER JOIN compras_recepciones_detalle rd ON rd.id_recepcion = r.id
                INNER JOIN items i ON i.id = rd.id_item
                WHERE {$w}
                GROUP BY rd.id_item, i.nombre
                HAVING costo_anterior > 0
                ORDER BY (MAX(rd.costo_unitario_real) - MIN(rd.costo_unitario_real)) DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $c->fetchColumn()];
    }

    /**
     * Busca ítems filtrando los que son de producción propia (Terminados y Semielaborados)
     */
    public function buscarInsumosAjax(string $q, int $idCategoria = 0, int $limite = 40): array
    {
        $params = [];
        
        // Corregido: usamos los valores exactos del ENUM de tu base de datos
        $where = ["deleted_at IS NULL", "tipo_item NOT IN ('producto_terminado', 'semielaborado')"];

        if ($q !== '') {
            // Corregido: la columna se llama 'sku', no 'codigo_sku'
            $where[] = "(nombre LIKE :q OR sku LIKE :q)";
            $params['q'] = "%{$q}%";
        }

        if ($idCategoria > 0) {
            $where[] = "id_categoria = :id_categoria";
            $params['id_categoria'] = $idCategoria;
        }

        $w = implode(' AND ', $where);
        
        // Corregido: Seleccionamos 'sku'
        $sql = "SELECT id, nombre, sku, tipo_item 
                FROM items 
                WHERE {$w} 
                ORDER BY nombre ASC 
                LIMIT :limite";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}