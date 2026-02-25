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
                'id_almacen' => $idAlmacen,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            $idRecepcion = (int) $db->lastInsertId();

            // PREPARAR SENTENCIAS
            // 1. Detalle de Recepción
            $sqlDet = 'INSERT INTO compras_recepciones_detalle (
                        id_recepcion, id_item, id_item_unidad, cantidad_recibida,
                        costo_unitario_real, lote, fecha_vencimiento,
                        created_by, updated_by, created_at, updated_at
                       ) VALUES (
                        :id_recepcion, :id_item, :id_item_unidad, :cantidad_base,
                        :costo_unitario, :lote, :fecha_vencimiento,
                        :created_by, :updated_by, NOW(), NOW()
                       )';
            $stmtDet = $db->prepare($sqlDet);

            // 2. Movimiento de Inventario (Guardamos id_item_unidad y cantidad base)
            $sqlMov = 'INSERT INTO inventario_movimientos (
                        tipo_movimiento, id_item, id_item_unidad,
                        id_almacen_origen, id_almacen_destino, cantidad, referencia, created_by
                       ) VALUES (
                        :tipo_movimiento, :id_item, :id_item_unidad,
                        :id_almacen_origen, :id_almacen_destino, :cantidad_base, :referencia, :created_by
                       )';
            $stmtMov = $db->prepare($sqlMov);

            // 3. Actualizar Orden de Compra Detalle
            $sqlUpdateOrdenDet = 'UPDATE compras_ordenes_detalle 
                                  SET cantidad_recibida = cantidad_recibida + :cantidad_unidad 
                                  WHERE id_orden = :id_orden AND id_item = :id_item
                                    AND ((:id_item_unidad_null IS NULL AND id_item_unidad IS NULL) OR id_item_unidad = :id_item_unidad_value)';
            $stmtUpdateOrdenDet = $db->prepare($sqlUpdateOrdenDet);

            // PROCESAR CADA LÍNEA
            foreach ($detalle as $linea) {
                $idItem = (int) $linea['id_item'];
                $idItemUnidad = !empty($linea['id_item_unidad']) ? (int) $linea['id_item_unidad'] : null;
                
                // Separación estricta de las cantidades para evitar corrupción de kardex
                $cantidadUnidad = (float) $linea['cantidad_unidad']; // Ej: 2 (Cajas)
                $cantidadBase = (float) $linea['cantidad_base'];     // Ej: 10400 (Tapas)
                $costoUnitario = (float) $linea['costo_unitario'];
                
                $lote = isset($linea['lote']) ? $linea['lote'] : null;
                $fechaVencimiento = isset($linea['fecha_vencimiento']) ? $linea['fecha_vencimiento'] : null;

                // 1. Guardar detalle de recepción
                $stmtDet->execute([
                    'id_recepcion' => $idRecepcion,
                    'id_item' => $idItem,
                    'id_item_unidad' => $idItemUnidad,
                    'cantidad_base' => $cantidadBase,
                    'costo_unitario' => $costoUnitario,
                    'lote' => $lote,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // 2. Generar Movimiento (Kardex)
                $factorConversion = (float) ($linea['factor_conversion_aplicado'] ?? 1);
                $unidadNombre = trim((string) ($linea['unidad_nombre'] ?? 'UND'));
                $unidadBaseTexto = trim((string) ($linea['unidad_base'] ?? 'UND'));

                $referencia = 'Recepción ' . $codigo . ' - OC ' . (string) $orden['codigo'];
                if ($factorConversion > 1) {
                    $referencia .= ' | Conv: ' . $this->normalizarNumero($cantidadUnidad) . ' ' . $unidadNombre
                                 . ' x ' . $this->normalizarNumero($factorConversion)
                                 . ' = ' . $this->normalizarNumero($cantidadBase) . ' ' . $unidadBaseTexto;
                } else {
                    $referencia .= ' | ' . $this->normalizarNumero($cantidadBase) . ' ' . $unidadBaseTexto;
                }

                $stmtMov->execute([
                    'tipo_movimiento' => 'COM', // Compra
                    'id_item' => $idItem,
                    'id_item_unidad' => $idItemUnidad,
                    'id_almacen_origen' => null,
                    'id_almacen_destino' => $idAlmacen,
                    'cantidad_base' => $cantidadBase, // El kardex siempre suma manzanas con manzanas
                    'referencia' => $referencia,
                    'created_by' => $userId,
                ]);

                // 3. Actualizar Stock Físico Global
                $this->actualizarStock($db, $idItem, $idAlmacen, $cantidadBase);

                // 4. Actualizar acumulado recibido en la Orden de Compra
                $stmtUpdateOrdenDet->execute([
                    'cantidad_unidad' => $cantidadUnidad,
                    'id_orden' => $idOrden,
                    'id_item' => $idItem,
                    'id_item_unidad_null' => $idItemUnidad,
                    'id_item_unidad_value' => $idItemUnidad,
                ]);
            }

            // Cambiar estado de Orden a 3 (Recepcionado)
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
        $sql = 'SELECT id, nombre FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerOrdenAprobada(PDO $db, int $idOrden): array
    {
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

    private function obtenerDetalleOrden(PDO $db, int $idOrden): array
    {
        // Optimizamos la consulta para devolver explícitamente la cantidad en unidad y la base
        $sql = 'SELECT d.id_item,
                       d.id_item_unidad,
                       COALESCE(d.factor_conversion_aplicado, 1) AS factor_conversion_aplicado,
                       d.cantidad_solicitada AS cantidad_unidad,
                       COALESCE(d.cantidad_base_solicitada, d.cantidad_solicitada) AS cantidad_base,
                       COALESCE(d.unidad_nombre, i.unidad_base, "UND") AS unidad_nombre,
                       COALESCE(i.unidad_base, "UND") AS unidad_base,
                       d.costo_unitario_pactado as costo_unitario
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