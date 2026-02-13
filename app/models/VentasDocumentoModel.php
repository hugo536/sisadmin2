<?php

declare(strict_types=1);

class VentasDocumentoModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        // CORREGIDO: fecha_emision en lugar de created_at para filtrar fechas
        $sql = 'SELECT v.id,
                       v.codigo,
                       v.id_cliente,
                       t.nombre_completo AS cliente,
                       v.fecha_emision,
                       v.total,
                       v.estado,
                       v.created_at
                FROM ventas_documentos v
                INNER JOIN terceros t ON t.id = v.id_cliente AND t.deleted_at IS NULL
                WHERE v.deleted_at IS NULL';

        $params = [];

        if (!empty($filtros['q'])) {
            $sql .= ' AND (v.codigo LIKE :q OR t.nombre_completo LIKE :q)';
            $params['q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        if (isset($filtros['estado']) && $filtros['estado'] !== '' && $filtros['estado'] !== null) {
            $sql .= ' AND v.estado = :estado';
            $params['estado'] = (int) $filtros['estado'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND v.fecha_emision >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND v.fecha_emision <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $sql .= ' ORDER BY v.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtener(int $idDocumento): array
    {
        // CORREGIDO: Agregado fecha_emision
        $sql = 'SELECT id, codigo, id_cliente, fecha_emision, observaciones, subtotal, total, estado
                FROM ventas_documentos
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idDocumento]);
        $venta = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$venta) {
            return [];
        }

        // CORREGIDO: id_documento_venta y total_linea
        $sqlDetalle = 'SELECT d.id,
                              d.id_item,
                              i.sku,
                              i.nombre AS item_nombre,
                              d.cantidad,
                              d.precio_unitario,
                              d.total_linea AS subtotal,
                              d.cantidad_despachada
                       FROM ventas_documentos_detalle d
                       INNER JOIN items i ON i.id = d.id_item AND i.deleted_at IS NULL
                       WHERE d.id_documento_venta = :id_documento
                         AND d.deleted_at IS NULL
                       ORDER BY d.id ASC';

        $stmtDetalle = $this->db()->prepare($sqlDetalle);
        $stmtDetalle->execute(['id_documento' => $idDocumento]);
        $detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($detalle as &$linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $despachada = (float) ($linea['cantidad_despachada'] ?? 0);
            $linea['cantidad_pendiente'] = max(0, $cantidad - $despachada);
        }
        unset($linea);

        $venta['detalle'] = $detalle;
        return $venta;
    }

    public function crearOActualizar(array $cabecera, array $detalle, int $userId): int
    {
        if ($userId <= 0) {
            throw new RuntimeException('Usuario inválido para registrar la venta.');
        }

        $db = $this->db();
        $db->beginTransaction();

        try {
            $idDocumento = (int) ($cabecera['id'] ?? 0);
            // Default fecha hoy si no viene
            $fechaEmision = !empty($cabecera['fecha_emision']) ? $cabecera['fecha_emision'] : date('Y-m-d');

            if ($idDocumento > 0) {
                $actual = $this->obtener($idDocumento);
                if ($actual === []) {
                    throw new RuntimeException('El pedido no existe.');
                }

                if ((int) ($actual['estado'] ?? 0) !== 0) {
                    throw new RuntimeException('Solo se pueden editar pedidos en borrador.');
                }

                // CORREGIDO: Agregado fecha_emision
                $sqlUpdate = 'UPDATE ventas_documentos
                              SET id_cliente = :id_cliente,
                                  fecha_emision = :fecha_emision,
                                  observaciones = :observaciones,
                                  subtotal = :subtotal,
                                  total = :total,
                                  updated_by = :updated_by,
                                  updated_at = NOW()
                              WHERE id = :id
                                AND deleted_at IS NULL';

                $db->prepare($sqlUpdate)->execute([
                    'id' => $idDocumento,
                    'id_cliente' => (int) $cabecera['id_cliente'],
                    'fecha_emision' => $fechaEmision,
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'subtotal' => (float) $cabecera['subtotal'],
                    'total' => (float) $cabecera['total'],
                    'updated_by' => $userId,
                ]);

                // Borrado lógico del detalle anterior
                $db->prepare('UPDATE ventas_documentos_detalle
                              SET deleted_at = NOW(), deleted_by = :user, updated_by = :user, updated_at = NOW()
                              WHERE id_documento_venta = :id_documento AND deleted_at IS NULL')
                    ->execute(['id_documento' => $idDocumento, 'user' => $userId]);
            } else {
                $codigo = $this->generarCodigo($db);
                
                // CORREGIDO: Agregado fecha_emision
                $sqlInsert = 'INSERT INTO ventas_documentos (
                                codigo,
                                id_cliente,
                                fecha_emision,
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
                                :id_cliente,
                                :fecha_emision,
                                :observaciones,
                                :subtotal,
                                :total,
                                0,
                                :created_by,
                                :updated_by,
                                NOW(),
                                NOW()
                              )';

                $db->prepare($sqlInsert)->execute([
                    'codigo' => $codigo,
                    'id_cliente' => (int) $cabecera['id_cliente'],
                    'fecha_emision' => $fechaEmision,
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'subtotal' => (float) $cabecera['subtotal'],
                    'total' => (float) $cabecera['total'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idDocumento = (int) $db->lastInsertId();
            }

            // CORREGIDO: id_documento_venta y total_linea en lugar de subtotal
            $sqlDet = 'INSERT INTO ventas_documentos_detalle (
                            id_documento_venta,
                            id_item,
                            cantidad,
                            precio_unitario,
                            total_linea,
                            created_by,
                            updated_by,
                            created_at,
                            updated_at
                        ) VALUES (
                            :id_documento,
                            :id_item,
                            :cantidad,
                            :precio_unitario,
                            :total_linea,
                            :created_by,
                            :updated_by,
                            NOW(),
                            NOW()
                        )';

            $stmtDet = $db->prepare($sqlDet);
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);

                if ($cantidad <= 0 || $precio < 0) {
                    throw new RuntimeException('Hay líneas con cantidad/precio inválido.');
                }

                $stmtDet->execute([
                    'id_documento' => $idDocumento,
                    'id_item' => (int) ($linea['id_item'] ?? 0),
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'total_linea' => round($cantidad * $precio, 2),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            $db->commit();
            return $idDocumento;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function aprobar(int $idDocumento, int $userId): bool
    {
        $sql = 'UPDATE ventas_documentos
                SET estado = 2,
                    updated_by = :user,
                    updated_at = NOW()
                WHERE id = :id
                  AND estado = 0
                  AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idDocumento, 'user' => $userId]);

        return $stmt->rowCount() > 0;
    }

    public function anular(int $idDocumento, int $userId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            $stmt = $db->prepare('UPDATE ventas_documentos
                                  SET estado = 9,
                                      deleted_at = NOW(),
                                      deleted_by = :user,
                                      updated_by = :user,
                                      updated_at = NOW()
                                  WHERE id = :id
                                    AND deleted_at IS NULL');
            $stmt->execute(['id' => $idDocumento, 'user' => $userId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('No se pudo anular el pedido.');
            }

            // CORREGIDO: id_documento_venta
            $db->prepare('UPDATE ventas_documentos_detalle
                          SET deleted_at = NOW(), deleted_by = :user, updated_by = :user, updated_at = NOW()
                          WHERE id_documento_venta = :id_documento
                            AND deleted_at IS NULL')
                ->execute(['id_documento' => $idDocumento, 'user' => $userId]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function buscarClientes(string $q = '', int $limit = 20): array
    {
        $sql = 'SELECT t.id,
                       t.nombre_completo,
                       t.numero_documento AS num_doc
                FROM terceros t
                LEFT JOIN terceros_clientes tc ON tc.id_tercero = t.id AND tc.deleted_at IS NULL
                WHERE t.es_cliente = 1
                  AND t.estado = 1
                  AND t.deleted_at IS NULL';
                FROM terceros_clientes tc
                INNER JOIN terceros t ON t.id = tc.id_tercero
                WHERE t.estado = 1
                  AND t.deleted_at IS NULL
                  AND tc.deleted_at IS NULL';

        $params = [];
        if ($q !== '') {
            $sql .= ' AND (t.nombre_completo LIKE :q OR t.numero_documento LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY t.nombre_completo ASC LIMIT :limite';
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function clienteEsValido(int $idCliente): bool
    {
        if ($idCliente <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM terceros t
                WHERE t.id = :id
                  AND t.es_cliente = 1
                FROM terceros_clientes tc
                INNER JOIN terceros t ON t.id = tc.id_tercero
                WHERE tc.id_tercero = :id
                  AND tc.deleted_at IS NULL
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idCliente]);

        return (bool) $stmt->fetchColumn();
    }

    public function buscarItems(string $q = '', int $idAlmacen = 0, int $limit = 30): array
    {
        $sql = 'SELECT i.id,
                       i.sku,
                       i.nombre,
                       COALESCE(' . ($idAlmacen > 0
                ? '(SELECT s.stock_actual FROM inventario_stock s WHERE s.id_item = i.id AND s.id_almacen = :id_almacen LIMIT 1)'
                : '(SELECT SUM(s.stock_actual) FROM inventario_stock s WHERE s.id_item = i.id)') . ', 0) AS stock_actual,
                       i.precio_venta
                FROM items i
                WHERE i.estado = 1
                  AND i.deleted_at IS NULL';

        $params = [];
        if ($idAlmacen > 0) {
            $params['id_almacen'] = $idAlmacen;
        }

        if ($q !== '') {
            $sql .= ' AND (i.sku LIKE :q OR i.nombre LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $sql .= ' ORDER BY i.nombre ASC LIMIT :limite';

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $key => $value) {
            $type = $key === 'id_almacen' ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $type);
        }
        $stmt->bindValue(':limite', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarAlmacenesActivos(): array
    {
        $sql = 'SELECT id, nombre
                FROM almacenes
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function generarCodigo(PDO $db): string
    {
        // Genera formato PED-2026-000001
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM ventas_documentos')->fetchColumn() + 1;
        return 'PED-' . date('Y') . '-' . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }
}
