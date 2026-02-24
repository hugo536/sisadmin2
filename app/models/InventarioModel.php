<?php
declare(strict_types=1);

class InventarioModel extends Modelo
{

    public function obtenerStock(int $idAlmacen = 0): array
    {
        $tablaPacksDisponible = $this->tablaExiste('precios_presentaciones');

        if ($idAlmacen > 0) {
            $sql = <<<'SQL'
SELECT i.id AS id_item,
       i.sku,
       CONCAT(
           i.nombre,
           CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != 'Ninguno' THEN CONCAT(' ', sbr.nombre) ELSE '' END,
           CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(' ', prs.nombre) ELSE '' END
       ) AS item_nombre,
       i.nombre AS item_nombre_base,
       i.descripcion AS item_descripcion,
       i.estado AS item_estado,
       a.id AS id_almacen,
       a.nombre AS almacen_nombre,
       i.stock_minimo,
       i.requiere_vencimiento,
       i.dias_alerta_vencimiento,
       i.controla_stock,
       i.permite_decimales,
       'item' AS tipo_registro,
       COALESCE(s.stock_actual, 0) AS stock_actual,
       (
           SELECT l.lote
           FROM inventario_lotes l
           WHERE l.id_item = i.id
             AND l.id_almacen = :id_almacen_lote
             AND l.stock_lote > 0
           ORDER BY (l.fecha_vencimiento IS NULL) ASC,
                    l.fecha_vencimiento ASC,
                    l.id ASC
           LIMIT 1
       ) AS lote_actual,
       (
           SELECT MIN(l.fecha_vencimiento)
           FROM inventario_lotes l
           WHERE l.id_item = i.id
             AND l.id_almacen = :id_almacen_venc
             AND l.stock_lote > 0
             AND l.fecha_vencimiento IS NOT NULL
       ) AS proximo_vencimiento,
       (
           SELECT MAX(m.created_at)
           FROM inventario_movimientos m
           WHERE m.id_item = i.id
             AND (m.id_almacen_origen = :id_almacen_mov_ult_origen OR m.id_almacen_destino = :id_almacen_mov_ult_destino)
       ) AS ultimo_movimiento
FROM items i
INNER JOIN almacenes a ON a.id = :id_almacen AND a.estado = 1 AND a.deleted_at IS NULL
LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
LEFT JOIN inventario_stock s ON s.id_item = i.id AND s.id_almacen = :id_almacen_stock
WHERE i.controla_stock = 1
  AND i.deleted_at IS NULL
  AND (
    s.id IS NOT NULL
    OR EXISTS (
        SELECT 1
        FROM inventario_lotes lx
        WHERE lx.id_item = i.id
          AND lx.id_almacen = :id_almacen_mov_item
    )
  )
SQL;

            if ($tablaPacksDisponible) {
                $sql .= <<<'SQL'
 UNION ALL
SELECT p.id AS id_item,
       p.codigo_presentacion AS sku,
       COALESCE(
           p.nombre_manual,
           CONCAT(
               i.nombre,
               CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != 'Ninguno' THEN CONCAT(' ' , sbr.nombre) ELSE '' END,
               CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(' ' , prs.nombre) ELSE '' END,
               ' x ', CAST(p.factor AS UNSIGNED)
           )
       ) AS item_nombre,
       COALESCE(
           p.nombre_manual,
           CONCAT(
               i.nombre,
               CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != 'Ninguno' THEN CONCAT(' ' , sbr.nombre) ELSE '' END,
               CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(' ' , prs.nombre) ELSE '' END,
               ' x ', CAST(p.factor AS UNSIGNED)
           )
       ) AS item_nombre_base,
       'Pack Comercial' AS item_descripcion,
       p.estado AS item_estado,
       a.id AS id_almacen,
       a.nombre AS almacen_nombre,
       p.stock_minimo AS stock_minimo,
       p.requiere_vencimiento,
       p.dias_vencimiento_alerta AS dias_alerta_vencimiento,
       1 AS controla_stock,
       0 AS permite_decimales,
       'pack' AS tipo_registro,
       COALESCE(sp.stock_actual, 0) AS stock_actual,
       NULL AS lote_actual,
       NULL AS proximo_vencimiento,
       (
           SELECT MAX(m.created_at)
           FROM inventario_movimientos m
           WHERE m.id_item = i.id
             AND (m.id_almacen_origen = :id_almacen_mov_pack_origen OR m.id_almacen_destino = :id_almacen_mov_pack_destino)
             AND m.referencia LIKE CONCAT('Pack: ', p.codigo_presentacion, '%')
       ) AS ultimo_movimiento
FROM precios_presentaciones p
LEFT JOIN items i ON i.id = p.id_item
LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
INNER JOIN almacenes a ON a.id = :id_almacen_pack AND a.estado = 1 AND a.deleted_at IS NULL
LEFT JOIN inventario_stock sp ON sp.id_pack = p.id AND sp.id_almacen = :id_almacen_stock_pack
WHERE p.estado = 1
  AND p.deleted_at IS NULL
  AND sp.id IS NOT NULL
SQL;
            }

            $sql .= ' ORDER BY ultimo_movimiento DESC, sku ASC';

            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue(':id_almacen', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_lote', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_venc', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_stock', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_mov_item', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_mov_ult_origen', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_mov_ult_destino', $idAlmacen, PDO::PARAM_INT);
            if ($tablaPacksDisponible) {
                $stmt->bindValue(':id_almacen_pack', $idAlmacen, PDO::PARAM_INT);
                $stmt->bindValue(':id_almacen_stock_pack', $idAlmacen, PDO::PARAM_INT);
                $stmt->bindValue(':id_almacen_mov_pack_origen', $idAlmacen, PDO::PARAM_INT);
                $stmt->bindValue(':id_almacen_mov_pack_destino', $idAlmacen, PDO::PARAM_INT);
            }
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $sql = <<<'SQL'
SELECT i.id AS id_item,
       i.sku,
       CONCAT(
           i.nombre,
           CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != 'Ninguno' THEN CONCAT(' ', sbr.nombre) ELSE '' END,
           CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(' ', prs.nombre) ELSE '' END
       ) AS item_nombre,
       i.nombre AS item_nombre_base,
       i.descripcion AS item_descripcion,
       i.estado AS item_estado,
       0 AS id_almacen,
       'Global / Todos' AS almacen_nombre,
       i.stock_minimo,
       i.requiere_vencimiento,
       i.dias_alerta_vencimiento,
       i.controla_stock,
       i.permite_decimales,
       'item' AS tipo_registro,
       COALESCE(SUM(CASE WHEN a.estado = 1 AND a.deleted_at IS NULL THEN s.stock_actual ELSE 0 END), 0) AS stock_actual,
       (
           SELECT l.lote
           FROM inventario_lotes l
           INNER JOIN almacenes al ON al.id = l.id_almacen AND al.estado = 1
           WHERE l.id_item = i.id
             AND l.stock_lote > 0
           ORDER BY (l.fecha_vencimiento IS NULL) ASC,
                    l.fecha_vencimiento ASC,
                    l.id ASC
           LIMIT 1
       ) AS lote_actual,
       (
           SELECT MIN(l.fecha_vencimiento)
           FROM inventario_lotes l
           INNER JOIN almacenes al ON al.id = l.id_almacen AND al.estado = 1
           WHERE l.id_item = i.id
             AND l.stock_lote > 0
             AND l.fecha_vencimiento IS NOT NULL
       ) AS proximo_vencimiento,
       (
           SELECT MAX(m.created_at)
           FROM inventario_movimientos m
           WHERE m.id_item = i.id
       ) AS ultimo_movimiento
FROM items i
LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
LEFT JOIN inventario_stock s ON s.id_item = i.id
LEFT JOIN almacenes a ON a.id = s.id_almacen
WHERE i.controla_stock = 1
  AND i.deleted_at IS NULL
GROUP BY i.id, i.sku, i.nombre, sbr.nombre, prs.nombre, i.descripcion, i.estado, i.stock_minimo, i.requiere_vencimiento, i.dias_alerta_vencimiento, i.controla_stock, i.permite_decimales
SQL;

        if ($tablaPacksDisponible) {
            $sql .= <<<'SQL'
 UNION ALL
SELECT p.id AS id_item,
       p.codigo_presentacion AS sku,
       COALESCE(
           p.nombre_manual,
           CONCAT(
               i.nombre,
               CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != 'Ninguno' THEN CONCAT(' ' , sbr.nombre) ELSE '' END,
               CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(' ' , prs.nombre) ELSE '' END,
               ' x ', CAST(p.factor AS UNSIGNED)
           )
       ) AS item_nombre,
       COALESCE(
           p.nombre_manual,
           CONCAT(
               i.nombre,
               CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != 'Ninguno' THEN CONCAT(' ' , sbr.nombre) ELSE '' END,
               CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(' ' , prs.nombre) ELSE '' END,
               ' x ', CAST(p.factor AS UNSIGNED)
           )
       ) AS item_nombre_base,
       'Pack Comercial' AS item_descripcion,
       p.estado AS item_estado,
       0 AS id_almacen,
       'Global / Todos' AS almacen_nombre,
       p.stock_minimo AS stock_minimo,
       p.requiere_vencimiento,
       p.dias_vencimiento_alerta AS dias_alerta_vencimiento,
       1 AS controla_stock,
       0 AS permite_decimales,
       'pack' AS tipo_registro,
       COALESCE(SUM(CASE WHEN a.estado = 1 AND a.deleted_at IS NULL THEN sp.stock_actual ELSE 0 END), 0) AS stock_actual,
       NULL AS lote_actual,
       NULL AS proximo_vencimiento,
       (
           SELECT MAX(m.created_at)
           FROM inventario_movimientos m
           WHERE m.id_item = i.id
             AND m.referencia LIKE CONCAT('Pack: ', p.codigo_presentacion, '%')
       ) AS ultimo_movimiento
FROM precios_presentaciones p
LEFT JOIN items i ON i.id = p.id_item
LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
LEFT JOIN inventario_stock sp ON sp.id_pack = p.id
LEFT JOIN almacenes a ON a.id = sp.id_almacen
WHERE p.estado = 1
  AND p.deleted_at IS NULL
GROUP BY p.id, p.codigo_presentacion, p.nombre_manual, i.nombre, sbr.nombre, prs.nombre, p.factor, p.estado, p.stock_minimo, p.requiere_vencimiento, p.dias_vencimiento_alerta
SQL;
        }

        $sql .= ' ORDER BY ultimo_movimiento DESC, sku ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarItems(): array
    {
        $sql = 'SELECT id, sku, nombre, controla_stock, requiere_lote, requiere_vencimiento
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND controla_stock = 1
                  AND tipo_item NOT IN (\'semielaborado\', \'producto_terminado\', \'producto\')
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarItems(string $termino, int $limite = 20): array
    {
        $limite = max(1, (int) $limite);
        $busqueda = '%' . $termino . '%';

        $sqlItems = "SELECT i.id,
                            i.sku,
                            i.nombre,
                            i.tipo_item AS tipo,
                            'item' AS tipo_registro,
                            i.requiere_lote,
                            i.requiere_vencimiento,
                            CONCAT(
                                i.nombre,
                                CASE WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre) ELSE '' END,
                                CASE WHEN p.nombre IS NOT NULL THEN CONCAT(' ', p.nombre) ELSE '' END
                            ) AS nombre_full,
                            NULL AS nota
                     FROM items i
                     LEFT JOIN item_sabores s ON s.id = i.id_sabor
                     LEFT JOIN item_presentaciones p ON p.id = i.id_presentacion
                     WHERE i.estado = 1
                       AND i.deleted_at IS NULL
                       AND (i.sku LIKE :termino_sku_item OR i.nombre LIKE :termino_nombre_item)
                     LIMIT {$limite}";

        $sqlPacks = "SELECT p.id,
                            p.codigo_presentacion AS sku,
                            COALESCE(p.nombre_manual, i.nombre) AS nombre,
                            'pack' AS tipo,
                            'pack' AS tipo_registro,
                            0 AS requiere_lote,
                            0 AS requiere_vencimiento,
                            COALESCE(
                                p.nombre_manual,
                                CONCAT(
                                    i.nombre,
                                    CASE WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre) ELSE '' END,
                                    CASE WHEN ip.nombre IS NOT NULL THEN CONCAT(' ', ip.nombre) ELSE '' END,
                                    ' x ', CAST(p.factor AS UNSIGNED)
                                )
                            ) AS nombre_full,
                            COALESCE(NULLIF(TRIM(p.nota_pack), ''), '') AS nota
                     FROM precios_presentaciones p
                     INNER JOIN items i ON i.id = p.id_item
                     LEFT JOIN item_sabores s ON s.id = i.id_sabor
                     LEFT JOIN item_presentaciones ip ON ip.id = i.id_presentacion
                     WHERE p.estado = 1
                       AND p.deleted_at IS NULL
                       AND (
                           p.codigo_presentacion LIKE :termino_sku_pack
                           OR p.nombre_manual LIKE :termino_nombre_pack
                           OR i.nombre LIKE :termino_nombre_base_pack
                           OR p.nota_pack LIKE :termino_nota_pack
                       )
                     LIMIT {$limite}";

        $stmtItems = $this->db()->prepare($sqlItems);
        $stmtItems->bindValue(':termino_sku_item', $busqueda, PDO::PARAM_STR);
        $stmtItems->bindValue(':termino_nombre_item', $busqueda, PDO::PARAM_STR);
        $stmtItems->execute();
        $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $packs = [];
        if ($this->tablaExiste('precios_presentaciones')) {
            $stmtPacks = $this->db()->prepare($sqlPacks);
            $stmtPacks->bindValue(':termino_sku_pack', $busqueda, PDO::PARAM_STR);
            $stmtPacks->bindValue(':termino_nombre_pack', $busqueda, PDO::PARAM_STR);
            $stmtPacks->bindValue(':termino_nombre_base_pack', $busqueda, PDO::PARAM_STR);
            $stmtPacks->bindValue(':termino_nota_pack', $busqueda, PDO::PARAM_STR);
            $stmtPacks->execute();
            $packs = $stmtPacks->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $resultado = [];
        foreach ($items as $fila) {
            $fila['value'] = 'item:' . (int) ($fila['id'] ?? 0);
            $resultado[] = $fila;
        }
        foreach ($packs as $fila) {
            $fila['value'] = 'pack:' . (int) ($fila['id'] ?? 0);
            $resultado[] = $fila;
        }

        usort($resultado, static function (array $a, array $b): int {
            return strcasecmp((string) ($a['nombre_full'] ?? ''), (string) ($b['nombre_full'] ?? ''));
        });

        return array_slice($resultado, 0, $limite);
    }

    public function listarProveedoresActivos(): array
    {
        $sql = 'SELECT t.id, t.nombre_completo
                FROM terceros_proveedores tp
                INNER JOIN terceros t ON t.id = tp.id_tercero
                WHERE t.es_proveedor = 1
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                  AND tp.deleted_at IS NULL
                ORDER BY t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerStockPorItemAlmacen(int $idItem, int $idAlmacen, string $tipoRegistro = 'item'): float
    {
        if ($tipoRegistro === 'pack') {
            $sql = 'SELECT stock_actual
                    FROM inventario_stock
                    WHERE id_pack = :id_pack
                      AND id_almacen = :id_almacen
                    LIMIT 1';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'id_pack' => $idItem,
                'id_almacen' => $idAlmacen,
            ]);
            return (float) ($stmt->fetchColumn() ?: 0);
        }

        $sql = 'SELECT stock_actual
                FROM inventario_stock
                WHERE id_item = :id_item
                  AND id_almacen = :id_almacen
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
        ]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    public function obtenerResumenRegistro(int $idRegistro, string $tipoRegistro = 'item'): array
    {
        $tipoRegistro = in_array($tipoRegistro, ['item', 'pack'], true) ? $tipoRegistro : 'item';

        if ($tipoRegistro === 'pack') {
            $sqlStock = 'SELECT COALESCE(SUM(stock_actual), 0)
                         FROM inventario_stock
                         WHERE id_pack = :id_registro';
            $sqlMov = 'SELECT referencia, costo_unitario
                       FROM inventario_movimientos
                       WHERE id_item = :id_registro
                         AND referencia LIKE "Pack:%"
                       ORDER BY id DESC
                       LIMIT 100';
        } else {
            $sqlStock = 'SELECT COALESCE(SUM(stock_actual), 0)
                         FROM inventario_stock
                         WHERE id_item = :id_registro';
            $sqlMov = 'SELECT referencia, costo_unitario
                       FROM inventario_movimientos
                       WHERE id_item = :id_registro
                         AND (referencia IS NULL OR referencia NOT LIKE "Pack:%")
                       ORDER BY id DESC
                       LIMIT 100';
        }

        $stmtStock = $this->db()->prepare($sqlStock);
        $stmtStock->execute(['id_registro' => $idRegistro]);
        $stockActual = (float) ($stmtStock->fetchColumn() ?: 0);

        $stmtMov = $this->db()->prepare($sqlMov);
        $stmtMov->execute(['id_registro' => $idRegistro]);
        $movimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $costoPromedio = 0.0;
        foreach ($movimientos as $movimiento) {
            $costoUnitario = isset($movimiento['costo_unitario']) ? (float) $movimiento['costo_unitario'] : 0.0;
            if ($costoUnitario > 0) {
                $costoPromedio = $costoUnitario;
                break;
            }

            $ref = (string) ($movimiento['referencia'] ?? '');
            if (!is_string($ref) || $ref === '') {
                continue;
            }

            if (preg_match('/C\.Unit:\s*([0-9]+(?:\.[0-9]+)?)/i', $ref, $coincide)) {
                $costoPromedio = (float) $coincide[1];
                break;
            }
        }

        return [
            'stock_actual' => $stockActual,
            'costo_promedio_actual' => $costoPromedio,
        ];
    }

    public function obtenerKardex(array $filtros = []): array
    {
        $sql = 'SELECT m.id,
                       m.created_at,
                       m.tipo_movimiento,
                       m.cantidad,
                       m.referencia,
                       i.sku,
                       i.nombre AS item_nombre,
                       ao.nombre AS almacen_origen,
                       ad.nombre AS almacen_destino,
                       u.nombre_completo AS usuario
                FROM inventario_movimientos m
                INNER JOIN items i ON i.id = m.id_item
                LEFT JOIN almacenes ao ON ao.id = m.id_almacen_origen
                LEFT JOIN almacenes ad ON ad.id = m.id_almacen_destino
                LEFT JOIN usuarios u ON u.id = m.created_by
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['id_item'])) {
            $sql .= ' AND m.id_item = :id_item';
            $params['id_item'] = (int) $filtros['id_item'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND DATE(m.created_at) >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(m.created_at) <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        if (!empty($filtros['lote'])) {
            $sql .= ' AND m.referencia LIKE :lote';
            $params['lote'] = '%Lote: ' . (string) $filtros['lote'] . '%';
        }

        $sql .= ' ORDER BY m.created_at DESC LIMIT 1000';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function registrarMovimiento(array $datos): int
    {
        $tipo = (string) ($datos['tipo_movimiento'] ?? '');
        $tiposValidos = ['INI', 'AJ+', 'AJ-', 'TRF', 'CON'];

        if (!in_array($tipo, $tiposValidos, true)) {
            throw new InvalidArgumentException('Tipo de movimiento inválido.');
        }

        $tipoRegistro = (string) ($datos['tipo_registro'] ?? 'item');
        if (!in_array($tipoRegistro, ['item', 'pack'], true)) {
            $tipoRegistro = 'item';
        }

        $idRegistro = $tipoRegistro === 'pack'
            ? (int) ($datos['id_pack'] ?? 0)
            : (int) ($datos['id_item'] ?? 0);
        $idAlmacenOrigen = isset($datos['id_almacen_origen']) ? (int) $datos['id_almacen_origen'] : 0;
        $idAlmacenDestino = isset($datos['id_almacen_destino']) ? (int) $datos['id_almacen_destino'] : 0;
        $cantidad = (float) ($datos['cantidad'] ?? 0);
        $referencia = trim((string) ($datos['referencia'] ?? ''));
        $lote = trim((string) ($datos['lote'] ?? ''));
        $fechaVencimiento = isset($datos['fecha_vencimiento']) ? trim((string) $datos['fecha_vencimiento']) : '';
        $costoUnitario = isset($datos['costo_unitario']) ? (float) $datos['costo_unitario'] : 0.0;
        $idItemUnidad = isset($datos['id_item_unidad']) ? (int) $datos['id_item_unidad'] : 0;
        $createdBy = (int) ($datos['created_by'] ?? 0);

        if ($idRegistro <= 0 || $createdBy <= 0) {
            throw new InvalidArgumentException('Datos incompletos para registrar el movimiento.');
        }

        if ($tipo === 'INI') {
            if ($cantidad < 0) {
                throw new InvalidArgumentException('Para movimiento INI la cantidad debe ser mayor o igual a 0.');
            }
            if ($costoUnitario < 0) {
                throw new InvalidArgumentException('Para movimiento INI el costo unitario debe ser mayor o igual a 0.');
            }
        } elseif ($cantidad <= 0) {
            throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
        }

        if (in_array($tipo, ['INI', 'AJ+', 'AJ-', 'CON'], true) && $idAlmacenOrigen <= 0 && $idAlmacenDestino <= 0) {
            throw new InvalidArgumentException('Debe seleccionar almacén origen.');
        }

        if (in_array($tipo, ['INI', 'AJ+'], true) && $idAlmacenDestino <= 0 && $idAlmacenOrigen > 0) {
            $idAlmacenDestino = $idAlmacenOrigen;
        }

        if (in_array($tipo, ['AJ-', 'CON'], true) && $idAlmacenOrigen <= 0) {
            throw new InvalidArgumentException('Debe seleccionar almacén origen.');
        }

        if ($tipo === 'TRF') {
            if ($idAlmacenOrigen <= 0 || $idAlmacenDestino <= 0) {
                throw new InvalidArgumentException('Debe seleccionar almacén origen y destino para transferencias.');
            }
            if ($idAlmacenOrigen === $idAlmacenDestino) {
                throw new InvalidArgumentException('El almacén origen y destino no pueden ser iguales.');
            }
        }

        $db = $this->db();
        $iniciaTransaccion = !$db->inTransaction();
        if ($iniciaTransaccion) {
            $db->beginTransaction();
        }

        try {
            $configRegistro = $tipoRegistro === 'pack'
                ? $this->obtenerConfiguracionPack($db, $idRegistro)
                : $this->obtenerConfiguracionItem($db, $idRegistro);

            $idItemMovimiento = $tipoRegistro === 'pack'
                ? (int) ($configRegistro['id_item_base'] ?? 0)
                : $idRegistro;

            if ($idItemMovimiento <= 0) {
                throw new RuntimeException('No se pudo identificar el ítem base para registrar el movimiento.');
            }

            if ($tipo === 'INI' && $this->existeMovimientoInicial($db, $idItemMovimiento, $idAlmacenDestino)) {
                throw new InvalidArgumentException('Ya existe un movimiento INI para este registro y almacén.');
            }

            if ($tipoRegistro === 'pack') {
                $lote = '';
                $fechaVencimiento = '';
            }

            if ((int) ($configRegistro['requiere_lote'] ?? 0) === 1 && $lote === '') {
                throw new InvalidArgumentException('El registro seleccionado requiere lote para movimientos.');
            }

            if ((int) ($configRegistro['requiere_vencimiento'] ?? 0) === 1 && in_array($tipo, ['INI', 'AJ+'], true) && $fechaVencimiento === '') {
                throw new InvalidArgumentException('El registro seleccionado requiere fecha de vencimiento para entradas de stock.');
            }

            if ($fechaVencimiento !== '' && !$this->esFechaValida($fechaVencimiento)) {
                throw new InvalidArgumentException('La fecha de vencimiento no tiene un formato válido (YYYY-MM-DD).');
            }

            $costoTotal = $cantidad * $costoUnitario;
            $referenciaFinal = $this->construirReferencia($referencia, $lote, $fechaVencimiento, $costoUnitario, $costoTotal);
            if ($tipoRegistro === 'pack') {
                $codigoPack = (string) ($configRegistro['codigo_presentacion'] ?? ('PACK-' . $idRegistro));
                $prefix = 'Pack: ' . $codigoPack;
                $referenciaFinal = $referenciaFinal !== '' ? ($prefix . ' | ' . $referenciaFinal) : $prefix;
            }

            $sqlMovimiento = 'INSERT INTO inventario_movimientos
                                (id_item, id_item_unidad, id_almacen_origen, id_almacen_destino, tipo_movimiento, cantidad, costo_unitario, costo_total, referencia, created_by)
                              VALUES
                                (:id_item, :id_item_unidad, :id_almacen_origen, :id_almacen_destino, :tipo_movimiento, :cantidad, :costo_unitario, :costo_total, :referencia, :created_by)';
            $stmtMov = $db->prepare($sqlMovimiento);
            $stmtMov->execute([
                'id_item' => $idItemMovimiento,
                'id_item_unidad' => $idItemUnidad > 0 ? $idItemUnidad : null,
                'id_almacen_origen' => $idAlmacenOrigen > 0 ? $idAlmacenOrigen : null,
                'id_almacen_destino' => $idAlmacenDestino > 0 ? $idAlmacenDestino : null,
                'tipo_movimiento' => $tipo,
                'cantidad' => $cantidad,
                'costo_unitario' => $costoUnitario > 0 ? number_format($costoUnitario, 4, '.', '') : null,
                'costo_total' => $costoTotal > 0 ? number_format($costoTotal, 4, '.', '') : null,
                'referencia' => $referenciaFinal !== '' ? $referenciaFinal : null,
                'created_by' => $createdBy,
            ]);

            if (in_array($tipo, ['INI', 'AJ+'], true)) {
                if ($tipoRegistro === 'pack') {
                    $this->ajustarStockPack($db, $idRegistro, $idAlmacenDestino, $cantidad);
                } else {
                    $this->ajustarStock($db, $idRegistro, $idAlmacenDestino, $cantidad);
                    $this->incrementarStockLote($db, $idRegistro, $idAlmacenDestino, $lote, $fechaVencimiento !== '' ? $fechaVencimiento : null, $cantidad);
                }
            }

            if (in_array($tipo, ['AJ-', 'CON'], true)) {
                if ($tipoRegistro === 'pack') {
                    $this->validarStockDisponiblePack($db, $idRegistro, $idAlmacenOrigen, $cantidad);
                    $this->ajustarStockPack($db, $idRegistro, $idAlmacenOrigen, -$cantidad);
                } else {
                    $this->validarStockDisponible($db, $idRegistro, $idAlmacenOrigen, $cantidad);
                    $this->ajustarStock($db, $idRegistro, $idAlmacenOrigen, -$cantidad);
                    $this->decrementarStockLote($db, $idRegistro, $idAlmacenOrigen, $lote, $cantidad);
                }
            }

            if ($tipo === 'TRF') {
                if ($tipoRegistro === 'pack') {
                    $this->validarStockDisponiblePack($db, $idRegistro, $idAlmacenOrigen, $cantidad);
                    $this->ajustarStockPack($db, $idRegistro, $idAlmacenOrigen, -$cantidad);
                    $this->ajustarStockPack($db, $idRegistro, $idAlmacenDestino, $cantidad);
                } else {
                    $this->validarStockDisponible($db, $idRegistro, $idAlmacenOrigen, $cantidad);
                    $this->ajustarStock($db, $idRegistro, $idAlmacenOrigen, -$cantidad);
                    $this->decrementarStockLote($db, $idRegistro, $idAlmacenOrigen, $lote, $cantidad);
                    $this->ajustarStock($db, $idRegistro, $idAlmacenDestino, $cantidad);
                    $vencimientoLote = $fechaVencimiento !== '' ? $fechaVencimiento : $this->obtenerVencimientoLote($db, $idRegistro, $idAlmacenOrigen, $lote);
                    $this->incrementarStockLote($db, $idRegistro, $idAlmacenDestino, $lote, $vencimientoLote, $cantidad);
                }
            }

            $idMovimiento = (int) $db->lastInsertId();

            if ($iniciaTransaccion && $db->inTransaction()) {
                $db->commit();
            }

            return $idMovimiento;
        } catch (Throwable $e) {
            if ($iniciaTransaccion && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function obtenerDesglosePresentaciones(int $idItem, int $idAlmacen = 0): array
    {
        if ($idItem <= 0) {
            return [];
        }

        $sql = 'SELECT
                    COALESCE(m.id_item_unidad, 0) AS id_item_unidad,
                    COALESCE(u.nombre_unidad, i.unidad_base) AS unidad_nombre,
                    SUM(CASE
                            WHEN m.tipo_movimiento IN (\'INI\', \'AJ+\', \'TRF\') AND m.id_almacen_destino IS NOT NULL THEN m.cantidad
                            ELSE 0
                        END) AS total_entradas,
                    SUM(CASE
                            WHEN m.tipo_movimiento IN (\'AJ-\', \'CON\', \'TRF\') AND m.id_almacen_origen IS NOT NULL THEN m.cantidad
                            ELSE 0
                        END) AS total_salidas,
                    SUM(CASE
                            WHEN m.tipo_movimiento IN (\'INI\', \'AJ+\') THEN m.cantidad
                            WHEN m.tipo_movimiento = \'TRF\' THEN
                                CASE
                                    WHEN :id_almacen_trf > 0 AND m.id_almacen_destino = :id_almacen_trf THEN m.cantidad
                                    WHEN :id_almacen_trf > 0 AND m.id_almacen_origen = :id_almacen_trf THEN -m.cantidad
                                    ELSE 0
                                END
                            WHEN m.tipo_movimiento IN (\'AJ-\', \'CON\') THEN -m.cantidad
                            ELSE 0
                        END) AS saldo_neto
                FROM inventario_movimientos m
                INNER JOIN items i ON i.id = m.id_item
                LEFT JOIN items_unidades u ON u.id = m.id_item_unidad
                WHERE m.id_item = :id_item
                  AND (
                      :id_almacen = 0
                      OR m.id_almacen_destino = :id_almacen
                      OR m.id_almacen_origen = :id_almacen
                  )
                GROUP BY COALESCE(m.id_item_unidad, 0), COALESCE(u.nombre_unidad, i.unidad_base)
                HAVING ABS(saldo_neto) > 0.000001 OR ABS(total_entradas) > 0.000001 OR ABS(total_salidas) > 0.000001
                ORDER BY unidad_nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'id_almacen_trf' => $idAlmacen,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function validarStockDisponible(PDO $db, int $idItem, int $idAlmacen, float $cantidad): void
    {
        $sql = 'SELECT stock_actual
                FROM inventario_stock
                WHERE id_item = :id_item AND id_almacen = :id_almacen
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
        ]);

        $stock = (float) ($stmt->fetchColumn() ?: 0);

        if ($stock < $cantidad) {
            throw new RuntimeException('Stock insuficiente para realizar el movimiento.');
        }
    }

    private function validarStockDisponiblePack(PDO $db, int $idPack, int $idAlmacen, float $cantidad): void
    {
        $sql = 'SELECT stock_actual
                FROM inventario_stock
                WHERE id_pack = :id_pack AND id_almacen = :id_almacen
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_pack' => $idPack,
            'id_almacen' => $idAlmacen,
        ]);
        $stock = (float) ($stmt->fetchColumn() ?: 0);
        if ($stock < $cantidad) {
            throw new RuntimeException('Stock insuficiente para realizar el movimiento del pack seleccionado.');
        }
    }

    private function existeMovimientoInicial(PDO $db, int $idItem, int $idAlmacen): bool
    {
        if ($idItem <= 0 || $idAlmacen <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM inventario_movimientos
                WHERE id_item = :id_item
                  AND tipo_movimiento = :tipo
                  AND id_almacen_destino = :id_almacen
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'tipo' => 'INI',
            'id_almacen' => $idAlmacen,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    private function obtenerConfiguracionItem(PDO $db, int $idItem): array
    {
        $sql = 'SELECT controla_stock, requiere_lote, requiere_vencimiento
                FROM items
                WHERE id = :id_item
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($item === false) {
            throw new RuntimeException('El ítem seleccionado no existe.');
        }

        if ((int) ($item['controla_stock'] ?? 0) !== 1) {
            throw new RuntimeException('El ítem seleccionado no controla stock.');
        }

        return $item;
    }

    private function obtenerConfiguracionPack(PDO $db, int $idPack): array
    {
        if (!$this->tablaExiste('precios_presentaciones')) {
            throw new RuntimeException('No existe la tabla de presentaciones comerciales en esta base de datos.');
        }

        $sql = 'SELECT p.id, p.id_item AS id_item_base, p.codigo_presentacion, 1 AS controla_stock, 0 AS requiere_lote, 0 AS requiere_vencimiento
                FROM precios_presentaciones p
                WHERE p.id = :id_pack
                  AND p.estado = 1
                  AND p.deleted_at IS NULL
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id_pack' => $idPack]);
        $pack = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($pack === false) {
            throw new RuntimeException('La presentación/pack seleccionado no existe o está inactivo.');
        }

        return $pack;
    }

    private function obtenerVencimientoLote(PDO $db, int $idItem, int $idAlmacen, string $lote): ?string
    {
        if ($lote === '') return null;
       
        $sql = 'SELECT fecha_vencimiento FROM inventario_lotes
                WHERE id_item = :id_item AND id_almacen = :id_almacen AND lote = :lote LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id_item' => $idItem, 'id_almacen' => $idAlmacen, 'lote' => $lote]);
        return $stmt->fetchColumn() ?: null;
    }

    private function incrementarStockLote(PDO $db, int $idItem, int $idAlmacen, string $lote, ?string $fechaVencimiento, float $cantidad): void
    {
        if ($lote === '') {
            return;
        }

        $sql = 'INSERT INTO inventario_lotes (id_item, id_almacen, lote, fecha_vencimiento, stock_lote)
                VALUES (:id_item, :id_almacen, :lote, :fecha_vencimiento, :stock_lote)
                ON DUPLICATE KEY UPDATE
                    stock_lote = stock_lote + VALUES(stock_lote),
                    fecha_vencimiento = COALESCE(VALUES(fecha_vencimiento), fecha_vencimiento)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'lote' => $lote,
            'fecha_vencimiento' => $fechaVencimiento,
            'stock_lote' => $cantidad,
        ]);
    }

    private function decrementarStockLote(PDO $db, int $idItem, int $idAlmacen, string $lote, float $cantidad): void
    {
        if ($lote !== '') {
            $this->decrementarStockLoteEspecifico($db, $idItem, $idAlmacen, $lote, $cantidad);
            return;
        }

        $pendiente = $cantidad;
        $sql = 'SELECT lote, stock_lote
                FROM inventario_lotes
                WHERE id_item = :id_item
                  AND id_almacen = :id_almacen
                  AND stock_lote > 0
                ORDER BY CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END, fecha_vencimiento ASC, id ASC';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen
        ]);
        $lotes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($lotes as $loteItem) {
            if ($pendiente <= 0) {
                break;
            }

            $stockLote = (float) ($loteItem['stock_lote'] ?? 0);
            if ($stockLote <= 0) {
                continue;
            }

            $consumo = min($stockLote, $pendiente);
            $this->decrementarStockLoteEspecifico($db, $idItem, $idAlmacen, (string) ($loteItem['lote'] ?? ''), $consumo);
            $pendiente -= $consumo;
        }

        if ($pendiente > 0) {
            throw new RuntimeException('Stock de lotes insuficiente en este almacén para realizar la salida.');
        }
    }

    private function decrementarStockLoteEspecifico(PDO $db, int $idItem, int $idAlmacen, string $lote, float $cantidad): void
    {
        if ($lote === '') {
            throw new RuntimeException('Debe seleccionar un lote válido para la salida.');
        }

        $sqlStock = 'SELECT stock_lote
                     FROM inventario_lotes
                     WHERE id_item = :id_item
                       AND id_almacen = :id_almacen
                       AND lote = :lote
                     LIMIT 1';
        $stmtStock = $db->prepare($sqlStock);
        $stmtStock->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'lote' => $lote,
        ]);
        $stockLote = (float) ($stmtStock->fetchColumn() ?: 0);

        if ($stockLote < $cantidad) {
            throw new RuntimeException('Stock insuficiente en el lote seleccionado de este almacén.');
        }

        $sql = 'UPDATE inventario_lotes
                SET stock_lote = stock_lote - :cantidad
                WHERE id_item = :id_item
                  AND id_almacen = :id_almacen
                  AND lote = :lote';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'cantidad' => $cantidad,
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'lote' => $lote,
        ]);
    }

    private function esFechaValida(string $fecha): bool
    {
        $dt = DateTime::createFromFormat('Y-m-d', $fecha);
        return $dt instanceof DateTime && $dt->format('Y-m-d') === $fecha;
    }

    private function construirReferencia(string $referencia, string $lote, string $fechaVencimiento, float $costoUnitario, float $costoTotal): string
    {
        $partes = [];

        if ($referencia !== '') {
            $partes[] = $referencia;
        }

        if ($lote !== '') {
            $partes[] = 'Lote: ' . $lote;
        }

        if ($fechaVencimiento !== '') {
            $partes[] = 'Vence: ' . $fechaVencimiento;
        }

        if ($costoUnitario > 0) {
            $partes[] = 'C.Unit: ' . number_format($costoUnitario, 4, '.', '');
            $partes[] = 'C.Total: ' . number_format($costoTotal, 4, '.', '');
        }

        return implode(' | ', $partes);
    }

    private function ajustarStock(PDO $db, int $idItem, int $idAlmacen, float $delta): void
    {
        $sql = 'INSERT INTO inventario_stock (id_item, id_almacen, stock_actual)
                VALUES (:id_item, :id_almacen, :stock_actual)
                ON DUPLICATE KEY UPDATE stock_actual = stock_actual + VALUES(stock_actual)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'stock_actual' => $delta,
        ]);
    }

    private function ajustarStockPack(PDO $db, int $idPack, int $idAlmacen, float $delta): void
    {
        $sql = 'INSERT INTO inventario_stock (id_pack, id_almacen, stock_actual)
                VALUES (:id_pack, :id_almacen, :stock_actual)
                ON DUPLICATE KEY UPDATE stock_actual = stock_actual + VALUES(stock_actual)';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_pack' => $idPack,
            'id_almacen' => $idAlmacen,
            'stock_actual' => $delta,
        ]);
    }


    private function tablaExiste(string $tabla): bool
    {
        static $cache = [];

        $tabla = trim($tabla);
        if ($tabla === '') {
            return false;
        }

        if (array_key_exists($tabla, $cache)) {
            return $cache[$tabla];
        }

        $stmt = $this->db()->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabla LIMIT 1');
        $stmt->execute(['tabla' => $tabla]);
        $cache[$tabla] = (bool) $stmt->fetchColumn();

        return $cache[$tabla];
    }

}
