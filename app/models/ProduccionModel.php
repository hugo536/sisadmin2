<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/InventarioModel.php';

class ProduccionModel extends Modelo
{
    public function listarRecetas(): array
    {
        // Se corrigió el SQL duplicado
        $sql = 'SELECT r.id, r.codigo, r.version, r.descripcion, r.estado, r.created_at,
                       i.id AS id_producto, i.sku AS producto_sku, i.nombre AS producto_nombre,
                       (
                           SELECT COUNT(*)
                           FROM produccion_recetas_detalle d
                           WHERE d.id_receta = r.id
                             AND d.deleted_at IS NULL
                       ) AS total_insumos
                FROM produccion_recetas r
                INNER JOIN items i ON i.id = r.id_producto
                WHERE r.deleted_at IS NULL
                ORDER BY r.id DESC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarOrdenes(): array
    {
        // Se eliminaron los JOINS duplicados
        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.cantidad_planificada, o.cantidad_producida,
                       o.estado, o.fecha_inicio, o.fecha_fin, o.observaciones, o.created_at,
                       r.codigo AS receta_codigo,
                       p.nombre AS producto_nombre,
                       ao.nombre AS almacen_origen_nombre,
                       ad.nombre AS almacen_destino_nombre
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas r ON r.id = o.id_receta
                INNER JOIN items p ON p.id = r.id_producto
                INNER JOIN almacenes ao ON ao.id = o.id_almacen_origen
                INNER JOIN almacenes ad ON ad.id = o.id_almacen_destino
                WHERE o.deleted_at IS NULL
                ORDER BY o.id DESC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarRecetasActivas(): array
    {
        $sql = 'SELECT r.id, r.codigo, r.version, i.nombre AS producto_nombre
                FROM produccion_recetas r
                INNER JOIN items i ON i.id = r.id_producto
                WHERE r.estado = 1
                  AND r.deleted_at IS NULL
                ORDER BY i.nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarItemsStockeables(): array
    {
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND controla_stock = 1
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarAlmacenesActivos(): array
    {
        $sql = 'SELECT id, nombre
                FROM almacenes
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearReceta(array $payload, int $userId): int
    {
        $idProducto = (int) ($payload['id_producto'] ?? 0);
        $codigo = trim((string) ($payload['codigo'] ?? ''));
        $version = max(1, (int) ($payload['version'] ?? 1));
        $descripcion = trim((string) ($payload['descripcion'] ?? ''));
        $detalles = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];

        if ($idProducto <= 0 || $codigo === '' || $detalles === []) {
            throw new RuntimeException('Debe completar producto, código y al menos un detalle.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('INSERT INTO produccion_recetas
                                    (id_producto, codigo, version, descripcion, estado, created_by, updated_by)
                                  VALUES
                                    (:id_producto, :codigo, :version, :descripcion, 1, :created_by, :updated_by)');
            
            $stmt->execute([
                'id_producto' => $idProducto,
                'codigo' => $codigo,
                'version' => $version,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            $idReceta = (int) $db->lastInsertId();

            $stmtDet = $db->prepare('INSERT INTO produccion_recetas_detalle
                                        (id_receta, id_insumo, cantidad_por_unidad, merma_porcentaje, created_by, updated_by)
                                     VALUES
                                        (:id_receta, :id_insumo, :cantidad_por_unidad, :merma_porcentaje, :created_by, :updated_by)');

            foreach ($detalles as $detalle) {
                $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
                $cantidad = (float) ($detalle['cantidad_por_unidad'] ?? 0);
                $merma = (float) ($detalle['merma_porcentaje'] ?? 0);

                if ($idInsumo <= 0 || $cantidad <= 0) {
                    throw new RuntimeException('Detalle de receta inválido.');
                }

                $stmtDet->execute([
                    'id_receta' => $idReceta,
                    'id_insumo' => $idInsumo,
                    'cantidad_por_unidad' => number_format($cantidad, 4, '.', ''),
                    'merma_porcentaje' => number_format($merma, 2, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $db->commit();
            return $idReceta;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function crearOrden(array $payload, int $userId): int
    {
        $codigo = trim((string) ($payload['codigo'] ?? ''));
        $idReceta = (int) ($payload['id_receta'] ?? 0);
        $idAlmacenOrigen = (int) ($payload['id_almacen_origen'] ?? 0);
        $idAlmacenDestino = (int) ($payload['id_almacen_destino'] ?? 0);
        $cantidadPlanificada = (float) ($payload['cantidad_planificada'] ?? 0);
        $observaciones = trim((string) ($payload['observaciones'] ?? ''));

        if ($codigo === '' || $idReceta <= 0 || $idAlmacenOrigen <= 0 || $idAlmacenDestino <= 0 || $cantidadPlanificada <= 0) {
            throw new RuntimeException('Datos incompletos para crear la orden de producción.');
        }

        $sql = 'INSERT INTO produccion_ordenes
                    (codigo, id_receta, id_almacen_origen, id_almacen_destino, cantidad_planificada, estado, created_by, updated_by, observaciones)
                VALUES
                    (:codigo, :id_receta, :id_almacen_origen, :id_almacen_destino, :cantidad_planificada, 0, :created_by, :updated_by, :observaciones)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'codigo' => $codigo,
            'id_receta' => $idReceta,
            'id_almacen_origen' => $idAlmacenOrigen,
            'id_almacen_destino' => $idAlmacenDestino,
            'cantidad_planificada' => number_format($cantidadPlanificada, 4, '.', ''),
            'created_by' => $userId,
            'updated_by' => $userId,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    // Esta es la función crítica corregida
    public function ejecutarOrden(int $idOrden, float $cantidadProducida, int $userId, string $loteIngreso = '', array $lotesConsumo = []): void
    {
        if ($idOrden <= 0 || $cantidadProducida <= 0) {
            throw new RuntimeException('Datos inválidos para ejecutar la orden.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $orden = $this->obtenerOrdenPorId($idOrden);
            if (!$orden) {
                throw new RuntimeException('La orden no existe.');
            }
            if ((int) $orden['estado'] === 2) {
                throw new RuntimeException('La orden ya fue ejecutada.');
            }
            if ((int) $orden['estado'] === 9) {
                throw new RuntimeException('No se puede ejecutar una orden anulada.');
            }

            $detalle = $this->obtenerDetalleReceta((int) $orden['id_receta']);
            if (empty($detalle)) {
                throw new RuntimeException('La receta no tiene detalle activo.');
            }

            // Instancia de Inventario para mover kardex
            $inventarioModel = new InventarioModel(); 
            // IMPORTANTE: Asegúrate de que InventarioModel tenga el método registrarMovimiento compatible

            $idAlmacenOrigen = (int) $orden['id_almacen_origen'];
            $idAlmacenDestino = (int) $orden['id_almacen_destino'];
            $costoTotalConsumo = 0.0;

            $stmtConsumo = $db->prepare('INSERT INTO produccion_consumos
                                            (id_orden_produccion, id_item, id_lote, cantidad, costo_unitario, created_by, updated_by)
                                         VALUES
                                            (:id_orden_produccion, :id_item, :id_lote, :cantidad, :costo_unitario, :created_by, :updated_by)');

            // 1. Procesar CONSUMOS (Salidas)
            foreach ($detalle as $linea) {
                $idInsumo = (int) $linea['id_insumo'];
                $qtyBase = (float) $linea['cantidad_por_unidad'];
                $merma = (float) $linea['merma_porcentaje'];
                
                // Cálculo: (Base * Producido) * (1 + %merma)
                $cantidadRequerida = $qtyBase * $cantidadProducida * (1 + ($merma / 100));
                
                // Validar Stock
                $stock = $this->obtenerStockItemAlmacen($idInsumo, $idAlmacenOrigen);
                if ($stock < $cantidadRequerida) {
                    throw new RuntimeException('Stock insuficiente para el ítem ID ' . $idInsumo . '. Requerido: ' . number_format($cantidadRequerida, 2));
                }

                $costoUnitario = $this->obtenerCostoReferencial($idInsumo);
                $costoTotalConsumo += ($costoUnitario * $cantidadRequerida);
                
                $loteConsumo = $lotesConsumo[$idInsumo] ?? null; // Obtener lote seleccionado si existe

                // A. Registrar en tabla de consumos
                $stmtConsumo->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => $idInsumo,
                    'id_lote' => null, // Aquí podrías mapear el ID del lote si tu lógica lo requiere
                    'cantidad' => number_format($cantidadRequerida, 4, '.', ''),
                    'costo_unitario' => number_format($costoUnitario, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // B. Mover Kardex (Salida)
                // Nota: Verificamos si existe el método antes de llamar para evitar crash si InventarioModel es viejo
                if (method_exists($inventarioModel, 'registrarMovimiento')) {
                    $inventarioModel->registrarMovimiento([
                        'tipo_movimiento' => 'CON', // Consumo
                        'id_item' => $idInsumo,
                        'id_almacen_origen' => $idAlmacenOrigen,
                        'id_almacen_destino' => null, // Es consumo, no traslado
                        'cantidad' => $cantidadRequerida,
                        'referencia' => 'OP ' . $orden['codigo'] . ' consumo',
                        'lote' => $loteConsumo,
                        'costo_unitario' => $costoUnitario,
                        'created_by' => $userId
                    ]);
                }
            }

            // 2. Procesar INGRESO (Entrada Producto Terminado)
            $costoUnitarioIngreso = $cantidadProducida > 0 ? ($costoTotalConsumo / $cantidadProducida) : 0;

            $stmtIngreso = $db->prepare('INSERT INTO produccion_ingresos
                                            (id_orden_produccion, id_item, id_lote, cantidad, costo_unitario_calculado, created_by, updated_by)
                                         VALUES
                                            (:id_orden_produccion, :id_item, :id_lote, :cantidad, :costo_unitario_calculado, :created_by, :updated_by)');
            
            $stmtIngreso->execute([
                'id_orden_produccion' => $idOrden,
                'id_item' => (int) $orden['id_producto'],
                'id_lote' => null, // Se asignaría si creas un lote nuevo
                'cantidad' => number_format($cantidadProducida, 4, '.', ''),
                'costo_unitario_calculado' => number_format($costoUnitarioIngreso, 4, '.', ''),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // C. Mover Kardex (Entrada)
            if (method_exists($inventarioModel, 'registrarMovimiento')) {
                $inventarioModel->registrarMovimiento([
                    'tipo_movimiento' => 'PROD', // Producción (Asegúrate que tu Inventario acepte este código o usa AJ+)
                    'id_item' => (int) $orden['id_producto'],
                    'id_almacen_origen' => null,
                    'id_almacen_destino' => $idAlmacenDestino,
                    'cantidad' => $cantidadProducida,
                    'referencia' => 'OP ' . $orden['codigo'] . ' finalizado',
                    'lote' => $loteIngreso,
                    'costo_unitario' => $costoUnitarioIngreso,
                    'created_by' => $userId
                ]);
            }

            // 3. Actualizar Estado Orden
            $stmtUpdate = $db->prepare('UPDATE produccion_ordenes
                                        SET cantidad_producida = :cantidad_producida,
                                            estado = 2,
                                            fecha_inicio = COALESCE(fecha_inicio, NOW()),
                                            fecha_fin = NOW(),
                                            updated_at = NOW(),
                                            updated_by = :updated_by
                                        WHERE id = :id
                                          AND deleted_at IS NULL');
            $stmtUpdate->execute([
                'cantidad_producida' => number_format($cantidadProducida, 4, '.', ''),
                'updated_by' => $userId,
                'id' => $idOrden,
            ]);

            $db->commit();

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function anularOrden(int $idOrden, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE produccion_ordenes
                                      SET estado = 9,
                                          fecha_fin = NOW(),
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id
                                        AND estado IN (0, 1)
                                        AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $idOrden,
            'updated_by' => $userId,
        ]);
    }

    public function obtenerDetalleReceta(int $idReceta): array
    {
        $sql = 'SELECT d.id_insumo, d.cantidad_por_unidad, d.merma_porcentaje, i.nombre AS insumo_nombre
                FROM produccion_recetas_detalle d
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE d.id_receta = :id_receta
                  AND d.deleted_at IS NULL
                ORDER BY d.id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_receta' => $idReceta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerOrdenPorId(int $idOrden): array
    {
        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.id_almacen_origen, o.id_almacen_destino, o.estado,
                       r.id_producto
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas r ON r.id = o.id_receta
                WHERE o.id = :id
                  AND o.deleted_at IS NULL
                  AND r.deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idOrden]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    private function obtenerStockItemAlmacen(int $idItem, int $idAlmacen): float
    {
        $stmt = $this->db()->prepare('SELECT stock_actual
                                      FROM inventario_stock
                                      WHERE id_item = :id_item
                                        AND id_almacen = :id_almacen
                                      LIMIT 1');
        $stmt->execute([
            'id_item' => $idItem,
            'id_almacen' => $idAlmacen,
        ]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function obtenerCostoReferencial(int $idItem): float
    {
        $stmt = $this->db()->prepare('SELECT costo_referencial
                                      FROM items
                                      WHERE id = :id
                                      LIMIT 1');
        $stmt->execute(['id' => $idItem]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }
}