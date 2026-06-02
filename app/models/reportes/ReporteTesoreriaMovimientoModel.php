<?php
declare(strict_types=1);

class ReporteTesoreriaMovimientoModel extends Modelo
{
    /**
     * Obtiene la lista de cuentas bancarias / cajas activas para el filtro
     */
    public function listarCuentas(): array
    {
        $sql = 'SELECT id, nombre 
                FROM tesoreria_cuentas 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene la lista de métodos de pago desde la tabla correcta
     */
    public function listarMetodosPago(): array
    {
        $sql = 'SELECT id, nombre 
                FROM tesoreria_metodos_pago 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                ORDER BY nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Consulta principal para obtener los movimientos con filtros y paginación
     */
    public function listarMovimientos(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        
        $where = ['m.deleted_at IS NULL'];
        $params = [];

        // 1. Filtro por Rango de Fechas
        if (!empty($f['fecha_desde']) && !empty($f['fecha_hasta'])) {
            $where[] = 'DATE(m.fecha) BETWEEN :fecha_desde AND :fecha_hasta';
            $params['fecha_desde'] = $f['fecha_desde'];
            $params['fecha_hasta'] = $f['fecha_hasta'];
        }

        // 2. Filtro por Cuenta (Caja/Banco)
        if (!empty($f['id_cuenta'])) {
            $where[] = 'm.id_cuenta = :id_cuenta';
            $params['id_cuenta'] = (int) $f['id_cuenta'];
        }

        // 3. Filtro por Método de Pago
        if (!empty($f['id_metodo_pago'])) {
            $where[] = 'm.id_metodo_pago = :id_metodo_pago'; 
            $params['id_metodo_pago'] = (int) $f['id_metodo_pago'];
        }

        // 4. Filtro por Origen (CXC, CXP, TRANSFERENCIA)
        if (!empty($f['origen'])) {
            $where[] = 'm.origen = :origen';
            $params['origen'] = $f['origen'];
        }

        // 5. Filtro de Búsqueda Global (Input text)
        if (!empty($f['busqueda'])) {
            $where[] = '(
                t.nombre_completo LIKE :q OR 
                m.origen LIKE :q OR 
                c.nombre LIKE :q OR 
                mp.nombre LIKE :q OR 
                m.estado LIKE :q OR 
                m.observaciones LIKE :q
            )';
            $params['q'] = '%' . $f['busqueda'] . '%';
        }

        $whereSql = implode(' AND ', $where);

        // --- CONSULTA PARA OBTENER EL TOTAL (Paginación) ---
        $countSql = "SELECT COUNT(*) 
                     FROM tesoreria_movimientos m
                     LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                     LEFT JOIN tesoreria_metodos_pago mp ON mp.id = m.id_metodo_pago
                     LEFT JOIN terceros t ON t.id = m.id_tercero
                     WHERE {$whereSql}";
                     
        $stmtCount = $this->db()->prepare($countSql);
        foreach ($params as $k => $v) {
            $stmtCount->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmtCount->execute();
        $totalRegistros = (int) $stmtCount->fetchColumn();

        // --- CONSULTA PRINCIPAL PARA OBTENER LAS FILAS ---
        $sql = "SELECT 
                    m.id,
                    m.fecha,
                    m.tipo,
                    m.origen,
                    m.id_origen,
                    m.id_tercero,
                    t.nombre_completo AS tercero_nombre,
                    m.id_cuenta,
                    c.nombre AS cuenta_nombre,
                    mp.nombre AS metodo_pago,
                    m.monto,
                    m.estado,
                    m.observaciones,
                    '' AS observacion_origen
                FROM tesoreria_movimientos m
                LEFT JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                LEFT JOIN tesoreria_metodos_pago mp ON mp.id = m.id_metodo_pago
                LEFT JOIN terceros t ON t.id = m.id_tercero
                WHERE {$whereSql}
                ORDER BY m.fecha DESC, m.id DESC
                LIMIT :limite OFFSET :offset";

        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'rows' => $rows,
            'total' => $totalRegistros
        ];
    }
}