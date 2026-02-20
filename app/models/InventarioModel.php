<?php
declare(strict_types=1);

class InventarioModel extends Modelo
{

    public function obtenerStock(int $idAlmacen = 0): array
    {
        if ($idAlmacen > 0) {
            $sql = 'SELECT i.id AS id_item,
                           i.sku,
                           CONCAT(
                               i.nombre,
                               CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != \'Ninguno\' THEN CONCAT(\' \', sbr.nombre) ELSE \'\' END,
                               CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(\' \', prs.nombre) ELSE \'\' END
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
                           \'item\' AS tipo_registro,
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
                           ) AS proximo_vencimiento
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
                    UNION ALL
                    SELECT 
                        p.id AS id_item, 
                        p.codigo_presentacion AS sku,
                        COALESCE(
                            p.nombre_manual,
                            CONCAT(
                                i.nombre,
                                CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != \'Ninguno\' THEN CONCAT(\' \' , sbr.nombre) ELSE \'\' END,
                                CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(\' \' , prs.nombre) ELSE \'\' END,
                                \' x \', CAST(p.factor AS UNSIGNED)
                            )
                        ) AS item_nombre,
                        COALESCE(
                            p.nombre_manual,
                            CONCAT(
                                i.nombre,
                                CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != \'Ninguno\' THEN CONCAT(\' \' , sbr.nombre) ELSE \'\' END,
                                CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(\' \' , prs.nombre) ELSE \'\' END,
                                \' x \', CAST(p.factor AS UNSIGNED)
                            )
                        ) AS item_nombre_base,
                        \'Pack Comercial\' AS item_descripcion,
                        p.estado AS item_estado,
                        a.id AS id_almacen,
                        a.nombre AS almacen_nombre,
                        p.stock_minimo AS stock_minimo,
                        p.requiere_vencimiento,
                        p.dias_vencimiento_alerta AS dias_alerta_vencimiento,
                        1 AS controla_stock,
                        0 AS permite_decimales, 
                        \'pack\' AS tipo_registro,
                        COALESCE(sp.stock_actual, 0) AS stock_actual,
                        NULL AS lote_actual,
                        NULL AS proximo_vencimiento
                    FROM precios_presentaciones p
                    LEFT JOIN items i ON i.id = p.id_item
                    LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
                    LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
                    INNER JOIN almacenes a ON a.id = :id_almacen_pack AND a.estado = 1 AND a.deleted_at IS NULL
                    LEFT JOIN inventario_stock sp ON sp.id_pack = p.id AND sp.id_almacen = :id_almacen_stock_pack
                    WHERE p.estado = 1
                      AND p.deleted_at IS NULL
                      AND sp.id IS NOT NULL
                    ORDER BY item_nombre ASC';

            $stmt = $this->db()->prepare($sql);
            $stmt->bindValue(':id_almacen', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_lote', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_venc', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_stock', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_mov_item', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_pack', $idAlmacen, PDO::PARAM_INT);
            $stmt->bindValue(':id_almacen_stock_pack', $idAlmacen, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $sql = 'SELECT i.id AS id_item,
                       i.sku,
                       CONCAT(
                           i.nombre,
                           CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != \'Ninguno\' THEN CONCAT(\' \', sbr.nombre) ELSE \'\' END,
                           CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(\' \', prs.nombre) ELSE \'\' END
                       ) AS item_nombre,
                       i.nombre AS item_nombre_base,
                       i.descripcion AS item_descripcion,
                       i.estado AS item_estado,
                       0 AS id_almacen,
                       \'Global / Todos\' AS almacen_nombre,
                       i.stock_minimo,
                       i.requiere_vencimiento,
                       i.dias_alerta_vencimiento,
                       i.controla_stock,
                       i.permite_decimales,
                       \'item\' AS tipo_registro,
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
                       ) AS proximo_vencimiento
                FROM items i
                LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
                LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
                LEFT JOIN inventario_stock s ON s.id_item = i.id
                LEFT JOIN almacenes a ON a.id = s.id_almacen
                WHERE i.controla_stock = 1
                  AND i.deleted_at IS NULL
                GROUP BY i.id, i.sku, i.nombre, sbr.nombre, prs.nombre, i.descripcion, i.estado, i.stock_minimo, i.requiere_vencimiento, i.dias_alerta_vencimiento, i.controla_stock, i.permite_decimales
                UNION ALL
                SELECT 
                    p.id AS id_item, 
                    p.codigo_presentacion AS sku,
                    COALESCE(
                        p.nombre_manual,
                        CONCAT(
                            i.nombre,
                            CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != \'Ninguno\' THEN CONCAT(\' \' , sbr.nombre) ELSE \'\' END,
                            CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(\' \' , prs.nombre) ELSE \'\' END,
                            \' x \', CAST(p.factor AS UNSIGNED)
                        )
                    ) AS item_nombre,
                    COALESCE(
                        p.nombre_manual,
                        CONCAT(
                            i.nombre,
                            CASE WHEN sbr.nombre IS NOT NULL AND sbr.nombre != \'Ninguno\' THEN CONCAT(\' \' , sbr.nombre) ELSE \'\' END,
                            CASE WHEN prs.nombre IS NOT NULL THEN CONCAT(\' \' , prs.nombre) ELSE \'\' END,
                            \' x \', CAST(p.factor AS UNSIGNED)
                        )
                    ) AS item_nombre_base,
                    \'Pack Comercial\' AS item_descripcion,
                    p.estado AS item_estado,
                    0 AS id_almacen,
                    \'Global / Todos\' AS almacen_nombre,
                    p.stock_minimo AS stock_minimo,
                    p.requiere_vencimiento,
                    p.dias_vencimiento_alerta AS dias_alerta_vencimiento,
                    1 AS controla_stock,
                    0 AS permite_decimales,
                    \'pack\' AS tipo_registro,
                    COALESCE(SUM(CASE WHEN a.estado = 1 AND a.deleted_at IS NULL THEN sp.stock_actual ELSE 0 END), 0) AS stock_actual,
                    NULL AS lote_actual,
                    NULL AS proximo_vencimiento
                FROM precios_presentaciones p
                LEFT JOIN items i ON i.id = p.id_item
                LEFT JOIN item_sabores sbr ON i.id_sabor = sbr.id
                LEFT JOIN item_presentaciones prs ON i.id_presentacion = prs.id
                LEFT JOIN inventario_stock sp ON sp.id_pack = p.id
                LEFT JOIN almacenes a ON a.id = sp.id_almacen
                WHERE p.estado = 1 
                  AND p.deleted_at IS NULL
                GROUP BY p.id, p.codigo_presentacion, p.nombre_manual, i.nombre, sbr.nombre, prs.nombre, p.factor, p.estado, p.stock_minimo, p.requiere_vencimiento, p.dias_vencimiento_alerta
                ORDER BY item_nombre ASC';

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
        $limite = (int)$limite;
        $busqueda = '%' . $termino . '%'; // Guardamos el valor para usarlo 2 veces

        $sql = "SELECT id, sku, nombre, tipo_item AS tipo, requiere_lote, requiere_vencimiento
                FROM items
                WHERE estado = 1
                AND deleted_at IS NULL
                AND (sku LIKE :termino_sku OR nombre LIKE :termino_nombre)
                ORDER BY nombre ASC
                LIMIT $limite"; 

        $stmt = $this->db()->prepare($sql);
        // Vinculamos cada parámetro por separado
        $stmt->bindValue(':termino_sku', $busqueda, PDO::PARAM_STR);
        $stmt->bindValue(':termino_nombre', $busqueda, PDO::PARAM_STR);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    public function obtenerStockPorItemAlmacen(int $idItem, int $idAlmacen): float
    {
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

        $idItem = (int) ($datos['id_item'] ?? 0);
        $idAlmacenOrigen = isset($datos['id_almacen_origen']) ? (int) $datos['id_almacen_origen'] : 0;
        $idAlmacenDestino = isset($datos['id_almacen_destino']) ? (int) $datos['id_almacen_destino'] : 0;
        $cantidad = (float) ($datos['cantidad'] ?? 0);
        $referencia = trim((string) ($datos['referencia'] ?? ''));
        $lote = trim((string) ($datos['lote'] ?? ''));
        $fechaVencimiento = isset($datos['fecha_vencimiento']) ? trim((string) $datos['fecha_vencimiento']) : '';
        $costoUnitario = isset($datos['costo_unitario']) ? (float) $datos['costo_unitario'] : 0.0;
        $createdBy = (int) ($datos['created_by'] ?? 0);

        if ($idItem <= 0 || $cantidad <= 0 || $createdBy <= 0) {
            throw new InvalidArgumentException('Datos incompletos para registrar el movimiento.');
        }

        if (in_array($tipo, ['INI', 'AJ+'], true) && $idAlmacenDestino <= 0) {
            throw new InvalidArgumentException('Debe seleccionar almacén destino.');
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
            $configItem = $this->obtenerConfiguracionItem($db, $idItem);

            if ((int) ($configItem['requiere_lote'] ?? 0) === 1 && $lote === '') {
                throw new InvalidArgumentException('El ítem requiere lote para registrar movimientos.');
            }

            if ((int) ($configItem['requiere_vencimiento'] ?? 0) === 1 && in_array($tipo, ['INI', 'AJ+'], true) && $fechaVencimiento === '') {
                throw new InvalidArgumentException('El ítem requiere fecha de vencimiento para entradas de stock.');
            }

            if ($fechaVencimiento !== '' && !$this->esFechaValida($fechaVencimiento)) {
                throw new InvalidArgumentException('La fecha de vencimiento no tiene un formato válido (YYYY-MM-DD).');
            }

            $costoTotal = $cantidad * $costoUnitario;
            $referenciaFinal = $this->construirReferencia($referencia, $lote, $fechaVencimiento, $costoUnitario, $costoTotal);

            $sqlMovimiento = 'INSERT INTO inventario_movimientos
                                (id_item, id_almacen_origen, id_almacen_destino, tipo_movimiento, cantidad, referencia, created_by)
                              VALUES
                                (:id_item, :id_almacen_origen, :id_almacen_destino, :tipo_movimiento, :cantidad, :referencia, :created_by)';
            $stmtMov = $db->prepare($sqlMovimiento);
            $stmtMov->execute([
                'id_item' => $idItem,
                'id_almacen_origen' => $idAlmacenOrigen > 0 ? $idAlmacenOrigen : null,
                'id_almacen_destino' => $idAlmacenDestino > 0 ? $idAlmacenDestino : null,
                'tipo_movimiento' => $tipo,
                'cantidad' => $cantidad,
                'referencia' => $referenciaFinal !== '' ? $referenciaFinal : null,
                'created_by' => $createdBy,
            ]);

            if (in_array($tipo, ['INI', 'AJ+'], true)) {
                $this->ajustarStock($db, $idItem, $idAlmacenDestino, $cantidad);
                $this->incrementarStockLote($db, $idItem, $idAlmacenDestino, $lote, $fechaVencimiento !== '' ? $fechaVencimiento : null, $cantidad);
            }

            if (in_array($tipo, ['AJ-', 'CON'], true)) {
                $this->validarStockDisponible($db, $idItem, $idAlmacenOrigen, $cantidad);
                $this->ajustarStock($db, $idItem, $idAlmacenOrigen, -$cantidad);
                $this->decrementarStockLote($db, $idItem, $idAlmacenOrigen, $lote, $cantidad);
            }

            if ($tipo === 'TRF') {
                $this->validarStockDisponible($db, $idItem, $idAlmacenOrigen, $cantidad);
                
                $this->ajustarStock($db, $idItem, $idAlmacenOrigen, -$cantidad);
                $this->decrementarStockLote($db, $idItem, $idAlmacenOrigen, $lote, $cantidad);
                
                $this->ajustarStock($db, $idItem, $idAlmacenDestino, $cantidad);
                
                $vencimientoLote = $fechaVencimiento !== '' ? $fechaVencimiento : $this->obtenerVencimientoLote($db, $idItem, $idAlmacenOrigen, $lote);
                
                $this->incrementarStockLote($db, $idItem, $idAlmacenDestino, $lote, $vencimientoLote, $cantidad);
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
}
