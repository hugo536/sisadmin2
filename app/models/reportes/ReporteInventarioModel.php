<?php
declare(strict_types=1);

class ReporteInventarioModel extends Modelo
{
    private function normalizarListaIds($valor): array
    {
        $lista = is_array($valor) ? $valor : [$valor];
        $ids = array_values(array_unique(array_filter(array_map(static fn($v) => (int) $v, $lista), static fn($v) => $v > 0)));
        return $ids;
    }

    private function aplicarFiltroIds(array &$where, array &$params, string $columnaSql, string $prefijoParam, array $ids): void
    {
        if (empty($ids)) {
            return;
        }
        if (count($ids) === 1) {
            $where[] = "{$columnaSql} = :{$prefijoParam}";
            $params[$prefijoParam] = (int) $ids[0];
            return;
        }

        $inParams = [];
        foreach ($ids as $idx => $id) {
            $paramName = $prefijoParam . '_' . $idx;
            $inParams[] = ':' . $paramName;
            $params[$paramName] = (int) $id;
        }
        $where[] = "{$columnaSql} IN (" . implode(', ', $inParams) . ')';
    }

    public function listarAlmacenesActivos(): array
    {
        $sql = 'SELECT id, nombre
                FROM almacenes
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarCategoriasActivas(): array
    {
        $sql = 'SELECT id, nombre
                FROM categorias
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

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
        
        // Inicializamos arrays para condiciones
        $where = ['s.deleted_at IS NULL', 'i.deleted_at IS NULL', 'a.deleted_at IS NULL'];
        $having = [];
        $params = [];

        // Variable para saber si debemos agrupar o no
        $idsAlmacen = $this->normalizarListaIds($f['id_almacen'] ?? []);
        $idsCategoria = $this->normalizarListaIds($f['id_categoria'] ?? []);
        $isGlobal = empty($idsAlmacen);

        $this->aplicarFiltroIds($where, $params, 's.id_almacen', 'id_almacen', $idsAlmacen);
        $this->aplicarFiltroIds($where, $params, 'i.id_categoria', 'id_categoria', $idsCategoria);
        
        if (!empty($f['tipo_item'])) {
            $tipos = is_array($f['tipo_item']) ? $f['tipo_item'] : [$f['tipo_item']];
            $inParams = [];
            foreach ($tipos as $idx => $tipo) {
                $paramName = 'tipo_item_' . $idx;
                $inParams[] = ':' . $paramName;
                $params[$paramName] = (string) $tipo;
            }
            $where[] = 'i.tipo_item IN (' . implode(', ', $inParams) . ')';
        }

        $situacion = trim((string) ($f['situacion_alerta'] ?? ''));

        // LÓGICA DE AGRUPACIÓN PARA ALERTAS
        // Si es global, las validaciones de stock deben hacerse usando HAVING (después de sumar).
        // Si es por almacén, se hacen con WHERE (por cada fila).
        if ($isGlobal) {
            $stockExpr = "SUM(s.stock_actual)";
            if (!empty($f['solo_bajo_minimo'])) { $having[] = "{$stockExpr} <= i.stock_minimo"; }

            if ($situacion === 'disponible') {
                $having[] = "{$stockExpr} > i.stock_minimo";
            } elseif ($situacion === 'bajo_minimo') {
                $having[] = "{$stockExpr} > 0 AND {$stockExpr} <= i.stock_minimo";
            } elseif ($situacion === 'agotado') {
                $having[] = "{$stockExpr} <= 0";
            }
        } else {
            $stockExpr = "s.stock_actual";
            if (!empty($f['solo_bajo_minimo'])) { $where[] = "s.stock_actual <= i.stock_minimo"; }

            if ($situacion === 'disponible') {
                $where[] = "s.stock_actual > i.stock_minimo";
            } elseif ($situacion === 'bajo_minimo') {
                $where[] = "s.stock_actual > 0 AND s.stock_actual <= i.stock_minimo";
            } elseif ($situacion === 'agotado') {
                $where[] = "s.stock_actual <= 0";
            }
        }

        // Estas alertas sí se evalúan por WHERE porque comprueban existencia de registros subyacentes
        if ($situacion === 'proximo_a_vencer') {
            $where[] = 'EXISTS (
                SELECT 1 FROM inventario_lotes l 
                WHERE l.id_item = s.id_item AND l.id_almacen = s.id_almacen 
                AND l.deleted_at IS NULL AND l.fecha_vencimiento >= CURDATE() AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            )';
        } elseif ($situacion === 'vencido') {
            $where[] = 'EXISTS (
                SELECT 1 FROM inventario_lotes l 
                WHERE l.id_item = s.id_item AND l.id_almacen = s.id_almacen 
                AND l.deleted_at IS NULL AND l.fecha_vencimiento < CURDATE()
            )';
        } elseif ($situacion === 'sin_movimientos') {
            $where[] = 'NOT EXISTS (
                SELECT 1 FROM inventario_movimientos m 
                WHERE m.deleted_at IS NULL AND m.id_item = s.id_item AND (m.id_almacen_origen = s.id_almacen OR m.id_almacen_destino = s.id_almacen)
            )';
        }

        $whereSql = implode(' AND ', $where);
        $havingSql = !empty($having) ? ' HAVING ' . implode(' AND ', $having) : '';

        // 1. OBTENER TOTAL DE REGISTROS (PAGINACIÓN)
        if ($isGlobal) {
            $countSql = "SELECT COUNT(*) FROM (
                SELECT s.id_item FROM inventario_stock s INNER JOIN items i ON i.id=s.id_item INNER JOIN almacenes a ON a.id=s.id_almacen 
                WHERE {$whereSql} GROUP BY s.id_item, i.stock_minimo {$havingSql}
            ) AS t";
        } else {
            $countSql = "SELECT COUNT(*) FROM inventario_stock s INNER JOIN items i ON i.id=s.id_item INNER JOIN almacenes a ON a.id=s.id_almacen WHERE {$whereSql}";
        }
        $count = $this->db()->prepare($countSql);
        $count->execute($params);

        // 2. CONSTRUIR CONSULTA PRINCIPAL
        // Modificamos las columnas de selección dependiendo si agrupamos o no
        $selectAlmacen = $isGlobal ? "'TODOS LOS ALMACENES' AS almacen" : "a.nombre AS almacen";
        $selectStock   = $isGlobal ? "SUM(s.stock_actual) AS stock_actual" : "s.stock_actual";
        $selectCosto   = $isGlobal ? "MAX({$costoExpr})" : $costoExpr;
        $selectValor   = $isGlobal ? "SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * {$costoExpr} ELSE 0 END)" : "CASE WHEN s.stock_actual > 0 THEN s.stock_actual * {$costoExpr} ELSE 0 END";
        $selectAlerta  = $isGlobal ? "CASE WHEN SUM(s.stock_actual) <= i.stock_minimo THEN 'CRITICO' ELSE 'OK' END AS alerta" : "CASE WHEN s.stock_actual <= i.stock_minimo THEN 'CRITICO' ELSE 'OK' END AS alerta";
        
        $groupBySql = $isGlobal ? "GROUP BY s.id_item, i.nombre, i.stock_minimo, i.permite_decimales, i.unidad_base, i.estado" : "";

        $sql = "SELECT s.id_item, i.nombre AS item, {$selectAlmacen}, {$selectStock}, i.stock_minimo, i.permite_decimales, i.unidad_base AS unidad, i.estado,
                       ROUND({$selectCosto}, 4) AS costo_unitario,
                       ROUND({$selectValor}, 4) AS valor_total,
                       {$selectAlerta}
                FROM inventario_stock s
                INNER JOIN items i ON i.id = s.id_item
                INNER JOIN almacenes a ON a.id = s.id_almacen
                WHERE {$whereSql}
                {$groupBySql}
                {$havingSql}
                ORDER BY alerta DESC, i.nombre ASC
                LIMIT :limite OFFSET :offset";
                
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        // 3. CALCULAR TOTALES MONETARIOS DEL REPORTE
        if ($isGlobal) {
            $sqlTotales = "SELECT SUM(valor_total) AS valor_total, COUNT(*) AS registros FROM (
                SELECT SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * {$costoExpr} ELSE 0 END) AS valor_total 
                FROM inventario_stock s INNER JOIN items i ON i.id=s.id_item INNER JOIN almacenes a ON a.id=s.id_almacen 
                WHERE {$whereSql} GROUP BY s.id_item, i.stock_minimo {$havingSql}
            ) AS t";
        } else {
            $sqlTotales = "SELECT ROUND(SUM(CASE WHEN s.stock_actual > 0 THEN s.stock_actual * {$costoExpr} ELSE 0 END), 4) AS valor_total, COUNT(*) AS registros
                           FROM inventario_stock s INNER JOIN items i ON i.id=s.id_item INNER JOIN almacenes a ON a.id=s.id_almacen 
                           WHERE {$whereSql}";
        }
        $stmtTotales = $this->db()->prepare($sqlTotales);
        $stmtTotales->execute($params);
        $totales = $stmtTotales->fetch(PDO::FETCH_ASSOC) ?: ['valor_total' => 0, 'registros' => 0];

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'total' => (int) $count->fetchColumn(),
            'valor_total' => (float) ($totales['valor_total'] ?? 0),
        ];
    }

    public function stockAFecha(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $costoExpr = $this->costoUnitarioExpr('s', 'i');
        
        // 1. Capturar Fecha y Hora de corte (Si solo envían fecha, asumimos el final del día)
        $fechaCorte = trim((string) ($f['fecha_corte'] ?? date('Y-m-d H:i:s')));
        if (strlen($fechaCorte) === 10) {
            $fechaCorte .= ' 23:59:59';
        }

        $whereFiltros = ['s.deleted_at IS NULL', 'i.deleted_at IS NULL', 'a.deleted_at IS NULL'];
        
        // SOLUCIÓN: Creamos 4 parámetros idénticos para satisfacer las reglas de PDO
        $params = [
            'fecha_corte1' => $fechaCorte,
            'fecha_corte2' => $fechaCorte,
            'fecha_corte3' => $fechaCorte,
            'fecha_corte4' => $fechaCorte
        ];

        $idsAlmacen = $this->normalizarListaIds($f['id_almacen'] ?? []);
        $idsCategoria = $this->normalizarListaIds($f['id_categoria'] ?? []);
        $isGlobal = empty($idsAlmacen);

        $this->aplicarFiltroIds($whereFiltros, $params, 's.id_almacen', 'id_almacen', $idsAlmacen);
        $this->aplicarFiltroIds($whereFiltros, $params, 'i.id_categoria', 'id_categoria', $idsCategoria);
        if (!empty($f['tipo_item'])) {
            $tipos = is_array($f['tipo_item']) ? $f['tipo_item'] : [$f['tipo_item']];
            $inParams = [];
            foreach ($tipos as $idx => $tipo) {
                $paramName = ':tipo_item_' . $idx;
                $inParams[] = $paramName;
                $params[$paramName] = (string) $tipo;
            }
            $whereFiltros[] = 'i.tipo_item IN (' . implode(', ', $inParams) . ')';
        }

        $whereSql = implode(' AND ', $whereFiltros);
        
        $selectAlmacen = $isGlobal ? "'TODOS LOS ALMACENES'" : "calc.almacen";
        $groupBy = $isGlobal ? "calc.id_item, calc.item, calc.unidad, calc.costo_unitario" : "calc.id_item, calc.item, calc.almacen, calc.unidad, calc.costo_unitario";

        // Inyectamos las 4 variables independientes
        $coreSubquery = "
            FROM (
                SELECT 
                    base.id_item, base.item, base.almacen, base.unidad, base.costo_unitario,
                    (
                        base.stock_base
                        + COALESCE((SELECT SUM(m.cantidad) FROM inventario_movimientos m WHERE m.id_item = base.id_item AND m.id_almacen_destino = base.id_almacen AND m.created_at > base.fecha_base AND m.created_at <= :fecha_corte1 AND m.deleted_at IS NULL), 0)
                        - COALESCE((SELECT SUM(m.cantidad) FROM inventario_movimientos m WHERE m.id_item = base.id_item AND m.id_almacen_origen = base.id_almacen AND m.created_at > base.fecha_base AND m.created_at <= :fecha_corte2 AND m.deleted_at IS NULL), 0)
                    ) AS stock_calculado
                FROM (
                    SELECT 
                        s.id_item, s.id_almacen, i.nombre AS item, a.nombre AS almacen, i.unidad_base AS unidad,
                        ROUND({$costoExpr}, 4) AS costo_unitario,
                        COALESCE((SELECT sh.stock_cierre FROM inventario_stock_historico sh WHERE sh.id_item = s.id_item AND sh.id_almacen = s.id_almacen AND sh.created_at <= :fecha_corte3 ORDER BY sh.created_at DESC LIMIT 1), 0) AS stock_base,
                        COALESCE((SELECT sh.created_at FROM inventario_stock_historico sh WHERE sh.id_item = s.id_item AND sh.id_almacen = s.id_almacen AND sh.created_at <= :fecha_corte4 ORDER BY sh.created_at DESC LIMIT 1), '1970-01-01 00:00:00') AS fecha_base
                    FROM inventario_stock s
                    INNER JOIN items i ON i.id = s.id_item
                    INNER JOIN almacenes a ON a.id = s.id_almacen
                    WHERE {$whereSql}
                ) AS base
            ) AS calc
        ";

        // 2. Consulta para obtener el Total de filas (Paginación)
        $countQuery = "SELECT COUNT(*) FROM (SELECT calc.id_item " . $coreSubquery . " GROUP BY {$groupBy} HAVING SUM(calc.stock_calculado) <> 0) AS t";
        $stmtCount = $this->db()->prepare($countQuery);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // 3. Consulta Principal (Los datos reales para la tabla y el PDF)
        $sql = "SELECT 
                    calc.id_item, 
                    calc.item, 
                    {$selectAlmacen} AS almacen, 
                    calc.unidad, 
                    calc.costo_unitario, 
                    SUM(calc.stock_calculado) AS stock_actual, 
                    ROUND(SUM(calc.stock_calculado) * calc.costo_unitario, 4) AS valor_total 
                " . $coreSubquery . " 
                GROUP BY {$groupBy} 
                HAVING SUM(calc.stock_calculado) <> 0 
                ORDER BY calc.item ASC 
                LIMIT :limite OFFSET :offset";
        
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 4. Consulta para sumar todo el dinero del inventario a esa fecha
        $sqlTotales = "SELECT ROUND(SUM(t.valor_total), 4) AS valor_total FROM (SELECT ROUND(SUM(calc.stock_calculado) * calc.costo_unitario, 4) AS valor_total " . $coreSubquery . " GROUP BY {$groupBy} HAVING SUM(calc.stock_calculado) <> 0) AS t";
        $stmtTotales = $this->db()->prepare($sqlTotales);
        $stmtTotales->execute($params);
        $valorTotal = (float) $stmtTotales->fetchColumn();

        return [
            'rows' => $rows,
            'total' => $total,
            'valor_total' => $valorTotal,
        ];
    }

    public function kardex(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $where = ['m.deleted_at IS NULL', 'DATE(m.created_at) BETWEEN :fecha_desde AND :fecha_hasta'];
        $params = ['fecha_desde' => $f['fecha_desde'], 'fecha_hasta' => $f['fecha_hasta']];

        $idsCategoria = $this->normalizarListaIds($f['id_categoria'] ?? []);
        $idsAlmacen = $this->normalizarListaIds($f['id_almacen'] ?? []);
        $this->aplicarFiltroIds($where, $params, 'i.id_categoria', 'id_categoria', $idsCategoria);
        if (!empty($f['tipo_item'])) {
            $tipos = is_array($f['tipo_item']) ? $f['tipo_item'] : [$f['tipo_item']];
            $inParams = [];
            foreach ($tipos as $idx => $tipo) {
                $paramName = 'tipo_item_' . $idx;
                $inParams[] = ':' . $paramName;
                $params[$paramName] = (string) $tipo;
            }
            $where[] = 'i.tipo_item IN (' . implode(', ', $inParams) . ')';
        }
        if (!empty($f['id_item'])) { $where[] = 'm.id_item = :id_item'; $params['id_item'] = (int) $f['id_item']; }
        if (!empty($idsAlmacen)) {
            $inOrigen = [];
            $inDestino = [];
            foreach ($idsAlmacen as $idx => $idAlmacen) {
                $paramOrigen = 'id_almacen_origen_' . $idx;
                $paramDestino = 'id_almacen_destino_' . $idx;
                $inOrigen[] = ':' . $paramOrigen;
                $inDestino[] = ':' . $paramDestino;
                $params[$paramOrigen] = (int) $idAlmacen;
                $params[$paramDestino] = (int) $idAlmacen;
            }
            $where[] = '(m.id_almacen_origen IN (' . implode(', ', $inOrigen) . ') OR m.id_almacen_destino IN (' . implode(', ', $inDestino) . '))';
        }
        if (!empty($f['tipo_movimiento'])) { $where[] = 'm.tipo_movimiento = :tipo'; $params['tipo'] = (string) $f['tipo_movimiento']; }

        $whereSql = implode(' AND ', $where);
        $count = $this->db()->prepare("SELECT COUNT(*) FROM inventario_movimientos m INNER JOIN items i ON i.id = m.id_item WHERE {$whereSql}");
        $count->execute($params);

        $sql = "SELECT DATE(m.created_at) AS fecha, m.tipo_movimiento AS tipo, m.cantidad, COALESCE(m.costo_unitario, 0) AS costo_unitario,
                       ROUND(m.cantidad * COALESCE(m.costo_unitario, 0), 4) AS costo_total, m.referencia,
                       COALESCE(u.usuario, 'Sistema') AS usuario
                FROM inventario_movimientos m
                INNER JOIN items i ON i.id = m.id_item
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
        $idsItem = $this->normalizarListaIds($f['id_item'] ?? []);
        $idsAlmacen = $this->normalizarListaIds($f['id_almacen'] ?? []);
        $idsCategoria = $this->normalizarListaIds($f['id_categoria'] ?? []);
        $this->aplicarFiltroIds($where, $params, 'l.id_item', 'id_item', $idsItem);
        $this->aplicarFiltroIds($where, $params, 'l.id_almacen', 'id_almacen', $idsAlmacen);
        $this->aplicarFiltroIds($where, $params, 'i.id_categoria', 'id_categoria', $idsCategoria);
        if (!empty($f['tipo_item'])) {
            $tipos = is_array($f['tipo_item']) ? $f['tipo_item'] : [$f['tipo_item']];
            $inParams = [];
            foreach ($tipos as $idx => $tipo) {
                $paramName = 'tipo_item_' . $idx;
                $inParams[] = ':' . $paramName;
                $params[$paramName] = (string) $tipo;
            }
            $where[] = 'i.tipo_item IN (' . implode(', ', $inParams) . ')';
        }

        $situacion = trim((string) ($f['situacion_alerta'] ?? ''));
        if ($situacion === 'vencido') {
            $where[] = 'l.fecha_vencimiento < CURDATE()';
        } elseif ($situacion === 'proximo_a_vencer') {
            $where[] = 'l.fecha_vencimiento >= CURDATE() AND l.fecha_vencimiento <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
        } elseif ($situacion === 'agotado') {
            $where[] = 'l.stock_lote <= 0';
        }

        $whereSql = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM inventario_lotes l INNER JOIN items i ON i.id = l.id_item WHERE {$whereSql}");
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
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }
}
