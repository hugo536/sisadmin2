<?php

declare(strict_types=1);

class ComprasOrdenModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $sql = <<<SQL
            SELECT o.id,
                o.codigo,
                o.id_proveedor,
                t.nombre_completo AS proveedor,
                o.fecha_emision AS fecha_orden,
                o.fecha_entrega_estimada AS fecha_entrega,
                o.moneda, /* <--- CAMBIO BIMONETARIO */
                (SELECT cr.fecha_recepcion
                    FROM compras_recepciones cr
                    WHERE cr.id_orden_compra = o.id
                    ORDER BY cr.id DESC LIMIT 1) AS fecha_recepcion,
                COALESCE(
                    NULLIF(TRIM((SELECT cr.observaciones
                                    FROM compras_recepciones cr
                                    WHERE cr.id_orden_compra = o.id
                                    ORDER BY cr.id DESC LIMIT 1)), ''),
                    NULLIF(TRIM(o.observaciones), '')
                ) AS observacion_subtitulo,
                o.total,
                
                /* 👇 NUEVO: Cálculo dinámico del total neto para la tabla principal 👇 */
                CASE 
                    WHEN o.estado >= 3 THEN (
                        COALESCE((
                            SELECT SUM((COALESCE(cod.cantidad_recibida, 0) / COALESCE(NULLIF(cod.factor_conversion_aplicado, 0), 1)) * cod.costo_unitario_pactado)
                            FROM compras_ordenes_detalle cod
                            WHERE cod.id_orden = o.id AND cod.deleted_at IS NULL
                        ), 0) * CASE WHEN o.tipo_impuesto = 'mas_igv' THEN 1.18 ELSE 1 END
                    )
                    ELSE o.total
                END AS total_neto,
                /* 👆 FIN NUEVO 👆 */

                o.estado,
                o.created_at
            FROM compras_ordenes o
            INNER JOIN terceros t ON t.id = o.id_proveedor
            WHERE o.deleted_at IS NULL
            AND t.deleted_at IS NULL
            SQL;


        $params = [];

        if (!empty($filtros['q'])) {
            $sql .= ' AND (o.codigo LIKE :q1 OR t.nombre_completo LIKE :q2)';
            $valorBusqueda = '%' . trim((string) $filtros['q']) . '%';
            $params[':q1'] = $valorBusqueda;
            $params[':q2'] = $valorBusqueda;
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== '' && $filtros['estado'] !== null) {
            $sql .= ' AND o.estado = :estado';
            $params[':estado'] = (int) $filtros['estado'];
        }

        if (isset($filtros['excluir_estado'])) {
            $sql .= ' AND o.estado != :excluir_estado';
            $params[':excluir_estado'] = (int) $filtros['excluir_estado'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND DATE(o.fecha_emision) >= :fecha_desde';
            $params[':fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(o.fecha_emision) <= :fecha_hasta';
            $params[':fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $ordenFecha = $filtros['orden_fecha'] ?? 'orden';
        
        if ($ordenFecha === 'recepcion') {
            $sql .= ' ORDER BY fecha_recepcion DESC, o.id DESC';
        } else {
            $sql .= ' ORDER BY o.fecha_emision DESC, o.id DESC';
        }

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT o.id, o.codigo, o.id_proveedor,
                       t.nombre_completo AS proveedor,
                       o.fecha_emision AS fecha_orden, 
                       o.fecha_entrega_estimada AS fecha_entrega, 
                       o.moneda, 
                       o.observaciones, o.subtotal, o.total, o.estado,
                       o.cobro_inmediato, o.metodos_pago 
                FROM compras_ordenes o
                INNER JOIN terceros t ON t.id = o.id_proveedor AND t.deleted_at IS NULL
                WHERE o.id = :id
                  AND o.deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            return [];
        }
        
        $orden['metodos_pago'] = !empty($orden['metodos_pago']) ? json_decode($orden['metodos_pago'], true) : [];
        $orden['fecha_recepcion_sugerida'] = date('Y-m-d');
        $orden['moneda'] = !empty($orden['moneda']) ? $orden['moneda'] : 'PEN';

        // Detalle mejorado: Calculamos dinámicamente la cantidad devuelta por ítem
        $detalleSql = 'SELECT d.id,
                              d.id_item,
                              i.sku,
                              i.nombre AS item_nombre,
                              d.id_item_unidad,
                              COALESCE(d.unidad_nombre, i.unidad_base) AS unidad_nombre,
                              COALESCE(i.unidad_base, "UND") AS unidad_base,
                              COALESCE(d.factor_conversion_aplicado, 1) AS factor_conversion_aplicado,
                              COALESCE(d.cantidad_conversion, d.cantidad_solicitada) AS cantidad,
                              COALESCE(d.cantidad_conversion, d.cantidad_solicitada) AS cantidad_unidad,
                              COALESCE(d.cantidad_base_solicitada, d.cantidad_solicitada) AS cantidad_base,
                              COALESCE(d.cantidad_recibida, 0) AS cantidad_recibida,
                              (COALESCE(d.cantidad_base_solicitada, d.cantidad_solicitada) - COALESCE(d.cantidad_recibida, 0)) AS cantidad_pendiente,
                              d.id_centro_costo,
                              d.costo_unitario_pactado AS costo_unitario,
                              (COALESCE(d.cantidad_conversion, d.cantidad_solicitada) * d.costo_unitario_pactado) AS subtotal,
                              -- Subconsulta para saber cuánto se devolvió de esta línea
                              COALESCE((
                                  SELECT SUM(cdd.cantidad_base) /* <-- AQUÍ ESTÁ LA MAGIA: se suma cantidad_base */
                                  FROM compras_devoluciones_detalle cdd
                                  INNER JOIN compras_devoluciones cd ON cd.id = cdd.id_devolucion
                                  WHERE cd.id_orden = d.id_orden AND cdd.id_item = d.id_item
                              ), 0) AS cantidad_devuelta
                       FROM compras_ordenes_detalle d
                       INNER JOIN items i ON i.id = d.id_item AND i.deleted_at IS NULL
                       WHERE d.id_orden = :id_orden
                         AND d.deleted_at IS NULL
                       ORDER BY d.id ASC';

        $stmtDetalle = $this->db()->prepare($detalleSql);
        $stmtDetalle->execute(['id_orden' => $id]);
        $orden['detalle'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // NUEVO: Traemos el historial de devoluciones para mostrarlo en el resumen
        $sqlDev = 'SELECT id, motivo, tipo_resolucion, total_devuelto, created_at 
                   FROM compras_devoluciones 
                   WHERE id_orden = :id_orden 
                   ORDER BY created_at ASC';
        $stmtDev = $this->db()->prepare($sqlDev);
        $stmtDev->execute(['id_orden' => $id]);
        $orden['devoluciones_historial'] = $stmtDev->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $orden;
    }

    public function crearOActualizar(array $cabecera, array $detalle, int $userId): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('Usuario inválido para registrar la orden.');
        }

        if (empty($detalle)) {
            throw new RuntimeException('Debe agregar al menos un ítem al detalle de la orden.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $idOrden = (int) ($cabecera['id'] ?? 0);
            $estado = array_key_exists('estado', $cabecera) ? (int) $cabecera['estado'] : 0;
            
            $fechaEmision = !empty($cabecera['fecha_emision']) ? (string) $cabecera['fecha_emision'] : '';
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaEmision)) {
                throw new RuntimeException('La fecha de emisión no es válida.');
            }

            // NUEVA LÍNEA: Leer moneda del payload
            $moneda = strtoupper(trim((string) ($cabecera['moneda'] ?? 'PEN')));
            if (!in_array($moneda, ['PEN', 'USD'], true)) {
                $moneda = 'PEN';
            }

            if ($idOrden > 0) {
                $actual = $this->obtener($idOrden);
                if ($actual === []) {
                    throw new RuntimeException('La orden no existe o fue eliminada.');
                }

                if ((int) ($actual['estado'] ?? 0) !== 0) {
                    throw new RuntimeException('Solo se pueden editar órdenes en borrador.');
                }

                // Agregamos la columna moneda al UPDATE
                $sqlUpdate = 'UPDATE compras_ordenes
                                  SET id_proveedor = :id_proveedor,
                                      fecha_emision = :fecha_emision,
                                      fecha_entrega_estimada = :fecha_entrega,
                                      moneda = :moneda, /* NUEVO */
                                      observaciones = :observaciones,
                                      tipo_impuesto = :tipo_impuesto,
                                      subtotal = :subtotal,
                                      igv_monto = :igv_monto,
                                      total = :total,
                                      estado = :estado,
                                      cobro_inmediato = :cobro_inmediato, 
                                      metodos_pago = :metodos_pago,       
                                      updated_by = :updated_by,
                                      updated_at = NOW()
                              WHERE id = :id
                                AND deleted_at IS NULL';

                $db->prepare($sqlUpdate)->execute([
                    'id' => $idOrden,
                    'id_proveedor' => (int) $cabecera['id_proveedor'],
                    'fecha_emision' => $fechaEmision,
                    'fecha_entrega' => $fechaEmision,
                    'moneda' => $moneda, // NUEVO
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'tipo_impuesto' => $cabecera['tipo_impuesto'],
                    'subtotal' => (float) $cabecera['subtotal'],
                    'igv_monto' => (float) $cabecera['igv_monto'],
                    'total' => (float) $cabecera['total'],
                    'estado' => $estado,
                    'cobro_inmediato' => $cabecera['cobro_inmediato'] ?? 0,    
                    'metodos_pago' => is_string($cabecera['metodos_pago']) ? $cabecera['metodos_pago'] : json_encode($cabecera['metodos_pago'] ?? []),
                    'updated_by' => $userId,
                ]);

                $db->prepare('UPDATE compras_ordenes_detalle SET deleted_at = NOW(), deleted_by = :user WHERE id_orden = :id_orden AND deleted_at IS NULL')
                    ->execute(['user' => $userId, 'id_orden' => $idOrden]);
            } else {
                $codigo = $this->generarCodigo($db);

                // Agregamos moneda al INSERT
                $sqlInsert = 'INSERT INTO compras_ordenes (
                                codigo, id_proveedor, fecha_emision, fecha_entrega_estimada, moneda, observaciones,
                                tipo_impuesto, subtotal, igv_monto, total, estado,
                                cobro_inmediato, metodos_pago, 
                                created_by, updated_by, created_at, updated_at
                              ) VALUES (
                                :codigo, :id_proveedor, :fecha_emision, :fecha_entrega, :moneda, :observaciones,
                                :tipo_impuesto, :subtotal, :igv_monto, :total, :estado,
                                :cobro_inmediato, :metodos_pago, 
                                :created_by, :updated_by, NOW(), NOW()
                              )';

                $db->prepare($sqlInsert)->execute([
                    'codigo' => $codigo,
                    'id_proveedor' => (int) $cabecera['id_proveedor'],
                    'fecha_emision' => $fechaEmision,
                    'fecha_entrega' => $fechaEmision,
                    'moneda' => $moneda, // NUEVO
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'tipo_impuesto' => $cabecera['tipo_impuesto'],
                    'subtotal' => (float) $cabecera['subtotal'],
                    'igv_monto' => (float) $cabecera['igv_monto'],
                    'total' => (float) $cabecera['total'],
                    'estado' => $estado,
                    'cobro_inmediato' => $cabecera['cobro_inmediato'] ?? 0,    
                    'metodos_pago' => is_string($cabecera['metodos_pago']) ? $cabecera['metodos_pago'] : json_encode($cabecera['metodos_pago'] ?? []),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idOrden = (int) $db->lastInsertId();
            }

            // Inserción del detalle
            $sqlDet = 'INSERT INTO compras_ordenes_detalle (
                            id_orden,
                            id_item,
                            id_item_unidad,
                            unidad_nombre,
                            factor_conversion_aplicado,
                            cantidad_conversion,
                            cantidad_base_solicitada,
                            cantidad_solicitada,
                            costo_unitario_pactado,
                            id_centro_costo,
                            created_by,
                            updated_by,
                            created_at,
                            updated_at
                       ) VALUES (
                            :id_orden,
                            :id_item,
                            :id_item_unidad,
                            :unidad_nombre,
                            :factor_conversion_aplicado,
                            :cantidad_conversion,
                            :cantidad_base,
                            :cantidad,
                            :costo_unitario,
                            :id_centro_costo,
                            :created_by,
                            :updated_by,
                            NOW(),
                            NOW()
                       )';

            $stmtDet = $db->prepare($sqlDet);
            
            foreach ($detalle as $linea) {
                $cantidadConversion = (float) ($linea['cantidad'] ?? 0);
                $cantidadBase = (float) ($linea['cantidad_base'] ?? 0);
                $factorAplicado = (float) ($linea['factor_conversion_aplicado'] ?? 1);
                $costo = (float) ($linea['costo_unitario'] ?? 0);
                
                if ($cantidadConversion <= 0 || $cantidadBase <= 0 || $factorAplicado <= 0 || $costo < 0) {
                    throw new RuntimeException('Hay líneas con cantidad o costo inválido.');
                }

                $stmtDet->execute([
                    'id_orden' => $idOrden,
                    'id_item' => (int) ($linea['id_item'] ?? 0),
                    'id_item_unidad' => !empty($linea['id_item_unidad']) ? (int) $linea['id_item_unidad'] : null,
                    'unidad_nombre' => !empty($linea['unidad_nombre']) ? trim((string) $linea['unidad_nombre']) : null,
                    'factor_conversion_aplicado' => $factorAplicado,
                    'cantidad_conversion' => $cantidadConversion,
                    'cantidad_base' => $cantidadBase,
                    'cantidad' => $cantidadConversion, 
                    'costo_unitario' => $costo,
                    'id_centro_costo' => !empty($linea['id_centro_costo']) ? (int) $linea['id_centro_costo'] : null,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $db->commit();
            return $idOrden;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function aprobar(int $idOrden, int $userId): bool
    {
        $sql = 'UPDATE compras_ordenes
                SET estado = 2,
                    updated_by = :user,
                    updated_at = NOW()
                WHERE id = :id
                  AND estado = 0
                  AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idOrden, 'user' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function anular(int $idOrden, int $userId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('UPDATE compras_ordenes
                                  SET estado = 9,
                                      deleted_at = NOW(),
                                      deleted_by = :deleted_by,
                                      updated_by = :updated_by,
                                      updated_at = NOW()
                                  WHERE id = :id
                                    AND deleted_at IS NULL');
            
            $stmt->execute([
                'id' => $idOrden, 
                'deleted_by' => $userId,
                'updated_by' => $userId
            ]);

            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('No se pudo anular la orden.');
            }

            $db->prepare('UPDATE compras_ordenes_detalle
                          SET deleted_at = NOW(), 
                              deleted_by = :deleted_by, 
                              updated_by = :updated_by, 
                              updated_at = NOW()
                          WHERE id_orden = :id_orden
                            AND deleted_at IS NULL')
                ->execute([
                    'id_orden' => $idOrden, 
                    'deleted_by' => $userId,
                    'updated_by' => $userId
                ]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function revertirABorrador(int $idOrden, int $userId): bool
    {
        if ($idOrden <= 0) {
            throw new RuntimeException('Orden inválida.');
        }

        $stmtExiste = $this->db()->prepare('SELECT estado
                                            FROM compras_ordenes
                                            WHERE id = :id
                                              AND deleted_at IS NULL
                                            LIMIT 1');
        $stmtExiste->execute(['id' => $idOrden]);
        $orden = $stmtExiste->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            throw new RuntimeException('La orden no existe o fue eliminada.');
        }

        if ((int) ($orden['estado'] ?? -1) !== 2) {
            throw new RuntimeException('Solo se pueden revertir órdenes en estado aprobada.');
        }

        $stmt = $this->db()->prepare('UPDATE compras_ordenes
                                      SET estado = 0,
                                          updated_by = :user,
                                          updated_at = NOW()
                                      WHERE id = :id
                                        AND estado = 2
                                        AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $idOrden,
            'user' => $userId,
        ]);

        if ($stmt->rowCount() <= 0) {
            throw new RuntimeException('No se pudo revertir la orden a borrador.');
        }

        return true;
    }

    public function listarProveedoresActivos(): array
    {
        $sql = 'SELECT t.id, t.nombre_completo
                FROM terceros_proveedores tp
                INNER JOIN terceros t ON t.id = tp.id_tercero
                WHERE t.es_proveedor = 1
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                  AND tp.deleted_at IS NULL
                ORDER BY t.nombre_completo ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function proveedorEsValido(int $idProveedor): bool
    {
        if ($idProveedor <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM terceros_proveedores tp
                INNER JOIN terceros t ON t.id = tp.id_tercero
                WHERE tp.id_tercero = :id
                  AND t.es_proveedor = 1
                  AND tp.deleted_at IS NULL
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idProveedor]);

        return (bool) $stmt->fetchColumn();
    }

    public function listarItemsActivos(): array
    {
        $sql = "SELECT id, sku, nombre, unidad_base, requiere_factor_conversion,
                    costo_referencial, impuesto_porcentaje
                FROM items
                WHERE estado = 1
                AND deleted_at IS NULL
                AND tipo_item IN ('materia_prima', 'insumo', 'material_empaque', 'servicio')
                ORDER BY nombre ASC";

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarUnidadesConversionItem(int $idItem): array
    {
        if ($idItem <= 0) {
            return [];
        }

        $sql = 'SELECT u.id,
                    u.nombre,
                    u.nombre AS text,
                    u.factor_conversion,
                    i.unidad_base
                FROM items_unidades u
                INNER JOIN items i ON i.id = u.id_item
                WHERE u.id_item = :id_item
                AND i.deleted_at IS NULL
                AND i.requiere_factor_conversion = 1
                AND u.estado = 1
                AND u.deleted_at IS NULL
                ORDER BY u.nombre ASC, u.id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function tablaTieneColumna(string $tabla, string $columna): bool
    {
        try {
            $stmt = $this->db()->prepare("SHOW COLUMNS FROM {$tabla} LIKE :columna");
            $stmt->execute(['columna' => $columna]);
            return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function tablaExiste(string $tabla): bool
    {
        try {
            $stmt = $this->db()->prepare('SHOW TABLES LIKE :tabla');
            $stmt->execute(['tabla' => $tabla]);
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }

    private function generarCodigo(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM compras_ordenes')->fetchColumn() + 1;
        return sprintf('OC-%s-%05d', date('Ymd'), $correlativo);
    }

    public function obtenerPrecioProveedor(int $idProveedor, int $idItem, ?int $idUnidad = null): float
    {
        if ($idProveedor <= 0 || $idItem <= 0) {
            return 0.0;
        }

        $sqlAcuerdo = "SELECT capp.precio_recomendado
                       FROM comercial_acuerdos_proveedor_precios capp
                       INNER JOIN comercial_acuerdos_proveedor capv ON capv.id = capp.id_acuerdo_proveedor
                       WHERE capv.id_tercero = :id_proveedor
                         AND capv.estado = 1
                         AND capp.estado = 1
                         AND capp.id_item = :id_item
                         AND (
                               (:id_unidad_1 IS NOT NULL AND (capp.id_unidad_conversion = :id_unidad_2 OR capp.id_unidad_conversion IS NULL))
                               OR
                               (:id_unidad_3 IS NULL AND capp.id_unidad_conversion IS NULL)
                         )
                       ORDER BY CASE WHEN :id_unidad_4 IS NOT NULL AND capp.id_unidad_conversion = :id_unidad_5 THEN 0 ELSE 1 END,
                                capp.id DESC
                       LIMIT 1";

        try {
            $stmt = $this->db()->prepare($sqlAcuerdo);
            $stmt->execute([
                ':id_proveedor' => $idProveedor,
                ':id_item'      => $idItem,
                ':id_unidad_1'  => $idUnidad,
                ':id_unidad_2'  => $idUnidad,
                ':id_unidad_3'  => $idUnidad,
                ':id_unidad_4'  => $idUnidad,
                ':id_unidad_5'  => $idUnidad,
            ]);
            
            $precioPactado = $stmt->fetchColumn();

            if ($precioPactado !== false) {
                return (float)$precioPactado;
            }

            $stmtItem = $this->db()->prepare("SELECT costo_referencial FROM items WHERE id = :id");
            $stmtItem->execute([':id' => $idItem]);
            $costoReferencial = $stmtItem->fetchColumn();

            return $costoReferencial !== false ? (float)$costoReferencial : 0.0;

        } catch (Throwable $e) {
            return 0.0;
        }
    }

    public function registrarDevolucion(int $idOrden, string $motivo, string $resolucion, array $detalle, int $userId, bool $esperarReemplazo = true): void
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmtOrd = $db->prepare("SELECT codigo, id_proveedor FROM compras_ordenes WHERE id = ?");
            $stmtOrd->execute([$idOrden]);
            $ordenData = $stmtOrd->fetch(PDO::FETCH_ASSOC);
            $idProveedor = (int) ($ordenData['id_proveedor'] ?? 0);
            $codigoOrden = (string) ($ordenData['codigo'] ?? '');

            if (!$idProveedor) {
                throw new RuntimeException("La orden no existe.");
            }

            if (trim($motivo) === '') throw new RuntimeException('Debe indicar el motivo de la devolución.');
            if (trim($resolucion) === '') throw new RuntimeException('Debe indicar cómo se resolverá la devolución.');

            $stmtAlmacen = $db->prepare("SELECT id_almacen FROM compras_recepciones WHERE id_orden_compra = ? ORDER BY id DESC LIMIT 1");
            $stmtAlmacen->execute([$idOrden]);
            $idAlmacenPreferido = (int) $stmtAlmacen->fetchColumn();

            if ($idAlmacenPreferido <= 0) {
                $stmtFallback = $db->query("SELECT id FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY id ASC LIMIT 1");
                $idAlmacenPreferido = (int) $stmtFallback->fetchColumn();
            }
            if ($idAlmacenPreferido <= 0) {
                throw new RuntimeException('No existe un almacén activo para procesar la salida de la devolución.');
            }

            $totalDevuelto = 0.0;

            $sqlDev = "INSERT INTO compras_devoluciones (id_orden, id_proveedor, motivo, tipo_resolucion, total_devuelto, created_by) 
                       VALUES (:id_orden, :id_proveedor, :motivo, :resolucion, 0, :user)";
            $db->prepare($sqlDev)->execute([
                'id_orden' => $idOrden, 'id_proveedor' => $idProveedor,
                'motivo' => trim($motivo), 'resolucion' => trim($resolucion), 'user' => $userId
            ]);
            $idDevolucion = (int) $db->lastInsertId();

            require_once BASE_PATH . '/app/models/inventario/InventarioModel.php';
            $inventarioModel = new InventarioModel();

            $sqlDet = "INSERT INTO compras_devoluciones_detalle (id_devolucion, id_item, id_item_unidad, cantidad, cantidad_base, costo_unitario, subtotal)
                       VALUES (:id_dev, :id_item, :id_unidad, :cant, :cant_base, :costo, :subtotal)";
            $stmtDet = $db->prepare($sqlDet);
            
            $stmtOrdenDetalle = $db->prepare("SELECT id_item, COALESCE(cantidad_recibida, 0) AS cantidad_recibida, id_centro_costo,
                                                     (COALESCE(cantidad_conversion, cantidad_solicitada) * costo_unitario_pactado) AS subtotal_linea,
                                                     COALESCE(cantidad_base_solicitada, cantidad_solicitada) AS cantidad_base_total
                                              FROM compras_ordenes_detalle 
                                              WHERE id = :id_det AND id_orden = :id_orden AND deleted_at IS NULL LIMIT 1");
                                              
            $stmtUpdateOrdenDet = $db->prepare("UPDATE compras_ordenes_detalle SET cantidad_recibida = cantidad_recibida - :cant_base WHERE id = :id_doc_det");

            foreach ($detalle as $linea) {
                $idDetalleOrden = (int) ($linea['id_documento_detalle'] ?? 0);
                $idItemLinea = (int) ($linea['id_item'] ?? 0);
                $cantidadInput = (float) ($linea['cantidad_input'] ?? 0);
                $cantidadBase = (float) ($linea['cantidad_base'] ?? 0);

                if ($idDetalleOrden <= 0 || $idItemLinea <= 0 || $cantidadInput <= 0 || $cantidadBase <= 0) {
                    throw new RuntimeException('Una línea de devolución no tiene datos válidos.');
                }

                $stmtOrdenDetalle->execute(['id_det' => $idDetalleOrden, 'id_orden' => $idOrden]);
                $ordenDet = $stmtOrdenDetalle->fetch(PDO::FETCH_ASSOC);
                
                if (!$ordenDet || (int) ($ordenDet['id_item'] ?? 0) !== $idItemLinea) {
                    throw new RuntimeException('Línea de devolución no coincide.');
                }

                $cantidadRecibidaActual = (float) ($ordenDet['cantidad_recibida'] ?? 0);
                if ($cantidadBase > $cantidadRecibidaActual + 0.00001) {
                    throw new RuntimeException('No puede devolver más cantidad que la ya recepcionada.');
                }

                $subtotalLineaBD = (float) ($ordenDet['subtotal_linea'] ?? 0);
                $cantidadBaseTotalBD = (float) ($ordenDet['cantidad_base_total'] ?? 1);
                $costoBaseSeguro = $cantidadBaseTotalBD > 0 ? ($subtotalLineaBD / $cantidadBaseTotalBD) : 0;

                $idAlmacenOrigen = $this->resolverAlmacenOrigenDevolucion($db, $idItemLinea, $cantidadBase, $idAlmacenPreferido);

                $subtotalLinea = $cantidadBase * $costoBaseSeguro;
                $totalDevuelto += $subtotalLinea;

                $stmtDet->execute([
                    'id_dev' => $idDevolucion, 'id_item' => $idItemLinea,
                    'id_unidad' => !empty($linea['id_unidad']) ? (int) $linea['id_unidad'] : null,
                    'cant' => $cantidadInput, 'cant_base' => $cantidadBase,
                    'costo' => $costoBaseSeguro, 'subtotal' => $subtotalLinea
                ]);

                $stmtUpdateOrdenDet->execute(['cant_base' => $cantidadBase, 'id_doc_det' => $idDetalleOrden]);

                $inventarioModel->registrarMovimiento([
                    'tipo_movimiento' => 'AJ-', 'tipo_registro' => 'item', 'id_item' => $idItemLinea,
                    'id_item_unidad' => !empty($linea['id_unidad']) ? (int) $linea['id_unidad'] : 0,
                    'id_almacen_origen' => $idAlmacenOrigen, 'cantidad' => $cantidadBase,
                    'costo_unitario' => $costoBaseSeguro,
                    'referencia' => 'Devolución OC ' . $codigoOrden . ' | ' . trim($motivo),
                    'id_centro_costo' => !empty($ordenDet['id_centro_costo']) ? (int) $ordenDet['id_centro_costo'] : null,
                    'created_by' => $userId, 'fecha_documento' => date('Y-m-d'),
                ]);
            }

            $db->prepare("UPDATE compras_devoluciones SET total_devuelto = ? WHERE id = ?")->execute([$totalDevuelto, $idDevolucion]);

            $stmtPendiente = $db->prepare("SELECT COUNT(*) FROM compras_ordenes_detalle WHERE id_orden = ? AND deleted_at IS NULL AND COALESCE(cantidad_recibida, 0) > 0.00001");
            $stmtPendiente->execute([$idOrden]);
            $lineasConRecepcionPendiente = (int) $stmtPendiente->fetchColumn();

            $devolucionTotalCompletada = $lineasConRecepcionPendiente === 0;

            if ($esperarReemplazo) {
                $nuevoEstado = 2;
            } else {
                $nuevoEstado = $devolucionTotalCompletada ? 9 : 3; 
            }

            $db->prepare("UPDATE compras_ordenes SET estado = ?, updated_at = NOW() WHERE id = ?")->execute([$nuevoEstado, $idOrden]);

            if (!$esperarReemplazo) {
                $this->aplicarAjusteCxpPorDevolucion($db, $idOrden, $resolucion, $totalDevuelto, $userId);
            }

            $db->commit();
            
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function resolverAlmacenOrigenDevolucion(PDO $db, int $idItem, float $cantidadBase, int $idAlmacenPreferido): int
    {
        if ($idItem <= 0) {
            throw new RuntimeException('Ítem inválido para calcular almacén de salida.');
        }

        if ($cantidadBase <= 0) {
            throw new RuntimeException('Cantidad inválida para calcular almacén de salida.');
        }

        $sql = 'SELECT s.id_almacen
                FROM inventario_stock s
                INNER JOIN almacenes a ON a.id = s.id_almacen
                WHERE s.id_item = :id_item
                  AND s.stock_actual >= :cantidad
                  AND a.estado = 1
                  AND a.deleted_at IS NULL
                ORDER BY CASE WHEN s.id_almacen = :id_preferido THEN 0 ELSE 1 END,
                         s.stock_actual DESC,
                         s.id_almacen ASC
                LIMIT 1';

        $stmt = $db->prepare($sql);
        $stmt->execute([
            'id_item' => $idItem,
            'cantidad' => $cantidadBase,
            'id_preferido' => $idAlmacenPreferido,
        ]);

        $idAlmacen = (int) $stmt->fetchColumn();
        if ($idAlmacen <= 0) {
            throw new RuntimeException('Stock insuficiente para realizar el movimiento.');
        }

        return $idAlmacen;
    }

    private function aplicarAjusteCxpPorDevolucion(PDO $db, int $idOrden, string $resolucion, float $totalDevuelto, int $userId): void
    {
        if ($totalDevuelto <= 0) {
            return;
        }

        if (trim(strtolower($resolucion)) !== 'descuento_cxp') {
            return;
        }

        $stmtCxp = $db->prepare('SELECT id, monto_total, monto_pagado
                                 FROM tesoreria_cxp
                                 WHERE id_orden_compra = :id_orden
                                   AND deleted_at IS NULL
                                   AND estado <> "ANULADA"
                                 ORDER BY id DESC
                                 LIMIT 1
                                 FOR UPDATE');
        $stmtCxp->execute(['id_orden' => $idOrden]);
        $cxp = $stmtCxp->fetch(PDO::FETCH_ASSOC);
        if (!$cxp) {
            return;
        }

        $idCxp = (int) ($cxp['id'] ?? 0);
        $montoTotalActual = (float) ($cxp['monto_total'] ?? 0);
        $montoPagadoActual = (float) ($cxp['monto_pagado'] ?? 0);
        $nuevoMontoTotal = max(0.0, $montoTotalActual - $totalDevuelto);
        $nuevoPagado = min($montoPagadoActual, $nuevoMontoTotal);
        $nuevoSaldo = max(0.0, $nuevoMontoTotal - $nuevoPagado);

        $nuevoEstado = 'PENDIENTE';
        if ($nuevoSaldo <= 0.00001) {
            $nuevoEstado = 'PAGADA';
        } elseif ($nuevoPagado > 0) {
            $nuevoEstado = 'PARCIAL';
        }

        $stmtUpd = $db->prepare('UPDATE tesoreria_cxp
                                 SET monto_total = :monto_total,
                                     monto_pagado = :monto_pagado,
                                     saldo = :saldo,
                                     estado = :estado,
                                     updated_by = :user,
                                     updated_at = NOW()
                                 WHERE id = :id');
        $stmtUpd->execute([
            'monto_total' => round($nuevoMontoTotal, 4),
            'monto_pagado' => round($nuevoPagado, 4),
            'saldo' => round($nuevoSaldo, 4),
            'estado' => $nuevoEstado,
            'user' => $userId,
            'id' => $idCxp,
        ]);
    }
}