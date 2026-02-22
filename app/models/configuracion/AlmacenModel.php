<?php
declare(strict_types=1);

class AlmacenModel extends Modelo
{
    public function listarActivos(): array
    {
        $sql = 'SELECT * FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY COALESCE(updated_at, created_at) DESC, id DESC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarConFiltros(array $filtros): array
    {
        $where = ['1=1'];
        $params = [];

        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            $where[] = '(codigo LIKE :q OR nombre LIKE :q)';
            $params['q'] = '%' . $busqueda . '%';
        }

        $estadoFiltro = (string) ($filtros['estado_filtro'] ?? 'activos');
        if ($estadoFiltro === 'eliminados') {
            $where[] = 'deleted_at IS NOT NULL';
        } elseif ($estadoFiltro === 'todos') {
            // sin filtro adicional
        } else {
            $where[] = 'deleted_at IS NULL';
            if ($estadoFiltro === 'inactivos') {
                $where[] = 'estado = 0';
            } else {
                $where[] = 'estado = 1';
            }
        }

        $fechaDesde = trim((string) ($filtros['fecha_desde'] ?? ''));
        if ($fechaDesde !== '') {
            $where[] = 'DATE(created_at) >= :fecha_desde';
            $params['fecha_desde'] = $fechaDesde;
        }

        $fechaHasta = trim((string) ($filtros['fecha_hasta'] ?? ''));
        if ($fechaHasta !== '') {
            $where[] = 'DATE(created_at) <= :fecha_hasta';
            $params['fecha_hasta'] = $fechaHasta;
        }

        $orden = (string) ($filtros['orden'] ?? 'fecha_desc');
        $orderBy = match ($orden) {
            'codigo_asc' => 'codigo ASC',
            'codigo_desc' => 'codigo DESC',
            'fecha_asc' => 'created_at ASC',
            'fecha_desc' => 'created_at DESC',
            'nombre_desc' => 'nombre DESC',
            default => 'nombre ASC',
        };

        $sql = 'SELECT * FROM almacenes WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $orderBy;
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM almacenes WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return $data ?: null;
    }

    public function existeCodigo(string $codigo, ?int $ignorarId = null): bool
    {
        $sql = 'SELECT id FROM almacenes WHERE codigo = :codigo';
        $params = ['codigo' => $codigo];

        if ($ignorarId !== null && $ignorarId > 0) {
            $sql .= ' AND id <> :id';
            $params['id'] = $ignorarId;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function crear(array $data): bool
    {
        $sql = 'INSERT INTO almacenes (codigo, nombre, descripcion, estado, created_by, updated_by)
                VALUES (:codigo, :nombre, :descripcion, :estado, :created_by, :updated_by)';

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'estado' => $data['estado'],
            'created_by' => $data['user_id'],
            'updated_by' => $data['user_id'],
        ]);
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = 'UPDATE almacenes
                SET codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    estado = :estado,
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id';

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'id' => $id,
            'codigo' => $data['codigo'],
            'nombre' => $data['nombre'],
            'descripcion' => $data['descripcion'],
            'estado' => $data['estado'],
            'updated_by' => $data['user_id'],
        ]);
    }

    public function cambiarEstado(int $id, int $estado, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE almacenes SET estado = :estado, updated_by = :updated_by, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        return $stmt->execute([
            'id' => $id,
            'estado' => $estado,
            'updated_by' => $userId,
        ]);
    }

    public function eliminarLogico(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE almacenes
            SET deleted_at = NOW(), deleted_by = :deleted_by, estado = 0, updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }

    public function restaurar(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE almacenes
            SET deleted_at = NULL, deleted_by = NULL, updated_by = :updated_by, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NOT NULL');

        return $stmt->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    public function estaEnUso(int $id): bool
    {
        $consultas = [
            'SELECT 1 FROM inventario_stock WHERE id_almacen = :id AND stock_actual > 0 LIMIT 1',
            'SELECT 1 FROM inventario_lotes WHERE id_almacen = :id AND stock_lote > 0 LIMIT 1',
            'SELECT 1 FROM inventario_movimientos WHERE id_almacen_origen = :id OR id_almacen_destino = :id LIMIT 1',
            'SELECT 1 FROM produccion_ordenes WHERE id_almacen_origen = :id OR id_almacen_destino = :id LIMIT 1',
        ];

        foreach ($consultas as $sql) {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute(['id' => $id]);
            if ($stmt->fetchColumn()) {
                return true;
            }
        }

        return false;
    }

    public function resumen(): array
    {
        $totalActivos = (int) $this->db()->query('SELECT COUNT(*) FROM almacenes WHERE deleted_at IS NULL AND estado = 1')->fetchColumn();
        $totalInactivos = (int) $this->db()->query('SELECT COUNT(*) FROM almacenes WHERE deleted_at IS NULL AND estado = 0')->fetchColumn();

        $ultimos = $this->db()->query('SELECT codigo, nombre, created_at FROM almacenes WHERE deleted_at IS NULL ORDER BY created_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $sinActividad = $this->db()->query(
            'SELECT a.id, a.codigo, a.nombre, MAX(m.created_at) AS ultima_actividad
             FROM almacenes a
             LEFT JOIN inventario_movimientos m
               ON m.id_almacen_origen = a.id OR m.id_almacen_destino = a.id
             WHERE a.deleted_at IS NULL
             GROUP BY a.id, a.codigo, a.nombre
             HAVING ultima_actividad IS NULL OR ultima_actividad < DATE_SUB(NOW(), INTERVAL 30 DAY)
             ORDER BY ultima_actividad ASC
             LIMIT 10'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'activos' => $totalActivos,
            'inactivos' => $totalInactivos,
            'ultimos' => $ultimos,
            'sin_actividad' => $sinActividad,
        ];
    }
}
