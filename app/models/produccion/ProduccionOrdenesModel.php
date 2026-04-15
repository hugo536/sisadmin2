<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/inventario/InventarioModel.php';
require_once BASE_PATH . '/app/models/items/ItemModel.php';
require_once BASE_PATH . '/app/models/contabilidad/ContaAsientoModel.php';

class ProduccionOrdenesModel extends Modelo
{
    // =====================================================================
    // OBTENER DATOS PARA EL CALENDARIO DEL PLANIFICADOR (BLINDADO)
    // =====================================================================
    public function obtenerDatosPlanificador(string $desde, string $hasta): array
    {
        $sqlOps = 'SELECT DATE(o.fecha_programada) AS fecha_prog,
                          o.codigo AS op_codigo,
                          o.estado AS op_estado,
                          COALESCE(p.nombre, "Producto no encontrado") AS producto_nombre,
                          COALESCE(r.codigo, "Sin Receta") AS receta_codigo,
                          o.cantidad_planificada
                   FROM produccion_ordenes o
                   LEFT JOIN produccion_recetas r ON r.id = o.id_receta
                   LEFT JOIN items p ON p.id = r.id_producto
                   WHERE DATE(o.fecha_programada) >= :desde
                     AND DATE(o.fecha_programada) <= :hasta
                     AND o.estado IN (0, 1, 2)
                     AND o.deleted_at IS NULL
                   ORDER BY DATE(o.fecha_programada) ASC, o.id ASC';

        $stmtOps = $this->db()->prepare($sqlOps);
        $stmtOps->execute(['desde' => $desde, 'hasta' => $hasta]);
        $rawOps = $stmtOps->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $opsPorFecha = [];
        foreach ($rawOps as $row) {
            $fecha = (string) ($row['fecha_prog'] ?? '');
            if ($fecha === '') continue;

            if (!isset($opsPorFecha[$fecha])) {
                $opsPorFecha[$fecha] = [
                    'ops' => 0,
                    'detalle' => [],
                ];
            }

            $opsPorFecha[$fecha]['ops']++;
            $opsPorFecha[$fecha]['detalle'][] = [
                'codigo' => (string) ($row['op_codigo'] ?? '-'),
                'producto' => (string) ($row['producto_nombre'] ?? '-'),
                'receta' => (string) ($row['receta_codigo'] ?? '-'),
                'cantidad' => round((float) ($row['cantidad_planificada'] ?? 0), 2),
                'estado' => (int) ($row['op_estado'] ?? 0)
            ];
        }

        $planes = [];
        try {
            $sqlPlan = 'SELECT DATE(fecha) as fecha_plan, tipo_horario
                        FROM produccion_grupos_diarios
                        WHERE DATE(fecha) >= :desde AND DATE(fecha) <= :hasta';

            $stmtPlan = $this->db()->prepare($sqlPlan);
            $stmtPlan->execute(['desde' => $desde, 'hasta' => $hasta]);
            $planes = $stmtPlan->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            if ((string) $e->getCode() !== '42S02') {
                throw $e;
            }
        }

        $diccionario = [];

        foreach ($planes as $plan) {
            $fecha = (string) ($plan['fecha_plan'] ?? '');
            if ($fecha === '') continue;

            $tipo = strtolower((string) ($plan['tipo_horario'] ?? 'normal'));

            if (!isset($diccionario[$fecha])) {
                $diccionario[$fecha] = [
                    'tipo' => $tipo,
                    'ops' => $opsPorFecha[$fecha]['ops'] ?? 0,
                    'detalle' => $opsPorFecha[$fecha]['detalle'] ?? [],
                ];
            } elseif ($tipo === 'excepcion') {
                $diccionario[$fecha]['tipo'] = 'excepcion';
            }
        }

        foreach ($opsPorFecha as $fecha => $registroOp) {
            if (!isset($diccionario[$fecha])) {
                $diccionario[$fecha] = [
                    'tipo' => 'normal',
                    'ops' => $registroOp['ops'],
                    'detalle' => $registroOp['detalle'],
                ];
            }
        }

        return $diccionario;
    }

    public function listarOrdenes(): array
    {
        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.cantidad_planificada, o.cantidad_producida,
                       o.estado, o.fecha_programada,
                       o.fecha_inicio, o.fecha_fin, o.observaciones, o.justificacion_ajuste, o.created_at,
                       o.id_almacen_planta, ap.nombre AS almacen_planta_nombre,
                       o.id_producto_snapshot, o.receta_codigo_snapshot, o.receta_version_snapshot,
                       o.costo_teorico_unitario_snapshot, o.costo_teorico_total_snapshot,
                       COALESCE(o.total_md_real, o.costo_md_real, 0) AS costo_md_real,
                       COALESCE(o.total_mod_real, o.costo_mod_real, 0) AS costo_mod_real,
                       COALESCE(o.total_cif_real, o.costo_cif_real, 0) AS costo_cif_real,
                       COALESCE(o.costo_unitario_real, o.costo_real_unitario, 0) AS costo_real_unitario,
                       o.costo_real_total,
                       r.codigo AS receta_codigo,
                       COALESCE(r.rendimiento_base, 0) AS receta_rendimiento_base,
                       COALESCE(r.tiempo_produccion_horas, 0) AS receta_tiempo_horas,
                       COALESCE(r.costo_md_teorico, 0) AS costo_md_teorico_unit,
                       COALESCE(r.costo_mod_teorico, 0) AS costo_mod_teorico_unit,
                       COALESCE(r.costo_cif_teorico, 0) AS costo_cif_teorico_unit,
                       r.id_centro_costo AS receta_id_centro_costo,
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
        $idAlmacenPlanta = (int) ($payload['id_almacen_planta'] ?? 0);
        $observaciones = trim((string) ($payload['observaciones'] ?? ''));

        if ($cantidadPlanificada <= 0 || $fechaProgramada === '' || $idAlmacenPlanta <= 0) {
            throw new RuntimeException('Datos incompletos para editar la orden de producción.');
        }

        if (DateTime::createFromFormat('Y-m-d', $fechaProgramada) === false) {
            throw new RuntimeException('La fecha programada no tiene un formato válido.');
        }

        $stmtAlmacenPlanta = $this->db()->prepare('SELECT COUNT(*) FROM almacenes WHERE id = :id AND estado = 1 AND deleted_at IS NULL AND tipo = :tipo');
        $stmtAlmacenPlanta->execute(['id' => $idAlmacenPlanta, 'tipo' => 'Planta']);
        if ((int) $stmtAlmacenPlanta->fetchColumn() <= 0) {
            throw new RuntimeException('El almacén de planta seleccionado no es válido.');
        }

        $snapshot = $this->obtenerSnapshotReceta((int) ($orden['id_receta'] ?? 0), $cantidadPlanificada);

        $stmt = $this->db()->prepare('UPDATE produccion_ordenes
                                      SET cantidad_planificada = :cantidad_planificada,
                                          fecha_programada = :fecha_programada,
                                          id_almacen_planta = :id_almacen_planta,
                                          id_producto_snapshot = :id_producto_snapshot,
                                          receta_codigo_snapshot = :receta_codigo_snapshot,
                                          receta_version_snapshot = :receta_version_snapshot,
                                          costo_teorico_unitario_snapshot = :costo_teorico_unitario_snapshot,
                                          costo_teorico_total_snapshot = :costo_teorico_total_snapshot,
                                          observaciones = :observaciones,
                                          updated_at = NOW(),
                                          updated_by = :updated_by
                                      WHERE id = :id AND estado = 0 AND deleted_at IS NULL');
        $stmt->execute([
            'cantidad_planificada' => number_format($cantidadPlanificada, 4, '.', ''),
            'fecha_programada' => $fechaProgramada,
            'id_almacen_planta' => $idAlmacenPlanta,
            'id_producto_snapshot' => $snapshot['id_producto_snapshot'],
            'receta_codigo_snapshot' => $snapshot['receta_codigo_snapshot'],
            'receta_version_snapshot' => $snapshot['receta_version_snapshot'],
            'costo_teorico_unitario_snapshot' => number_format($snapshot['costo_teorico_unitario_snapshot'], 4, '.', ''),
            'costo_teorico_total_snapshot' => number_format($snapshot['costo_teorico_total_snapshot'], 4, '.', ''),
            'observaciones' => $observaciones !== '' ? $observaciones : null,
            'updated_by' => $userId,
            'id' => $idOrden,
        ]);
    }

    public function eliminarOrdenBorrador(int $idOrden, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE produccion_ordenes
                                      SET deleted_at = NOW(), updated_at = NOW(), updated_by = :updated_by
                                      WHERE id = :id AND estado IN (0, 1) AND deleted_at IS NULL');
        $stmt->execute(['id' => $idOrden, 'updated_by' => $userId]);
        if ($stmt->rowCount() < 1) {
            throw new RuntimeException('Solo se pueden eliminar órdenes en Borrador o En proceso.');
        }
    }

    public function marcarOrdenEnProceso(int $idOrden, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE produccion_ordenes SET estado = 1, fecha_inicio = COALESCE(fecha_inicio, NOW()), updated_at = NOW(), updated_by = :updated_by WHERE id = :id AND estado = 0 AND deleted_at IS NULL');
        $stmt->execute(['id' => $idOrden, 'updated_by' => $userId]);
    }

    private function evaluarPrecheckOrden(int $idOrden): array
    {
        if ($idOrden <= 0) {
            return ['ok' => false, 'resumen' => 'Sin datos para pre-chequeo', 'detalle' => []];
        }

        $sql = 'SELECT d.id_insumo,
                       i.nombre AS insumo_nombre,
                       i.requiere_lote,
                       (d.cantidad_por_unidad * (o.cantidad_planificada / NULLIF(r.rendimiento_base, 0)) * (1 + (d.merma_porcentaje / 100))) AS qty_requerida,
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
                INNER JOIN produccion_recetas r ON r.id = o.id_receta AND r.deleted_at IS NULL
                INNER JOIN produccion_recetas_detalle d ON d.id_receta = o.id_receta AND d.deleted_at IS NULL
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE o.id = :id_orden AND o.deleted_at IS NULL';

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
                $faltantes[] = sprintf('%s (requiere lote)', (string) ($fila['insumo_nombre'] ?? 'Insumo'));
            }

            $detalle[] = [
                'insumo' => (string) ($fila['insumo_nombre'] ?? ''),
                'requerido' => $requerido,
                'en_planta' => $stock,
                'estado' => $estadoItem,
            ];
        }

        $resumen = $ok ? 'Stock de planta suficiente para todos los insumos' : ('Faltantes: ' . implode('; ', $faltantes));

        return ['ok' => $ok, 'resumen' => $resumen, 'detalle' => $detalle];
    }

    public function listarRecetasActivas(): array
    {
        $sql = 'SELECT r.id, r.codigo, r.version, i.nombre AS producto_nombre,
                       r.rendimiento_base, 
                       r.tiempo_produccion_horas AS tiempo_estimado_horas,
                       r.id_almacen_planta
                FROM produccion_recetas r
                INNER JOIN items i ON i.id = r.id_producto
                WHERE r.estado = 1 AND r.deleted_at IS NULL
                ORDER BY i.nombre ASC';
        
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function buscarInsumosStockeables(string $termino, int $limite = 30): array
    {
        $busqueda = '%' . $termino . '%';
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote, costo_referencial
                FROM items
                WHERE estado = 1 AND deleted_at IS NULL AND controla_stock = 1
                  AND (nombre LIKE :termino_nombre OR sku LIKE :termino_sku)
                ORDER BY nombre ASC LIMIT ' . (int)$limite;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['termino_nombre' => $busqueda, 'termino_sku' => $busqueda]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as &$item) {
            $item['costo_calculado'] = $this->obtenerCostoReferencial((int)$item['id']);
        }
        return $items;
    }

    public function listarItemsStockeables(): array
    {
        $sql = 'SELECT id, sku, nombre, tipo_item, requiere_lote, costo_referencial
                FROM items
                WHERE estado = 1 AND deleted_at IS NULL AND controla_stock = 1
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
        $sql = 'SELECT id, nombre FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarAlmacenesActivosPorTipo(string $tipo): array
    {
        $tipo = trim($tipo);
        if ($tipo === '') return [];
        $stmt = $this->db()->prepare('SELECT id, nombre FROM almacenes WHERE estado = 1 AND deleted_at IS NULL AND tipo = :tipo ORDER BY nombre ASC');
        $stmt->execute(['tipo' => $tipo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function crearOrden(array $payload, int $userId): int
    {
        $codigo = trim((string) ($payload['codigo'] ?? ''));
        $idReceta = (int) ($payload['id_receta'] ?? 0);
        $cantidadPlanificada = (float) ($payload['cantidad_planificada'] ?? 0);
        $fechaProgramada = trim((string) ($payload['fecha_programada'] ?? ''));
        $observaciones = trim((string) ($payload['observaciones'] ?? ''));
        $idAlmacenPlanta = (int) ($payload['id_almacen_planta'] ?? 0);

        if ($codigo === '' || $idReceta <= 0 || $cantidadPlanificada <= 0 || $fechaProgramada === '' || $idAlmacenPlanta <= 0) {
            throw new RuntimeException('Datos incompletos para crear la orden de producción.');
        }

        if (DateTime::createFromFormat('Y-m-d', $fechaProgramada) === false) {
            throw new RuntimeException('La fecha programada no tiene un formato válido.');
        }

        $stmtAlmacenPlanta = $this->db()->prepare('SELECT COUNT(*) FROM almacenes WHERE id = :id AND estado = 1 AND deleted_at IS NULL AND tipo = :tipo');
        $stmtAlmacenPlanta->execute(['id' => $idAlmacenPlanta, 'tipo' => 'Planta']);
        if ((int) $stmtAlmacenPlanta->fetchColumn() <= 0) {
            throw new RuntimeException('El almacén de planta seleccionado no es válido.');
        }

        $snapshot = $this->obtenerSnapshotReceta($idReceta, $cantidadPlanificada);

        $sql = 'INSERT INTO produccion_ordenes
                    (codigo, id_receta, id_producto_snapshot, receta_codigo_snapshot, receta_version_snapshot,
                     costo_teorico_unitario_snapshot, costo_teorico_total_snapshot,
                     id_almacen_planta, cantidad_planificada, fecha_programada, estado, created_by, updated_by, observaciones)
                VALUES
                    (:codigo, :id_receta, :id_producto_snapshot, :receta_codigo_snapshot, :receta_version_snapshot,
                     :costo_teorico_unitario_snapshot, :costo_teorico_total_snapshot,
                     :id_almacen_planta, :cantidad_planificada, :fecha_programada, 0, :created_by, :updated_by, :observaciones)';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'codigo' => $codigo,
            'id_receta' => $idReceta,
            'id_producto_snapshot' => $snapshot['id_producto_snapshot'],
            'receta_codigo_snapshot' => $snapshot['receta_codigo_snapshot'],
            'receta_version_snapshot' => $snapshot['receta_version_snapshot'],
            'costo_teorico_unitario_snapshot' => number_format($snapshot['costo_teorico_unitario_snapshot'], 4, '.', ''),
            'costo_teorico_total_snapshot' => number_format($snapshot['costo_teorico_total_snapshot'], 4, '.', ''),
            'id_almacen_planta' => $idAlmacenPlanta,
            'cantidad_planificada' => number_format($cantidadPlanificada, 4, '.', ''),
            'fecha_programada' => $fechaProgramada,
            'created_by' => $userId,
            'updated_by' => $userId,
            'observaciones' => $observaciones !== '' ? $observaciones : null,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function obtenerSnapshotReceta(int $idReceta, float $cantidadPlanificada): array
    {
        $stmt = $this->db()->prepare('SELECT id_producto, codigo, version, costo_teorico_unitario FROM produccion_recetas WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $idReceta]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        if ($row === []) {
            throw new RuntimeException('La receta de producción no existe o fue eliminada.');
        }

        $costoTeoricoUnitario = (float) ($row['costo_teorico_unitario'] ?? 0);

        return [
            'id_producto_snapshot' => (int) ($row['id_producto'] ?? 0),
            'receta_codigo_snapshot' => (string) ($row['codigo'] ?? ''),
            'receta_version_snapshot' => (int) ($row['version'] ?? 1),
            'costo_teorico_unitario_snapshot' => $costoTeoricoUnitario,
            'costo_teorico_total_snapshot' => $costoTeoricoUnitario * max(0, $cantidadPlanificada),
        ];
    }


    // =====================================================================
    // MOTOR DE EJECUCIÓN (NUEVO COSTEO ESTÁNDAR POR LÍNEA)
    // =====================================================================
    public function ejecutarOrden(
        int $idOrden, 
        array $consumos, 
        array $ingresos, 
        int $userId, 
        string $justificacion = '', 
        ?string $fechaInicio = null, 
        ?string $fechaFin = null, 
        float $horasParada = 0.0,
        int $idCentroCosto = 0 
    ): void {
        if ($idOrden <= 0 || empty($consumos) || empty($ingresos)) {
            throw new RuntimeException('Faltan datos de consumos o ingresos para ejecutar la orden.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $orden = $this->obtenerOrdenPorId($idOrden);
            if (!$orden) throw new RuntimeException('La orden no existe.');
            if ((int) $orden['estado'] === 2) throw new RuntimeException('La orden ya fue ejecutada.');
            if ((int) $orden['estado'] === 9) throw new RuntimeException('No se puede ejecutar una orden anulada.');

            $inventarioModel = new InventarioModel(); 
            $costoTotalConsumo = 0.0;
            
            // Usamos la fecha de fin de producción (o la actual) como la fecha real del Kardex
            $fechaDocumento = substr((string) ($fechaFin ?? date('Y-m-d')), 0, 10);

            // 1. REGISTRAR CONSUMOS (MD)
            $stmtInfoItem = $db->prepare('SELECT controla_stock, requiere_lote, requiere_vencimiento FROM items WHERE id = :id LIMIT 1');
            $stmtConsumo = $db->prepare('INSERT INTO produccion_consumos (id_orden_produccion, id_item, id_almacen, id_lote, cantidad, costo_unitario, created_by, updated_by) VALUES (:id_orden_produccion, :id_item, :id_almacen, :id_lote, :cantidad, :costo_unitario, :created_by, :updated_by)');

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
                    throw new RuntimeException("El insumo (ID {$idInsumo}) controla stock. Debe seleccionar un almacén de origen.");
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

                if ($controlaStock && method_exists($inventarioModel, 'registrarMovimiento')) {
                    $inventarioModel->registrarMovimiento([
                        'tipo_movimiento' => 'CON', 
                        'id_item' => $idInsumo,
                        'id_item_unidad' => $this->obtenerUnidadPorDefecto($idInsumo), 
                        'id_almacen_origen' => $idAlmacenOrigen,
                        'id_almacen_destino' => null, 
                        'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : null,
                        'cantidad' => $cantidadUsada,
                        'referencia' => 'OP ' . $orden['codigo'] . ' consumo',
                        'lote' => $lote,
                        'costo_unitario' => $costoUnitario,
                        'created_by' => $userId,
                        'fecha_documento' => $fechaDocumento // <-- AQUÍ SE GUARDA LA FECHA
                    ]);
                }
            }

            // 2. CÁLCULO DE HORAS Y COSTOS (MOD / CIF AUTOMÁTICOS)
            $tarifaMod = 0.0;
            $tarifaCif = 0.0;
            $idPlanta = (int) ($orden['id_almacen_planta'] ?? 0);

            if ($idPlanta > 0) {
                $stmtPlanta = $db->prepare('SELECT tarifa_mod_hora, tarifa_cif_hora FROM almacenes WHERE id = :id');
                $stmtPlanta->execute(['id' => $idPlanta]);
                $planta = $stmtPlanta->fetch(PDO::FETCH_ASSOC);
                if ($planta) {
                    $tarifaMod = (float) ($planta['tarifa_mod_hora'] ?? 0);
                    $tarifaCif = (float) ($planta['tarifa_cif_hora'] ?? 0);
                }
            }

            // Matemática de Tiempos
            $fIni = $fechaInicio ? new DateTime($fechaInicio) : new DateTime();
            $fFin = $fechaFin ? new DateTime($fechaFin) : new DateTime();
            $diff = $fIni->diff($fFin);
            
            $totalHours = ($diff->days * 24) + $diff->h + ($diff->i / 60) + ($diff->s / 3600);
            $netHours = max(0, $totalHours - $horasParada);

            $costoModReal = $netHours * $tarifaMod;
            $costoCifReal = $netHours * $tarifaCif;

            $db->prepare('DELETE FROM produccion_ordenes_mod WHERE id_orden = :id_orden')->execute(['id_orden' => $idOrden]);
            $db->prepare('DELETE FROM produccion_ordenes_cif WHERE id_orden = :id_orden')->execute(['id_orden' => $idOrden]);

            if ($costoModReal > 0) {
                try {
                    $db->prepare('INSERT INTO produccion_ordenes_mod (id_orden, id_empleado, horas_reales, costo_hora_real, costo_total_mod) VALUES (?, NULL, ?, ?, ?)')
                       ->execute([$idOrden, $netHours, $tarifaMod, $costoModReal]);
                } catch (Throwable $e) {} 
            }

            if ($costoCifReal > 0) {
                try {
                    $db->prepare('INSERT INTO produccion_ordenes_cif (id_orden, concepto, base_distribucion, costo_aplicado) VALUES (?, ?, ?, ?)')
                       ->execute([$idOrden, 'Tarifa Estándar de Planta (Luz, Agua, Depreciación)', number_format($netHours, 2) . ' Hrs Máquina', $costoCifReal]);
                } catch (Throwable $e) {}
            }

            // 3. REGISTRAR INGRESOS AL ALMACÉN
            $cantidadTotalProducida = array_sum(array_column($ingresos, 'cantidad'));
            if ($cantidadTotalProducida <= 0) {
                throw new RuntimeException('La cantidad total producida debe ser mayor a cero.');
            }

            $costoRealTotal = $costoTotalConsumo + $costoModReal + $costoCifReal;
            $costoUnitarioIngreso = $costoRealTotal / $cantidadTotalProducida;

            $stmtInfoItem->execute(['id' => (int) $orden['id_producto']]);
            $infoProducto = $stmtInfoItem->fetch(PDO::FETCH_ASSOC);
            $controlaStockProd = (int)($infoProducto['controla_stock'] ?? 0) === 1;

            if (!$controlaStockProd) {
                throw new RuntimeException('El producto terminado no controla stock. Active "Controla stock" en el ítem para registrar la producción en Kardex.');
            }

            $stmtIngreso = $db->prepare('INSERT INTO produccion_ingresos (id_orden_produccion, id_item, id_almacen, lote, fecha_vencimiento, cantidad, costo_unitario_calculado, created_by, updated_by) VALUES (:id_orden_produccion, :id_item, :id_almacen, :lote, :fecha_vencimiento, :cantidad, :costo_unitario_calculado, :created_by, :updated_by)');
            
            $idItemUnidadTerminado = $this->obtenerUnidadPorDefecto((int) $orden['id_producto']);

            foreach ($ingresos as $ingreso) {
                $cantidadIngresada = (float) ($ingreso['cantidad'] ?? 0);
                if ($cantidadIngresada <= 0) continue; 

                $idAlmacenDestino = (int) ($ingreso['id_almacen'] ?? 0);
                $lote = trim((string) ($ingreso['lote'] ?? ''));
                $fechaVencimiento = trim((string) ($ingreso['fecha_vencimiento'] ?? ''));

                if ($controlaStockProd && $idAlmacenDestino <= 0) {
                    throw new RuntimeException("El producto fabricado controla stock. Debe seleccionar un almacén de destino.");
                }

                $stmtIngreso->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => (int) $orden['id_producto'],
                    'id_almacen' => $idAlmacenDestino > 0 ? $idAlmacenDestino : null,
                    'lote' => $lote !== '' ? $lote : null, 
                    'fecha_vencimiento' => $fechaVencimiento !== '' ? $fechaVencimiento : null, 
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
                        'created_by' => $userId,
                        'fecha_documento' => $fechaDocumento // <-- AQUÍ SE GUARDA LA FECHA
                    ]);
                }
            }

            // 4. CERRAR LA ORDEN Y ENVIAR A CONTABILIDAD
            $stmtUpdate = $db->prepare('UPDATE produccion_ordenes
                                        SET cantidad_producida = :cantidad_producida,
                                            costo_md_real = :costo_md_real,
                                            costo_mod_real = :costo_mod_real,
                                            costo_cif_real = :costo_cif_real,
                                            total_md_real = :total_md_real,
                                            total_mod_real = :total_mod_real,
                                            total_cif_real = :total_cif_real,
                                            costo_real_unitario = :costo_real_unitario,
                                            costo_unitario_real = :costo_unitario_real,
                                            costo_real_total = :costo_real_total,
                                            estado = 2,
                                            fecha_inicio = COALESCE(:fecha_inicio, fecha_inicio, NOW()),
                                            fecha_fin = COALESCE(:fecha_fin, NOW()),
                                            justificacion_ajuste = :justificacion,
                                            updated_at = NOW(),
                                            updated_by = :updated_by
                                        WHERE id = :id AND deleted_at IS NULL');
            $stmtUpdate->execute([
                'cantidad_producida' => number_format($cantidadTotalProducida, 4, '.', ''),
                'costo_md_real' => number_format($costoTotalConsumo, 4, '.', ''),
                'costo_mod_real' => number_format($costoModReal, 4, '.', ''),
                'costo_cif_real' => number_format($costoCifReal, 4, '.', ''),
                'costo_real_unitario' => number_format($costoUnitarioIngreso, 4, '.', ''),
                'costo_unitario_real' => number_format($costoUnitarioIngreso, 4, '.', ''),
                'total_md_real' => number_format($costoTotalConsumo, 4, '.', ''),
                'total_mod_real' => number_format($costoModReal, 4, '.', ''),
                'total_cif_real' => number_format($costoCifReal, 4, '.', ''),
                'costo_real_total' => number_format($costoRealTotal, 4, '.', ''),
                'fecha_inicio' => $fechaInicio, 
                'fecha_fin' => $fechaFin,       
                'justificacion' => $justificacion !== '' ? $justificacion : null,
                'updated_by' => $userId,
                'id' => $idOrden,
            ]);

            $itemModel = new ItemModel();
            $itemModel->actualizarCostoReferencial((int) $orden['id_producto'], $costoUnitarioIngreso, $userId);

            if ($costoRealTotal > 0) {
                $asientoModel = new ContaAsientoModel();
                $asientoModel->registrarAutomaticoProduccion($db, [
                    'id_orden' => $idOrden,
                    'codigo_orden' => (string) ($orden['codigo'] ?? ''),
                    'fecha' => $fechaDocumento, // Se usa la misma fecha en el asiento contable
                    'costo_md' => $costoTotalConsumo,
                    'costo_mod' => $costoModReal,
                    'costo_cif' => $costoCifReal,
                    'costo_total' => $costoRealTotal,
                    'id_centro_costo' => $idCentroCosto > 0 ? $idCentroCosto : null,
                ], $userId);
            }

            $db->commit();

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }


    public function reportarAvanceDiario(int $idOrden, float $cantidadAvance, int $userId, ?string $fechaOperacion = null, string $nota = ''): array
    {
        if ($idOrden <= 0 || $cantidadAvance <= 0 || $userId <= 0) {
            throw new RuntimeException('Datos inválidos para reportar avance diario.');
        }

        $orden = $this->obtenerOrdenPorId($idOrden);
        if ($orden === []) throw new RuntimeException('La orden no existe.');
        if (!in_array((int) ($orden['estado'] ?? -1), [1, 2], true)) throw new RuntimeException('Solo se puede reportar avance para órdenes en proceso o ejecutadas.');

        $idAlmacenPlanta = (int) ($orden['id_almacen_planta'] ?? 0);
        if ($idAlmacenPlanta <= 0) throw new RuntimeException('La orden no tiene almacén planta configurado.');

        $fecha = trim((string) ($fechaOperacion ?? ''));
        if ($fecha === '') $fecha = date('Y-m-d');

        $detalles = $this->obtenerDetalleReceta((int) ($orden['id_receta'] ?? 0));
        if ($detalles === []) throw new RuntimeException('La receta no tiene detalle de insumos para consumo teórico.');

        $inventarioModel = new InventarioModel();
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmtConsumo = $db->prepare('INSERT INTO produccion_consumos (id_orden_produccion, id_item, id_almacen, id_lote, cantidad, costo_unitario, created_by, updated_by) VALUES (:id_orden_produccion, :id_item, :id_almacen, :id_lote, :cantidad, :costo_unitario, :created_by, :updated_by)');

            $totalMd = 0.0;
            $lineas = 0;

            foreach ($detalles as $d) {
                $idInsumo = (int) ($d['id_insumo'] ?? 0);
                $qtyBase = (float) ($d['cantidad_por_unidad'] ?? 0);
                $merma = (float) ($d['merma_porcentaje'] ?? 0);
                if ($idInsumo <= 0 || $qtyBase <= 0) continue;

                $rendimientoBase = (float) ($d['rendimiento_base'] ?? 0);
                $factorEscala = $rendimientoBase > 0 ? ($cantidadAvance / $rendimientoBase) : 0;
                $cantidadTeorica = $qtyBase * $factorEscala * (1 + ($merma / 100));
                if ($cantidadTeorica <= 0) continue;

                $costoUnitario = $this->obtenerCostoReferencial($idInsumo);
                $stmtConsumo->execute([
                    'id_orden_produccion' => $idOrden,
                    'id_item' => $idInsumo,
                    'id_almacen' => $idAlmacenPlanta,
                    'id_lote' => null,
                    'cantidad' => number_format($cantidadTeorica, 4, '.', ''),
                    'costo_unitario' => number_format($costoUnitario, 4, '.', ''),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idItemUnidad = $this->obtenerUnidadPorDefecto($idInsumo);
                $inventarioModel->registrarMovimiento([
                    'tipo_movimiento' => 'CON',
                    'id_item' => $idInsumo,
                    'id_item_unidad' => $idItemUnidad,
                    'id_almacen_origen' => $idAlmacenPlanta,
                    'id_almacen_destino' => null,
                    'cantidad' => $cantidadTeorica,
                    'referencia' => 'OP ' . $orden['codigo'] . ' avance teórico ' . $fecha . ($nota !== '' ? (' | ' . $nota) : ''),
                    'costo_unitario' => $costoUnitario,
                    'created_by' => $userId,
                    'fecha_documento' => $fecha // <-- AQUÍ AGREGAMOS LA FECHA DE LA OPERACIÓN
                ]);

                $lineas++;
                $totalMd += $cantidadTeorica * $costoUnitario;
            }

            if ($lineas <= 0) {
                throw new RuntimeException('No se pudo generar consumo teórico para el avance reportado.');
            }

            $stmtUpdate = $db->prepare('UPDATE produccion_ordenes SET cantidad_producida = COALESCE(cantidad_producida, 0) + :avance, updated_at = NOW(), updated_by = :updated_by WHERE id = :id AND deleted_at IS NULL');
            $stmtUpdate->execute(['avance' => number_format($cantidadAvance, 4, '.', ''), 'updated_by' => $userId, 'id' => $idOrden]);

            $db->commit();

            return [
                'lineas_consumidas' => $lineas,
                'cantidad_reportada' => round($cantidadAvance, 4),
                'costo_md_teorico' => round($totalMd, 4),
            ];
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function anularOrden(int $idOrden, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE produccion_ordenes SET estado = 9, fecha_fin = NOW(), updated_at = NOW(), updated_by = :updated_by WHERE id = :id AND estado IN (0, 1) AND deleted_at IS NULL');
        $stmt->execute(['id' => $idOrden, 'updated_by' => $userId]);
    }

    public function obtenerDetalleReceta(int $idReceta): array
    {
        $sql = 'SELECT d.id_insumo, d.etapa, d.cantidad_por_unidad, d.merma_porcentaje, i.nombre AS insumo_nombre, r.rendimiento_base
                FROM produccion_recetas_detalle d
                INNER JOIN produccion_recetas r ON r.id = d.id_receta
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE d.id_receta = :id_receta AND d.deleted_at IS NULL
                ORDER BY d.id ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_receta' => $idReceta]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerDesgloseCostosOrden(int $idOrden): array
    {
        if ($idOrden <= 0) {
            return ['materiales' => [], 'mod' => [], 'cif' => []];
        }

        $stmtMd = $this->db()->prepare('SELECT d.id_insumo AS id_item,
                                               i.nombre AS item_nombre,
                                               (d.cantidad_por_unidad * (COALESCE(NULLIF(o.cantidad_producida, 0), o.cantidad_planificada, 0) / NULLIF(r.rendimiento_base, 0)) * (1 + (d.merma_porcentaje / 100))) AS cantidad,
                                               COALESCE(SUM(c.cantidad), 0) AS cantidad_real,
                                               COALESCE(AVG(c.costo_unitario), 0) AS costo_unitario,
                                               COALESCE(SUM(c.cantidad * c.costo_unitario), 0) AS costo_total
                                        FROM produccion_ordenes o
                                        INNER JOIN produccion_recetas r ON r.id = o.id_receta AND r.deleted_at IS NULL
                                        INNER JOIN produccion_recetas_detalle d ON d.id_receta = o.id_receta AND d.deleted_at IS NULL
                                        INNER JOIN items i ON i.id = d.id_insumo
                                        LEFT JOIN produccion_consumos c ON c.id_orden_produccion = o.id AND c.id_item = d.id_insumo AND c.deleted_at IS NULL
                                        WHERE o.id = :id_orden AND o.deleted_at IS NULL
                                        GROUP BY d.id_insumo, i.nombre, d.cantidad_por_unidad, d.merma_porcentaje, o.cantidad_producida, o.cantidad_planificada, r.rendimiento_base
                                        ORDER BY i.nombre ASC');
        $stmtMd->execute(['id_orden' => $idOrden]);
        $materiales = $stmtMd->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Incluye consumos reales que no estén en la receta (excepciones por almacén/ajustes manuales).
        $stmtMdExtras = $this->db()->prepare('SELECT c.id_item,
                                                     i.nombre AS item_nombre,
                                                     0 AS cantidad,
                                                     COALESCE(SUM(c.cantidad), 0) AS cantidad_real,
                                                     COALESCE(AVG(c.costo_unitario), 0) AS costo_unitario,
                                                     COALESCE(SUM(c.cantidad * c.costo_unitario), 0) AS costo_total
                                              FROM produccion_consumos c
                                              INNER JOIN items i ON i.id = c.id_item
                                              WHERE c.id_orden_produccion = :id_orden
                                                AND c.deleted_at IS NULL
                                                AND NOT EXISTS (
                                                    SELECT 1
                                                    FROM produccion_ordenes o
                                                    INNER JOIN produccion_recetas_detalle d
                                                        ON d.id_receta = o.id_receta
                                                       AND d.deleted_at IS NULL
                                                    WHERE o.id = :id_orden_receta
                                                      AND o.deleted_at IS NULL
                                                      AND d.id_insumo = c.id_item
                                                )
                                              GROUP BY c.id_item, i.nombre
                                              ORDER BY i.nombre ASC');
        $stmtMdExtras->execute([
            'id_orden' => $idOrden,
            'id_orden_receta' => $idOrden,
        ]);
        $materialesExtras = $stmtMdExtras->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!empty($materialesExtras)) {
            $materiales = array_merge($materiales, $materialesExtras);
        }

        // MOD: Mostramos si hay un empleado, sino usamos la etiqueta genérica de Planta
        $stmtMod = $this->db()->prepare('SELECT m.id_empleado,
                                                COALESCE(t.nombre_completo, "Tarifa Estándar de Línea") AS empleado,
                                                m.horas_reales,
                                                m.costo_hora_real,
                                                COALESCE(m.costo_total_mod, (m.horas_reales * m.costo_hora_real)) AS costo_total_mod
                                         FROM produccion_ordenes_mod m
                                         LEFT JOIN terceros t ON t.id = m.id_empleado
                                         WHERE m.id_orden = :id_orden
                                         ORDER BY m.id ASC');
        $stmtMod->execute(['id_orden' => $idOrden]);
        $modRows = $stmtMod->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Fallback: Si no se pudo guardar el detalle por FK estricto, leemos el global de la orden
        if (empty($modRows)) {
            $stmtM = $this->db()->prepare('SELECT costo_mod_real FROM produccion_ordenes WHERE id = ?');
            $stmtM->execute([$idOrden]);
            $cMod = (float) $stmtM->fetchColumn();
            if ($cMod > 0) {
                $modRows[] = ['id_empleado' => null, 'empleado' => 'Tarifa Estándar de Línea (Global)', 'horas_reales' => 1, 'costo_hora_real' => $cMod, 'costo_total_mod' => $cMod];
            }
        }

        $stmtCif = $this->db()->prepare('SELECT concepto, base_distribucion, costo_aplicado FROM produccion_ordenes_cif WHERE id_orden = :id_orden ORDER BY id ASC');
        $stmtCif->execute(['id_orden' => $idOrden]);
        $cifRows = $stmtCif->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($cifRows)) {
            $stmtC = $this->db()->prepare('SELECT costo_cif_real FROM produccion_ordenes WHERE id = ?');
            $stmtC->execute([$idOrden]);
            $cCif = (float) $stmtC->fetchColumn();
            if ($cCif > 0) {
                $cifRows[] = ['concepto' => 'CIF Estándar de Planta (Global)', 'base_distribucion' => 'Global', 'costo_aplicado' => $cCif];
            }
        }

        return [
            'materiales' => $materiales,
            'mod' => $modRows,
            'cif' => $cifRows,
        ];
    }

    public function obtenerStockTotalItem(int $idItem): float
    {
        $sql = 'SELECT COALESCE(SUM(t.stock_disponible), 0)
                FROM (
                    SELECT COALESCE(s.id_almacen, l.id_almacen) AS id_almacen,
                           GREATEST(COALESCE(s.stock_actual, 0), COALESCE(l.stock_lote, 0)) AS stock_disponible
                    FROM (
                        SELECT id_almacen, SUM(stock_actual) AS stock_actual
                        FROM inventario_stock
                        WHERE id_item = :id_item
                        GROUP BY id_almacen
                    ) s
                    LEFT JOIN (
                        SELECT id_almacen, SUM(stock_lote) AS stock_lote
                        FROM inventario_lotes
                        WHERE id_item = :id_item_lotes
                          AND stock_lote > 0
                        GROUP BY id_almacen
                    ) l ON l.id_almacen = s.id_almacen

                    UNION

                    SELECT l.id_almacen,
                           GREATEST(COALESCE(s.stock_actual, 0), COALESCE(l.stock_lote, 0)) AS stock_disponible
                    FROM (
                        SELECT id_almacen, SUM(stock_lote) AS stock_lote
                        FROM inventario_lotes
                        WHERE id_item = :id_item_lotes_union
                          AND stock_lote > 0
                        GROUP BY id_almacen
                    ) l
                    LEFT JOIN (
                        SELECT id_almacen, SUM(stock_actual) AS stock_actual
                        FROM inventario_stock
                        WHERE id_item = :id_item_union
                        GROUP BY id_almacen
                    ) s ON s.id_almacen = l.id_almacen
                    WHERE s.id_almacen IS NULL
                ) t
                LEFT JOIN almacenes a ON a.id = t.id_almacen
                WHERE (t.id_almacen = 0 OR (a.estado = 1 AND a.deleted_at IS NULL))';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_item_lotes' => $idItem,
            'id_item_lotes_union' => $idItem,
            'id_item_union' => $idItem,
        ]);

        return (float) ($stmt->fetchColumn() ?: 0);
    }

    public function obtenerAlmacenesConStockItem(int $idItem, int $idAlmacenPlanta = 0): array
    {
        $sql = 'SELECT a.id,
                       a.nombre,
                       a.tipo,
                       t.stock_disponible AS stock_actual,
                       CASE WHEN a.id = :id_almacen_planta THEN 1 ELSE 0 END AS es_planta
                FROM (
                    SELECT COALESCE(s.id_almacen, l.id_almacen) AS id_almacen,
                           GREATEST(COALESCE(s.stock_actual, 0), COALESCE(l.stock_lote, 0)) AS stock_disponible
                    FROM (
                        SELECT id_almacen, SUM(stock_actual) AS stock_actual
                        FROM inventario_stock
                        WHERE id_item = :id_item
                        GROUP BY id_almacen
                    ) s
                    LEFT JOIN (
                        SELECT id_almacen, SUM(stock_lote) AS stock_lote
                        FROM inventario_lotes
                        WHERE id_item = :id_item_lotes
                          AND stock_lote > 0
                        GROUP BY id_almacen
                    ) l ON l.id_almacen = s.id_almacen

                    UNION

                    SELECT l.id_almacen,
                           GREATEST(COALESCE(s.stock_actual, 0), COALESCE(l.stock_lote, 0)) AS stock_disponible
                    FROM (
                        SELECT id_almacen, SUM(stock_lote) AS stock_lote
                        FROM inventario_lotes
                        WHERE id_item = :id_item_lotes_union
                          AND stock_lote > 0
                        GROUP BY id_almacen
                    ) l
                    LEFT JOIN (
                        SELECT id_almacen, SUM(stock_actual) AS stock_actual
                        FROM inventario_stock
                        WHERE id_item = :id_item_union
                        GROUP BY id_almacen
                    ) s ON s.id_almacen = l.id_almacen
                    WHERE s.id_almacen IS NULL
                ) t
                INNER JOIN almacenes a ON a.id = t.id_almacen
                WHERE a.estado = 1
                  AND a.deleted_at IS NULL
                  AND t.stock_disponible > 0
                ORDER BY es_planta DESC, t.stock_disponible DESC, a.nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'id_item_lotes' => $idItem,
            'id_item_lotes_union' => $idItem,
            'id_item_union' => $idItem,
            'id_almacen_planta' => $idAlmacenPlanta,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }


    public function obtenerCabeceraOrden(int $idOrden): array
    {
        if ($idOrden <= 0) {
            return [];
        }

        $stmt = $this->db()->prepare('SELECT id, id_receta, cantidad_planificada, id_almacen_planta FROM produccion_ordenes WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $idOrden]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerOrdenPorId(int $idOrden): array
    {
        $sql = 'SELECT o.id, o.codigo, o.id_receta, o.cantidad_planificada, o.estado, o.id_almacen_planta,
                       r.id_producto
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas r ON r.id = o.id_receta
                WHERE o.id = :id AND o.deleted_at IS NULL AND r.deleted_at IS NULL LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idOrden]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerStockItemAlmacen(int $idItem, int $idAlmacen): float
    {
        $stmt = $this->db()->prepare('SELECT stock_actual FROM inventario_stock WHERE id_item = :id_item AND id_almacen = :id_almacen LIMIT 1');
        $stmt->execute(['id_item' => $idItem, 'id_almacen' => $idAlmacen]);
        return (float) ($stmt->fetchColumn() ?: 0);
    }

    private function obtenerCostoReferencial(int $idItem): float
    {
        $stmt = $this->db()->prepare('SELECT costo_referencial FROM items WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $idItem]);
        $costoFijo = (float)($stmt->fetchColumn() ?: 0);
        if ($costoFijo > 0) return $costoFijo;

        $stmtRec = $this->db()->prepare('SELECT costo_teorico_unitario FROM produccion_recetas WHERE id_producto = :id AND estado = 1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 1');
        $stmtRec->execute(['id' => $idItem]);
        $costoReceta = (float)($stmtRec->fetchColumn() ?: 0);
        if ($costoReceta > 0) return $costoReceta;

        $stmtMov = $this->db()->prepare('SELECT costo_unitario FROM inventario_movimientos WHERE id_item = :id AND costo_unitario IS NOT NULL AND costo_unitario > 0 ORDER BY id DESC LIMIT 1');
        $stmtMov->execute(['id' => $idItem]);
        return (float)($stmtMov->fetchColumn() ?: 0);
    }

    private function obtenerUnidadPorDefecto(int $idItem): ?int
    {
        $stmt = $this->db()->prepare('SELECT id_item_unidad FROM inventario_movimientos WHERE id_item = :id_item AND id_item_unidad IS NOT NULL ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id_item' => $idItem]);
        $val = $stmt->fetchColumn();
        return $val ? (int) $val : null;
    }

    // =====================================================================
    // MOTOR MRP: EXPLOSIÓN DE SEMIELABORADOS
    // =====================================================================
    public function analizarSemielaboradosFaltantes(int $idOrden): array
    {
        $sql = 'SELECT d.id_insumo,
                       i.nombre AS insumo_nombre,
                       (d.cantidad_por_unidad * (o.cantidad_planificada / NULLIF(r.rendimiento_base, 0)) * (1 + (d.merma_porcentaje / 100))) AS qty_requerida,
                       COALESCE((
                           SELECT s.stock_actual 
                           FROM inventario_stock s 
                           WHERE s.id_item = d.id_insumo AND s.id_almacen = o.id_almacen_planta LIMIT 1
                       ), 0) AS stock_planta,
                       (
                           SELECT r2.id 
                           FROM produccion_recetas r2 
                           WHERE r2.id_producto = d.id_insumo AND r2.estado = 1 AND r2.deleted_at IS NULL 
                           ORDER BY r2.version DESC LIMIT 1
                       ) as id_receta_hija
                FROM produccion_ordenes o
                INNER JOIN produccion_recetas r ON r.id = o.id_receta AND r.deleted_at IS NULL
                INNER JOIN produccion_recetas_detalle d ON d.id_receta = o.id_receta AND d.deleted_at IS NULL
                INNER JOIN items i ON i.id = d.id_insumo
                WHERE o.id = :id_orden AND o.deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_orden' => $idOrden]);
        $insumos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $faltantes = [];
        foreach ($insumos as $insumo) {
            $idRecetaHija = (int)($insumo['id_receta_hija'] ?? 0);
            if ($idRecetaHija <= 0) continue;

            $requerido = (float)$insumo['qty_requerida'];
            $stock = (float)$insumo['stock_planta'];
            $faltante = round(max(0, $requerido - $stock), 4);

            if ($faltante > 0) {
                $faltantes[] = [
                    'id_insumo' => (int)$insumo['id_insumo'],
                    'insumo_nombre' => $insumo['insumo_nombre'],
                    'id_receta_hija' => $idRecetaHija,
                    'cantidad_faltante' => $faltante
                ];
            }
        }
        return $faltantes;
    }

    public function generarSubOrdenesAutomatica(int $idOrdenPadre, array $faltantes, int $userId): void
    {
        $stmtPadre = $this->db()->prepare('SELECT id_almacen_planta, fecha_programada, codigo FROM produccion_ordenes WHERE id = ?');
        $stmtPadre->execute([$idOrdenPadre]);
        $datosPadre = $stmtPadre->fetch(PDO::FETCH_ASSOC);

        if (!$datosPadre) return;

        $correlativo = (int) $this->db()->query('SELECT COUNT(*) FROM produccion_ordenes')->fetchColumn();

        $sqlInsert = 'INSERT INTO produccion_ordenes
                        (codigo, id_receta, id_producto_snapshot, receta_codigo_snapshot, receta_version_snapshot,
                         costo_teorico_unitario_snapshot, costo_teorico_total_snapshot,
                         id_almacen_planta, cantidad_planificada, fecha_programada, estado, created_by, updated_by, id_orden_padre, observaciones)
                      VALUES
                        (:codigo, :id_receta, :id_producto_snapshot, :receta_codigo_snapshot, :receta_version_snapshot,
                         :costo_teorico_unitario_snapshot, :costo_teorico_total_snapshot,
                         :id_almacen_planta, :cantidad_planificada, :fecha_programada, 0, :created_by, :updated_by, :id_orden_padre, :obs)';
        $stmt = $this->db()->prepare($sqlInsert);

        foreach ($faltantes as $f) {
            $idRecetaHija = (int)($f['id_receta_hija'] ?? 0);
            $cantidad = (float)($f['cantidad_faltante'] ?? 0);

            if ($idRecetaHija <= 0 || $cantidad <= 0) continue;

            $correlativo++;
            $nuevoCodigo = 'OP-' . date('ymd') . '-' . str_pad((string)$correlativo, 4, '0', STR_PAD_LEFT) . '-SUB';
            $snapshot = $this->obtenerSnapshotReceta($idRecetaHija, $cantidad);

            $stmt->execute([
                'codigo' => $nuevoCodigo,
                'id_receta' => $idRecetaHija,
                'id_producto_snapshot' => $snapshot['id_producto_snapshot'],
                'receta_codigo_snapshot' => $snapshot['receta_codigo_snapshot'],
                'receta_version_snapshot' => $snapshot['receta_version_snapshot'],
                'costo_teorico_unitario_snapshot' => number_format($snapshot['costo_teorico_unitario_snapshot'], 4, '.', ''),
                'costo_teorico_total_snapshot' => number_format($snapshot['costo_teorico_total_snapshot'], 4, '.', ''),
                'id_almacen_planta' => (int)$datosPadre['id_almacen_planta'],
                'cantidad_planificada' => number_format($cantidad, 4, '.', ''),
                'fecha_programada' => $datosPadre['fecha_programada'],
                'created_by' => $userId,
                'updated_by' => $userId,
                'id_orden_padre' => $idOrdenPadre,
                'obs' => 'Sub-orden auto-generada por faltante para OP principal: ' . $datosPadre['codigo']
            ]);
        }
    }

    // =====================================================================
    // NUEVA FUNCIÓN PARA LISTAR LOS CENTROS DE COSTO
    // =====================================================================
    public function listarCentrosCosto(): array
    {
        $sql = 'SELECT id, codigo, nombre, estado 
                FROM conta_centros_costo 
                WHERE deleted_at IS NULL 
                ORDER BY nombre ASC';
                
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
