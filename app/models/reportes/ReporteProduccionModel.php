<?php
declare(strict_types=1);

class ReporteProduccionModel extends Modelo
{
    private function centroPorKeywords(string $keywordsSql): string
    {
        return "SELECT id FROM conta_centros_costo WHERE deleted_at IS NULL AND estado = 1 AND ({$keywordsSql})";
    }

    public function resumenGerencialMensual(array $f): array
    {
        $fd = (string) ($f['fecha_desde'] ?? date('Y-m-01'));
        $fh = (string) ($f['fecha_hasta'] ?? date('Y-m-d'));
        $params = ['fd' => $fd, 'fh' => $fh];

        $sqlMp = "SELECT COALESCE(SUM(COALESCE(m.costo_total, m.cantidad * COALESCE(m.costo_unitario, 0))), 0)
                  FROM inventario_movimientos m
                  INNER JOIN items i ON i.id = m.id_item
                  WHERE m.deleted_at IS NULL
                    AND DATE(m.created_at) BETWEEN :fd AND :fh
                    AND m.tipo_movimiento = 'CON'
                    AND i.tipo_item = 'materia_prima'
                    AND m.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%PROD%' OR UPPER(nombre) LIKE '%PRODUCCION%' OR UPPER(nombre) LIKE '%PLANTA%'") . ')';

        $sqlCifInv = "SELECT COALESCE(SUM(COALESCE(m.costo_total, m.cantidad * COALESCE(m.costo_unitario, 0))), 0)
                      FROM inventario_movimientos m
                      LEFT JOIN items i ON i.id = m.id_item
                      WHERE m.deleted_at IS NULL
                        AND DATE(m.created_at) BETWEEN :fd AND :fh
                        AND m.tipo_movimiento = 'CON'
                        AND (i.tipo_item IS NULL OR i.tipo_item <> 'materia_prima')
                        AND m.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%PROD%' OR UPPER(nombre) LIKE '%PRODUCCION%' OR UPPER(nombre) LIKE '%PLANTA%'") . ')';

        $sqlMod = "SELECT COALESCE(SUM(COALESCE(o.total_mod_real, o.costo_mod_real, 0)), 0)
                   FROM produccion_ordenes o
                   WHERE o.deleted_at IS NULL
                     AND o.estado = 2
                     AND DATE(COALESCE(o.fecha_fin, o.updated_at, o.created_at)) BETWEEN :fd AND :fh";

        $sqlCifGastos = "SELECT COALESCE(SUM(gr.total), 0)
                         FROM gastos_registros gr
                         WHERE gr.deleted_at IS NULL
                           AND gr.estado <> 'ANULADO'
                           AND gr.fecha BETWEEN :fd AND :fh
                           AND gr.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%PROD%' OR UPPER(nombre) LIKE '%PRODUCCION%' OR UPPER(nombre) LIKE '%PLANTA%'") . ')';

        $sqlDep = "SELECT COALESCE(SUM(d.monto), 0)
                   FROM conta_depreciaciones d
                   INNER JOIN activos_fijos a ON a.id = d.id_activo_fijo
                   WHERE d.deleted_at IS NULL
                     AND a.deleted_at IS NULL
                     AND CONCAT(d.periodo, '-01') BETWEEN DATE_FORMAT(:fd, '%Y-%m-01') AND DATE_FORMAT(:fh, '%Y-%m-01')";

        $sqlDepProd = $sqlDep . " AND a.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%PROD%' OR UPPER(nombre) LIKE '%PRODUCCION%' OR UPPER(nombre) LIKE '%PLANTA%'") . ')';
        $sqlDepAdm = $sqlDep . " AND a.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%ADM%' OR UPPER(nombre) LIKE '%ADMIN%'") . ')';
        $sqlDepVentas = $sqlDep . " AND a.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%VTA%' OR UPPER(codigo) LIKE '%VEN%' OR UPPER(nombre) LIKE '%VENTA%'") . ')';

        $sqlVentas = "SELECT COALESCE(SUM(v.total), 0)
                      FROM ventas_documentos v
                      WHERE v.deleted_at IS NULL
                        AND v.estado <> 'ANULADO'
                        AND v.fecha_emision BETWEEN :fd AND :fh";

        $sqlCogs = "SELECT COALESCE(SUM(COALESCE(m.costo_total, m.cantidad * COALESCE(m.costo_unitario, 0))), 0)
                    FROM inventario_movimientos m
                    WHERE m.deleted_at IS NULL
                      AND DATE(m.created_at) BETWEEN :fd AND :fh
                      AND m.tipo_movimiento = 'VEN'";

        $sqlGastoServAdm = "SELECT COALESCE(SUM(gr.total), 0)
                            FROM gastos_registros gr
                            WHERE gr.deleted_at IS NULL
                              AND gr.estado <> 'ANULADO'
                              AND gr.fecha BETWEEN :fd AND :fh
                              AND gr.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%ADM%' OR UPPER(nombre) LIKE '%ADMIN%'") . ')';

        $sqlGastoServVentas = "SELECT COALESCE(SUM(gr.total), 0)
                               FROM gastos_registros gr
                               WHERE gr.deleted_at IS NULL
                                 AND gr.estado <> 'ANULADO'
                                 AND gr.fecha BETWEEN :fd AND :fh
                                 AND gr.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%VTA%' OR UPPER(codigo) LIKE '%VEN%' OR UPPER(nombre) LIKE '%VENTA%'") . ')';

        $sqlInvAdm = "SELECT COALESCE(SUM(COALESCE(m.costo_total, m.cantidad * COALESCE(m.costo_unitario, 0))), 0)
                      FROM inventario_movimientos m
                      WHERE m.deleted_at IS NULL
                        AND DATE(m.created_at) BETWEEN :fd AND :fh
                        AND m.tipo_movimiento = 'CON'
                        AND m.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%ADM%' OR UPPER(nombre) LIKE '%ADMIN%'") . ')';

        $sqlInvVentas = "SELECT COALESCE(SUM(COALESCE(m.costo_total, m.cantidad * COALESCE(m.costo_unitario, 0))), 0)
                         FROM inventario_movimientos m
                         WHERE m.deleted_at IS NULL
                           AND DATE(m.created_at) BETWEEN :fd AND :fh
                           AND m.tipo_movimiento = 'CON'
                           AND m.id_centro_costo IN (" . $this->centroPorKeywords("UPPER(codigo) LIKE '%VTA%' OR UPPER(codigo) LIKE '%VEN%' OR UPPER(nombre) LIKE '%VENTA%'") . ')';

        $sqlFin = "SELECT COALESCE(SUM(CASE
                            WHEN UPPER(COALESCE(naturaleza_pago, 'DOCUMENTO')) = 'INTERES' THEN monto
                            WHEN UPPER(COALESCE(naturaleza_pago, 'DOCUMENTO')) = 'MIXTO' THEN COALESCE(monto_interes, 0)
                            ELSE 0 END), 0)
                   FROM tesoreria_movimientos
                   WHERE deleted_at IS NULL
                     AND estado = 'CONFIRMADO'
                     AND tipo = 'PAGO'
                     AND fecha BETWEEN :fd AND :fh";

        $q = fn(string $sql) => (float) ((function () use ($sql, $params) {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn();
        })() ?: 0);

        $mp = $q($sqlMp);
        $mod = $q($sqlMod);
        $cifInv = $q($sqlCifInv);
        $cifGastos = $q($sqlCifGastos);
        $cifDep = $q($sqlDepProd);
        $cifTotal = $cifInv + $cifGastos + $cifDep;
        $costoFabricacion = $mp + $mod + $cifTotal;

        $ventas = $q($sqlVentas);
        $cogs = $q($sqlCogs);
        $bruta = $ventas - $cogs;

        $gAdm = $q($sqlGastoServAdm) + $q($sqlInvAdm) + $q($sqlDepAdm);
        $gVentas = $q($sqlGastoServVentas) + $q($sqlInvVentas) + $q($sqlDepVentas);
        $gFin = $q($sqlFin);
        $neta = $bruta - $gAdm - $gVentas - $gFin;

        return [
            'costo_produccion' => [
                'materia_prima_consumida' => $mp,
                'mano_obra_directa' => $mod,
                'cif_total' => $cifTotal,
                'cif_desglose' => [
                    'inventario_consumido_planta' => $cifInv,
                    'servicios_planta' => $cifGastos,
                    'depreciacion_planta' => $cifDep,
                ],
                'costo_total_fabricacion' => $costoFabricacion,
            ],
            'estado_resultados' => [
                'ventas_totales' => $ventas,
                'costo_productos_vendidos' => $cogs,
                'ganancia_bruta' => $bruta,
                'gastos_administracion' => $gAdm,
                'gastos_ventas' => $gVentas,
                'gastos_financieros' => $gFin,
                'ganancia_neta' => $neta,
            ],
        ];
    }

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

    public function costosMensualesMdModCif(array $f): array
    {
        $params = [
            'fd' => (string) ($f['fecha_desde'] ?? date('Y-m-01')),
            'fh' => (string) ($f['fecha_hasta'] ?? date('Y-m-d')),
        ];

        $sql = "SELECT DATE_FORMAT(COALESCE(o.fecha_fin, o.updated_at, o.created_at), '%Y-%m') AS periodo,
                       ROUND(SUM(COALESCE(o.total_md_real, o.costo_md_real, 0)), 4) AS md_real,
                       ROUND(SUM(COALESCE(o.total_mod_real, o.costo_mod_real, 0)), 4) AS mod_real,
                       ROUND(SUM(COALESCE(o.total_cif_real, o.costo_cif_real, 0)), 4) AS cif_real,
                       ROUND(SUM(COALESCE(o.costo_real_total, 0)), 4) AS costo_real_total,
                       ROUND(SUM(COALESCE(o.costo_teorico_total_snapshot, 0)), 4) AS costo_teorico_total,
                       ROUND(SUM(COALESCE(o.costo_real_total, 0) - COALESCE(o.costo_teorico_total_snapshot, 0)), 4) AS variacion_total,
                       COUNT(*) AS ordenes
                FROM produccion_ordenes o
                WHERE o.deleted_at IS NULL
                  AND o.estado = 2
                  AND DATE(COALESCE(o.fecha_fin, o.updated_at, o.created_at)) BETWEEN :fd AND :fh
                GROUP BY DATE_FORMAT(COALESCE(o.fecha_fin, o.updated_at, o.created_at), '%Y-%m')
                ORDER BY periodo ASC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

}
