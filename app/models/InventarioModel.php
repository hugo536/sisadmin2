<?php
declare(strict_types=1);

class InventarioModel extends Modelo
{
    public function obtenerStock(): array
    {
        // CORRECCIÓN PRINCIPAL: Ahora la subconsulta de lotes se filtra por 'a.id' (el almacén actual de la fila)
        $sql = 'SELECT i.id AS id_item,
                       i.sku,
                       COALESCE(NULLIF(TRIM(i.nombre), \'\'), NULLIF(TRIM(i.descripcion), \'\')) AS item_nombre,
                       i.nombre AS item_nombre_base,
                       i.descripcion AS item_descripcion,
                       i.estado AS item_estado,
                       a.id AS id_almacen,
                       a.nombre AS almacen_nombre,
                       i.stock_minimo,
                       i.requiere_vencimiento,
                       i.dias_alerta_vencimiento,
                       COALESCE(s.stock_actual, 0) AS stock_actual,
                       (
                           SELECT l.lote
                           FROM inventario_lotes l
                           WHERE l.id_item = i.id
                             AND l.id_almacen = a.id  -- AHORA SÍ: Lote específico de este almacén
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
                             AND l.id_almacen = a.id  -- AHORA SÍ: Vencimiento específico de este almacén
                             AND l.stock_lote > 0
                             AND l.fecha_vencimiento IS NOT NULL
                       ) AS proximo_vencimiento
                FROM items i
                CROSS JOIN almacenes a
                LEFT JOIN inventario_stock s ON s.id_item = i.id AND s.id_almacen = a.id
                WHERE i.controla_stock = 1
                  AND a.estado = 1
                ORDER BY i.nombre ASC, a.nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarItems(): array
    {
        $sql = 'SELECT id, sku, nombre, controla_stock, requiere_lote, requiere_vencimiento
                FROM items
                WHERE estado = 1
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarItems(string $termino, int $limite = 20): array
    {
        $sql = 'SELECT id, sku, nombre, requiere_lote, requiere_vencimiento
                FROM items
                WHERE estado = 1
                  AND controla_stock = 1
                  AND (
                    sku LIKE :termino
                    OR nombre LIKE :termino
                  )
                ORDER BY nombre ASC
                LIMIT :limite';

        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limite', max(5, min(100, $limite)), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
        $db->beginTransaction();

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

            // LÓGICA ACTUALIZADA PARA MANEJAR LOTES POR ALMACÉN

            if (in_array($tipo, ['INI', 'AJ+'], true)) {
                $this->ajustarStock($db, $idItem, $idAlmacenDestino, $cantidad);
                // Ahora pasamos el ID del almacén destino
                $this->incrementarStockLote($db, $idItem, $idAlmacenDestino, $lote, $fechaVencimiento !== '' ? $fechaVencimiento : null, $cantidad);
            }

            if (in_array($tipo, ['AJ-', 'CON'], true)) {
                $this->validarStockDisponible($db, $idItem, $idAlmacenOrigen, $cantidad);
                $this->ajustarStock($db, $idItem, $idAlmacenOrigen, -$cantidad);
                // Ahora pasamos el ID del almacén origen para descontar del lote correcto
                $this->decrementarStockLote($db, $idItem, $idAlmacenOrigen, $lote, $cantidad);
            }

            if ($tipo === 'TRF') {
                $this->validarStockDisponible($db, $idItem, $idAlmacenOrigen, $cantidad);
                
                // 1. Sacar de Origen
                $this->ajustarStock($db, $idItem, $idAlmacenOrigen, -$cantidad);
                $this->decrementarStockLote($db, $idItem, $idAlmacenOrigen, $lote, $cantidad);
                
                // 2. Ingresar a Destino (La transferencia mueve el lote tal cual)
                $this->ajustarStock($db, $idItem, $idAlmacenDestino, $cantidad);
                
                // NOTA: En transferencia, mantenemos el vencimiento original del lote si ya existe,
                // si es nuevo en destino, deberíamos saber el vencimiento. 
                // Aquí asumimos que el lote se mueve con sus propiedades.
                // Idealmente deberías recuperar el vencimiento del lote origen si no viene en el form.
                $vencimientoLote = $fechaVencimiento !== '' ? $fechaVencimiento : $this->obtenerVencimientoLote($db, $idItem, $idAlmacenOrigen, $lote);
                
                $this->incrementarStockLote($db, $idItem, $idAlmacenDestino, $lote, $vencimientoLote, $cantidad);
            }

            $idMovimiento = (int) $db->lastInsertId();
            $db->commit();

            return $idMovimiento;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
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

    // NUEVO HELPER PARA OBTENER VENCIMIENTO EN TRANSFERENCIAS
    private function obtenerVencimientoLote(PDO $db, int $idItem, int $idAlmacen, string $lote): ?string
    {
        if ($lote === '') return null;
        
        $sql = 'SELECT fecha_vencimiento FROM inventario_lotes 
                WHERE id_item = :id_item AND id_almacen = :id_almacen AND lote = :lote LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id_item' => $idItem, 'id_almacen' => $idAlmacen, 'lote' => $lote]);
        return $stmt->fetchColumn() ?: null;
    }

    // AHORA RECIBE ID_ALMACEN
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

    // AHORA RECIBE ID_ALMACEN PARA DESCONTAR DEL LUGAR CORRECTO
    private function decrementarStockLote(PDO $db, int $idItem, int $idAlmacen, string $lote, float $cantidad): void
    {
        if ($lote !== '') {
            $this->decrementarStockLoteEspecifico($db, $idItem, $idAlmacen, $lote, $cantidad);
            return;
        }

        // Si no se especifica lote, aplicamos FIFO pero SOLO dentro del almacén origen
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

        // Validación estricta por almacén
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