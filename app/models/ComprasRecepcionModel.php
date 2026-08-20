<?php

declare(strict_types=1);

class ComprasRecepcionModel extends Modelo
{
    /**
     * Registra el ingreso de mercadería al almacén desde una orden de compra aprobada.
     */
    public function registrarRecepcion(
        int $idOrden,
        array $detalleIngreso,
        bool $cerrarForzado,
        int $userId,
        string $fechaRecepcion = '',
        string $observaciones = ''
    ): int {
        $db = $this->db();

        // 1. Blindaje Inicial antes de abrir transacción
        if ($idOrden <= 0) {
            throw new RuntimeException('ID de Orden inválido para recepcionar.');
        }

        if (empty($detalleIngreso) && !$cerrarForzado) {
            throw new RuntimeException('Debe indicar los productos a ingresar o forzar el cierre de la orden.');
        }

        $fechaDocumento = $this->normalizarFechaRecepcion($fechaRecepcion);
        $fechaDocumentoConHora = $fechaDocumento . ' ' . date('H:i:s');
        $observaciones = trim($observaciones);

        $db->beginTransaction();

        try {
            // 2. Validación de Orden y Proveedor
            $orden = $this->obtenerOrdenAprobada($db, $idOrden);
            if (!$orden) {
                throw new RuntimeException('La orden no existe, ha sido eliminada o no está en estado Aprobada/Recepcionando.');
            }

            if (!$this->proveedorActivo($db, (int) $orden['id_proveedor'])) {
                throw new RuntimeException('No se puede recepcionar: el proveedor se encuentra inactivo.');
            }

            // 3. Generación de Cabecera de Recepción
            $codigoRecepcion = $this->generarCodigoRecepcion($db);
            // Tomamos el primer almacén de la lista como referencia principal
            $idAlmacenPrincipal = !empty($detalleIngreso) ? (int) $detalleIngreso[0]['id_almacen'] : null;

            $sqlRecep = 'INSERT INTO compras_recepciones (
                            codigo, id_orden_compra, id_almacen, fecha_recepcion, observaciones,
                            created_by, updated_by, created_at, updated_at
                         ) VALUES (
                            :codigo, :id_orden, :id_almacen, :fecha_recepcion, :observaciones,
                            :created_by, :updated_by, NOW(), NOW()
                         )';
            $db->prepare($sqlRecep)->execute([
                'codigo'          => $codigoRecepcion,
                'id_orden'        => $idOrden,
                'id_almacen'      => $idAlmacenPrincipal,
                'fecha_recepcion' => $fechaDocumento,
                'observaciones'   => $observaciones !== '' ? $observaciones : null,
                'created_by'      => $userId,
                'updated_by'      => $userId,
            ]);
            $idRecepcion = (int) $db->lastInsertId();

            // 4. Preparación de Sentencias (Optimización N+1)
            $stmtOriginal = $db->prepare('SELECT id_item, id_item_unidad, costo_unitario_pactado, factor_conversion_aplicado, 
                                                 unidad_nombre, COALESCE(cantidad_base_solicitada, cantidad_solicitada) as cant_total 
                                          FROM compras_ordenes_detalle 
                                          WHERE id = ? AND deleted_at IS NULL');

            $stmtDet = $db->prepare('INSERT INTO compras_recepciones_detalle (
                                        id_recepcion, id_item, id_item_unidad, cantidad_recibida,
                                        costo_unitario_real, created_by, updated_by, created_at, updated_at
                                     ) VALUES (
                                        :id_recepcion, :id_item, :id_item_unidad, :cantidad_base,
                                        :costo_unitario, :created_by, :updated_by, NOW(), NOW()
                                     )');

            $stmtMov = $db->prepare('INSERT INTO inventario_movimientos (
                                        tipo_movimiento, id_item, id_item_unidad,
                                        id_almacen_origen, id_almacen_destino, cantidad, costo_unitario, costo_total, 
                                        referencia, created_by, fecha_documento
                                     ) VALUES (
                                        :tipo_movimiento, :id_item, :id_item_unidad,
                                        :id_almacen_origen, :id_almacen_destino, :cantidad_base, :costo_unitario_base, :costo_total, 
                                        :referencia, :created_by, :fecha_documento
                                     )');

            $stmtUpdateOrdenDet = $db->prepare('UPDATE compras_ordenes_detalle
                                                SET cantidad_recibida = COALESCE(cantidad_recibida, 0) + :cantidad_base,
                                                    updated_at = NOW()
                                                WHERE id = :id_detalle');

            // 5. Procesamiento del Detalle
            $codigoOrdenStr = (string) ($orden['codigo'] ?? $idOrden);

            foreach ($detalleIngreso as $linea) {
                $idDetalleOrden = (int) $linea['id_documento_detalle'];
                $idAlmacen = (int) $linea['id_almacen'];
                $cantidadBase = (float) $linea['cantidad']; 
                
                if ($cantidadBase <= 0) continue;

                $stmtOriginal->execute([$idDetalleOrden]);
                $original = $stmtOriginal->fetch(PDO::FETCH_ASSOC);

                if (!$original) {
                    throw new RuntimeException('Una línea enviada no corresponde a esta orden de compra o fue eliminada.');
                }

                $idItem = (int) $original['id_item'];
                $idItemUnidad = !empty($original['id_item_unidad']) ? (int) $original['id_item_unidad'] : null;
                $factor = (float) ($original['factor_conversion_aplicado'] ?? 1);
                if ($factor <= 0) {
                    $factor = 1.0;
                }
                
                // Calculamos el costo prorrateado a la unidad base (si es un regalo, el costo pactado será 0)
                $costoPactado = (float) ($original['costo_unitario_pactado'] ?? 0);
                $costoUnitarioBase = $factor > 0 ? ($costoPactado / $factor) : $costoPactado;
                $costoTotal = $cantidadBase * $costoUnitarioBase;

                // A. Insertamos en el detalle de la recepción
                $stmtDet->execute([
                    'id_recepcion'   => $idRecepcion,
                    'id_item'        => $idItem,
                    'id_item_unidad' => $idItemUnidad,
                    'cantidad_base'  => $cantidadBase,
                    'costo_unitario' => $costoUnitarioBase,
                    'created_by'     => $userId,
                    'updated_by'     => $userId,
                ]);

                // B. Generamos el movimiento de kardex (Ingreso por Compra)
                $referencia = 'Recepción ' . $codigoRecepcion . ' - OC ' . $codigoOrdenStr . ' | Ingreso: ' . $cantidadBase . ' UND';
                if ($observaciones !== '') {
                    $referencia .= ' | Obs: ' . mb_substr($observaciones, 0, 100);
                }

                $stmtMov->execute([
                    'tipo_movimiento'     => 'COM',
                    'id_item'             => $idItem,
                    'id_item_unidad'      => $idItemUnidad,
                    'id_almacen_origen'   => null, // Entra desde el exterior
                    'id_almacen_destino'  => $idAlmacen,
                    'cantidad_base'       => $cantidadBase,
                    'costo_unitario_base' => $costoUnitarioBase,
                    'costo_total'         => $costoTotal,
                    'referencia'          => $referencia,
                    'created_by'          => $userId,
                    'fecha_documento'     => $fechaDocumentoConHora,
                ]);

                // C. Actualizamos el Stock Físico
                $this->actualizarStock($db, $idItem, $idAlmacen, $cantidadBase, $userId);

                // D. Actualizamos el acumulado en la Orden
                $stmtUpdateOrdenDet->execute([
                    'cantidad_base' => $cantidadBase,
                    'id_detalle'    => $idDetalleOrden,
                ]);
            }

            // 6. Verificación de Estado Final de la Orden
            $stmtPendientes = $db->prepare('SELECT COUNT(*) 
                                            FROM compras_ordenes_detalle 
                                            WHERE id_orden = :id_orden 
                                              AND (COALESCE(cantidad_base_solicitada, cantidad_solicitada) - COALESCE(cantidad_recibida, 0)) > 0.001 
                                              AND deleted_at IS NULL');
            $stmtPendientes->execute(['id_orden' => $idOrden]);
            $lineasConRecepcionPendiente = (int) $stmtPendientes->fetchColumn();

            $nuevoEstado = ($lineasConRecepcionPendiente === 0 || $cerrarForzado) ? 3 : 2;

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

    private function obtenerOrdenAprobada(PDO $db, int $idOrden): array
    {
        $sql = 'SELECT id, codigo, id_proveedor, total, estado, fecha_emision 
                FROM compras_ordenes 
                WHERE id = :id 
                  AND estado = 2 
                  AND deleted_at IS NULL 
                LIMIT 1 
                FOR UPDATE';
        $stmt = $db->prepare($sql);
        $stmt->execute(['id' => $idOrden]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarAlmacenesActivos(): array
    {
        $sql = 'SELECT id, nombre FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    private function normalizarFechaRecepcion(string $fecha): string
    {
        $fecha = trim($fecha);

        if ($fecha === '') {
            return date('Y-m-d');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha;
        }

        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fecha, $m)) {
            $normalizada = sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalizada)) {
                return $normalizada;
            }
        }

        return date('Y-m-d');
    }

    private function actualizarStock(PDO $db, int $idItem, int $idAlmacen, float $cantidadBase, int $userId): void
    {
        $sql = 'INSERT INTO inventario_stock (id_item, id_almacen, stock_actual, created_by, updated_by, created_at, updated_at)
                VALUES (:id_item, :id_almacen, :cantidad, :created_by, :updated_by, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                    stock_actual = stock_actual + VALUES(stock_actual),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()';
        
        $db->prepare($sql)->execute([
            'id_item'    => $idItem,
            'id_almacen' => $idAlmacen,
            'cantidad'   => $cantidadBase,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    private function generarCodigoRecepcion(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM compras_recepciones')->fetchColumn() + 1;
        return sprintf('RC-%s-%05d', date('Ymd'), $correlativo);
    }
}