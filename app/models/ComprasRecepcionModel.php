<?php

declare(strict_types=1);

class ComprasRecepcionModel extends Modelo
{
    public function registrarRecepcion(int $idOrden, int $idAlmacen, int $userId): int
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $orden = $this->obtenerOrdenAprobada($db, $idOrden);
            if (!$orden) {
                throw new RuntimeException('La orden no existe o no está aprobada para recepcionar.');
            }

            if (!$this->proveedorActivo($db, (int) $orden['id_proveedor'])) {
                throw new RuntimeException('No se puede recepcionar: el proveedor está inactivo.');
            }

            $detalle = $this->obtenerDetalleOrden($db, $idOrden);
            if (empty($detalle)) {
                throw new RuntimeException('La orden no tiene detalle para recepcionar.');
            }

            $codigo = $this->generarCodigoRecepcion($db);

            // INSERT Cabecera Recepción
            // Corregido: id_orden_compra en lugar de id_orden. Sin campo 'total'.
            $sqlRecep = 'INSERT INTO compras_recepciones (
                            codigo,
                            id_orden_compra,
                            id_almacen,
                            fecha_recepcion,
                            created_by,
                            updated_by,
                            created_at,
                            updated_at
                          ) VALUES (
                            :codigo,
                            :id_orden,
                            :id_almacen,
                            NOW(),
                            :created_by,
                            :updated_by,
                            NOW(),
                            NOW()
                          )';

            $stmtRecep = $db->prepare($sqlRecep);
            $stmtRecep->execute([
                'codigo' => $codigo,
                'id_orden' => $idOrden,
                'id_almacen' => $idAlmacen,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $idRecepcion = (int) $db->lastInsertId();

            // INSERT Detalle Recepción
            // Corregido: nombres de columnas (cantidad_recibida, costo_unitario_real) y sin subtotal.
            $sqlDet = 'INSERT INTO compras_recepciones_detalle (
                        id_recepcion,
                        id_item,
                        cantidad_recibida,
                        costo_unitario_real,
                        lote,
                        fecha_vencimiento,
                        created_by,
                        updated_by,
                        created_at,
                        updated_at
                       ) VALUES (
                        :id_recepcion,
                        :id_item,
                        :cantidad,
                        :costo_unitario,
                        :lote,
                        :fecha_vencimiento,
                        :created_by,
                        :updated_by,
                        NOW(),
                        NOW()
                       )';
            $stmtDet = $db->prepare($sqlDet);

            // INSERT Movimiento Inventario
            $sqlMov = 'INSERT INTO inventario_movimientos (
                            tipo_movimiento,
                            id_item,
                            id_almacen_origen,
                            id_almacen_destino,
                            cantidad,
                            referencia,
                            created_by
                       ) VALUES (
                            :tipo_movimiento,
                            :id_item,
                            :id_almacen_origen,
                            :id_almacen_destino,
                            :cantidad,
                            :referencia,
                            :created_by
                       )';
            $stmtMov = $db->prepare($sqlMov);

            // UPDATE Orden de Compra Detalle (Para actualizar lo recibido)
            $sqlUpdateOrdenDet = 'UPDATE compras_ordenes_detalle 
                                  SET cantidad_recibida = cantidad_recibida + :cantidad 
                                  WHERE id_orden = :id_orden AND id_item = :id_item';
            $stmtUpdateOrdenDet = $db->prepare($sqlUpdateOrdenDet);


            foreach ($detalle as $linea) {
                // Mapeo de datos (asumiendo que $linea viene con los datos correctos del frontend o de la orden)
                // Nota: Aquí se asume que recibimos todo lo de la orden.
                // Si la recepción es parcial, la lógica del frontend debe enviar 'cantidad_recibir'
                $cantidad = (float) $linea['cantidad']; 
                $costo = (float) $linea['costo_unitario'];
                
                // Campos opcionales
                $lote = isset($linea['lote']) ? $linea['lote'] : null;
                $fechaVencimiento = isset($linea['fecha_vencimiento']) ? $linea['fecha_vencimiento'] : null;

                // 1. Guardar detalle de recepción
                $stmtDet->execute([
                    'id_recepcion' => $idRecepcion,
                    'id_item' => (int) $linea['id_item'],
                    'cantidad' => $cantidad,
                    'costo_unitario' => $costo,
                    'lote' => $lote,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // 2. Generar Movimiento (Kardex)
                $stmtMov->execute([
                    'tipo_movimiento' => 'INI', // O 'COM' (Compra) según tu lógica
                    'id_item' => (int) $linea['id_item'],
                    'id_almacen_origen' => null,
                    'id_almacen_destino' => $idAlmacen,
                    'cantidad' => $cantidad,
                    'referencia' => 'Recepción ' . $codigo . ' - OC ' . (string) $orden['codigo'],
                    'created_by' => $userId,
                ]);

                // 3. Actualizar Stock Físico
                $this->actualizarStock($db, (int) $linea['id_item'], $idAlmacen, $cantidad);

                // 4. Actualizar acumulado recibido en la Orden de Compra
                $stmtUpdateOrdenDet->execute([
                    'cantidad' => $cantidad,
                    'id_orden' => $idOrden,
                    'id_item' => (int) $linea['id_item']
                ]);
            }

            // Cambiar estado de Orden a 3 (Recepcionado / Cerrado)
            // Nota: En un sistema más complejo, validarías si ya se recibió todo antes de cerrar la orden.
            $db->prepare('UPDATE compras_ordenes SET estado = 3, updated_by = :user, updated_at = NOW() WHERE id = :id_orden AND deleted_at IS NULL')
                ->execute(['user' => $userId, 'id_orden' => $idOrden]);

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
        $sql = 'SELECT id, nombre
                FROM almacenes
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerOrdenAprobada(PDO $db, int $idOrden): array
    {
        $sql = 'SELECT id, codigo, id_proveedor, total, estado
                FROM compras_ordenes
                WHERE id = :id
                  AND estado = 2
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $idOrden]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function proveedorActivo(PDO $db, int $idProveedor): bool
    {
        $sql = 'SELECT id
                FROM terceros
                WHERE id = :id
                  AND es_proveedor = 1
                  AND estado = 1
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $idProveedor]);

        return (bool) $stmt->fetchColumn();
    }

    private function obtenerDetalleOrden(PDO $db, int $idOrden): array
    {
        // Se corrige para traer cantidad_solicitada como 'cantidad' y costo_unitario_pactado como 'costo_unitario'
        // para mantener compatibilidad con el loop de inserción
        $sql = 'SELECT id_item,
                       COALESCE(cantidad_base_solicitada, cantidad_solicitada) AS cantidad,
                       costo_unitario_pactado as costo_unitario
                FROM compras_ordenes_detalle
                WHERE id_orden = :id_orden
                  AND deleted_at IS NULL
                ORDER BY id ASC';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id_orden' => $idOrden]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function actualizarStock(PDO $db, int $idItem, int $idAlmacen, float $cantidad): void
    {
        $sql = 'INSERT INTO inventario_stock (id_item, id_almacen, stock_actual, updated_at)
                VALUES (:id_item, :id_almacen, :cantidad, NOW())
                ON DUPLICATE KEY UPDATE
                    stock_actual = stock_actual + VALUES(stock_actual),
                    updated_at = NOW()';

        $db->prepare($sql)->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
            'cantidad' => $cantidad,
        ]);
    }

    private function generarCodigoRecepcion(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM compras_recepciones')->fetchColumn() + 1;
        return sprintf('RC-%s-%05d', date('Ymd'), $correlativo);
    }
}
