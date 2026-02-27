<?php

declare(strict_types=1);

class VentasDocumentoModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
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

        $sql .= ' ORDER BY COALESCE(v.updated_at, v.created_at) DESC, v.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtener(int $idDocumento): array
    {
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
            $fechaEmision = !empty($cabecera['fecha_emision']) ? $cabecera['fecha_emision'] : date('Y-m-d');

            if ($idDocumento > 0) {
                $actual = $this->obtener($idDocumento);
                if ($actual === []) {
                    throw new RuntimeException('El pedido no existe.');
                }

                if ((int) ($actual['estado'] ?? 0) !== 0) {
                    throw new RuntimeException('Solo se pueden editar pedidos en borrador.');
                }

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

                $db->prepare('UPDATE ventas_documentos_detalle
                              SET deleted_at = NOW(), deleted_by = :user, updated_by = :user, updated_at = NOW()
                              WHERE id_documento_venta = :id_documento AND deleted_at IS NULL')
                    ->execute(['id_documento' => $idDocumento, 'user' => $userId]);
            } else {
                $codigo = $this->generarCodigo($db);
                
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
        $sql = 'SELECT DISTINCT t.id, t.nombre_completo, t.numero_documento AS num_doc
                FROM terceros t
                LEFT JOIN distribuidores d
                    ON d.id_tercero = t.id
                   AND d.deleted_at IS NULL
                WHERE (t.es_cliente = 1 OR d.id_tercero IS NOT NULL)
                  AND t.estado = 1
                  AND t.deleted_at IS NULL';

        $params = [];

        if ($q !== '') {
            $sql .= ' AND (nombre_completo LIKE ? OR numero_documento LIKE ?)';
            $term = '%' . $q . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= ' ORDER BY nombre_completo ASC LIMIT ' . (int)$limit;
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function clienteEsValido(int $idCliente): bool
    {
        if ($idCliente <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM terceros t
                LEFT JOIN distribuidores d
                    ON d.id_tercero = t.id
                   AND d.deleted_at IS NULL
                WHERE t.id = :id
                  AND (t.es_cliente = 1 OR d.id_tercero IS NOT NULL)
                  AND t.estado = 1
                  AND t.deleted_at IS NULL
                LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idCliente]);

        return (bool) $stmt->fetchColumn();
    }

    public function buscarItems(string $q = '', int $idAlmacen = 0, int $idCliente = 0, float $cantidad = 1, int $limit = 30): array
    {
        $params = [];
        $cantidad = $cantidad > 0 ? $cantidad : 1.0;
        $acuerdo = $this->obtenerAcuerdoActivoCliente($idCliente);
        
        if ($idAlmacen > 0) {
            $stockSql = "(SELECT s.stock_actual FROM inventario_stock s WHERE s.id_item = i.id AND s.id_almacen = ? LIMIT 1)";
            $params[] = $idAlmacen;
        } else {
            $stockSql = "(SELECT SUM(s.stock_actual) FROM inventario_stock s WHERE s.id_item = i.id)";
        }

        if ($acuerdo['tiene_acuerdo']) {
            $sql = "SELECT i.id,
                        i.sku,
                        i.nombre,
                        cap.precio_pactado AS precio_venta,
                        i.tipo_item,
                        COALESCE($stockSql, 0) AS stock_actual
                    FROM comercial_acuerdos_precios cap
                    INNER JOIN items i ON i.id = cap.id_presentacion
                    WHERE cap.id_acuerdo = ?
                      AND cap.estado = 1
                      AND i.estado = 1
                      AND i.deleted_at IS NULL
                      AND i.tipo_item IN ('producto', 'producto_terminado')";
            $params[] = $acuerdo['id_acuerdo'];
        } else {
            $sql = "SELECT i.id,
                        i.sku,
                        i.nombre,
                        COALESCE(
                            (
                                SELECT ipv.precio_unitario
                                FROM item_precios_volumen ipv
                                WHERE ipv.id_item = i.id
                                  AND ipv.cantidad_minima <= ?
                                ORDER BY ipv.cantidad_minima DESC
                                LIMIT 1
                            ),
                            i.precio_venta,
                            0
                        ) AS precio_venta,
                        i.tipo_item,
                        COALESCE($stockSql, 0) AS stock_actual
                    FROM items i
                    WHERE i.estado = 1
                      AND i.deleted_at IS NULL
                      AND i.tipo_item IN ('producto', 'producto_terminado')";
            $params[] = $cantidad;
        }

        if ($q !== '') {
            $sql .= ' AND (i.nombre LIKE ? OR i.sku LIKE ?)';
            $term = '%' . $q . '%';
            $params[] = $term;
            $params[] = $term;
        }

        $sql .= ' ORDER BY i.nombre ASC LIMIT ' . (int)$limit;

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerPrecioUnitario(int $idCliente, int $idItem, float $cantidad): array
    {
        if ($idItem <= 0) {
            return ['precio' => 0.0, 'origen' => 'none'];
        }

        $cantidad = $cantidad > 0 ? $cantidad : 1.0;
        $acuerdo = $this->obtenerAcuerdoActivoCliente($idCliente);

        if ($acuerdo['tiene_acuerdo']) {
            $sql = 'SELECT cap.precio_pactado
                    FROM comercial_acuerdos_precios cap
                    WHERE cap.id_acuerdo = :id_acuerdo
                      AND cap.id_presentacion = :id_item
                      AND cap.estado = 1
                    LIMIT 1';
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'id_acuerdo' => $acuerdo['id_acuerdo'],
                'id_item' => $idItem,
            ]);
            $precio = $stmt->fetchColumn();

            return [
                'precio' => $precio !== false ? (float) $precio : 0.0,
                'origen' => 'acuerdo',
            ];
        }

        $sqlVolumen = 'SELECT ipv.precio_unitario
                       FROM item_precios_volumen ipv
                       WHERE ipv.id_item = :id_item
                         AND ipv.cantidad_minima <= :cantidad
                       ORDER BY ipv.cantidad_minima DESC
                       LIMIT 1';
        $stmtVolumen = $this->db()->prepare($sqlVolumen);
        $stmtVolumen->execute([
            'id_item' => $idItem,
            'cantidad' => $cantidad,
        ]);
        $precioVolumen = $stmtVolumen->fetchColumn();
        if ($precioVolumen !== false) {
            return [
                'precio' => (float) $precioVolumen,
                'origen' => 'volumen',
            ];
        }

        $stmtBase = $this->db()->prepare('SELECT COALESCE(precio_venta, 0) FROM items WHERE id = :id_item LIMIT 1');
        $stmtBase->execute(['id_item' => $idItem]);

        return [
            'precio' => (float) $stmtBase->fetchColumn(),
            'origen' => 'base',
        ];
    }

    public function tieneAcuerdoConProductosVigentes(int $idCliente): array
    {
        $acuerdo = $this->obtenerAcuerdoActivoCliente($idCliente);
        if (!$acuerdo['tiene_acuerdo']) {
            return ['tiene_acuerdo' => false, 'lista_vacia' => false];
        }

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM comercial_acuerdos_precios WHERE id_acuerdo = :id_acuerdo AND estado = 1');
        $stmt->execute(['id_acuerdo' => $acuerdo['id_acuerdo']]);

        return [
            'tiene_acuerdo' => true,
            'lista_vacia' => ((int) $stmt->fetchColumn()) === 0,
        ];
    }

    private function obtenerAcuerdoActivoCliente(int $idCliente): array
    {
        if ($idCliente <= 0 || !$this->tablaExiste('comercial_acuerdos') || !$this->tablaExiste('comercial_acuerdos_precios')) {
            return ['tiene_acuerdo' => false, 'id_acuerdo' => 0];
        }

        $stmt = $this->db()->prepare('SELECT id FROM comercial_acuerdos WHERE id_tercero = :id_cliente AND estado = 1 ORDER BY id DESC LIMIT 1');
        $stmt->execute(['id_cliente' => $idCliente]);
        $idAcuerdo = (int) $stmt->fetchColumn();

        return [
            'tiene_acuerdo' => $idAcuerdo > 0,
            'id_acuerdo' => $idAcuerdo,
        ];
    }

    private function tablaExiste(string $tabla): bool
    {
        static $cache = [];

        if (isset($cache[$tabla])) {
            return $cache[$tabla];
        }

        $stmt = $this->db()->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabla LIMIT 1');
        $stmt->execute(['tabla' => $tabla]);
        $cache[$tabla] = (bool) $stmt->fetchColumn();

        return $cache[$tabla];
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

    public function obtenerStockDisponibleItem(int $idItem): float
    {
        if ($idItem <= 0) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(s.stock_actual), 0)
                FROM inventario_stock s
                INNER JOIN items i ON i.id = s.id_item
                WHERE s.id_item = :id_item
                  AND i.deleted_at IS NULL
                  AND i.estado = 1
                  AND i.tipo_item IN ('producto', 'producto_terminado')";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

        return (float) $stmt->fetchColumn();
    }

    private function generarCodigo(PDO $db): string
    {
        $correlativo = (int) $db->query('SELECT COUNT(*) FROM ventas_documentos')->fetchColumn() + 1;
        return 'PED-' . date('Y') . '-' . str_pad((string) $correlativo, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Registra el despacho de mercadería (Salida de Almacén)
     * Soporta múltiples filas con diferentes almacenes.
     */
    public function guardarDespacho(int $idDoc, array $detalle, string $obs, bool $cerrarForzado, int $userId): void
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Validar estado actual del pedido
            $stmt = $db->prepare("SELECT estado FROM ventas_documentos WHERE id = ?");
            $stmt->execute([$idDoc]);
            $estadoActual = $stmt->fetchColumn();

            if ($estadoActual == 3 || $estadoActual == 9) {
                throw new Exception("El pedido ya está cerrado o anulado.");
            }

            // 2. Sentencias Preparadas (Para optimizar dentro del bucle)
            // Actualizar cantidad despachada en el detalle del pedido
            $sqlUpdateDetalle = "UPDATE ventas_documentos_detalle 
                                 SET cantidad_despachada = cantidad_despachada + ? 
                                 WHERE id = ?";
            
            // Restar Stock (Validando que exista en ese almacén específico)
            $sqlRestarStock = "UPDATE inventario_stock 
                               SET stock_actual = stock_actual - ?, updated_at = NOW() 
                               WHERE id_item = (SELECT id_item FROM ventas_documentos_detalle WHERE id = ?) 
                               AND id_almacen = ?";

            // Registrar movimiento en Kardex
            $sqlKardex = "INSERT INTO inventario_movimientos 
                          (id_almacen, id_item, tipo_movimiento, cantidad, referencia, created_at, created_by) 
                          SELECT ?, id_item, 'SALIDA', ?, CONCAT('Despacho Pedido #', ?), NOW(), ? 
                          FROM ventas_documentos_detalle WHERE id = ?";

            $stmtUpdDet = $db->prepare($sqlUpdateDetalle);
            $stmtStock  = $db->prepare($sqlRestarStock);
            $stmtKardex = $db->prepare($sqlKardex);

            foreach ($detalle as $item) {
                $idDetalle = (int)$item['id_documento_detalle'];
                $idAlmacen = (int)$item['id_almacen']; // Aquí obtenemos el almacén de la fila
                $cantidad  = (float)$item['cantidad'];

                if ($cantidad <= 0) continue;

                // A. Actualizar lo despachado en el pedido
                $stmtUpdDet->execute([$cantidad, $idDetalle]);

                // B. Restar Stock
                $stmtStock->execute([$cantidad, $idDetalle, $idAlmacen]);
                
                // Si rowCount es 0, significa que no encontró el ítem en ese almacén en la tabla de stock
                if ($stmtStock->rowCount() === 0) {
                    throw new Exception("Error de stock: El producto no existe o no está asignado al almacén seleccionado (ID: $idAlmacen).");
                }

                // C. Kardex
                $stmtKardex->execute([$idAlmacen, $cantidad, $idDoc, $userId, $idDetalle]);
            }

            // 3. Actualizar Observaciones de cabecera si hay nuevas
            if (!empty($obs)) {
                $db->prepare("UPDATE ventas_documentos SET observaciones = CONCAT(COALESCE(observaciones, ''), ' | Despacho: ', ?) WHERE id = ?")
                   ->execute([$obs, $idDoc]);
            }

            // 4. Calcular si se cierra el pedido
            $sqlPendiente = "SELECT SUM(cantidad - cantidad_despachada) as pendiente 
                             FROM ventas_documentos_detalle 
                             WHERE id_documento_venta = ? AND deleted_at IS NULL";
            $stmtPen = $db->prepare($sqlPendiente);
            $stmtPen->execute([$idDoc]);
            $pendienteTotal = (float)$stmtPen->fetchColumn();

            $nuevoEstado = 2; // Aprobado (Parcial)
            if ($pendienteTotal <= 0.01 || $cerrarForzado) {
                $nuevoEstado = 3; // Cerrado / Entregado
            }

            $db->prepare("UPDATE ventas_documentos SET estado = ? WHERE id = ?")->execute([$nuevoEstado, $idDoc]);

            $db->commit();

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
