<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/InventarioModel.php';

class ProduccionModel extends Modelo
{
    private ?bool $produccionOrdenesTieneAlmacenOrigen = null;

    public function listarRecetas(): array
    {
        // Se quitaron las columnas fijas de parámetros (brix, ph, etc.)
        $sql = 'SELECT r.id, r.codigo, r.version, r.descripcion, r.estado, r.created_at,
                       r.rendimiento_base, r.unidad_rendimiento,
                       r.costo_teorico_unitario AS costo_teorico,
                       i.id AS id_producto, i.sku AS producto_sku, i.nombre AS producto_nombre,
                       (
                           SELECT COUNT(*)
                           FROM produccion_recetas_detalle d
                           WHERE d.id_receta = r.id
                             AND d.deleted_at IS NULL
                       ) AS total_insumos,
                       CASE
                           WHEN (
                               SELECT COUNT(*)
                               FROM produccion_recetas_detalle d
                               WHERE d.id_receta = r.id
                                 AND d.deleted_at IS NULL
                           ) = 0 THEN 1
                           ELSE 0
                       END AS sin_receta
                FROM produccion_recetas r
                INNER JOIN items i ON i.id = r.id_producto
                WHERE r.deleted_at IS NULL
                ORDER BY COALESCE(r.updated_at, r.created_at) DESC, r.id DESC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarOrdenes(): array
    {
        $usaAlmacenOrigen = $this->tablaTieneColumna('produccion_ordenes', 'id_almacen_origen');

        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.cantidad_planificada, o.cantidad_producida,
                       o.estado, o.fecha_inicio, o.fecha_fin, o.observaciones, o.created_at,
                       r.codigo AS receta_codigo,
                       p.nombre AS producto_nombre,
                       ' . ($usaAlmacenOrigen ? 'COALESCE(ao.nombre, ad.nombre)' : 'ad.nombre') . ' AS almacen_origen_nombre,
                       ad.nombre AS almacen_destino_nombre
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas r ON r.id = o.id_receta
                INNER JOIN items p ON p.id = r.id_producto
                ' . ($usaAlmacenOrigen ? 'LEFT JOIN almacenes ao ON ao.id = o.id_almacen_origen' : '') . '
                INNER JOIN almacenes ad ON ad.id = o.id_almacen_destino
                WHERE o.deleted_at IS NULL
                ORDER BY COALESCE(o.updated_at, o.created_at) DESC, o.id DESC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function tablaTieneColumna(string $tabla, string $columna): bool
    {
        if ($tabla === 'produccion_ordenes' && $columna === 'id_almacen_origen' && $this->produccionOrdenesTieneAlmacenOrigen !== null) {
            return $this->produccionOrdenesTieneAlmacenOrigen;
        }

        $stmt = $this->db()->prepare('SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabla
              AND COLUMN_NAME = :columna
            LIMIT 1');
        $stmt->execute([
            'tabla' => $tabla,
            'columna' => $columna,
        ]);

        $existe = (bool) $stmt->fetchColumn();

        if ($tabla === 'produccion_ordenes' && $columna === 'id_almacen_origen') {
            $this->produccionOrdenesTieneAlmacenOrigen = $existe;
        }

        return $existe;
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
        // Se asegura de traer el tipo_item para las validaciones cruzadas
        $sql = 'SELECT i.id, i.sku, i.nombre, i.tipo_item, i.requiere_lote, i.costo_referencial,
                       COALESCE(
                           NULLIF(i.costo_referencial, 0),
                           rec.costo_teorico_unitario,
                           mov.costo_unitario,
                           0
                       ) AS costo_calculado
                FROM items i
                LEFT JOIN (
                    SELECT r1.id_producto, r1.costo_teorico_unitario
                    FROM produccion_recetas r1
                    INNER JOIN (
                        SELECT id_producto, MAX(id) AS max_id
                        FROM produccion_recetas
                        WHERE estado = 1
                          AND deleted_at IS NULL
                        GROUP BY id_producto
                    ) latest_receta ON latest_receta.max_id = r1.id
                ) rec ON rec.id_producto = i.id
                LEFT JOIN (
                    SELECT m1.id_item, m1.costo_unitario
                    FROM inventario_movimientos m1
                    INNER JOIN (
                        SELECT id_item, MAX(id) AS max_id
                        FROM inventario_movimientos
                        WHERE costo_unitario IS NOT NULL
                          AND costo_unitario > 0
                        GROUP BY id_item
                    ) latest_mov ON latest_mov.max_id = m1.id
                ) mov ON mov.id_item = i.id
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL
                  AND i.controla_stock = 1
                ORDER BY i.nombre ASC';

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

    // --- NUEVO MÉTODO: Catálogo de Parámetros ---
    public function listarParametrosCatalogo(): array
    {
        $sql = 'SELECT id, nombre, unidad_medida, descripcion 
                FROM produccion_parametros_catalogo 
                ORDER BY nombre ASC';

        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearParametroCatalogo(array $data): int
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $unidadMedida = trim((string) ($data['unidad_medida'] ?? ''));
        $descripcion = trim((string) ($data['descripcion'] ?? ''));

        if ($nombre === '') {
            throw new RuntimeException('El nombre del parámetro es obligatorio.');
        }

        $stmt = $this->db()->prepare('INSERT INTO produccion_parametros_catalogo (nombre, unidad_medida, descripcion)
                                      VALUES (:nombre, :unidad_medida, :descripcion)');
        $stmt->execute([
            'nombre' => $nombre,
            'unidad_medida' => $unidadMedida !== '' ? $unidadMedida : null,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarParametroCatalogo(int $id, array $data): bool
    {
        if ($id <= 0) {
            throw new RuntimeException('Parámetro inválido.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $unidadMedida = trim((string) ($data['unidad_medida'] ?? ''));
        $descripcion = trim((string) ($data['descripcion'] ?? ''));

        if ($nombre === '') {
            throw new RuntimeException('El nombre del parámetro es obligatorio.');
        }

        $stmt = $this->db()->prepare('UPDATE produccion_parametros_catalogo
                                      SET nombre = :nombre,
                                          unidad_medida = :unidad_medida,
                                          descripcion = :descripcion
                                      WHERE id = :id');

        return $stmt->execute([
            'id' => $id,
            'nombre' => $nombre,
            'unidad_medida' => $unidadMedida !== '' ? $unidadMedida : null,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
        ]);
    }

    public function eliminarParametroCatalogo(int $id): bool
    {
        if ($id <= 0) {
            throw new RuntimeException('Parámetro inválido.');
        }

        $stmtUso = $this->db()->prepare('SELECT COUNT(*) FROM produccion_recetas_parametros WHERE id_parametro = :id');
        $stmtUso->execute(['id' => $id]);
        if ((int) $stmtUso->fetchColumn() > 0) {
            throw new RuntimeException('No se puede eliminar el parámetro porque está asociado a recetas.');
        }

        $stmt = $this->db()->prepare('DELETE FROM produccion_parametros_catalogo WHERE id = :id');
        return $stmt->execute(['id' => $id]);
    }

    // --- NUEVO MÉTODO: Parámetros específicos de una receta ---
    public function obtenerParametrosReceta(int $idReceta): array
    {
        $sql = 'SELECT id_parametro, valor_objetivo, margen_tolerancia 
                FROM produccion_recetas_parametros 
                WHERE id_receta = :id_receta';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_receta' => $idReceta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearReceta(array $payload, int $userId): int
    {
        $idProducto = (int) ($payload['id_producto'] ?? 0);
        $codigo = trim((string) ($payload['codigo'] ?? ''));
        $version = max(1, (int) ($payload['version'] ?? 1));
        $descripcion = trim((string) ($payload['descripcion'] ?? ''));
        $rendimientoBase = (float) ($payload['rendimiento_base'] ?? 0);
        $unidadRendimiento = trim((string) ($payload['unidad_rendimiento'] ?? ''));
        
        $detalles = is_array($payload['detalles'] ?? null) ? $payload['detalles'] : [];
        // Nueva variable para los parámetros dinámicos
        $parametros = is_array($payload['parametros'] ?? null) ? $payload['parametros'] : [];

        if ($idProducto <= 0 || $codigo === '' || $detalles === [] || $rendimientoBase <= 0) {
            throw new RuntimeException('Debe completar producto, código, rendimiento base y al menos un detalle de insumos.');
        }

        $insumosUtilizados = [];
        foreach ($detalles as $detalle) {
            $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
            if ($idInsumo <= 0) {
                continue;
            }

            if ($idInsumo === $idProducto) {
                throw new RuntimeException('No se puede usar el mismo producto destino como insumo de su propia receta.');
            }

            if (isset($insumosUtilizados[$idInsumo])) {
                throw new RuntimeException('No se permiten insumos repetidos en la misma receta.');
            }

            $insumosUtilizados[$idInsumo] = true;
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Calcular Costo Total Teórico de la Receta (recursividad a través del costo_referencial)
            $costoTotalReceta = 0.0;
            foreach ($detalles as $detalle) {
                $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
                $cantidad = (float) ($detalle['cantidad_por_unidad'] ?? 0);
                $merma = (float) ($detalle['merma_porcentaje'] ?? 0);

                if ($idInsumo > 0 && $cantidad > 0) {
                    $costoCapturado = isset($detalle['costo_unitario']) ? (float) $detalle['costo_unitario'] : 0.0;
                    $costoInsumo = $costoCapturado > 0 ? $costoCapturado : $this->obtenerCostoReferencial($idInsumo);
                    $cantidadReal = $cantidad * (1 + ($merma / 100));
                    $costoTotalReceta += ($costoInsumo * $cantidadReal);
                }
            }

            $costoUnitarioTeorico = $costoTotalReceta / $rendimientoBase;

            // 2. Reusar receta pendiente sin detalle o insertar una nueva receta
            $stmtPendiente = $db->prepare('SELECT r.id
                                           FROM produccion_recetas r
                                           LEFT JOIN produccion_recetas_detalle d ON d.id_receta = r.id AND d.deleted_at IS NULL
                                           WHERE r.codigo = :codigo
                                             AND r.id_producto = :id_producto
                                             AND r.deleted_at IS NULL
                                           GROUP BY r.id
                                           HAVING COUNT(d.id) = 0
                                           LIMIT 1');
            $stmtPendiente->execute([
                'codigo' => $codigo,
                'id_producto' => $idProducto,
            ]);
            $idRecetaPendiente = (int) ($stmtPendiente->fetchColumn() ?: 0);

            if ($idRecetaPendiente > 0) {
                $stmt = $db->prepare('UPDATE produccion_recetas
                                      SET version = :version,
                                          descripcion = :descripcion,
                                          rendimiento_base = :rendimiento_base,
                                          unidad_rendimiento = :unidad_rendimiento,
                                          costo_teorico_unitario = :costo_unitario,
                                          estado = 1,
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id_receta');
                $stmt->execute([
                    'version' => $version,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'rendimiento_base' => number_format($rendimientoBase, 4, '.', ''),
                    'unidad_rendimiento' => $unidadRendimiento !== '' ? $unidadRendimiento : null,
                    'costo_unitario' => number_format($costoUnitarioTeorico, 4, '.', ''),
                    'updated_by' => $userId,
                    'id_receta' => $idRecetaPendiente,
                ]);
                $idReceta = $idRecetaPendiente;
            } else {
                $stmtExiste = $db->prepare('SELECT id FROM produccion_recetas WHERE codigo = :codigo AND deleted_at IS NULL LIMIT 1');
                $stmtExiste->execute(['codigo' => $codigo]);
                if ((int) ($stmtExiste->fetchColumn() ?: 0) > 0) {
                    throw new RuntimeException('Ya existe una receta con ese código. Use "Nueva Versión" o cambie el código.');
                }

                $stmt = $db->prepare('INSERT INTO produccion_recetas
                                        (id_producto, codigo, version, descripcion,
                                         rendimiento_base, unidad_rendimiento,
                                         costo_teorico_unitario, estado, created_by, updated_by)
                                      VALUES
                                        (:id_producto, :codigo, :version, :descripcion,
                                         :rendimiento_base, :unidad_rendimiento,
                                         :costo_unitario, 1, :created_by, :updated_by)');

                $stmt->execute([
                    'id_producto' => $idProducto,
                    'codigo' => $codigo,
                    'version' => $version,
                    'descripcion' => $descripcion !== '' ? $descripcion : null,
                    'rendimiento_base' => number_format($rendimientoBase, 4, '.', ''),
                    'unidad_rendimiento' => $unidadRendimiento !== '' ? $unidadRendimiento : null,
                    'costo_unitario' => number_format($costoUnitarioTeorico, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idReceta = (int) $db->lastInsertId();
            }

            // 3. Desactivar versiones anteriores de receta para este producto
            $stmtDesactivar = $db->prepare('UPDATE produccion_recetas
                                            SET estado = 0,
                                                updated_at = NOW(),
                                                updated_by = :updated_by
                                            WHERE id_producto = :id_producto
                                              AND id <> :id_receta
                                              AND deleted_at IS NULL');
            $stmtDesactivar->execute([
                'updated_by' => $userId,
                'id_producto' => $idProducto,
                'id_receta' => $idReceta,
            ]);

            // 4. Insertar Detalles de la BOM (Insumos)
            $stmtDet = $db->prepare('INSERT INTO produccion_recetas_detalle
                                        (id_receta, id_insumo, etapa, cantidad_por_unidad, merma_porcentaje, costo_unitario, created_by, updated_by)
                                     VALUES
                                        (:id_receta, :id_insumo, :etapa, :cantidad_por_unidad, :merma_porcentaje, :costo_unitario, :created_by, :updated_by)');

            foreach ($detalles as $detalle) {
                $idInsumo = (int) ($detalle['id_insumo'] ?? 0);
                $cantidad = (float) ($detalle['cantidad_por_unidad'] ?? 0);
                $merma = (float) ($detalle['merma_porcentaje'] ?? 0);
                $etapa = trim((string) ($detalle['etapa'] ?? 'General'));
                $costoUnitario = isset($detalle['costo_unitario']) ? (float) $detalle['costo_unitario'] : $this->obtenerCostoReferencial($idInsumo);

                if ($idInsumo <= 0 || $cantidad <= 0) {
                    throw new RuntimeException('Detalle de receta inválido.');
                }

                $stmtDet->execute([
                    'id_receta' => $idReceta,
                    'id_insumo' => $idInsumo,
                    'etapa' => $etapa,
                    'cantidad_por_unidad' => number_format($cantidad, 4, '.', ''),
                    'merma_porcentaje' => number_format($merma, 2, '.', ''),
                    'costo_unitario' => number_format(max(0, $costoUnitario), 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            // 5. NUEVO: Insertar Parámetros Dinámicos (IPC)
            if (!empty($parametros)) {
                $stmtParam = $db->prepare('INSERT INTO produccion_recetas_parametros 
                                            (id_receta, id_parametro, valor_objetivo) 
                                           VALUES 
                                            (:id_receta, :id_parametro, :valor_objetivo)');

                foreach ($parametros as $param) {
                    $idParametro = (int) ($param['id_parametro'] ?? 0);
                    $valorObjetivo = $param['valor_objetivo'] ?? '';

                    if ($idParametro > 0 && $valorObjetivo !== '') {
                        $stmtParam->execute([
                            'id_receta' => $idReceta,
                            'id_parametro' => $idParametro,
                            'valor_objetivo' => number_format((float) $valorObjetivo, 4, '.', '')
                        ]);
                    }
                }
            }

            // 6. Actualizar Costo Referencial en la tabla de Items
            $stmtUpdateItem = $db->prepare('UPDATE items 
                                            SET costo_referencial = :costo,
                                                updated_at = NOW(),
                                                updated_by = :user
                                            WHERE id = :id');
            $stmtUpdateItem->execute([
                'costo' => number_format($costoUnitarioTeorico, 4, '.', ''),
                'user' => $userId,
                'id' => $idProducto
            ]);

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
        $idAlmacenDestino = (int) ($payload['id_almacen_destino'] ?? 0);
        $idAlmacenOrigen = (int) ($payload['id_almacen_origen'] ?? 0);
        if ($idAlmacenOrigen <= 0) {
            $idAlmacenOrigen = $idAlmacenDestino;
        }
        $cantidadPlanificada = (float) ($payload['cantidad_planificada'] ?? 0);
        $observaciones = trim((string) ($payload['observaciones'] ?? ''));

        if ($codigo === '' || $idReceta <= 0 || $idAlmacenDestino <= 0 || $cantidadPlanificada <= 0) {
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

    public function ejecutarOrden(int $idOrden, array $consumos, array $ingresos, int $userId): void
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

            $idAlmacenDestino = (int) $orden['id_almacen_destino'];
            $idAlmacenOrigen = (int) ($orden['id_almacen_origen'] ?? 0);
            if ($idAlmacenOrigen <= 0) {
                $idAlmacenOrigen = $idAlmacenDestino;
            }
            $costoTotalConsumo = 0.0;

            // --- 1. PROCESAR CONSUMOS (Salidas) ---
            $stmtConsumo = $db->prepare('INSERT INTO produccion_consumos
                                            (id_orden_produccion, id_item, id_almacen, id_lote, cantidad, costo_unitario, created_by, updated_by)
                                         VALUES
                                            (:id_orden_produccion, :id_item, :id_almacen, :id_lote, :cantidad, :costo_unitario, :created_by, :updated_by)');

            foreach ($consumos as $consumo) {
                $idInsumo = (int) $consumo['id_insumo'];
                $idAlmacenOrigen = (int) $consumo['id_almacen'];
                $cantidadUsada = (float) $consumo['cantidad'];
                $idLote = !empty($consumo['id_lote']) ? (int)$consumo['id_lote'] : null;

                if ($cantidadUsada <= 0) continue;

                // Validamos que exista stock en ese almacén específico
                $stock = $this->obtenerStockItemAlmacen($idInsumo, $idAlmacenOrigen);
                if ($stock < $cantidadUsada) {
                    throw new RuntimeException('Stock insuficiente para el insumo ID ' . $idInsumo . ' en el almacén seleccionado.');
                }

                $costoUnitario = $this->obtenerCostoReferencial($idInsumo);
                $costoTotalConsumo += ($costoUnitario * $cantidadUsada);

                $stmtConsumo->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => $idInsumo,
                    'id_almacen' => $idAlmacenOrigen,
                    'id_lote' => $idLote, 
                    'cantidad' => number_format($cantidadUsada, 4, '.', ''),
                    'costo_unitario' => number_format($costoUnitario, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // Descontar de inventario
                if (method_exists($inventarioModel, 'registrarMovimiento')) {
                    $inventarioModel->registrarMovimiento([
                        'tipo_movimiento' => 'CON', 
                        'id_item' => $idInsumo,
                        'id_almacen_origen' => $idAlmacenOrigen,
                        'id_almacen_destino' => null, 
                        'cantidad' => $cantidadUsada,
                        'referencia' => 'OP ' . $orden['codigo'] . ' consumo',
                        'lote' => $idLote,
                        'costo_unitario' => $costoUnitario,
                        'created_by' => $userId
                    ]);
                }
            }

            // --- 2. PROCESAR INGRESOS (Entradas de producto terminado) ---
            // Sumamos la cantidad total real producida para calcular el costo unitario
            $cantidadTotalProducida = array_sum(array_column($ingresos, 'cantidad'));
            
            if ($cantidadTotalProducida <= 0) {
                throw new RuntimeException('La cantidad total producida debe ser mayor a cero.');
            }

            $costoUnitarioIngreso = $costoTotalConsumo / $cantidadTotalProducida;

            $stmtIngreso = $db->prepare('INSERT INTO produccion_ingresos
                                            (id_orden_produccion, id_item, id_almacen, id_lote, cantidad, costo_unitario_calculado, created_by, updated_by)
                                         VALUES
                                            (:id_orden_produccion, :id_item, :id_almacen, :id_lote, :cantidad, :costo_unitario_calculado, :created_by, :updated_by)');
            
            foreach ($ingresos as $ingreso) {
                $idAlmacenDestino = (int) $ingreso['id_almacen'];
                $cantidadIngresada = (float) $ingreso['cantidad'];
                $idLote = !empty($ingreso['id_lote']) ? (int)$ingreso['id_lote'] : null;

                if ($cantidadIngresada <= 0) continue;

                $stmtIngreso->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => (int) $orden['id_producto'],
                    'id_almacen' => $idAlmacenDestino,
                    'id_lote' => $idLote,
                    'cantidad' => number_format($cantidadIngresada, 4, '.', ''),
                    'costo_unitario_calculado' => number_format($costoUnitarioIngreso, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                // Ingresar a inventario
                if (method_exists($inventarioModel, 'registrarMovimiento')) {
                    $inventarioModel->registrarMovimiento([
                        'tipo_movimiento' => 'PROD', 
                        'id_item' => (int) $orden['id_producto'],
                        'id_almacen_origen' => null,
                        'id_almacen_destino' => $idAlmacenDestino,
                        'cantidad' => $cantidadIngresada,
                        'referencia' => 'OP ' . $orden['codigo'] . ' finalizado',
                        'lote' => $idLote,
                        'costo_unitario' => $costoUnitarioIngreso,
                        'created_by' => $userId
                    ]);
                }
            }

            // --- 3. ACTUALIZAR CABECERA DE LA OP ---
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
                'cantidad_producida' => number_format($cantidadTotalProducida, 4, '.', ''),
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

    private function obtenerCostoReferencial(int $idItem): float
    {
        $stmt = $this->db()->prepare('SELECT COALESCE(
                                            NULLIF(i.costo_referencial, 0),
                                            rec.costo_teorico_unitario,
                                            mov.costo_unitario,
                                            0
                                        ) AS costo
                                      FROM items i
                                      LEFT JOIN (
                                          SELECT r1.id_producto, r1.costo_teorico_unitario
                                          FROM produccion_recetas r1
                                          INNER JOIN (
                                              SELECT id_producto, MAX(id) AS max_id
                                              FROM produccion_recetas
                                              WHERE estado = 1
                                                AND deleted_at IS NULL
                                              GROUP BY id_producto
                                          ) latest_receta ON latest_receta.max_id = r1.id
                                      ) rec ON rec.id_producto = i.id
                                      LEFT JOIN (
                                          SELECT m1.id_item, m1.costo_unitario
                                          FROM inventario_movimientos m1
                                          INNER JOIN (
                                              SELECT id_item, MAX(id) AS max_id
                                              FROM inventario_movimientos
                                              WHERE costo_unitario IS NOT NULL
                                                AND costo_unitario > 0
                                              GROUP BY id_item
                                          ) latest_mov ON latest_mov.max_id = m1.id
                                      ) mov ON mov.id_item = i.id
                                      WHERE i.id = :id
                                      LIMIT 1');
        $stmt->execute(['id' => $idItem]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    public function crearNuevaVersion(int $idRecetaBase, int $userId): int
    {
        $receta = $this->obtenerRecetaPorId($idRecetaBase);
        if ($receta === []) {
            throw new RuntimeException('La receta base no existe.');
        }

        $detalles = $this->obtenerDetalleReceta($idRecetaBase);
        if ($detalles === []) {
            throw new RuntimeException('La receta base no tiene detalles.');
        }

        // Obtener los parámetros de la receta anterior
        $parametrosOld = $this->obtenerParametrosReceta($idRecetaBase);

        $siguienteVersion = $this->obtenerSiguienteVersion((int) $receta['id_producto']);

        return $this->crearReceta([
            'id_producto' => (int) $receta['id_producto'],
            'codigo' => $this->generarCodigoVersion((string) $receta['codigo'], $siguienteVersion),
            'version' => $siguienteVersion,
            'descripcion' => (string) ($receta['descripcion'] ?? ''),
            'rendimiento_base' => (float) ($receta['rendimiento_base'] ?? 0),
            'unidad_rendimiento' => (string) ($receta['unidad_rendimiento'] ?? ''),
            
            // Pasar los detalles
            'detalles' => array_map(static fn (array $d): array => [
                'id_insumo' => (int) $d['id_insumo'],
                'etapa' => (string) ($d['etapa'] ?? ''),
                'cantidad_por_unidad' => (float) $d['cantidad_por_unidad'],
                'merma_porcentaje' => (float) $d['merma_porcentaje'],
            ], $detalles),

            // Pasar los parámetros dinámicos heredados
            'parametros' => array_map(static fn (array $p): array => [
                'id_parametro' => (int) $p['id_parametro'],
                'valor_objetivo' => (float) $p['valor_objetivo']
            ], $parametrosOld),

        ], $userId);
    }

    private function obtenerRecetaPorId(int $idReceta): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM produccion_recetas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $idReceta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: [];
    }

    private function obtenerSiguienteVersion(int $idProducto): int
    {
        $stmt = $this->db()->prepare('SELECT COALESCE(MAX(version), 0) + 1 FROM produccion_recetas WHERE id_producto = :id_producto AND deleted_at IS NULL');
        $stmt->execute(['id_producto' => $idProducto]);
        return max(1, (int) $stmt->fetchColumn());
    }

    private function generarCodigoVersion(string $codigoBase, int $version): string
    {
        $codigoLimpio = preg_replace('/-V\d+$/', '', trim($codigoBase));
        return sprintf('%s-V%d', $codigoLimpio ?: 'REC', $version);
    }
}
