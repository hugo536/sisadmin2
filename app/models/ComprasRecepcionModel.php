<?php

declare(strict_types=1);

class ComprasRecepcionModel extends Modelo
{
    public function registrarRecepcion(int $idOrden, array $detalleIngreso, bool $cerrarForzado, int $userId): int
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $orden = $this->obtenerOrdenAprobada($db, $idOrden);
            if (!$orden) {
                throw new RuntimeException('La orden no existe o no está en estado válido para recepcionar.');
            }

            if (!$this->proveedorActivo($db, (int) $orden['id_proveedor'])) {
                throw new RuntimeException('No se puede recepcionar: el proveedor está inactivo.');
            }

            if (empty($detalleIngreso)) {
                throw new RuntimeException('Debe indicar los productos a ingresar.');
            }

            $codigo = $this->generarCodigoRecepcion($db);
            // Usamos el primer almacén de la lista para la cabecera
            $idAlmacenPrincipal = (int) $detalleIngreso[0]['id_almacen'];

            $sqlRecep = 'INSERT INTO compras_recepciones (
                            codigo, id_orden_compra, id_almacen, fecha_recepcion,
                            created_by, updated_by, created_at, updated_at
                          ) VALUES (
                            :codigo, :id_orden, :id_almacen, NOW(),
                            :created_by, :updated_by, NOW(), NOW()
                          )';
            $db->prepare($sqlRecep)->execute([
                'codigo' => $codigo,
                'id_orden' => $idOrden,
                'id_almacen' => $idAlmacenPrincipal,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $idRecepcion = (int) $db->lastInsertId();

            $sqlDet = 'INSERT INTO compras_recepciones_detalle (
                        id_recepcion, id_item, id_item_unidad, cantidad_recibida,
                        costo_unitario_real, created_by, updated_by, created_at, updated_at
                       ) VALUES (
                        :id_recepcion, :id_item, :id_item_unidad, :cantidad_base,
                        :costo_unitario, :created_by, :updated_by, NOW(), NOW()
                       )';
            $stmtDet = $db->prepare($sqlDet);

            $sqlMov = 'INSERT INTO inventario_movimientos (
                        tipo_movimiento, id_item, id_item_unidad,
                        id_almacen_origen, id_almacen_destino, cantidad, referencia, created_by
                       ) VALUES (
                        :tipo_movimiento, :id_item, :id_item_unidad,
                        :id_almacen_origen, :id_almacen_destino, :cantidad_base, :referencia, :created_by
                       )';
            $stmtMov = $db->prepare($sqlMov);

            $sqlUpdateOrdenDet = 'UPDATE compras_ordenes_detalle
                                  SET cantidad_recibida = COALESCE(cantidad_recibida, 0) + :cantidad_base
                                  WHERE id = :id_detalle';
            $stmtUpdateOrdenDet = $db->prepare($sqlUpdateOrdenDet);

            foreach ($detalleIngreso as $linea) {
                $idDetalleOrden = (int) $linea['id_documento_detalle'];
                $idAlmacen = (int) $linea['id_almacen'];
                $cantidadBase = (float) $linea['cantidad']; // Ya viene en unidad base desde el JS
                
                // Obtener datos originales del ítem desde la orden
                $stmtOriginal = $db->prepare('SELECT id_item, id_item_unidad, costo_unitario_pactado, factor_conversion_aplicado, unidad_nombre, COALESCE(cantidad_base_solicitada, cantidad_solicitada) as cant_total FROM compras_ordenes_detalle WHERE id = ?');
                $stmtOriginal->execute([$idDetalleOrden]);
                $original = $stmtOriginal->fetch(PDO::FETCH_ASSOC);

                if (!$original) throw new RuntimeException('Línea de orden no encontrada.');

                $idItem = (int) $original['id_item'];
                $idItemUnidad = $original['id_item_unidad'] ? (int) $original['id_item_unidad'] : null;

                $stmtDet->execute([
                    'id_recepcion' => $idRecepcion,
                    'id_item' => $idItem,
                    'id_item_unidad' => $idItemUnidad,
                    'cantidad_base' => $cantidadBase,
                    'costo_unitario' => (float) $original['costo_unitario_pactado'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // Generar referencia descriptiva para el movimiento de inventario
                $codigoOrdenStr = (string) ($orden['codigo'] ?? $idOrden);
                $referencia = 'Recepción ' . $codigo . ' - OC ' . $codigoOrdenStr . ' | Ingreso: ' . $cantidadBase . ' UND';

                $stmtMov->execute([
                    'tipo_movimiento' => 'COM',
                    'id_item' => $idItem,
                    'id_item_unidad' => $idItemUnidad,
                    'id_almacen_origen' => null,
                    'id_almacen_destino' => $idAlmacen,
                    'cantidad_base' => $cantidadBase,
                    'referencia' => $referencia,
                    'created_by' => $userId,
                ]);

                $this->actualizarStock($db, $idItem, $idAlmacen, $cantidadBase);

                $stmtUpdateOrdenDet->execute([
                    'cantidad_base' => $cantidadBase,
                    'id_detalle' => $idDetalleOrden,
                ]);
            }

            // Verificar si aún quedan pendientes por recepcionar
            // Restamos la cantidad solicitada menos la cantidad recibida.
            $stmtPendientes = $db->prepare('SELECT COUNT(*) FROM compras_ordenes_detalle WHERE id_orden = :id_orden AND (COALESCE(cantidad_base_solicitada, cantidad_solicitada) - COALESCE(cantidad_recibida, 0)) > 0.001 AND deleted_at IS NULL');
            $stmtPendientes->execute(['id_orden' => $idOrden]);
            $pendientes = (int) $stmtPendientes->fetchColumn();

            // Si ya no hay pendientes (se recibió todo), o si el usuario forzó el cierre, el estado pasa a 3 (Recepcionada/Cerrada). 
            // Si aún hay saldo pendiente, se mantiene en 2 (Aprobada / Parcial)
            $nuevoEstado = ($pendientes === 0 || $cerrarForzado) ? 3 : 2;

            $db->prepare('UPDATE compras_ordenes SET estado = :estado, updated_by = :user, updated_at = NOW() WHERE id = :id_orden AND deleted_at IS NULL')
                ->execute(['estado' => $nuevoEstado, 'user' => $userId, 'id_orden' => $idOrden]);

            $db->commit();
            return $idRecepcion;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function listarAlmacenesActivos(): array
    {
        $sql = 'SELECT id, nombre FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerOrdenAprobada(PDO $db, int $idOrden): array
    {
        // Permitimos recepcionar órdenes en estado 2 (Aprobada/Parcial)
        $sql = 'SELECT id, codigo, id_proveedor, total, estado FROM compras_ordenes WHERE id = :id AND estado = 2 AND deleted_at IS NULL LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $idOrden]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function proveedorActivo(PDO $db, int $idProveedor): bool
    {
        $sql = 'SELECT id FROM terceros WHERE id = :id AND es_proveedor = 1 AND estado = 1 AND deleted_at IS NULL LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $idProveedor]);
        return (bool) $stmt->fetchColumn();
    }

    // Ya no se usa la versión simple, la estructura actual la dejaremos aquí por si la llamas desde otro lado,
    // pero recuerda que ahora la vista principal obtiene el detalle completo de `ComprasOrdenModel->obtener()`
    private function obtenerDetalleOrden(PDO $db, int $idOrden): array
    {
        $sql = 'SELECT d.id, -- Agregamos el ID del detalle
                       d.id_item,
                       d.id_item_unidad,
                       COALESCE(d.factor_conversion_aplicado, 1) AS factor_conversion_aplicado,
                       d.cantidad_solicitada AS cantidad_unidad,
                       COALESCE(d.cantidad_base_solicitada, d.cantidad_solicitada) AS cantidad_base,
                       COALESCE(d.cantidad_recibida, 0) AS cantidad_recibida,
                       -- Calculamos el pendiente en unidad base
                       (COALESCE(d.cantidad_base_solicitada, d.cantidad_solicitada) - COALESCE(d.cantidad_recibida, 0)) AS cantidad_pendiente,
                       COALESCE(d.unidad_nombre, i.unidad_base, "UND") AS unidad_nombre,
                       COALESCE(i.unidad_base, "UND") AS unidad_base,
                       d.costo_unitario_pactado as costo_unitario,
                       i.sku,
                       i.nombre AS item_nombre
                FROM compras_ordenes_detalle d
                INNER JOIN items i ON i.id = d.id_item
                WHERE d.id_orden = :id_orden
                  AND d.deleted_at IS NULL
                ORDER BY d.id ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id_orden' => $idOrden]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function normalizarNumero(float $valor): string
    {
        if (abs($valor) < 0.0000001) return '0';
        $texto = rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
        return $texto === '' ? '0' : $texto;
    }

    private function actualizarStock(PDO $db, int $idItem, int $idAlmacen, float $cantidadBase): void
    {
        $sql = 'INSERT INTO inventario_stock (id_item, id_almacen, stock_actual, updated_at)
                VALUES (:id_item, :id_almacen, :cantidad, NOW())
                ON DUPLICATE KEY UPDATE
                    stock_actual = stock_actual + VALUES(stock_actual),
                    updated_at = NOW()';
        $db->prepare($sql)->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'cantidad' => $cantidadBase,
        ]);
    }

    private function generarCodigoRecepcion(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM compras_recepciones')->fetchColumn() + 1;
        return sprintf('RC-%s-%05d', date('Ymd'), $correlativo);
    }
}