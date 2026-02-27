<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/InventarioModel.php';

class ProduccionOrdenesModel extends Modelo
{
    public function listarOrdenes(): array
    {
        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.cantidad_planificada, o.cantidad_producida,
                       o.estado, o.fecha_programada, o.turno_programado,
                       o.fecha_inicio, o.fecha_fin, o.observaciones, o.justificacion_ajuste, o.created_at,
                       o.id_almacen_planta, ap.nombre AS almacen_planta_nombre,
                       r.codigo AS receta_codigo,
                       p.nombre AS producto_nombre,
                       p.requiere_lote, p.requiere_vencimiento, p.unidad_base 
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas r ON r.id = o.id_receta
                INNER JOIN items p ON p.id = r.id_producto
                LEFT JOIN almacenes ap ON ap.id = o.id_almacen_planta
                WHERE o.deleted_at IS NULL
                ORDER BY COALESCE(o.updated_at, o.created_at) DESC, o.id DESC';

        $stmt = $this->db()->query($sql);
        $ordenes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($ordenes as &$orden) {
            $precheck = $this->evaluarPrecheckOrden((int) ($orden['id'] ?? 0));
            $orden['precheck_ok'] = $precheck['ok'] ? 1 : 0;
            $orden['precheck_resumen'] = $precheck['resumen'];
            $orden['precheck_detalle'] = $precheck['detalle'];
        }

        return $ordenes;
    }

    public function actualizarOrdenBorrador(int $idOrden, array $payload, int $userId): void
    {
        $orden = $this->obtenerOrdenPorId($idOrden);
        if ($orden === []) {
            throw new RuntimeException('La orden no existe.');
        }
        if ((int) ($orden['estado'] ?? -1) !== 0) {
            throw new RuntimeException('Solo se pueden editar órdenes en estado Borrador.');
        }

        $cantidadPlanificada = (float) ($payload['cantidad_planificada'] ?? 0);
        $fechaProgramada = trim((string) ($payload['fecha_programada'] ?? ''));
        $turnoProgramado = trim((string) ($payload['turno_programado'] ?? ''));
        $idAlmacenPlanta = (int) ($payload['id_almacen_planta'] ?? 0);
        $observaciones = trim((string) ($payload['observaciones'] ?? ''));

        if ($cantidadPlanificada <= 0 || $fechaProgramada === '' || $turnoProgramado === '' || $idAlmacenPlanta <= 0) {
            throw new RuntimeException('Datos incompletos para editar la orden de producción.');
        }

        if (DateTime::createFromFormat('Y-m-d', $fechaProgramada) === false) {
            throw new RuntimeException('La fecha programada no tiene un formato válido.');
        }

        $turnosPermitidos = ['Mañana', 'Tarde', 'Noche'];
        if (!in_array($turnoProgramado, $turnosPermitidos, true)) {
            throw new RuntimeException('El turno programado no es válido.');
        }

        $stmtAlmacenPlanta = $this->db()->prepare('SELECT COUNT(*)
                                                   FROM almacenes
                                                   WHERE id = :id
                                                     AND estado = 1
                                                     AND deleted_at IS NULL
                                                     AND tipo = :tipo');
        $stmtAlmacenPlanta->execute([
            'id' => $idAlmacenPlanta,
            'tipo' => 'Planta',
        ]);
        if ((int) $stmtAlmacenPlanta->fetchColumn() <= 0) {
            throw new RuntimeException('El almacén de planta seleccionado no es válido.');
        }

        $stmt = $this->db()->prepare('UPDATE produccion_ordenes
                                      SET cantidad_planificada = :cantidad_planificada,
                                          fecha_programada = :fecha_programada,
                                          turno_programado = :turno_programado,
                                          id_almacen_planta = :id_almacen_planta,
                                          observaciones = :observaciones,
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id
                                        AND estado = 0
                                        AND deleted_at IS NULL');
        $stmt->execute([
            'cantidad_planificada' => number_format($cantidadPlanificada, 4, '.', ''),
            'fecha_programada' => $fechaProgramada,
            'turno_programado' => $turnoProgramado,
            'id_almacen_planta' => $idAlmacenPlanta,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'updated_by' => $userId,
            'id' => $idOrden,
        ]);
    }

    public function eliminarOrdenBorrador(int $idOrden, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE produccion_ordenes
                                      SET deleted_at = NOW(),
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id
                                        AND estado = 0
                                        AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $idOrden,
            'updated_by' => $userId,
        ]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Solo se pueden eliminar órdenes en Borrador.');
        }
    }

    public function marcarOrdenEnProceso(int $idOrden, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE produccion_ordenes
                                      SET estado = 1,
                                          fecha_inicio = COALESCE(fecha_inicio, NOW()),
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id
                                        AND estado = 0
                                        AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $idOrden,
            'updated_by' => $userId,
        ]);
    }

    private function evaluarPrecheckOrden(int $idOrden): array
    {
        if ($idOrden <= 0) {
            return ['ok' => false, 'resumen' => 'Sin datos para pre-chequeo', 'detalle' => []];
        }

        $sql = 'SELECT d.id_insumo,
                       i.nombre AS insumo_nombre,
                       i.requiere_lote,
                       (d.cantidad_por_unidad * o.cantidad_planificada * (1 + (d.merma_porcentaje / 100))) AS qty_requerida,
                       COALESCE((
                           SELECT s.stock_actual
                           FROM inventario_stock s
                           WHERE s.id_item = d.id_insumo
                             AND s.id_almacen = o.id_almacen_planta
                           LIMIT 1
                       ), 0) AS stock_planta,
                       (
                           SELECT COUNT(*)
                           FROM inventario_lotes l
                           WHERE l.id_item = d.id_insumo
                             AND l.id_almacen = o.id_almacen_planta
                             AND l.stock_lote > 0
                       ) AS lotes_disponibles
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas_detalle d ON d.id_receta = o.id_receta AND d.deleted_at IS NULL
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE o.id = :id_orden
                  AND o.deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_orden' => $idOrden]);
        $filas = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($filas === []) {
            return ['ok' => false, 'resumen' => 'Sin insumos para evaluar', 'detalle' => []];
        }

        $ok = true;
        $faltantes = [];
        $detalle = [];

        foreach ($filas as $fila) {
            $requerido = round((float) ($fila['qty_requerida'] ?? 0), 4);
            $stock = round((float) ($fila['stock_planta'] ?? 0), 4);
            $requiereLote = (int) ($fila['requiere_lote'] ?? 0) === 1;
            $lotesDisponibles = (int) ($fila['lotes_disponibles'] ?? 0);
            $faltante = round(max(0, $requerido - $stock), 4);

            $estadoItem = 'OK';
            if ($faltante > 0) {
                $ok = false;
                $estadoItem = 'FALTA';
                $faltantes[] = sprintf('%s (faltan %.4f)', (string) ($fila['insumo_nombre'] ?? 'Insumo'), $faltante);
            }
            if ($requiereLote && $lotesDisponibles <= 0) {
                $ok = false;
                $estadoItem = 'SIN LOTE';
                $faltantes[] = sprintf('%s (requiere lote sin disponibilidad)', (string) ($fila['insumo_nombre'] ?? 'Insumo'));
            }

            $detalle[] = [
                'insumo' => (string) ($fila['insumo_nombre'] ?? ''),
                'requerido' => $requerido,
                'en_planta' => $stock,
                'estado' => $estadoItem,
            ];
        }

        $resumen = $ok
            ? 'Stock de planta suficiente para todos los insumos'
            : ('Faltantes: ' . implode('; ', $faltantes));

        return ['ok' => $ok, 'resumen' => $resumen, 'detalle' => $detalle];
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

    // NUEVA FUNCIÓN OPTIMIZADA: Para el buscador AJAX del Tom Select
    public function buscarInsumosStockeables(string $termino, int $limite = 30): array
    {
        $busqueda = '%' . $termino . '%';
        
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote, costo_referencial
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND controla_stock = 1
                  AND (nombre LIKE :termino_nombre OR sku LIKE :termino_sku)
                ORDER BY nombre ASC
                LIMIT ' . (int)$limite;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'termino_nombre' => $busqueda,
            'termino_sku' => $busqueda
        ]);
        
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Calculamos el costo dinámico en un loop rápido (Máximo 30 iteraciones)
        // Esto es muchísimo más eficiente que agrupar toda la tabla en un JOIN
        foreach ($items as &$item) {
            $item['costo_calculado'] = $this->obtenerCostoReferencial((int)$item['id']);
        }

        return $items;
    }

    // Se mantiene por retrocompatibilidad, pero ahora usa la lógica rápida
    public function listarItemsStockeables(): array
    {
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote, costo_referencial
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND controla_stock = 1
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as &$item) {
            $item['costo_calculado'] = $this->obtenerCostoReferencial((int)$item['id']);
        }

        return $items;
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

    public function listarAlmacenesActivosPorTipo(string $tipo): array
    {
        $tipo = trim($tipo);
        if ($tipo === '') {
            return [];
        }

        $stmt = $this->db()->prepare('SELECT id, nombre
                                      FROM almacenes
                                      WHERE estado = 1
                                        AND deleted_at IS NULL
                                        AND tipo = :tipo
                                      ORDER BY nombre ASC');
        $stmt->execute(['tipo' => $tipo]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function crearOrden(array $payload, int $userId): int
    {
        $codigo = trim((string) ($payload['codigo'] ?? ''));
        $idReceta = (int) ($payload['id_receta'] ?? 0);
        $cantidadPlanificada = (float) ($payload['cantidad_planificada'] ?? 0);
        $fechaProgramada = trim((string) ($payload['fecha_programada'] ?? ''));
        $turnoProgramado = trim((string) ($payload['turno_programado'] ?? ''));
        $observaciones = trim((string) ($payload['observaciones'] ?? ''));
        $idAlmacenPlanta = (int) ($payload['id_almacen_planta'] ?? 0);

        if ($codigo === '' || $idReceta <= 0 || $cantidadPlanificada <= 0 || $fechaProgramada === '' || $turnoProgramado === '' || $idAlmacenPlanta <= 0) {
            throw new RuntimeException('Datos incompletos para crear la orden de producción.');
        }

        if (DateTime::createFromFormat('Y-m-d', $fechaProgramada) === false) {
            throw new RuntimeException('La fecha programada no tiene un formato válido.');
        }

        $turnosPermitidos = ['Mañana', 'Tarde', 'Noche'];
        if (!in_array($turnoProgramado, $turnosPermitidos, true)) {
            throw new RuntimeException('El turno programado no es válido.');
        }

        $stmtAlmacenPlanta = $this->db()->prepare('SELECT COUNT(*)
                                                   FROM almacenes
                                                   WHERE id = :id
                                                     AND estado = 1
                                                     AND deleted_at IS NULL
                                                     AND tipo = :tipo');
        $stmtAlmacenPlanta->execute([
            'id' => $idAlmacenPlanta,
            'tipo' => 'Planta',
        ]);
        if ((int) $stmtAlmacenPlanta->fetchColumn() <= 0) {
            throw new RuntimeException('El almacén de planta seleccionado no es válido.');
        }

        $sql = 'INSERT INTO produccion_ordenes
                    (codigo, id_receta, id_almacen_planta, cantidad_planificada, fecha_programada, turno_programado, estado, created_by, updated_by, observaciones)
                VALUES
                    (:codigo, :id_receta, :id_almacen_planta, :cantidad_planificada, :fecha_programada, :turno_programado, 0, :created_by, :updated_by, :observaciones)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'codigo' => $codigo,
            'id_receta' => $idReceta,
            'id_almacen_planta' => $idAlmacenPlanta,
            'cantidad_planificada' => number_format($cantidadPlanificada, 4, '.', ''),
            'fecha_programada' => $fechaProgramada !== '' ? $fechaProgramada : null,
            'turno_programado' => $turnoProgramado !== '' ? $turnoProgramado : null,
            'created_by' => $userId,
            'updated_by' => $userId,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function ejecutarOrden(int $idOrden, array $consumos, array $ingresos, int $userId, string $justificacion = '', ?string $fechaInicio = null, ?string $fechaFin = null): void
    {
        if ($idOrden <= 0 || empty($consumos) || empty($ingresos)) {
            throw new RuntimeException('Faltan datos de consumos o ingresos para ejecutar la orden.');
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

            $inventarioModel = new InventarioModel(); 
            $costoTotalConsumo = 0.0;

            $stmtInfoItem = $db->prepare('SELECT controla_stock, requiere_lote, requiere_vencimiento FROM items WHERE id = :id LIMIT 1');

            $stmtConsumo = $db->prepare('INSERT INTO produccion_consumos
                                            (id_orden_produccion, id_item, id_almacen, id_lote, cantidad, costo_unitario, created_by, updated_by)
                                         VALUES
                                            (:id_orden_produccion, :id_item, :id_almacen, :id_lote, :cantidad, :costo_unitario, :created_by, :updated_by)');

            foreach ($consumos as $consumo) {
                $idInsumo = (int) ($consumo['id_insumo'] ?? 0);
                $cantidadUsada = (float) ($consumo['cantidad'] ?? 0);
                
                if ($idInsumo <= 0 || $cantidadUsada <= 0) continue;

                $stmtInfoItem->execute(['id' => $idInsumo]);
                $infoInsumo = $stmtInfoItem->fetch(PDO::FETCH_ASSOC);
                $controlaStock = (int)($infoInsumo['controla_stock'] ?? 0) === 1;
                
                $idAlmacenOrigen = (int) ($consumo['id_almacen'] ?? 0);
                $idLote = !empty($consumo['id_lote']) ? (int)$consumo['id_lote'] : null;
                $lote = trim((string) ($consumo['lote'] ?? ''));

                if ($controlaStock && $idAlmacenOrigen <= 0) {
                    throw new RuntimeException("El insumo (ID {$idInsumo}) controla stock. Debe seleccionar obligatoriamente un almacén de origen.");
                }

                $costoUnitario = $this->obtenerCostoReferencial($idInsumo);
                $costoTotalConsumo += ($costoUnitario * $cantidadUsada);

                $stmtConsumo->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => $idInsumo,
                    'id_almacen' => $idAlmacenOrigen > 0 ? $idAlmacenOrigen : null,
                    'id_lote' => $idLote, 
                    'cantidad' => number_format($cantidadUsada, 4, '.', ''),
                    'costo_unitario' => number_format($costoUnitario, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idItemUnidadInsumo = $this->obtenerUnidadPorDefecto($idInsumo);

                if ($controlaStock && method_exists($inventarioModel, 'registrarMovimiento')) {
                    $inventarioModel->registrarMovimiento([
                        'tipo_movimiento' => 'CON', 
                        'id_item' => $idInsumo,
                        'id_item_unidad' => $idItemUnidadInsumo, 
                        'id_almacen_origen' => $idAlmacenOrigen,
                        'id_almacen_destino' => null, 
                        'cantidad' => $cantidadUsada,
                        'referencia' => 'OP ' . $orden['codigo'] . ' consumo',
                        'lote' => $lote,
                        'costo_unitario' => $costoUnitario,
                        'created_by' => $userId
                    ]);
                }
            }

            $cantidadTotalProducida = array_sum(array_column($ingresos, 'cantidad'));
            
            if ($cantidadTotalProducida <= 0) {
                throw new RuntimeException('La cantidad total producida debe ser mayor a cero.');
            }

            $costoUnitarioIngreso = $costoTotalConsumo / $cantidadTotalProducida;

            $stmtInfoItem->execute(['id' => (int) $orden['id_producto']]);
            $infoProducto = $stmtInfoItem->fetch(PDO::FETCH_ASSOC);
            $controlaStockProd = (int)($infoProducto['controla_stock'] ?? 0) === 1;

            $stmtIngreso = $db->prepare('INSERT INTO produccion_ingresos
                                            (id_orden_produccion, id_item, id_almacen, lote, fecha_vencimiento, cantidad, costo_unitario_calculado, created_by, updated_by)
                                         VALUES
                                            (:id_orden_produccion, :id_item, :id_almacen, :lote, :fecha_vencimiento, :cantidad, :costo_unitario_calculado, :created_by, :updated_by)');
            
            $idItemUnidadTerminado = $this->obtenerUnidadPorDefecto((int) $orden['id_producto']);

            foreach ($ingresos as $ingreso) {
                $cantidadIngresada = (float) ($ingreso['cantidad'] ?? 0);
                if ($cantidadIngresada <= 0) continue; 

                $idAlmacenDestino = (int) ($ingreso['id_almacen'] ?? 0);
                $lote = trim((string) ($ingreso['lote'] ?? ''));
                $fechaVencimiento = trim((string) ($ingreso['fecha_vencimiento'] ?? ''));

                $loteDb = $lote !== '' ? $lote : null;
                $fechaVencDb = $fechaVencimiento !== '' ? $fechaVencimiento : null;

                if ($controlaStockProd && $idAlmacenDestino <= 0) {
                    throw new RuntimeException("El producto fabricado controla stock. Debe seleccionar un almacén de destino.");
                }

                $stmtIngreso->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => (int) $orden['id_producto'],
                    'id_almacen' => $idAlmacenDestino > 0 ? $idAlmacenDestino : null,
                    'lote' => $loteDb, 
                    'fecha_vencimiento' => $fechaVencDb, 
                    'cantidad' => number_format($cantidadIngresada, 4, '.', ''),
                    'costo_unitario_calculado' => number_format($costoUnitarioIngreso, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                if ($controlaStockProd && method_exists($inventarioModel, 'registrarMovimiento')) {
                    $inventarioModel->registrarMovimiento([
                        'tipo_movimiento' => 'PROD', 
                        'id_item' => (int) $orden['id_producto'],
                        'id_item_unidad' => $idItemUnidadTerminado, 
                        'id_almacen_origen' => null,
                        'id_almacen_destino' => $idAlmacenDestino,
                        'cantidad' => $cantidadIngresada,
                        'referencia' => 'OP ' . $orden['codigo'] . ' finalizado',
                        'lote' => $lote,
                        'fecha_vencimiento' => $fechaVencimiento, 
                        'costo_unitario' => $costoUnitarioIngreso,
                        'created_by' => $userId
                    ]);
                }
            }

            // Se actualizó para recibir las fechas exactas del formulario
            $stmtUpdate = $db->prepare('UPDATE produccion_ordenes
                                        SET cantidad_producida = :cantidad_producida,
                                            estado = 2,
                                            fecha_inicio = COALESCE(:fecha_inicio, fecha_inicio, NOW()),
                                            fecha_fin = COALESCE(:fecha_fin, NOW()),
                                            justificacion_ajuste = :justificacion,
                                            updated_at = NOW(),
                                            updated_by = :updated_by
                                        WHERE id = :id
                                          AND deleted_at IS NULL');
            $stmtUpdate->execute([
                'cantidad_producida' => number_format($cantidadTotalProducida, 4, '.', ''),
                'fecha_inicio' => $fechaInicio, // Pasa la fecha recibida
                'fecha_fin' => $fechaFin,       // Pasa la fecha recibida
                'justificacion' => $justificacion !== '' ? $justificacion : null,
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
        $sql = 'SELECT d.id_insumo, d.etapa, d.cantidad_por_unidad, d.merma_porcentaje, i.nombre AS insumo_nombre
                FROM produccion_recetas_detalle d
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE d.id_receta = :id_receta
                  AND d.deleted_at IS NULL
                ORDER BY d.id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_receta' => $idReceta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerStockTotalItem(int $idItem): float
    {
        $stmt = $this->db()->prepare('SELECT COALESCE(SUM(stock_actual), 0)
                                      FROM inventario_stock
                                      WHERE id_item = :id_item');
        $stmt->execute(['id_item' => $idItem]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    public function obtenerAlmacenesConStockItem(int $idItem): array
    {
        $stmt = $this->db()->prepare('SELECT a.id, a.nombre, s.stock_actual
                                      FROM inventario_stock s
                                      INNER JOIN almacenes a ON a.id = s.id_almacen
                                      WHERE s.id_item = :id_item
                                        AND s.stock_actual > 0');
        $stmt->execute(['id_item' => $idItem]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerOrdenPorId(int $idOrden): array
    {
        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.cantidad_planificada, o.estado,
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

    // FUNCIÓN EXTREMADAMENTE OPTIMIZADA

    private function obtenerCostoReferencial(int $idItem): float
    {
        // 1. Primero buscamos el costo fijo en el ítem
        $stmt = $this->db()->prepare('SELECT costo_referencial FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idItem]);
        $costoFijo = (float)($stmt->fetchColumn() ?: 0);
        if ($costoFijo > 0) return $costoFijo;

        // 2. Si no hay, buscamos el costo de su última receta (Si es producto terminado)
        $stmtRec = $this->db()->prepare('SELECT costo_teorico_unitario 
                                         FROM produccion_recetas 
                                         WHERE id_producto = :id AND estado = 1 AND deleted_at IS NULL 
                                         ORDER BY id DESC LIMIT 1');
        $stmtRec->execute(['id' => $idItem]);
        $costoReceta = (float)($stmtRec->fetchColumn() ?: 0);
        if ($costoReceta > 0) return $costoReceta;

        // 3. Si no hay receta, buscamos el último costo al que se compró o movió
        $stmtMov = $this->db()->prepare('SELECT costo_unitario 
                                         FROM inventario_movimientos 
                                         WHERE id_item = :id AND costo_unitario IS NOT NULL AND costo_unitario > 0 
                                         ORDER BY id DESC LIMIT 1');
        $stmtMov->execute(['id' => $idItem]);
        return (float)($stmtMov->fetchColumn() ?: 0);
    }

    /**
     * Obtiene el ID de la última unidad de medida utilizada para un ítem
     * Útil para registrar los movimientos de inventario con la unidad correcta.
     */
    private function obtenerUnidadPorDefecto(int $idItem): ?int
    {
        $stmt = $this->db()->prepare('SELECT id_item_unidad
                                      FROM inventario_movimientos
                                      WHERE id_item = :id_item
                                        AND id_item_unidad IS NOT NULL
                                      ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id_item' => $idItem]);
        $val = $stmt->fetchColumn();
        
        return $val ? (int) $val : null;
    }
}
