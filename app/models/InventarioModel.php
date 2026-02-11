<?php
declare(strict_types=1);

class InventarioModel extends Modelo
{
    public function obtenerStock(): array
    {
        $sql = 'SELECT s.id_item,
                       s.id_almacen,
                       s.stock_actual,
                       i.sku,
                       i.nombre AS item_nombre,
                       a.nombre AS almacen_nombre
                FROM inventario_stock s
                INNER JOIN items i ON i.id = s.id_item
                INNER JOIN almacenes a ON a.id = s.id_almacen
                ORDER BY i.nombre ASC, a.nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarItems(): array
    {
        $sql = 'SELECT id, sku, nombre, controla_stock
                FROM items
                WHERE estado = 1
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
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
            $this->validarItemControlaStock($db, $idItem);

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
                'referencia' => $referencia !== '' ? $referencia : null,
                'created_by' => $createdBy,
            ]);

            if (in_array($tipo, ['INI', 'AJ+'], true)) {
                $this->ajustarStock($db, $idItem, $idAlmacenDestino, $cantidad);
            }

            if (in_array($tipo, ['AJ-', 'CON'], true)) {
                $this->validarStockDisponible($db, $idItem, $idAlmacenOrigen, $cantidad);
                $this->ajustarStock($db, $idItem, $idAlmacenOrigen, -$cantidad);
            }

            if ($tipo === 'TRF') {
                $this->validarStockDisponible($db, $idItem, $idAlmacenOrigen, $cantidad);
                $this->ajustarStock($db, $idItem, $idAlmacenOrigen, -$cantidad);
                $this->ajustarStock($db, $idItem, $idAlmacenDestino, $cantidad);
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

    private function validarItemControlaStock(PDO $db, int $idItem): void
    {
        $sql = 'SELECT controla_stock
                FROM items
                WHERE id = :id_item
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

        $controlaStock = $stmt->fetchColumn();

        if ($controlaStock === false) {
            throw new RuntimeException('El ítem seleccionado no existe.');
        }

        if ((int) $controlaStock !== 1) {
            throw new RuntimeException('El ítem seleccionado no controla stock.');
        }
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
