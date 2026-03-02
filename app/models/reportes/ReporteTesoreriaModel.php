<?php
declare(strict_types=1);

class ReporteTesoreriaModel extends Modelo
{
    public function contarCxcVencida(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM tesoreria_cxc WHERE deleted_at IS NULL AND saldo > 0 AND fecha_vencimiento < CURDATE()")->fetchColumn();
    }

    public function contarCxpVencida(): int
    {
        return (int) $this->db()->query("SELECT COUNT(*) FROM tesoreria_cxp WHERE deleted_at IS NULL AND saldo > 0 AND fecha_vencimiento < CURDATE()")->fetchColumn();
    }

    public function agingCxc(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $count = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_cxc c WHERE c.deleted_at IS NULL AND c.fecha_emision BETWEEN :fd AND :fh');
        $count->execute($params);

        $sql = "SELECT t.nombre_completo AS cliente, c.saldo, c.fecha_vencimiento,
                       GREATEST(DATEDIFF(CURDATE(), c.fecha_vencimiento), 0) AS dias_atraso,
                       CASE
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 0 AND 7 THEN '0-7'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 8 AND 30 THEN '8-30'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 31 AND 60 THEN '31-60'
                         ELSE '61+'
                       END AS bucket
                FROM tesoreria_cxc c
                INNER JOIN terceros t ON t.id = c.id_cliente
                WHERE c.deleted_at IS NULL
                  AND c.fecha_emision BETWEEN :fd AND :fh
                  AND c.saldo > 0
                ORDER BY dias_atraso DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':fd', $params['fd']);
        $stmt->bindValue(':fh', $params['fh']);
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function agingCxp(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $count = $this->db()->prepare('SELECT COUNT(*) FROM tesoreria_cxp c WHERE c.deleted_at IS NULL AND c.fecha_emision BETWEEN :fd AND :fh');
        $count->execute($params);

        $sql = "SELECT t.nombre_completo AS proveedor, c.saldo, c.fecha_vencimiento,
                       GREATEST(DATEDIFF(CURDATE(), c.fecha_vencimiento), 0) AS dias_atraso,
                       CASE
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 0 AND 7 THEN '0-7'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 8 AND 30 THEN '8-30'
                         WHEN DATEDIFF(CURDATE(), c.fecha_vencimiento) BETWEEN 31 AND 60 THEN '31-60'
                         ELSE '61+'
                       END AS bucket
                FROM tesoreria_cxp c
                INNER JOIN terceros t ON t.id = c.id_proveedor
                WHERE c.deleted_at IS NULL
                  AND c.fecha_emision BETWEEN :fd AND :fh
                  AND c.saldo > 0
                ORDER BY dias_atraso DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':fd', $params['fd']);
        $stmt->bindValue(':fh', $params['fh']);
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }

    public function flujoPorCuenta(array $f, int $pagina, int $tamano): array
    {
        $offset = ($pagina - 1) * $tamano;
        $params = ['fd' => $f['fecha_desde'], 'fh' => $f['fecha_hasta']];
        $whereCuenta = '';
        if (!empty($f['id_cuenta'])) {
            $whereCuenta = ' AND m.id_cuenta = :id_cuenta';
            $params['id_cuenta'] = (int) $f['id_cuenta'];
        }

        $count = $this->db()->prepare('SELECT COUNT(DISTINCT m.id_cuenta) FROM tesoreria_movimientos m WHERE m.deleted_at IS NULL AND m.fecha BETWEEN :fd AND :fh' . $whereCuenta);
        $count->execute($params);

        $sql = "SELECT c.nombre AS cuenta,
                       ROUND(SUM(CASE WHEN m.tipo='COBRO' THEN m.monto ELSE 0 END),2) AS total_ingresos,
                       ROUND(SUM(CASE WHEN m.tipo='PAGO' THEN m.monto ELSE 0 END),2) AS total_egresos,
                       ROUND(SUM(CASE WHEN m.tipo='COBRO' THEN m.monto ELSE -m.monto END),2) AS saldo_neto
                FROM tesoreria_movimientos m
                INNER JOIN tesoreria_cuentas c ON c.id = m.id_cuenta
                WHERE m.deleted_at IS NULL
                  AND m.fecha BETWEEN :fd AND :fh {$whereCuenta}
                GROUP BY m.id_cuenta, c.nombre
                ORDER BY saldo_neto DESC
                LIMIT :limite OFFSET :offset";
        $stmt = $this->db()->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue(':' . $k, $v, is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR); }
        $stmt->bindValue(':limite', $tamano, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return ['rows' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => (int) $count->fetchColumn()];
    }
}
