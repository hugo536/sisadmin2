<?php

declare(strict_types=1);

class VentasDespachoModel extends Modelo
{
    // 1. ELIMINAMOS $idAlmacen de los parámetros. Ahora viene dentro de $lineas.
    public function registrarDespacho(int $idDocumento, array $lineas, bool $cerrarForzado, string $observaciones, int $userId): void
    {
        if ($idDocumento <= 0) {
            throw new RuntimeException('Documento inválido.');
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

            // 2. AGRUPAR LÍNEAS POR ALMACÉN
            // Esto es crucial para crear una "Guía de Despacho" (cabecera) por cada almacén involucrado.
            $despachosAgrupados = [];
            
            // También llevaremos un control temporal de lo que estamos validando para no exceder el pendiente
            // si un mismo item se despacha desde 2 almacenes a la vez.
            $cantidadesAcumuladasItem = []; 

            foreach ($lineas as $linea) {
                $idDetalle = (int) ($linea['id_documento_detalle'] ?? 0);
                $idAlmacenLinea = (int) ($linea['id_almacen'] ?? 0);
                $cantidad = (float) ($linea['cantidad'] ?? 0);

                if ($idDetalle <= 0 || $idAlmacenLinea <= 0 || $cantidad <= 0) {
                    continue; // Ignorar líneas mal formadas o vacías
                }

                if (!isset($mapaDetalle[$idDetalle])) {
                    throw new RuntimeException('Una línea no pertenece al pedido.');
                }

                $detalle = $mapaDetalle[$idDetalle];
                
                // Acumulamos para validar contra el pendiente global del item
                $cantidadesAcumuladasItem[$idDetalle] = ($cantidadesAcumuladasItem[$idDetalle] ?? 0) + $cantidad;
                $pendiente = (float) ($detalle['cantidad_pendiente'] ?? 0);
                
                if ($cantidadesAcumuladasItem[$idDetalle] > ($pendiente + 0.0001)) {
                    throw new RuntimeException('La suma de cantidades a despachar excede el pendiente del ítem ' . ($detalle['item_nombre'] ?? '')); 
                }

                // Validamos el stock físico en ESE almacén específico
                $stockActual = $this->obtenerStockItem($db, (int) $detalle['id_item'], $idAlmacenLinea);
                if ($cantidad > $stockActual) {
                    throw new RuntimeException('Stock insuficiente para ' . ($detalle['item_nombre'] ?? '') . ' en el almacén seleccionado. Disponible: ' . number_format($stockActual, 2));
                }

                // Agrupamos la línea válida bajo su ID de almacén
                $despachosAgrupados[$idAlmacenLinea][] = [
                    'id_documento_detalle' => $idDetalle,
                    'id_item' => (int) $detalle['id_item'],
                    'cantidad' => $cantidad,
                    'id_almacen' => $idAlmacenLinea // <--- ¡Asegúrate de agregar esta línea!
                ];
            }

            if (empty($despachosAgrupados)) {
                throw new RuntimeException('No hay cantidades válidas para despachar.');
            }

            // Prepared Statements para reutilizar en el bucle
            $stmtInsertDespacho = $db->prepare('INSERT INTO ventas_despachos (
                                            codigo, id_documento_venta, id_almacen, fecha_despacho, documento_referencia, created_by, created_at
                                        ) VALUES (
                                            :codigo, :id_documento, :id_almacen, NOW(), :observaciones, :created_by, NOW()
                                        )');

            $stmtDetalle = $db->prepare('INSERT INTO ventas_despachos_detalle (
                                            id_despacho, id_item, cantidad_despachada, created_at
                                         ) VALUES (
                                            :id_despacho, :id_item, :cantidad, NOW()
                                         )');

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

            // 3. PROCESAR CADA ALMACÉN Y SUS LÍNEAS
            foreach ($despachosAgrupados as $idAlmacenFisico => $lineasAlmacen) {
                // Generamos una cabecera de despacho por cada almacén
                $codigoDespacho = $this->generarCodigo($db);
                
                $stmtInsertDespacho->execute([
                    'codigo' => $codigoDespacho,
                    'id_documento' => $idDocumento,
                    'id_almacen' => $idAlmacenFisico,
                    'observaciones' => $observaciones !== '' ? $observaciones : null,
                    'created_by' => $userId,
                ]);

                $idDespacho = (int) $db->lastInsertId();

                foreach ($lineasAlmacen as $lineaValida) {
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
                        'id_almacen' => $idAlmacenFisico,
                    ]);

                    // 4. Descontar stock físico
                    $stmtDescuento->execute([
                        'cantidad' => $lineaValida['cantidad'],
                        'id_item' => $lineaValida['id_item'],
                        'id_almacen' => $idAlmacenFisico,
                    ]);

                    // 5. Registrar Kardex
                    $stmtMov->execute([
                        'id_item' => $lineaValida['id_item'],
                        'id_almacen_origen' => $idAlmacenFisico,
                        'tipo' => 'VEN', 
                        'cantidad' => $lineaValida['cantidad'],
                        'referencia' => 'Despacho ' . $codigoDespacho,
                        'created_by' => $userId,
                    ]);
                }
                $this->registrarAjusteEnvasesPorDespacho(
                    $db,
                    $idDocumento,
                    (int) ($documento['id_cliente'] ?? 0),
                    $lineasAlmacen,
                    $codigoDespacho
                );
            }

            // 4. Verificar si se completó todo el pedido (después de procesar todos los almacenes)
            $pendienteTotal = $this->obtenerPendienteTotal($db, $idDocumento);
            
            $nuevoEstado = ($cerrarForzado || $pendienteTotal <= 0.001) ? 3 : 2;

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
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    private function obtenerDocumento(PDO $db, int $idDocumento): array
    {
        $stmt = $db->prepare('SELECT id, id_cliente, estado FROM ventas_documentos WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $idDocumento]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerDetallePendiente(PDO $db, int $idDocumento): array
    {
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
        $stmt = $db->prepare('SELECT COALESCE(SUM(cantidad - cantidad_despachada), 0)
                              FROM ventas_documentos_detalle
                              WHERE id_documento_venta = :id_documento
                                AND deleted_at IS NULL');
        $stmt->execute(['id_documento' => $idDocumento]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }


    private function registrarAjusteEnvasesPorDespacho(PDO $db, int $idDocumento, int $idCliente, array $lineasDespachadas, string $codigoDespacho): void
    {
        if ($idCliente <= 0 || empty($lineasDespachadas)) {
            return;
        }

        $ajustesPorEnvase = [];

        // 1. Identificar cuántos envases vacíos requiere esta venta
        foreach ($lineasDespachadas as $linea) {
            $idProducto = (int) ($linea['id_item'] ?? 0);
            $cantidadDespachada = (float) ($linea['cantidad'] ?? 0);

            if ($idProducto <= 0 || $cantidadDespachada <= 0) {
                continue;
            }

            // Buscamos si el producto vendido tiene un envase retornable en su receta
            $envasesReceta = $this->obtenerEnvasesRetornablesDeReceta($db, $idProducto);

            if ($envasesReceta === []) {
                $envasesReceta = $this->obtenerEnvasesRetornablesDesdeHistoricoProduccion($db, $idProducto);
            }

            foreach ($envasesReceta as $envase) {
                $idItemEnvase = (int) ($envase['id_item_envase'] ?? 0);
                $factor = (float) ($envase['factor_envase'] ?? 0);
                if ($idItemEnvase <= 0 || $factor <= 0) {
                    continue;
                }

                $cantidadAjuste = (int) round($cantidadDespachada * $factor, 0);
                if ($cantidadAjuste <= 0) {
                    continue;
                }

                $ajustesPorEnvase[$idItemEnvase] = ($ajustesPorEnvase[$idItemEnvase] ?? 0) + $cantidadAjuste;
            }
        }

        if (empty($ajustesPorEnvase)) {
            return;
        }

        // 2. Preparar SOLO la consulta de Cuenta Corriente (No tocamos el Kardex físico aquí)
        $operacionUuid = bin2hex(random_bytes(8));
        
        $stmtCtaCte = $db->prepare('INSERT INTO cta_cte_envases (id_tercero, id_item_envase, tipo_operacion, cantidad, id_venta, observaciones) VALUES (:id_tercero, :id_item_envase, :tipo_operacion, :cantidad, :id_venta, :observaciones)');
        
        // 3. Ejecutar las salidas (Cuenta Corriente) por cada envase identificado
        foreach ($ajustesPorEnvase as $idItemEnvase => $cantidadAjuste) {
            $obsFinal = 'Salida automática por despacho ' . $codigoDespacho . ' | OP:' . $operacionUuid;
            
            // Registramos la ENTREGA_LLENO en envases (Afecta el saldo del cliente, pero NO el Kardex físico)
            $stmtCtaCte->execute([
                'id_tercero' => $idCliente,
                'id_item_envase' => $idItemEnvase,
                'tipo_operacion' => 'ENTREGA_LLENO',
                'cantidad' => $cantidadAjuste,
                'id_venta' => $idDocumento,
                'observaciones' => $obsFinal,
            ]);
        }
    }

    private function obtenerEnvasesRetornablesDeReceta(PDO $db, int $idProducto): array
    {
        if ($idProducto <= 0) {
            return [];
        }

        $stmt = $db->prepare('SELECT d.id_insumo AS id_item_envase,
                                     (d.cantidad_por_unidad / NULLIF(r.rendimiento_base, 0)) AS factor_envase
                              FROM produccion_recetas_detalle d
                              INNER JOIN items i ON i.id = d.id_insumo
                              INNER JOIN produccion_recetas r ON r.id = d.id_receta
                              WHERE d.deleted_at IS NULL
                                AND i.deleted_at IS NULL
                                AND i.es_envase_retornable = 1
                                AND r.deleted_at IS NULL

                                AND r.estado = 1

                                AND d.id_receta = (
                                    SELECT r2.id
                                    FROM produccion_recetas r2
                                    WHERE r2.id_producto = :id_producto
                                      AND r2.estado = 1
                                      AND r2.deleted_at IS NULL
                                    ORDER BY r2.version DESC, r2.id DESC
                                    LIMIT 1
                                )');
        $stmt->execute(['id_producto' => $idProducto]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }



    private function obtenerEnvasesRetornablesDesdeHistoricoProduccion(PDO $db, int $idProducto): array
    {
        if ($idProducto <= 0) {
            return [];
        }

        $stmt = $db->prepare('SELECT c.id_item AS id_item_envase,
                                     (SUM(c.cantidad) / NULLIF(SUM(o.cantidad_producida), 0)) AS factor_envase
                              FROM produccion_ordenes o
                              INNER JOIN produccion_consumos c ON c.id_orden_produccion = o.id AND c.deleted_at IS NULL
                              INNER JOIN items i ON i.id = c.id_item
                              WHERE o.deleted_at IS NULL
                                AND o.estado = 2
                                AND o.cantidad_producida > 0
                                AND o.id_producto_snapshot = :id_producto
                                AND i.deleted_at IS NULL
                                AND i.es_envase_retornable = 1
                              GROUP BY c.id_item
                              HAVING factor_envase > 0');
        $stmt->execute(['id_producto' => $idProducto]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function generarCodigo(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM ventas_despachos')->fetchColumn() + 1;
        return 'GUIA-' . date('Y') . '-' . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }
}