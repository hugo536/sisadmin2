<?php

declare(strict_types=1);

class ComprasOrdenModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT o.id,
                       o.codigo,
                       o.id_proveedor,
                       t.nombre_completo AS proveedor,
                       DATE_FORMAT(o.fecha_emision, "%d/%m/%Y") AS fecha_orden,
                       /* Formateamos la fecha de entrega igual que la de emisión */
                       DATE_FORMAT(o.fecha_entrega_estimada, "%d/%m/%Y") AS fecha_entrega,
                       o.total,
                       o.estado,
                       o.created_at
                FROM compras_ordenes o
                INNER JOIN terceros t ON t.id = o.id_proveedor
                WHERE o.deleted_at IS NULL
                  AND t.deleted_at IS NULL';

        $params = [];

        if (!empty($filtros['q'])) {
            $sql .= ' AND (o.codigo LIKE :q OR t.nombre_completo LIKE :q)';
            $params['q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== '' && $filtros['estado'] !== null) {
            $sql .= ' AND o.estado = :estado';
            $params['estado'] = (int) $filtros['estado'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND o.fecha_emision >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND o.fecha_emision <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $sql .= ' ORDER BY COALESCE(o.updated_at, o.created_at) DESC, o.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT id, codigo, id_proveedor, 
                       fecha_emision AS fecha_orden, 
                       fecha_entrega_estimada AS fecha_entrega, 
                       observaciones, subtotal, total, estado
                FROM compras_ordenes
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $orden = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$orden) {
            return [];
        }

        // AGREGAMOS: cantidad_recibida, cantidad_pendiente, cantidad_unidad y unidad_base
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
                              (COALESCE(d.cantidad_conversion, d.cantidad_solicitada) * d.costo_unitario_pactado) AS subtotal
                       FROM compras_ordenes_detalle d
                       INNER JOIN items i ON i.id = d.id_item AND i.deleted_at IS NULL
                       WHERE d.id_orden = :id_orden
                         AND d.deleted_at IS NULL
                       ORDER BY d.id ASC';

        $stmtDetalle = $this->db()->prepare($detalleSql);
        $stmtDetalle->execute(['id_orden' => $id]);
        $orden['detalle'] = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC) ?: [];

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
            
            // Preparar fecha de entrega (puede ser null)
            $fechaEntrega = !empty($cabecera['fecha_entrega']) ? $cabecera['fecha_entrega'] : null;

            if ($idOrden > 0) {
                $actual = $this->obtener($idOrden);
                if ($actual === []) {
                    throw new RuntimeException('La orden no existe o fue eliminada.');
                }

                if ((int) ($actual['estado'] ?? 0) !== 0) {
                    throw new RuntimeException('Solo se pueden editar órdenes en borrador.');
                }

                $sqlUpdate = 'UPDATE compras_ordenes
                              SET id_proveedor = :id_proveedor,
                                  fecha_entrega_estimada = :fecha_entrega,
                                  observaciones = :observaciones,
                                  tipo_impuesto = :tipo_impuesto,
                                  subtotal = :subtotal,
                                  igv_monto = :igv_monto,
                                  total = :total,
                                  estado = :estado,
                                  updated_by = :updated_by,
                                  updated_at = NOW()
                              WHERE id = :id
                                AND deleted_at IS NULL';

                $db->prepare($sqlUpdate)->execute([
                    'id' => $idOrden,
                    'id_proveedor' => (int) $cabecera['id_proveedor'],
                    'fecha_entrega' => $fechaEntrega,
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'tipo_impuesto' => $cabecera['tipo_impuesto'],
                    'subtotal' => (float) $cabecera['subtotal'],
                    'igv_monto' => (float) $cabecera['igv_monto'],
                    'total' => (float) $cabecera['total'],
                    'estado' => $estado,
                    'updated_by' => $userId,
                ]);

                // Borrado lógico del detalle anterior para reinsertar
                $db->prepare('UPDATE compras_ordenes_detalle SET deleted_at = NOW(), deleted_by = :user WHERE id_orden = :id_orden AND deleted_at IS NULL')
                    ->execute(['user' => $userId, 'id_orden' => $idOrden]);
            } else {
                $codigo = $this->generarCodigo($db);

                $sqlInsert = 'INSERT INTO compras_ordenes (
                                codigo,
                                id_proveedor,
                                fecha_emision,
                                fecha_entrega_estimada,
                                observaciones,
                                tipo_impuesto,
                                subtotal,
                                igv_monto,
                                total,
                                estado,
                                created_by,
                                updated_by,
                                created_at,
                                updated_at
                              ) VALUES (
                                :codigo,
                                :id_proveedor,
                                NOW(),
                                :fecha_entrega,
                                :observaciones,
                                :tipo_impuesto,
                                :subtotal,
                                :igv_monto,
                                :total,
                                :estado,
                                :created_by,
                                :updated_by,
                                NOW(),
                                NOW()
                              )';

                $db->prepare($sqlInsert)->execute([
                    'codigo' => $codigo,
                    'id_proveedor' => (int) $cabecera['id_proveedor'],
                    'fecha_entrega' => $fechaEntrega,
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'tipo_impuesto' => $cabecera['tipo_impuesto'],
                    'subtotal' => (float) $cabecera['subtotal'],
                    'igv_monto' => (float) $cabecera['igv_monto'],
                    'total' => (float) $cabecera['total'],
                    'estado' => $estado,
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
                    // ERROR CORREGIDO: Antes decía $cantidadBase. 
                    // Debe ser la cantidad original solicitada en esa unidad.
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

        // Eliminamos las consultas dinámicas (SHOW COLUMNS/TABLES) para mejorar drásticamente la velocidad.
        // Añadimos múltiples alias (nombre, text, label) para asegurar que el frontend lo lea sin importar el framework.
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

    /**
     * Obtiene el precio pactado con un proveedor específico.
     * Si no existe un acuerdo, devuelve el costo referencial por defecto del ítem.
     */
    public function obtenerPrecioProveedor(int $idProveedor, int $idItem, ?int $idUnidad = null): float
    {
        if ($idProveedor <= 0 || $idItem <= 0) {
            return 0.0;
        }

        // 1. Buscamos si existe un precio recomendado en los acuerdos
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

            // Si encontró un precio en los acuerdos, lo devolvemos
            if ($precioPactado !== false) {
                return (float)$precioPactado;
            }

            // 2. FALLBACK: Si el proveedor no tiene este ítem en sus acuerdos, 
            // traemos el costo referencial base del ítem como sugerencia.
            $stmtItem = $this->db()->prepare("SELECT costo_referencial FROM items WHERE id = :id");
            $stmtItem->execute([':id' => $idItem]);
            $costoReferencial = $stmtItem->fetchColumn();

            return $costoReferencial !== false ? (float)$costoReferencial : 0.0;

        } catch (Throwable $e) {
            // En caso de error de base de datos, retornamos 0 para no romper la app
            return 0.0;
        }
    }

    public function registrarDevolucion(int $idOrden, string $motivo, string $resolucion, array $detalle, int $userId): void
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Obtener info de la orden para saber el proveedor
            $stmtOrd = $db->prepare("SELECT id_proveedor FROM compras_ordenes WHERE id = ?");
            $stmtOrd->execute([$idOrden]);
            $idProveedor = (int) $stmtOrd->fetchColumn();

            if (!$idProveedor) {
                throw new RuntimeException("La orden no existe.");
            }

            $totalDevuelto = 0.0;

            // 2. Crear cabecera de la devolución
            $sqlDev = "INSERT INTO compras_devoluciones (id_orden, id_proveedor, motivo, tipo_resolucion, total_devuelto, created_by) 
                       VALUES (:id_orden, :id_proveedor, :motivo, :resolucion, 0, :user)"; // Total temporal 0
            
            $db->prepare($sqlDev)->execute([
                'id_orden' => $idOrden,
                'id_proveedor' => $idProveedor,
                'motivo' => trim($motivo),
                'resolucion' => trim($resolucion),
                'user' => $userId
            ]);
            
            $idDevolucion = (int) $db->lastInsertId();

            // 3. Insertar detalle y sumar el total
            $sqlDet = "INSERT INTO compras_devoluciones_detalle (id_devolucion, id_item, id_item_unidad, cantidad, cantidad_base, costo_unitario, subtotal)
                       VALUES (:id_dev, :id_item, :id_unidad, :cant, :cant_base, :costo, :subtotal)";
            $stmtDet = $db->prepare($sqlDet);

            foreach ($detalle as $linea) {
                $subtotalLinea = (float)$linea['cantidad_base'] * (float)$linea['costo_base'];
                $totalDevuelto += $subtotalLinea;

                $stmtDet->execute([
                    'id_dev' => $idDevolucion,
                    'id_item' => $linea['id_item'],
                    'id_unidad' => $linea['id_unidad'] ?: null,
                    'cant' => $linea['cantidad_input'],
                    'cant_base' => $linea['cantidad_base'],
                    'costo' => $linea['costo_base'],
                    'subtotal' => $subtotalLinea
                ]);

                // 4. DESCONTAR LA CANTIDAD RECIBIDA DE LA ORDEN ORIGINAL (Para que el sistema sepa que ya no la tenemos)
                $db->prepare("UPDATE compras_ordenes_detalle 
                              SET cantidad_recibida = cantidad_recibida - :cant_base 
                              WHERE id = :id_doc_det")
                   ->execute([
                       'cant_base' => $linea['cantidad_base'], 
                       'id_doc_det' => $linea['id_documento_detalle']
                   ]);
                
                // IMPORTANTE: Aquí iría tu query de KARDEX para sacar la mercadería del almacén físico.
                // Ejemplo: $this->kardexModel->registrarSalidaDevolucion($linea['id_item'], $linea['cantidad_base']);
            }

            // 5. Actualizar el total recuperado en la cabecera
            $db->prepare("UPDATE compras_devoluciones SET total_devuelto = ? WHERE id = ?")
               ->execute([$totalDevuelto, $idDevolucion]);

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
