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
                       o.fecha_emision AS fecha_orden,
                       o.fecha_entrega_estimada AS fecha_entrega,
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

        // Seleccionamos los campos correctos de la BD (cantidad_solicitada, costo_unitario_pactado)
        // Calculamos el subtotal al vuelo ya que no existe en la tabla detalle
        $detalleSql = 'SELECT d.id,
                              d.id_item,
                              i.sku,
                              i.nombre AS item_nombre,
                              d.id_item_unidad,
                              COALESCE(d.unidad_nombre, i.unidad_base) AS unidad_nombre,
                              COALESCE(d.factor_conversion_aplicado, 1) AS factor_conversion_aplicado,
                              COALESCE(d.cantidad_conversion, d.cantidad_solicitada) AS cantidad,
                              COALESCE(d.cantidad_base_solicitada, d.cantidad_solicitada) AS cantidad_base,
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
                                  subtotal = :subtotal,
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
                    'subtotal' => (float) $cabecera['subtotal'],
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
                                subtotal,
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
                                :subtotal,
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
                    'subtotal' => (float) $cabecera['subtotal'],
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
                    'cantidad' => $cantidadBase,
                    'costo_unitario' => $costo,
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
        // CORRECCIÓN DEFINITIVA: 
        // Usamos una lista blanca (IN) en lugar de exclusión (NOT IN).
        // Solo permitimos que se compren componentes operativos o materias primas.
        $sql = "SELECT id, sku, nombre, unidad_base, requiere_factor_conversion
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

        if (!$this->tablaTieneColumna('items_unidades', 'id_item')) {
            return [];
        }

        $condiciones = [
            'u.id_item = :id_item',
            'i.deleted_at IS NULL',
            'i.requiere_factor_conversion = 1',
        ];

        if ($this->tablaTieneColumna('items_unidades', 'estado')) {
            $condiciones[] = 'u.estado = 1';
        }

        if ($this->tablaTieneColumna('items_unidades', 'deleted_at')) {
            $condiciones[] = 'u.deleted_at IS NULL';
        }

        $sql = 'SELECT u.id,
                       u.nombre,
                       COALESCE(u.factor_conversion, 1.0000) AS factor_conversion,
                       i.unidad_base
                FROM items_unidades u
                INNER JOIN items i ON i.id = u.id_item
                WHERE ' . implode(' AND ', $condiciones) . '
                ORDER BY u.nombre ASC, u.id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function tablaTieneColumna(string $tabla, string $columna): bool
    {
        $stmt = $this->db()->prepare("SHOW COLUMNS FROM {$tabla} LIKE :columna");
        $stmt->execute(['columna' => $columna]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    private function generarCodigo(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM compras_ordenes')->fetchColumn() + 1;
        return sprintf('OC-%s-%05d', date('Ymd'), $correlativo);
    }
}
