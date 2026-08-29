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

    private function aplicarFiltroItem(array &$where, array &$params, array $f, string $aliasDocumento = 'v'): void
    {
        $condiciones = ["d.id_documento_venta = {$aliasDocumento}.id", "d.deleted_at IS NULL"];
        
        // FORZAMOS: Solo contabilizar "Productos Terminados"
        $condiciones[] = "i.tipo_item = 'producto_terminado'";

        if (!empty($f['id_categoria'])) {
            $condiciones[] = 'i.id_categoria = :id_categoria';
            $params['id_categoria'] = (int) $f['id_categoria'];
        }

        if (!empty($f['id_item'])) {
            $condiciones[] = 'd.id_item = :id_item';
            $params['id_item'] = (int) $f['id_item'];
        }

        // Unificamos todo en una sola subconsulta EXISTS para mayor velocidad
        $wDetalle = implode(' AND ', $condiciones);
        $where[] = "EXISTS (
            SELECT 1 
            FROM ventas_documentos_detalle d 
            INNER JOIN items i ON i.id = d.id_item 
            WHERE {$wDetalle}
        )";
    }

    public function contarPorDespachar(): int
    {
        // Aquí NO filtramos donaciones, porque el almacén SÍ debe despacharlas.
        $sql = "SELECT COUNT(*)
                FROM ventas_documentos v
                WHERE v.deleted_at IS NULL AND v.estado IN (2,6)
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
        
        // Usamos nuestro filtro de items
        $this->aplicarFiltroItem($where, $params, $f, 'v');

        if (($f['estado'] ?? 'validas') === 'validas') {
            $where[] = 'v.estado NOT IN (0, 9)'; // Ni borradores ni anuladas
        } elseif ($f['estado'] !== 'todas' && $f['estado'] !== '') {
            $where[] = 'v.estado = :estado';
            $params['estado'] = (int) $f['estado'];
        }
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
        
        $where = ["v.tipo_operacion = 'VENTA'", 'v.deleted_at IS NULL', 'd.deleted_at IS NULL', 'DATE(v.fecha_emision) BETWEEN :fd AND :fh'];
        
        $where[] = "i.tipo_item = 'producto_terminado'";
        if (!empty($f['id_categoria'])) { $where[] = 'i.id_categoria = :id_categoria'; $params['id_categoria'] = (int) $f['id_categoria']; }
        
        if (!empty($f['id_cliente'])) { $where[] = 'v.id_cliente = :id_cliente'; $params['id_cliente'] = (int) $f['id_cliente']; }
        if (!empty($f['id_item'])) { $where[] = 'd.id_item = :id_item'; $params['id_item'] = (int) $f['id_item']; }
        if (!empty($f['id_presentacion'])) { $where[] = 'd.id_presentacion = :id_presentacion'; $params['id_presentacion'] = (int) $f['id_presentacion']; }
        if (($f['estado'] ?? 'validas') === 'validas') {
            $where[] = 'v.estado NOT IN (0, 9)'; // Ni borradores ni anuladas
        } elseif ($f['estado'] !== 'todas' && $f['estado'] !== '') {
            $where[] = 'v.estado = :estado';
            $params['estado'] = (int) $f['estado'];
        }
        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');
        $w = implode(' AND ', $where);

        $sql = "SELECT COALESCE(i.nombre, pp.nombre) AS producto,
                       ROUND(SUM(d.cantidad),2) AS total_cantidad,
                       ROUND(SUM(d.cantidad * d.precio_unitario),2) AS total_monto 
                FROM ventas_documentos v
                INNER JOIN ventas_documentos_detalle d ON d.id_documento_venta = v.id
                LEFT JOIN items i ON i.id = d.id_item
                LEFT JOIN precios_presentaciones pp ON pp.id = d.id_presentacion
                WHERE {$w}
                GROUP BY d.id_item, d.id_presentacion, i.nombre, pp.nombre
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
        
        $where = ['v.deleted_at IS NULL', 'DATE(v.fecha_emision) BETWEEN :fd AND :fh', 'v.estado IN (2,6)'];
        if (!empty($f['id_cliente'])) { $where[] = 'v.id_cliente = :id_cliente'; $params['id_cliente'] = (int) $f['id_cliente']; }
        
        // Usamos nuestro filtro de items
        $this->aplicarFiltroItem($where, $params, $f, 'v');

        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');
        $w = implode(' AND ', $where);

        $count = $this->db()->prepare("SELECT COUNT(*) FROM ventas_documentos v WHERE {$w}");
        $count->execute($params);

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
        
        // Usamos nuestro filtro de items
        $this->aplicarFiltroItem($where, $params, $f, 'v');

        if (($f['estado'] ?? 'validas') === 'validas') {
            $where[] = 'v.estado NOT IN (0, 9)'; // Ni borradores ni anuladas
        } elseif ($f['estado'] !== 'todas' && $f['estado'] !== '') {
            $where[] = 'v.estado = :estado';
            $params['estado'] = (int) $f['estado'];
        }
        $this->aplicarFiltroTipoTercero($where, $params, $f, 'v');

        $w = implode(' AND ', $where);

        if ($agrupacion === 'semanal') {
            $sql = "SELECT YEAR(v.fecha_emision) AS periodo_anio,
                           WEEK(v.fecha_emision, 1) AS periodo_semana,
                           CONCAT(
                               'S', LPAD(WEEK(MIN(v.fecha_emision), 1), 2, '0'), '-', YEAR(MIN(v.fecha_emision)),
                               ' (',
                               DATE_FORMAT(DATE_ADD(MIN(v.fecha_emision), INTERVAL -WEEKDAY(MIN(v.fecha_emision)) DAY), '%d/%m'),
                               ' al ',
                               DATE_FORMAT(DATE_ADD(MIN(v.fecha_emision), INTERVAL 6 - WEEKDAY(MIN(v.fecha_emision)) DAY), '%d/%m'),
                               ')'
                           ) AS etiqueta,
                           ROUND(SUM(v.total), 2) AS total_vendido,
                           COUNT(*) AS documentos
                    FROM ventas_documentos v
                    WHERE {$w}
                    GROUP BY YEAR(v.fecha_emision), WEEK(v.fecha_emision, 1)
                    ORDER BY periodo_anio DESC, periodo_semana DESC
                    LIMIT :limite";
        } elseif ($agrupacion === 'mensual') {
            $sql = "SELECT DATE_FORMAT(v.fecha_emision, '%Y-%m') AS periodo_mes,
                           DATE_FORMAT(v.fecha_emision, '%m-%Y') AS etiqueta,
                           ROUND(SUM(v.total), 2) AS total_vendido,
                           COUNT(*) AS documentos
                    FROM ventas_documentos v
                    WHERE {$w}
                    GROUP BY DATE_FORMAT(v.fecha_emision, '%Y-%m')
                    ORDER BY periodo_mes DESC
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

    // --- NUEVA FUNCIÓN AGREGADA AQUÍ ---
    public function categoriasProductosTerminados(): array
    {
        $sql = "SELECT DISTINCT c.id, c.nombre 
                FROM categorias c
                INNER JOIN items i ON i.id_categoria = c.id
                WHERE c.estado = 1 AND i.tipo_item = 'producto_terminado'
                ORDER BY c.nombre ASC";
                
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}