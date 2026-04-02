<?php
declare(strict_types=1);

class SerieModel extends Modelo
{
    public function listarConFiltros(array $filtros): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(codigo_serie LIKE :q OR tipo_documento LIKE :q OR prefijo LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $modulo = strtoupper(trim((string) ($filtros['modulo'] ?? '')));
        if (in_array($modulo, ['VENTAS', 'COMPRAS'], true)) {
            $where[] = 'modulo = :modulo';
            $params['modulo'] = $modulo;
        }

        $estadoFiltro = (string) ($filtros['estado_filtro'] ?? 'activos');
        if ($estadoFiltro === 'activos') {
            $where[] = 'estado = 1';
        } elseif ($estadoFiltro === 'inactivos') {
            $where[] = 'estado = 0';
        }

        $sql = 'SELECT * FROM configuracion_series WHERE ' . implode(' AND ', $where) . ' ORDER BY COALESCE(updated_at, created_at) DESC, id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function existeSerie(string $modulo, string $tipoDocumento, string $codigoSerie, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM configuracion_series
                WHERE modulo = :modulo
                  AND tipo_documento = :tipo_documento
                  AND codigo_serie = :codigo_serie
                  AND deleted_at IS NULL';

        $params = [
            'modulo' => $modulo,
            'tipo_documento' => $tipoDocumento,
            'codigo_serie' => $codigoSerie,
        ];

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
            if ((int) ($data['predeterminada'] ?? 0) === 1) {
                $this->limpiarPredeterminada($db, (string) $data['modulo'], (string) $data['tipo_documento']);
            }

            $sql = 'INSERT INTO configuracion_series
                    (modulo, tipo_documento, codigo_serie, prefijo, correlativo_actual, longitud_correlativo, predeterminada, estado, observaciones, created_by, updated_by)
                    VALUES
                    (:modulo, :tipo_documento, :codigo_serie, :prefijo, :correlativo_actual, :longitud_correlativo, :predeterminada, :estado, :observaciones, :created_by, :updated_by)';

            $ok = $db->prepare($sql)->execute([
                'modulo' => $data['modulo'],
                'tipo_documento' => $data['tipo_documento'],
                'codigo_serie' => $data['codigo_serie'],
                'prefijo' => $data['prefijo'],
                'correlativo_actual' => $data['correlativo_actual'],
                'longitud_correlativo' => $data['longitud_correlativo'],
                'predeterminada' => $data['predeterminada'],
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
            if ((int) ($data['predeterminada'] ?? 0) === 1) {
                $this->limpiarPredeterminada($db, (string) $data['modulo'], (string) $data['tipo_documento'], $id);
            }

            $sql = 'UPDATE configuracion_series
                    SET modulo = :modulo,
                        tipo_documento = :tipo_documento,
                        codigo_serie = :codigo_serie,
                        prefijo = :prefijo,
                        correlativo_actual = :correlativo_actual,
                        longitud_correlativo = :longitud_correlativo,
                        predeterminada = :predeterminada,
                        estado = :estado,
                        observaciones = :observaciones,
                        updated_by = :updated_by,
                        updated_at = NOW()
                    WHERE id = :id
                      AND deleted_at IS NULL';

            $ok = $db->prepare($sql)->execute([
                'id' => $id,
                'modulo' => $data['modulo'],
                'tipo_documento' => $data['tipo_documento'],
                'codigo_serie' => $data['codigo_serie'],
                'prefijo' => $data['prefijo'],
                'correlativo_actual' => $data['correlativo_actual'],
                'longitud_correlativo' => $data['longitud_correlativo'],
                'predeterminada' => $data['predeterminada'],
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
        $stmt = $this->db()->prepare('UPDATE configuracion_series
            SET deleted_at = NOW(), deleted_by = :user, updated_by = :user, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        return $stmt->execute(['id' => $id, 'user' => $userId]);
    }

    public function restaurar(int $id, int $userId): bool
    {
        $stmt = $this->db()->prepare('UPDATE configuracion_series
            SET deleted_at = NULL, deleted_by = NULL, estado = 1, updated_by = :user, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NOT NULL');

        return $stmt->execute(['id' => $id, 'user' => $userId]);
    }

    public function resumen(): array
    {
        $db = $this->db();
        return [
            'activos' => (int) $db->query('SELECT COUNT(*) FROM configuracion_series WHERE deleted_at IS NULL AND estado = 1')->fetchColumn(),
            'inactivos' => (int) $db->query('SELECT COUNT(*) FROM configuracion_series WHERE deleted_at IS NULL AND estado = 0')->fetchColumn(),
            'predeterminadas' => (int) $db->query('SELECT COUNT(*) FROM configuracion_series WHERE deleted_at IS NULL AND predeterminada = 1')->fetchColumn(),
        ];
    }

    private function limpiarPredeterminada(PDO $db, string $modulo, string $tipoDocumento, ?int $excludeId = null): void
    {
        $sql = 'UPDATE configuracion_series
                SET predeterminada = 0, updated_at = NOW()
                WHERE modulo = :modulo AND tipo_documento = :tipo_documento AND deleted_at IS NULL';
        $params = [
            'modulo' => $modulo,
            'tipo_documento' => $tipoDocumento,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $db->prepare($sql)->execute($params);
    }
}
