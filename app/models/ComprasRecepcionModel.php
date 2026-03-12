<?php

declare(strict_types=1);

class ComprasRecepcionModel extends Modelo
{
    public function registrarRecepcion(int $idOrden, array $distribucion, int $userId): int
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

            $distribucionValidada = $this->validarDistribucion($distribucion, $detalle);
            $idAlmacenPrincipal = (int) $distribucionValidada[0]['id_almacen'];
            $codigo = $this->generarCodigoRecepcion($db);

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
                        costo_unitario_real, lote, fecha_vencimiento,
                        created_by, updated_by, created_at, updated_at
                       ) VALUES (
                        :id_recepcion, :id_item, :id_item_unidad, :cantidad_base,
                        :costo_unitario, :lote, :fecha_vencimiento,
                        :created_by, :updated_by, NOW(), NOW()
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
                                  SET cantidad_recibida = cantidad_recibida + :cantidad_unidad
                                  WHERE id_orden = :id_orden AND id_item = :id_item
                                    AND ((:id_item_unidad_null IS NULL AND id_item_unidad IS NULL) OR id_item_unidad = :id_item_unidad_value)';
            $stmtUpdateOrdenDet = $db->prepare($sqlUpdateOrdenDet);

            foreach ($detalle as $linea) {
                $idItem = (int) $linea['id_item'];
                $idItemUnidad = !empty($linea['id_item_unidad']) ? (int) $linea['id_item_unidad'] : null;
                $cantidadUnidad = (float) $linea['cantidad_unidad'];
                $cantidadBase = (float) $linea['cantidad_base'];
                $costoUnitario = (float) $linea['costo_unitario'];
                $lote = $linea['lote'] ?? null;
                $fechaVencimiento = $linea['fecha_vencimiento'] ?? null;

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

                $factorConversion = (float) ($linea['factor_conversion_aplicado'] ?? 1);
                $unidadNombre = trim((string) ($linea['unidad_nombre'] ?? 'UND'));
                $unidadBaseTexto = trim((string) ($linea['unidad_base'] ?? 'UND'));
                $referenciaBase = 'Recepción ' . $codigo . ' - OC ' . (string) $orden['codigo'];

                $asignaciones = $this->distribuirCantidadPorAlmacen($cantidadBase, $distribucionValidada);
                foreach ($asignaciones as $asignacion) {
                    if ($asignacion['cantidad'] <= 0) {
                        continue;
                    }

                    $referencia = $referenciaBase;
                    if ($factorConversion > 1) {
                        $referencia .= ' | Conv: ' . $this->normalizarNumero($cantidadUnidad) . ' ' . $unidadNombre
                                     . ' x ' . $this->normalizarNumero($factorConversion)
                                     . ' = ' . $this->normalizarNumero($asignacion['cantidad']) . ' ' . $unidadBaseTexto;
                    } else {
                        $referencia .= ' | ' . $this->normalizarNumero($asignacion['cantidad']) . ' ' . $unidadBaseTexto;
                    }

                    $stmtMov->execute([
                        'tipo_movimiento' => 'COM',
                        'id_item' => $idItem,
                        'id_item_unidad' => $idItemUnidad,
                        'id_almacen_origen' => null,
                        'id_almacen_destino' => (int) $asignacion['id_almacen'],
                        'cantidad_base' => (float) $asignacion['cantidad'],
                        'referencia' => $referencia,
                        'created_by' => $userId,
                    ]);

                    $this->actualizarStock($db, $idItem, (int) $asignacion['id_almacen'], (float) $asignacion['cantidad']);
                }

                $stmtUpdateOrdenDet->execute([
                    'cantidad_unidad' => $cantidadUnidad,
                    'id_orden' => $idOrden,
                    'id_item' => $idItem,
                    'id_item_unidad_null' => $idItemUnidad,
                    'id_item_unidad_value' => $idItemUnidad,
                ]);
            }

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

    private function validarDistribucion(array $distribucion, array $detalle): array
    {
        if (empty($distribucion)) {
            throw new RuntimeException('Debe indicar la distribución por almacenes.');
        }

        $totalCompra = array_reduce($detalle, static function (float $acc, array $linea): float {
            return $acc + (float) ($linea['cantidad_base'] ?? 0);
        }, 0.0);

        $normalizada = [];
        $ids = [];
        $totalDistribuido = 0.0;

        foreach ($distribucion as $fila) {
            $idAlmacen = (int) ($fila['id_almacen'] ?? 0);
            $cantidad = (float) ($fila['cantidad'] ?? 0);

            if ($idAlmacen <= 0) {
                throw new RuntimeException('Todos los almacenes de la distribución son obligatorios.');
            }

            if ($cantidad <= 0) {
                throw new RuntimeException('La cantidad distribuida por almacén debe ser mayor a cero.');
            }

            if (isset($ids[$idAlmacen])) {
                throw new RuntimeException('No puede repetir el mismo almacén en la distribución.');
            }

            $ids[$idAlmacen] = true;
            $totalDistribuido += $cantidad;
            $normalizada[] = [
                'id_almacen' => $idAlmacen,
                'cantidad' => $cantidad,
            ];
        }

        if (abs($totalDistribuido - $totalCompra) > 0.0001) {
            throw new RuntimeException('La suma distribuida por almacenes debe coincidir exactamente con el total comprado.');
        }

        return $normalizada;
    }

    private function distribuirCantidadPorAlmacen(float $cantidadBase, array $distribucion): array
    {
        $totalDistribucion = array_reduce($distribucion, static function (float $acc, array $fila): float {
            return $acc + (float) ($fila['cantidad'] ?? 0);
        }, 0.0);

        if ($totalDistribucion <= 0) {
            return [];
        }

        $asignaciones = [];
        $acumulado = 0.0;
        $ultimo = count($distribucion) - 1;

        foreach ($distribucion as $index => $fila) {
            $idAlmacen = (int) $fila['id_almacen'];
            if ($index === $ultimo) {
                $cantidad = max(0.0, $cantidadBase - $acumulado);
            } else {
                $ratio = (float) $fila['cantidad'] / $totalDistribucion;
                $cantidad = round($cantidadBase * $ratio, 6);
                $acumulado += $cantidad;
            }

            $asignaciones[] = [
                'id_almacen' => $idAlmacen,
                'cantidad' => $cantidad,
            ];
        }

        return $asignaciones;
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
