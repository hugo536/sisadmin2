<?php

declare(strict_types=1);

class VentasDespachoModel extends Modelo
{
    public function registrarDespacho(int $idDocumento, int $idAlmacen, array $lineas, bool $cerrarForzado, string $observaciones, int $userId): int
    {
        if ($idDocumento <= 0 || $idAlmacen <= 0) {
            throw new RuntimeException('Documento o almacén inválido.');
        }

        if ($userId <= 0) {
            throw new RuntimeException('Usuario inválido para registrar despacho.');
        }

        if (empty($lineas)) {
            throw new RuntimeException('Debe indicar al menos una línea a despachar.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $documento = $this->obtenerDocumento($db, $idDocumento);
            if ($documento === []) {
                throw new RuntimeException('El pedido no existe.');
            }

            if ((int) ($documento['estado'] ?? 0) !== 2) {
                throw new RuntimeException('Solo se puede despachar pedidos aprobados.');
            }

            $detalles = $this->obtenerDetallePendiente($db, $idDocumento);
            if ($detalles === []) {
                throw new RuntimeException('El pedido no tiene líneas pendientes por despachar.');
            }

            $mapaDetalle = [];
            foreach ($detalles as $d) {
                $mapaDetalle[(int) $d['id']] = $d;
            }

            $lineasValidas = [];
            foreach ($lineas as $linea) {
                $idDetalle = (int) ($linea['id_documento_detalle'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);

                if ($idDetalle <= 0 || $cantidad <= 0) {
                    continue;
                }

                if (!isset($mapaDetalle[$idDetalle])) {
                    throw new RuntimeException('Una línea no pertenece al pedido.');
                }

                $detalle = $mapaDetalle[$idDetalle];
                $pendiente = (float) ($detalle['cantidad_pendiente'] ?? 0);
                
                // Permitir un pequeño margen de error por decimales
                if ($cantidad > ($pendiente + 0.0001)) {
                    throw new RuntimeException('La cantidad a despachar excede el pendiente del ítem ' . ($detalle['item_nombre'] ?? '')); 
                }

                $stockActual = $this->obtenerStockItem($db, (int) $detalle['id_item'], $idAlmacen);
                if ($cantidad > $stockActual) {
                    throw new RuntimeException('Stock insuficiente para ' . ($detalle['item_nombre'] ?? '') . '. Disponible: ' . number_format($stockActual, 2));
                }

                $lineasValidas[] = [
                    'id_documento_detalle' => $idDetalle,
                    'id_item' => (int) $detalle['id_item'],
                    'cantidad' => $cantidad,
                ];
            }

            if ($lineasValidas === []) {
                throw new RuntimeException('No hay cantidades válidas para despachar.');
            }

            $codigoDespacho = $this->generarCodigo($db);
            
            // CORREGIDO: id_documento_venta en lugar de id_documento
            $sqlDesp = 'INSERT INTO ventas_despachos (
                            codigo,
                            id_documento_venta,
                            id_almacen,
                            fecha_despacho,
                            documento_referencia,
                            created_by,
                            created_at
                        ) VALUES (
                            :codigo,
                            :id_documento,
                            :id_almacen,
                            NOW(),
                            :observaciones,
                            :created_by,
                            NOW()
                        )';

            $db->prepare($sqlDesp)->execute([
                'codigo' => $codigoDespacho,
                'id_documento' => $idDocumento,
                'id_almacen' => $idAlmacen,
                'observaciones' => $observaciones !== '' ? $observaciones : null, // Usamos observaciones como referencia o guía
                'created_by' => $userId,
            ]);

            $idDespacho = (int) $db->lastInsertId();

            $stmtDetalle = $db->prepare('INSERT INTO ventas_despachos_detalle (
                                            id_despacho,
                                            id_item,
                                            cantidad_despachada,
                                            created_at
                                         ) VALUES (
                                            :id_despacho,
                                            :id_item,
                                            :cantidad,
                                            NOW()
                                         )');

            // Actualizar acumulado en ventas_documentos_detalle
            $stmtUpdateDocDetalle = $db->prepare('UPDATE ventas_documentos_detalle 
                                                  SET cantidad_despachada = cantidad_despachada + :cantidad,
                                                      updated_at = NOW()
                                                  WHERE id = :id_detalle');

            $stmtStock = $db->prepare('INSERT INTO inventario_stock (id_item, id_almacen, stock_actual)
                                       VALUES (:id_item, :id_almacen, 0)
                                       ON DUPLICATE KEY UPDATE stock_actual = stock_actual');
                                       
            $stmtDescuento = $db->prepare('UPDATE inventario_stock
                                           SET stock_actual = stock_actual - :cantidad,
                                               updated_at = NOW()
                                           WHERE id_item = :id_item
                                             AND id_almacen = :id_almacen');

            $stmtMov = $db->prepare('INSERT INTO inventario_movimientos
                                        (id_item, id_almacen_origen, id_almacen_destino, tipo_movimiento, cantidad, referencia, created_by, created_at)
                                     VALUES
                                        (:id_item, :id_almacen_origen, NULL, :tipo, :cantidad, :referencia, :created_by, NOW())');

            foreach ($lineasValidas as $lineaValida) {
                // 1. Insertar detalle despacho
                $stmtDetalle->execute([
                    'id_despacho' => $idDespacho,
                    'id_item' => $lineaValida['id_item'],
                    'cantidad' => $lineaValida['cantidad'],
                ]);

                // 2. Actualizar acumulado en documento origen
                $stmtUpdateDocDetalle->execute([
                    'cantidad' => $lineaValida['cantidad'],
                    'id_detalle' => $lineaValida['id_documento_detalle']
                ]);

                // 3. Asegurar registro de stock
                $stmtStock->execute([
                    'id_item' => $lineaValida['id_item'],
                    'id_almacen' => $idAlmacen,
                ]);

                // 4. Descontar stock físico
                $stmtDescuento->execute([
                    'cantidad' => $lineaValida['cantidad'],
                    'id_item' => $lineaValida['id_item'],
                    'id_almacen' => $idAlmacen,
                ]);

                // 5. Registrar Kardex
                $stmtMov->execute([
                    'id_item' => $lineaValida['id_item'],
                    'id_almacen_origen' => $idAlmacen,
                    'tipo' => 'VTA', // Venta / Salida
                    'cantidad' => $lineaValida['cantidad'],
                    'referencia' => 'Despacho ' . $codigoDespacho,
                    'created_by' => $userId,
                ]);
            }

            // Verificar si se completó todo el pedido
            $pendienteTotal = $this->obtenerPendienteTotal($db, $idDocumento);
            
            // Estado 3: Despachado Totalmente, Estado 2: Aprobado/Parcial
            $nuevoEstado = ($cerrarForzado || $pendienteTotal <= 0.001) ? 3 : 2;

            // Si cambió a despachado total, actualizamos la cabecera
            if ($nuevoEstado === 3) {
                $db->prepare('UPDATE ventas_documentos
                              SET estado = :estado,
                                  updated_by = :user,
                                  updated_at = NOW()
                              WHERE id = :id
                                AND deleted_at IS NULL')
                    ->execute([
                        'estado' => $nuevoEstado,
                        'user' => $userId,
                        'id' => $idDocumento,
                    ]);
            }

            $db->commit();
            return $idDespacho;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function obtenerDocumento(PDO $db, int $idDocumento): array
    {
        $stmt = $db->prepare('SELECT id, estado FROM ventas_documentos WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $idDocumento]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerDetallePendiente(PDO $db, int $idDocumento): array
    {
        // CORREGIDO: nombres de columnas id_documento_venta y cantidad_despachada
        $sql = 'SELECT d.id,
                       d.id_item,
                       i.nombre AS item_nombre,
                       d.cantidad,
                       d.cantidad_despachada,
                       (d.cantidad - d.cantidad_despachada) AS cantidad_pendiente
                FROM ventas_documentos_detalle d
                INNER JOIN items i ON i.id = d.id_item
                WHERE d.id_documento_venta = :id_documento
                  AND d.deleted_at IS NULL
                  AND (d.cantidad - d.cantidad_despachada) > 0';

        $stmt = $db->prepare($sql);
        $stmt->execute(['id_documento' => $idDocumento]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerStockItem(PDO $db, int $idItem, int $idAlmacen): float
    {
        $stmt = $db->prepare('SELECT COALESCE(stock_actual, 0) FROM inventario_stock WHERE id_item = :id_item AND id_almacen = :id_almacen LIMIT 1');
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
        ]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function obtenerPendienteTotal(PDO $db, int $idDocumento): float
    {
        // CORREGIDO: Cálculo simple basado en la columna acumulativa ya existente
        $stmt = $db->prepare('SELECT COALESCE(SUM(cantidad - cantidad_despachada), 0)
                              FROM ventas_documentos_detalle
                              WHERE id_documento_venta = :id_documento
                                AND deleted_at IS NULL');
        $stmt->execute(['id_documento' => $idDocumento]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function generarCodigo(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM ventas_despachos')->fetchColumn() + 1;
        return 'GUIA-' . date('Y') . '-' . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }
}