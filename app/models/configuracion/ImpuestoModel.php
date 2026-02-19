<?php
declare(strict_types=1);

class ImpuestoModel extends Modelo
{
    public function listarConFiltros(array $filtros): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(codigo LIKE :q OR nombre LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $tipo = strtoupper(trim((string) ($filtros['tipo'] ?? '')));
        if (in_array($tipo, ['VENTA', 'COMPRA', 'AMBOS'], true)) {
            $where[] = 'tipo = :tipo';
            $params['tipo'] = $tipo;
        }

        $estadoFiltro = (string) ($filtros['estado_filtro'] ?? 'activos');
        if ($estadoFiltro === 'activos') {
            $where[] = 'estado = 1';
        } elseif ($estadoFiltro === 'inactivos') {
            $where[] = 'estado = 0';
        }

        $sql = 'SELECT * FROM configuracion_impuestos WHERE ' . implode(' AND ', $where) . ' ORDER BY es_default DESC, nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existeCodigo(string $codigo, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM configuracion_impuestos WHERE codigo = :codigo AND deleted_at IS NULL';
        $params = ['codigo' => $codigo];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function crear(array $data): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            if ((int) ($data['es_default'] ?? 0) === 1) {
                $this->limpiarDefaultPorTipo($db, (string) $data['tipo']);
            }

            $sql = 'INSERT INTO configuracion_impuestos
                    (codigo, nombre, porcentaje, tipo, es_default, estado, observaciones, created_by, updated_by)
                    VALUES
                    (:codigo, :nombre, :porcentaje, :tipo, :es_default, :estado, :observaciones, :created_by, :updated_by)';

            $ok = $db->prepare($sql)->execute([
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'porcentaje' => $data['porcentaje'],
                'tipo' => $data['tipo'],
                'es_default' => $data['es_default'],
                'estado' => $data['estado'],
                'observaciones' => $data['observaciones'],
                'created_by' => $data['user_id'],
                'updated_by' => $data['user_id'],
            ]);

            $db->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function actualizar(int $id, array $data): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            if ((int) ($data['es_default'] ?? 0) === 1) {
                $this->limpiarDefaultPorTipo($db, (string) $data['tipo'], $id);
            }

            $sql = 'UPDATE configuracion_impuestos
                    SET codigo = :codigo,
                        nombre = :nombre,
                        porcentaje = :porcentaje,
                        tipo = :tipo,
                        es_default = :es_default,
                        estado = :estado,
                        observaciones = :observaciones,
                        updated_by = :updated_by,
                        updated_at = NOW()
                    WHERE id = :id
                      AND deleted_at IS NULL';

            $ok = $db->prepare($sql)->execute([
                'id' => $id,
                'codigo' => $data['codigo'],
                'nombre' => $data['nombre'],
                'porcentaje' => $data['porcentaje'],
                'tipo' => $data['tipo'],
                'es_default' => $data['es_default'],
                'estado' => $data['estado'],
                'observaciones' => $data['observaciones'],
                'updated_by' => $data['user_id'],
            ]);

            $db->commit();
            return $ok;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function eliminarLogico(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE configuracion_impuestos
            SET deleted_at = NOW(), deleted_by = :user, updated_by = :user, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute(['id' => $id, 'user' => $userId]);
    }

    public function restaurar(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE configuracion_impuestos
            SET deleted_at = NULL, deleted_by = NULL, estado = 1, updated_by = :user, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NOT NULL');

        return $stmt->execute(['id' => $id, 'user' => $userId]);
    }

    public function resumen(): array
    {
        $db = $this->db();
        return [
            'activos' => (int) $db->query('SELECT COUNT(*) FROM configuracion_impuestos WHERE deleted_at IS NULL AND estado = 1')->fetchColumn(),
            'inactivos' => (int) $db->query('SELECT COUNT(*) FROM configuracion_impuestos WHERE deleted_at IS NULL AND estado = 0')->fetchColumn(),
            'default' => (int) $db->query('SELECT COUNT(*) FROM configuracion_impuestos WHERE deleted_at IS NULL AND es_default = 1')->fetchColumn(),
        ];
    }

    private function limpiarDefaultPorTipo(PDO $db, string $tipo, ?int $excludeId = null): void
    {
        $sql = 'UPDATE configuracion_impuestos SET es_default = 0, updated_at = NOW() WHERE tipo = :tipo AND deleted_at IS NULL';
        $params = ['tipo' => $tipo];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $db->prepare($sql)->execute($params);
    }
}
