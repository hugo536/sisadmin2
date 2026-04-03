<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/inventario/InventarioModel.php';

class InventarioKardexModel extends InventarioModel
{
    /**
     * Obtiene los movimientos del kardex priorizando la fecha del documento
     * sobre la fecha de registro en el sistema.
     */
    public function obtenerKardex(array $filtros = []): array
    {
        $sql = 'SELECT m.id,
                       m.created_at,
                       m.fecha_documento, 
                       m.tipo_movimiento,
                       m.cantidad,
                       m.referencia,
                       i.sku,
                       i.nombre AS item_nombre,
                       ao.nombre AS almacen_origen,
                       ad.nombre AS almacen_destino,
                       u.nombre_completo AS usuario
                FROM inventario_movimientos m
                INNER JOIN items i ON i.id = m.id_item
                LEFT JOIN almacenes ao ON ao.id = m.id_almacen_origen
                LEFT JOIN almacenes ad ON ad.id = m.id_almacen_destino
                LEFT JOIN usuarios u ON u.id = m.created_by
                WHERE 1=1';

        $params = [];

        if (!empty($filtros['id_item'])) {
            $sql .= ' AND m.id_item = :id_item';
            $params['id_item'] = (int) $filtros['id_item'];
        }

        if (!empty($filtros['fecha_desde'])) {
            $sql .= ' AND DATE(COALESCE(m.fecha_documento, m.created_at)) >= :fecha_desde';
            $params['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sql .= ' AND DATE(COALESCE(m.fecha_documento, m.created_at)) <= :fecha_hasta';
            $params['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        if (!empty($filtros['lote'])) {
            $sql .= ' AND m.referencia LIKE :lote';
            $params['lote'] = '%Lote: ' . (string) $filtros['lote'] . '%';
        }

        // Ordenamos para que las fechas más recientes salgan primero
        $sql .= ' ORDER BY COALESCE(m.fecha_documento, m.created_at) DESC, m.created_at DESC LIMIT 1000';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}