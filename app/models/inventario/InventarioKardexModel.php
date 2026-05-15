<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/models/inventario/InventarioModel.php';

class InventarioKardexModel extends InventarioModel
{
    /**
     * Obtiene los movimientos del kardex priorizando la fecha del documento
     * sobre la fecha de registro en el sistema.
     * Incluye cálculo ultra-rápido de Saldo Inicial por SQL.
     */
    public function obtenerKardex(array $filtros = []): array
    {
        // 1. Construir filtros de ítems (Se usarán para ambas consultas)
        $whereItems = [];
        $params = [];

        if (!empty($filtros['id_item'])) {
            if (is_array($filtros['id_item'])) {
                $inPlaceholders = [];
                foreach ($filtros['id_item'] as $index => $id) {
                    $paramName = 'item_' . $index;
                    $inPlaceholders[] = ':' . $paramName;
                    $params[$paramName] = (int) $id;
                }
                $whereItems[] = 'm.id_item IN (' . implode(', ', $inPlaceholders) . ')';
            } else {
                $whereItems[] = 'm.id_item = :id_item';
                $params['id_item'] = (int) $filtros['id_item'];
            }
        }

        $sqlFiltroItems = !empty($whereItems) ? ' AND ' . implode(' AND ', $whereItems) : '';

        // 2. CONSULTA PRINCIPAL (Movimientos en el rango de fechas)
        $sqlMain = 'SELECT m.id,
                       m.created_at,
                       m.fecha_documento, 
                       m.tipo_movimiento,
                       m.cantidad,
                       m.referencia,
                       i.sku,
                       i.nombre AS item_nombre,
                       ao.nombre AS almacen_origen,
                       ad.nombre AS almacen_destino,
                       u.nombre_completo AS usuario,
                       t.nombre_completo AS tercero_nombre,
                       CASE
                           WHEN d.id_tercero IS NULL THEN \'CLIENTE\'
                           ELSE \'DISTRIBUIDOR\'
                       END AS tercero_tipo
                FROM inventario_movimientos m
                INNER JOIN items i ON i.id = m.id_item
                LEFT JOIN almacenes ao ON ao.id = m.id_almacen_origen
                LEFT JOIN almacenes ad ON ad.id = m.id_almacen_destino
                LEFT JOIN usuarios u ON u.id = m.created_by
                LEFT JOIN ventas_despachos vd
                       ON m.tipo_movimiento = \'VEN\'
                      AND m.referencia LIKE CONCAT(\'Despacho \', vd.codigo, \'%\')
                LEFT JOIN ventas_documentos v ON v.id = vd.id_documento_venta
                LEFT JOIN terceros t ON t.id = v.id_cliente
                LEFT JOIN distribuidores d ON d.id_tercero = t.id AND d.deleted_at IS NULL
                WHERE m.deleted_at IS NULL' . $sqlFiltroItems;

        $mainParams = $params;
        
        if (!empty($filtros['fecha_desde'])) {
            $sqlMain .= ' AND DATE(COALESCE(m.fecha_documento, m.created_at)) >= :fecha_desde';
            $mainParams['fecha_desde'] = (string) $filtros['fecha_desde'];
        }

        if (!empty($filtros['fecha_hasta'])) {
            $sqlMain .= ' AND DATE(COALESCE(m.fecha_documento, m.created_at)) <= :fecha_hasta';
            $mainParams['fecha_hasta'] = (string) $filtros['fecha_hasta'];
        }

        $sqlMain .= ' ORDER BY COALESCE(m.fecha_documento, m.created_at) DESC, m.created_at DESC LIMIT 1000';

        $stmt = $this->db()->prepare($sqlMain);
        $stmt->execute($mainParams);
        $movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // ====================================================================
        // 3. LA MAGIA: Calcular Saldo Inicial (La Foto al Pasado)
        // Sumamos entradas y restamos salidas ANTES de la fecha filtrada
        // ====================================================================
        if (!empty($filtros['fecha_desde'])) {
            $sqlSaldo = "SELECT SUM(
                            CASE 
                                WHEN m.tipo_movimiento IN ('INI', 'AJ+', 'COM', 'PROD') THEN m.cantidad
                                WHEN m.tipo_movimiento IN ('AJ-', 'CON', 'VEN') THEN -m.cantidad
                                ELSE 0 
                            END
                         ) AS saldo_inicial
                         FROM inventario_movimientos m
                         WHERE m.deleted_at IS NULL
                         AND DATE(COALESCE(m.fecha_documento, m.created_at)) < :fecha_corte" . $sqlFiltroItems;

            $saldoParams = $params;
            $saldoParams['fecha_corte'] = (string) $filtros['fecha_desde'];

            $stmtSaldo = $this->db()->prepare($sqlSaldo);
            $stmtSaldo->execute($saldoParams);
            $saldoInicial = (float) $stmtSaldo->fetchColumn();

            // Si hay saldo histórico, lo inyectamos al final de la lista 
            // (Para que al invertirlo en PHP, aparezca como el movimiento más antiguo)
            if ($saldoInicial != 0) {
                $fechaAyer = date('Y-m-d', strtotime('-1 day', strtotime($filtros['fecha_desde'])));
                $movimientos[] = [
                    'id' => 0,
                    'created_at' => $filtros['fecha_desde'] . ' 00:00:00',
                    'fecha_documento' => $filtros['fecha_desde'],
                    'tipo_movimiento' => $saldoInicial >= 0 ? 'INI' : 'AJ-', 
                    'cantidad' => abs($saldoInicial),
                    'referencia' => 'SALDO HISTÓRICO ACUMULADO HASTA EL ' . date('d/m/Y', strtotime($fechaAyer)),
                    'sku' => '-',
                    'item_nombre' => '📌 SALDO INICIAL',
                    'almacen_origen' => '-',
                    'almacen_destino' => '-',
                    'usuario' => 'Sistema ERP',
                    'tercero_nombre' => '',
                    'tercero_tipo' => 'CLIENTE',
                ];
            }
        }

        return $movimientos;
    }
}