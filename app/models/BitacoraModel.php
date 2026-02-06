<?php
declare(strict_types=1);

class BitacoraModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $sql = 'SELECT b.id, b.evento, b.descripcion, b.ip_address, b.user_agent, b.created_at,
                       COALESCE(u.usuario, "Sistema") AS usuario
                FROM bitacora_seguridad b
                LEFT JOIN usuarios u ON u.id = b.created_by
                WHERE 1=1';
        $params = [];

        if (!empty($filtros['usuario'])) {
            $sql .= ' AND u.id = :usuario';
            $params['usuario'] = (int) $filtros['usuario'];
        }

        if (!empty($filtros['evento'])) {
            $sql .= ' AND b.evento LIKE :evento';
            $params['evento'] = '%' . $filtros['evento'] . '%';
        }

        if (!empty($filtros['fecha_inicio'])) {
            $sql .= ' AND DATE(b.created_at) >= :fecha_inicio';
            $params['fecha_inicio'] = $filtros['fecha_inicio'];
        }

        if (!empty($filtros['fecha_fin'])) {
            $sql .= ' AND DATE(b.created_at) <= :fecha_fin';
            $params['fecha_fin'] = $filtros['fecha_fin'];
        }

        $sql .= ' ORDER BY b.created_at DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function usuarios_para_filtro(): array
    {
        return $this->db()->query('SELECT id, usuario FROM usuarios ORDER BY usuario')->fetchAll();
    }
}
