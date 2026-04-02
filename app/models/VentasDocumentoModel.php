<?php

declare(strict_types=1);

class VentasDocumentoModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT v.id,
                       v.codigo,
                       v.tipo_operacion,
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
            $sql .= ' AND (v.codigo LIKE :q_codigo OR t.nombre_completo LIKE :q_cliente)';
            $busqueda = '%' . trim((string) $filtros['q']) . '%';
            $params['q_codigo'] = $busqueda;
            $params['q_cliente'] = $busqueda;
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
        $sql = 'SELECT v.id, v.codigo, v.id_cliente, 
                       v.tipo_operacion, v.tipo_impuesto,
                       t.nombre_completo AS cliente, 
                       t.numero_documento AS cliente_doc, 
                       t.direccion AS cliente_direccion, 
                       v.fecha_emision, v.observaciones, v.subtotal, v.total, v.estado
                FROM ventas_documentos v
                LEFT JOIN terceros t ON t.id = v.id_cliente
                WHERE v.id = :id
                  AND v.deleted_at IS NULL
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
                              d.cantidad_despachada,
                              (SELECT COALESCE(SUM(s.stock_actual), 0) FROM inventario_stock s WHERE s.id_item = d.id_item) AS stock_actual
                       FROM ventas_documentos_detalle d
                       INNER JOIN items i ON i.id = d.id_item AND i.deleted_at IS NULL
                       WHERE d.id_documento_venta = :id_documento
                         AND d.deleted_at IS NULL
                       ORDER BY d.id ASC';

        $stmtDetalle = $this->db()->prepare($sqlDetalle);
        $stmtDetalle->execute(['id_documento' => $idDocumento]);
        $detalle = $stmtDetalle->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $estadoDocumento = (int) ($venta['estado'] ?? 0);
        foreach ($detalle as &$linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            $despachada = (float) ($linea['cantidad_despachada'] ?? 0);
            $pendiente = max(0, $cantidad - $despachada);
            $linea['cantidad_pendiente'] = $pendiente;
            $linea['cantidad_cancelada'] = ($estadoDocumento === 3 && $pendiente > 0.0001) ? $pendiente : 0;
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
            
            // MODIFICACIÓN: Aquí aseguramos el fallback a 'exonerado'
            $tipoImpuesto = trim((string) ($cabecera['tipo_impuesto'] ?? 'exonerado'));
            $tipoOperacion = trim((string) ($cabecera['tipo_operacion'] ?? 'VENTA'));

            // 1. RECALCULAR TOTALES POR SEGURIDAD
            $sumaLineas = 0.0;
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);
                if ($cantidad <= 0 || $precio < 0) {
                    throw new RuntimeException('Hay líneas con cantidad o precio inválido.');
                }
                $sumaLineas += ($cantidad * $precio);
            }

            // 2. LÓGICA DE IMPUESTOS EN BACKEND
            $subtotal = 0.0;
            $igvMonto = 0.0;
            $totalFinal = 0.0;

            if ($tipoOperacion === 'DONACION') {
                // BLINDAJE: Si es donación, la cabecera siempre es cero.
                $subtotal = 0.0;
                $igvMonto = 0.0;
                $totalFinal = 0.0;
                $tipoImpuesto = 'exonerado';
            } else {
                if ($tipoImpuesto === 'incluido') {
                    $totalFinal = $sumaLineas;
                    $subtotal = $totalFinal / 1.18;
                    $igvMonto = $totalFinal - $subtotal;
                } elseif ($tipoImpuesto === 'mas_igv') {
                    $subtotal = $sumaLineas;
                    $igvMonto = $subtotal * 0.18;
                    $totalFinal = $subtotal + $igvMonto;
                } else { // exonerado
                    $subtotal = $sumaLineas;
                    $igvMonto = 0.0;
                    $totalFinal = $subtotal;
                }
            }

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
                                  tipo_impuesto = :tipo_impuesto,
                                  tipo_operacion = :tipo_operacion,
                                  subtotal = :subtotal,
                                  igv_monto = :igv_monto,
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
                    'tipo_impuesto' => $tipoImpuesto,
                    'tipo_operacion' => $tipoOperacion,
                    'subtotal' => round($subtotal, 4),
                    'igv_monto' => round($igvMonto, 4),
                    'total' => round($totalFinal, 2),
                    'updated_by' => $userId,
                ]);

                $db->prepare('UPDATE ventas_documentos_detalle
                              SET deleted_at = NOW(), updated_by = :user, updated_at = NOW()
                              WHERE id_documento_venta = :id_documento AND deleted_at IS NULL')
                    ->execute(['id_documento' => $idDocumento, 'user' => $userId]);
            } else {
                $codigo = $this->generarCodigo($db);
                
                $sqlInsert = 'INSERT INTO ventas_documentos (
                                codigo, tipo_operacion, id_cliente, fecha_emision, observaciones,
                                tipo_impuesto, subtotal, igv_monto, total, estado,
                                created_by, updated_by, created_at, updated_at
                              ) VALUES (
                                :codigo, :tipo_operacion, :id_cliente, :fecha_emision, :observaciones,
                                :tipo_impuesto, :subtotal, :igv_monto, :total, 0,
                                :created_by, :updated_by, NOW(), NOW()
                              )';

                $db->prepare($sqlInsert)->execute([
                    'codigo' => $codigo,
                    'tipo_operacion' => $tipoOperacion,
                    'id_cliente' => (int) $cabecera['id_cliente'],
                    'fecha_emision' => $fechaEmision,
                    'observaciones' => $cabecera['observaciones'] ?: null,
                    'tipo_impuesto' => $tipoImpuesto,
                    'subtotal' => round($subtotal, 4),
                    'igv_monto' => round($igvMonto, 4),
                    'total' => round($totalFinal, 2),
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);

                $idDocumento = (int) $db->lastInsertId();
            }

            $sqlDet = 'INSERT INTO ventas_documentos_detalle (
                            id_documento_venta, id_item, cantidad, precio_unitario, total_linea,
                            created_by, updated_by, created_at, updated_at
                        ) VALUES (
                            :id_documento, :id_item, :cantidad, :precio_unitario, :total_linea,
                            :created_by, :updated_by, NOW(), NOW()
                        )';

            $stmtDet = $db->prepare($sqlDet);
            foreach ($detalle as $linea) {
                $cantidad = (float) ($linea['cantidad'] ?? 0);
                $precio = (float) ($linea['precio_unitario'] ?? 0);

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
                                      deleted_by = :deleted_user,
                                      updated_by = :updated_user,
                                      updated_at = NOW()
                                  WHERE id = :id
                                    AND deleted_at IS NULL');
            $stmt->execute([
                'id' => $idDocumento,
                'deleted_user' => $userId,
                'updated_user' => $userId,
            ]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('No se pudo anular el pedido.');
            }

            $db->prepare('UPDATE ventas_documentos_detalle
                          SET deleted_at = NOW(), updated_by = :user, updated_at = NOW()
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

    public function guardarDespacho(int $idDoc, array $detalle, string $obs, bool $cerrarForzado, int $userId): void
    {
        require_once BASE_PATH . '/app/models/VentasDespachoModel.php';
        $despachoModel = new VentasDespachoModel();
        
        $despachoModel->registrarDespacho($idDoc, $detalle, $cerrarForzado, $obs, $userId);
    }
}