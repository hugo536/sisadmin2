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

        // 2. Filtro por Cuenta (Soporte para Selección Múltiple)
        if (!empty($f['id_cuenta'])) {
            $cuentas = is_array($f['id_cuenta']) ? $f['id_cuenta'] : [$f['id_cuenta']];
            $inParams = [];
            foreach ($cuentas as $i => $id) {
                $key = 'cuenta_' . $i;
                $inParams[] = ':' . $key;
                $params[$key] = (int) $id;
            }
            $where[] = 'm.id_cuenta IN (' . implode(',', $inParams) . ')';
        }

        // 3. Filtro por Método de Pago (Soporte para Selección Múltiple)
        if (!empty($f['id_metodo_pago'])) {
            $metodos = is_array($f['id_metodo_pago']) ? $f['id_metodo_pago'] : [$f['id_metodo_pago']];
            $idsMetodo = [];
            $nombresMetodo = [];

            foreach ($metodos as $valor) {
                $valor = trim((string) $valor);
                if ($valor === '') {
                    continue;
                }

                if (ctype_digit($valor)) {
                    $idsMetodo[] = (int) $valor;
                    continue;
                }

                $nombresMetodo[] = $valor;
            }

            $condicionesMetodo = [];

            if (!empty($idsMetodo)) {
                $inParams = [];
                foreach (array_values(array_unique($idsMetodo)) as $i => $id) {
                    $key = 'metodo_id_' . $i;
                    $inParams[] = ':' . $key;
                    $params[$key] = $id;
                }
                $condicionesMetodo[] = 'm.id_metodo_pago IN (' . implode(',', $inParams) . ')';
            }

            if (!empty($nombresMetodo)) {
                $inParams = [];
                foreach (array_values(array_unique($nombresMetodo)) as $i => $nombre) {
                    $key = 'metodo_nombre_' . $i;
                    $inParams[] = ':' . $key;
                    $params[$key] = $nombre;
                }
                $condicionesMetodo[] = 'mp.nombre IN (' . implode(',', $inParams) . ')';
            }

            if (!empty($condicionesMetodo)) {
                $where[] = '(' . implode(' OR ', $condicionesMetodo) . ')';
            }
        }

        // 4. Filtro por Origen (Soporte para Selección Múltiple)
        if (!empty($f['origen'])) {
            $origenes = is_array($f['origen']) ? $f['origen'] : [$f['origen']];
            $inParams = [];
            foreach ($origenes as $i => $origen) {
                $key = 'origen_' . $i;
                $inParams[] = ':' . $key;
                $params[$key] = (string) $origen;
            }
            $where[] = 'm.origen IN (' . implode(',', $inParams) . ')';
        }

        // 5. Filtros de acceso directo desde otros módulos
        if (!empty($f['id_origen'])) {
            $where[] = 'm.id_origen = :id_origen';
            $params['id_origen'] = (int) $f['id_origen'];
        }

        if (!empty($f['id_tercero'])) {
            $where[] = 'm.id_tercero = :id_tercero';
            $params['id_tercero'] = (int) $f['id_tercero'];
        }

        // 6. Filtro de Búsqueda Global (Input text)
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