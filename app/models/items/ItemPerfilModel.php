<?php
declare(strict_types=1);

class ItemPerfilModel extends Modelo
{
    public function obtenerPerfil(int $id): array
    {
        $sql = 'SELECT i.id, i.sku, i.nombre, i.descripcion, i.tipo_item, i.id_rubro, i.id_categoria,
                       r.nombre AS rubro_nombre,
                       c.nombre AS categoria_nombre,
                       i.id_marca, i.id_sabor, i.id_presentacion,
                       i.marca, i.unidad_base, i.peso_kg, i.permite_decimales, i.requiere_lote, i.requiere_vencimiento,
                       i.dias_alerta_vencimiento, i.controla_stock, i.requiere_formula_bom,
                       i.requiere_factor_conversion, i.es_envase_retornable, i.stock_minimo, i.precio_venta,
                       i.costo_referencial, i.moneda, i.impuesto_porcentaje AS impuesto, i.estado,
                       i.created_at, i.updated_at,
                       i.ultimo_costo_compra,
                       (
                           SELECT
                               CASE
                                   WHEN SUM(CASE WHEN stock_actual > 0 THEN stock_actual ELSE 0 END) > 0
                                       THEN SUM(CASE WHEN stock_actual > 0 THEN stock_actual * costo_promedio ELSE 0 END)
                                            / NULLIF(SUM(CASE WHEN stock_actual > 0 THEN stock_actual ELSE 0 END), 0)
                                   ELSE COALESCE(
                                       NULLIF(i.ultimo_costo_compra, 0),
                                       NULLIF((
                                           SELECT m.costo_promedio_resultante
                                           FROM inventario_movimientos m
                                           WHERE m.id_item = i.id
                                             AND m.costo_promedio_resultante IS NOT NULL
                                             AND m.costo_promedio_resultante > 0
                                           ORDER BY m.created_at DESC, m.id DESC
                                           LIMIT 1
                                       ), 0),
                                       i.costo_referencial
                                   )
                               END
                           FROM inventario_stock
                           WHERE id_item = i.id
                       ) AS costo_promedio
                FROM items i
                LEFT JOIN item_rubros r ON r.id = i.id_rubro
                LEFT JOIN categorias c ON c.id = i.id_categoria
                WHERE i.id = :id
                  AND i.deleted_at IS NULL
                LIMIT 1';
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function obtenerHistorialCostos(int $itemId): array
    {
        $sql = "SELECT * FROM (
                    SELECT 
                        id,
                        created_at AS fecha_movimiento, 
                        tipo_movimiento,
                        cantidad,
                        costo_unitario AS precio_compra,
                        costo_promedio_resultante,
                        COALESCE(
                            LAG(costo_promedio_resultante) OVER (PARTITION BY id_item ORDER BY created_at ASC, id ASC),
                            0
                        ) AS costo_promedio_anterior
                    FROM inventario_movimientos
                    WHERE id_item = :id_item
                      AND tipo_movimiento IN (
                        'INI', 'AJ+', 'COM', 'PROD',
                        'COMPRA', 'RECEPCION_COMPRA', 'SALDO_INICIAL', 'AJUSTE_INGRESO', 'ENTRADA'
                      )
                ) AS sub
                ORDER BY fecha_movimiento DESC, id DESC
                LIMIT 20";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $itemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDocumentos(int $itemId): array
    {
        $sql = 'SELECT * FROM item_documentos WHERE id_item = :id_item AND estado = 1 ORDER BY created_at DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $itemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarDocumento(array $docData): bool
    {
        $sql = 'INSERT INTO item_documentos (id_item, tipo_documento, nombre_archivo, ruta_archivo, extension, created_by, created_at, updated_at)
                VALUES (:id_item, :tipo_documento, :nombre_archivo, :ruta_archivo, :extension, :created_by, NOW(), NOW())';

        return $this->db()->prepare($sql)->execute($docData);
    }

    public function actualizarDocumento(int $id, string $tipo): bool
    {
        $sql = 'UPDATE item_documentos SET tipo_documento = :tipo, updated_at = NOW() WHERE id = :id';

        return $this->db()->prepare($sql)->execute([
            'tipo' => $tipo,
            'id' => $id,
        ]);
    }

    public function eliminarDocumento(int $docId): bool
    {
        $stmt = $this->db()->prepare('SELECT ruta_archivo FROM item_documentos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($row) && !empty($row['ruta_archivo'])) {
            $rutaPublica = ltrim((string) $row['ruta_archivo'], '/');
            $rutaFisica = BASE_PATH . '/public/' . $rutaPublica;
            if (is_file($rutaFisica)) {
                @unlink($rutaFisica);
            }
        }

        return $this->db()->prepare('DELETE FROM item_documentos WHERE id = :id')->execute(['id' => $docId]);
    }

    /**
     * SCRIPT DE RECÁLCULO HISTÓRICO DE COSTOS
     * Recalcula el costo promedio de todo el historial.
     */
    public function recalcularCostosHistoricos(): void
    {
        $db = $this->db();
        
        try {
            $db->beginTransaction();

            $stmtItems = $db->query("SELECT id FROM items WHERE controla_stock = 1 AND deleted_at IS NULL");
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            foreach ($items as $item) {
                $idItem = (int) $item['id'];
                $ultimoCostoCompra = 0.0;
                $stockPorAlmacen = [];

                $stmtMov = $db->prepare("SELECT * FROM inventario_movimientos WHERE id_item = ? ORDER BY created_at ASC, id ASC");
                $stmtMov->execute([$idItem]);
                $movimientos = $stmtMov->fetchAll(PDO::FETCH_ASSOC);

                foreach ($movimientos as $mov) {
                    $idMov = (int) $mov['id'];
                    $tipo = $mov['tipo_movimiento'];
                    $idAlmacenOrigen = (int) $mov['id_almacen_origen'];
                    $idAlmacenDestino = (int) $mov['id_almacen_destino'];
                    $cantidad = (float) $mov['cantidad'];
                    $costoUnitario = (float) $mov['costo_unitario'];

                    $esEntrada = in_array($tipo, ['INI', 'AJ+', 'COM', 'PROD', 'RECEPCION_COMPRA', 'ENTRADA']);
                    $esSalida = in_array($tipo, ['AJ-', 'CON', 'VEN', 'SALIDA_MERMA_PLANTA']);
                    $esTransferencia = ($tipo === 'TRF');

                    $costoResultante = 0.0;

                    if ($esEntrada) {
                        if (!isset($stockPorAlmacen[$idAlmacenDestino])) $stockPorAlmacen[$idAlmacenDestino] = ['stock' => 0.0, 'costo' => 0.0];
                        
                        $stockActual = $stockPorAlmacen[$idAlmacenDestino]['stock'];
                        $costoActual = $stockPorAlmacen[$idAlmacenDestino]['costo'];

                        $valorInventario = $stockActual * $costoActual;
                        $valorIngreso = $cantidad * $costoUnitario;
                        $nuevoStock = $stockActual + $cantidad;

                        if ($nuevoStock > 0 && $stockActual >= 0) {
                            $nuevoCosto = ($valorInventario + $valorIngreso) / $nuevoStock;
                        } else {
                            $nuevoCosto = $costoUnitario;
                        }

                        $stockPorAlmacen[$idAlmacenDestino]['stock'] = $nuevoStock;
                        $stockPorAlmacen[$idAlmacenDestino]['costo'] = $nuevoCosto;
                        $costoResultante = $nuevoCosto;

                        if ($costoUnitario > 0) $ultimoCostoCompra = $costoUnitario;
                        
                    } elseif ($esSalida) {
                        if (!isset($stockPorAlmacen[$idAlmacenOrigen])) $stockPorAlmacen[$idAlmacenOrigen] = ['stock' => 0.0, 'costo' => 0.0];
                        $stockPorAlmacen[$idAlmacenOrigen]['stock'] -= $cantidad;
                        $costoResultante = $stockPorAlmacen[$idAlmacenOrigen]['costo'];
                        
                    } elseif ($esTransferencia) {
                        if (!isset($stockPorAlmacen[$idAlmacenOrigen])) $stockPorAlmacen[$idAlmacenOrigen] = ['stock' => 0.0, 'costo' => 0.0];
                        if (!isset($stockPorAlmacen[$idAlmacenDestino])) $stockPorAlmacen[$idAlmacenDestino] = ['stock' => 0.0, 'costo' => 0.0];

                        $costoOrigen = $stockPorAlmacen[$idAlmacenOrigen]['costo'];
                        $stockPorAlmacen[$idAlmacenOrigen]['stock'] -= $cantidad;

                        $stockDestinoActual = $stockPorAlmacen[$idAlmacenDestino]['stock'];
                        $costoDestinoActual = $stockPorAlmacen[$idAlmacenDestino]['costo'];

                        $valorInvDest = $stockDestinoActual * $costoDestinoActual;
                        $valorIngresoTrf = $cantidad * $costoOrigen;
                        $nuevoStockDest = $stockDestinoActual + $cantidad;

                        if ($nuevoStockDest > 0 && $stockDestinoActual >= 0) {
                            $nuevoCostoDest = ($valorInvDest + $valorIngresoTrf) / $nuevoStockDest;
                        } else {
                            $nuevoCostoDest = $costoOrigen;
                        }

                        $stockPorAlmacen[$idAlmacenDestino]['stock'] = $nuevoStockDest;
                        $stockPorAlmacen[$idAlmacenDestino]['costo'] = $nuevoCostoDest;
                        $costoResultante = $nuevoCostoDest;
                    }

                    $db->prepare("UPDATE inventario_movimientos SET costo_promedio_resultante = ? WHERE id = ?")
                       ->execute([$costoResultante, $idMov]);
                }

                $db->prepare("UPDATE items SET ultimo_costo_compra = ? WHERE id = ?")
                   ->execute([$ultimoCostoCompra, $idItem]);

                foreach ($stockPorAlmacen as $idAlmacen => $data) {
                    $db->prepare("UPDATE inventario_stock SET costo_promedio = ? WHERE id_item = ? AND id_almacen = ?")
                       ->execute([$data['costo'], $idItem, $idAlmacen]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e; // Pasamos el error al controlador
        }
    }
}
