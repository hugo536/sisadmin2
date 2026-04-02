<?php
declare(strict_types=1);

class DistribuidoresModel extends Modelo
{
    /** @var array<string,bool> */
    private array $columnCache = [];

    public function guardar(int $idTercero, array $data, int $userId): void
    {
        $sql = "INSERT INTO distribuidores (id_tercero, created_by, updated_by)
                VALUES (:id_tercero, :created_by, :updated_by)
                ON DUPLICATE KEY UPDATE
                    updated_by = VALUES(updated_by),
                    updated_at = NOW(),
                    deleted_at = NULL";

        $this->db()->prepare($sql)->execute([
            'id_tercero' => $idTercero,
            'created_by' => $userId,
            'updated_by' => $userId
        ]);

        $this->sincronizarZonasExclusivas($idTercero, $data['zonas_exclusivas'] ?? [], $userId);
    }

    public function eliminar(int $idTercero, int $userId): void
    {
        $this->db()->prepare('DELETE FROM distribuidores_zonas WHERE distribuidor_id = ?')->execute([$idTercero]);

        if ($this->hasColumn('distribuidores', 'deleted_by')) {
            $this->db()->prepare('UPDATE distribuidores SET deleted_at = NOW(), deleted_by = ?, updated_by = ? WHERE id_tercero = ?')
                ->execute([$userId, $userId, $idTercero]);
            return;
        }

        $this->db()->prepare('UPDATE distribuidores SET deleted_at = NOW(), updated_by = ? WHERE id_tercero = ?')
            ->execute([$userId, $idTercero]);
    }

    public function sincronizarZonasExclusivas(int $idTercero, array $zonas, int $userId): void
    {
        $this->db()->prepare('DELETE FROM distribuidores_zonas WHERE distribuidor_id = ?')->execute([$idTercero]);

        if (empty($zonas)) {
            return;
        }

        $usaAuditoria = $this->hasColumn('distribuidores_zonas', 'created_by')
            && $this->hasColumn('distribuidores_zonas', 'updated_by');

        $stmt = $this->db()->prepare(
            $usaAuditoria
                ? 'INSERT INTO distribuidores_zonas (distribuidor_id, departamento_id, provincia_id, distrito_id, created_by, updated_by)
                   VALUES (:distribuidor_id, :departamento_id, :provincia_id, :distrito_id, :created_by, :updated_by)'
                : 'INSERT INTO distribuidores_zonas (distribuidor_id, departamento_id, provincia_id, distrito_id)
                   VALUES (:distribuidor_id, :departamento_id, :provincia_id, :distrito_id)'
        );

        $insertadas = [];

        foreach ($zonas as $zona) {
            if (!preg_match('/^([^|]+)\|([^|]*)\|([^|]*)$/', (string)$zona, $parts)) {
                continue;
            }

            $departamentoId = trim($parts[1]);
            $provinciaId = trim($parts[2]);
            $distritoId = trim($parts[3]);
            if ($departamentoId === '') {
                continue;
            }

            $key = $departamentoId . '|' . $provinciaId . '|' . $distritoId;
            if (isset($insertadas[$key])) {
                continue;
            }
            $insertadas[$key] = true;

            $params = [
                'distribuidor_id' => $idTercero,
                'departamento_id' => $departamentoId,
                'provincia_id'    => $provinciaId !== '' ? $provinciaId : null,
                'distrito_id'     => $distritoId !== '' ? $distritoId : null,
            ];

            if ($usaAuditoria) {
                $params['created_by'] = $userId;
                $params['updated_by'] = $userId;
            }

            $stmt->execute($params);
        }
    }

    public function obtenerZonasPorTercero(int $idTercero): array
    {
        return $this->obtenerZonasPorTerceros([$idTercero])[$idTercero] ?? [];
    }

    public function obtenerConflictosZonas(array $zonas, int $excludeDistribuidorId = 0): array
    {
        $sql = 'SELECT dz.distribuidor_id,
                       dz.departamento_id,
                       dz.provincia_id,
                       dz.distrito_id,
                       t.nombre_completo AS distribuidor_nombre,
                       d.nombre AS departamento_nombre,
                       p.nombre AS provincia_nombre,
                       dt.nombre AS distrito_nombre
                FROM distribuidores_zonas dz
                INNER JOIN terceros t ON t.id = dz.distribuidor_id
                LEFT JOIN departamentos d ON d.id = dz.departamento_id
                LEFT JOIN provincias p ON p.id = dz.provincia_id
                LEFT JOIN distritos dt ON dt.id = dz.distrito_id
                WHERE dz.departamento_id = :departamento_id
                  AND dz.provincia_id <=> :provincia_id
                  AND dz.distrito_id <=> :distrito_id';

        if ($excludeDistribuidorId > 0) {
            $sql .= ' AND dz.distribuidor_id <> :exclude_distribuidor_id';
        }

        $stmt = $this->db()->prepare($sql);
        $conflictos = [];

        foreach ($zonas as $zona) {
            if (!preg_match('/^([^|]+)\|([^|]*)\|([^|]*)$/', (string)$zona, $parts)) {
                continue;
            }

            $departamentoId = trim($parts[1]);
            $provinciaId = trim($parts[2]);
            $distritoId = trim($parts[3]);
            if ($departamentoId === '') {
                continue;
            }

            $params = [
                'departamento_id' => $departamentoId,
                'provincia_id' => $provinciaId !== '' ? $provinciaId : null,
                'distrito_id' => $distritoId !== '' ? $distritoId : null,
            ];
            if ($excludeDistribuidorId > 0) {
                $params['exclude_distribuidor_id'] = $excludeDistribuidorId;
            }

            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $key = ($row['departamento_id'] ?? '') . '|' . ($row['provincia_id'] ?? '') . '|' . ($row['distrito_id'] ?? '');
                $conflictos[$key] = [
                    'valor' => $key,
                    'label' => trim(($row['departamento_nombre'] ?? '') . ' - ' . ($row['provincia_nombre'] ?? '') . ' - ' . ($row['distrito_nombre'] ?? '')),
                    'tercero_id' => (int)($row['distribuidor_id'] ?? 0),
                    'distribuidor_id' => (int)($row['distribuidor_id'] ?? 0),
                    'distribuidor_nombre' => $row['distribuidor_nombre'] ?? ''
                ];
            }
        }

        return array_values($conflictos);
    }

    public function obtenerZonasPorTerceros(array $terceroIds): array
    {
        if (empty($terceroIds)) {
            return [];
        }

        $in = implode(',', array_fill(0, count($terceroIds), '?'));
        $sql = "SELECT dz.distribuidor_id,
                       dz.departamento_id,
                       dz.provincia_id,
                       dz.distrito_id,
                       d.nombre AS departamento_nombre,
                       p.nombre AS provincia_nombre,
                       dt.nombre AS distrito_nombre
                FROM distribuidores_zonas dz
                LEFT JOIN departamentos d ON d.id = dz.departamento_id
                LEFT JOIN provincias p ON p.id = dz.provincia_id
                LEFT JOIN distritos dt ON dt.id = dz.distrito_id
                WHERE dz.distribuidor_id IN ($in)
                ORDER BY dz.distribuidor_id, d.nombre, p.nombre, dt.nombre";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($terceroIds);

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = (int)$row['distribuidor_id'];
            $grouped[$id][] = [
                'departamento_id' => (string)$row['departamento_id'],
                'provincia_id' => (string)($row['provincia_id'] ?? ''),
                'distrito_id' => (string)($row['distrito_id'] ?? ''),
                'departamento_nombre' => $row['departamento_nombre'] ?? '',
                'provincia_nombre' => $row['provincia_nombre'] ?? '',
                'distrito_nombre' => $row['distrito_nombre'] ?? '',
                'label' => trim(($row['departamento_nombre'] ?? '') . ' - ' . ($row['provincia_nombre'] ?? '') . ' - ' . ($row['distrito_nombre'] ?? '')),
                'valor' => $row['departamento_id'] . '|' . ($row['provincia_id'] ?? '') . '|' . ($row['distrito_id'] ?? '')
            ];
        }

        return $grouped;
    }

    public function listar(): array
    {
        $sql = "SELECT t.id, t.tipo_documento, t.numero_documento, t.nombre_completo,
                       t.telefono, t.email
                FROM distribuidores d
                INNER JOIN terceros t ON t.id = d.id_tercero
                WHERE t.deleted_at IS NULL AND d.deleted_at IS NULL
                ORDER BY COALESCE(t.updated_at, t.created_at) DESC, t.id DESC";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $zonas = $this->obtenerZonasPorTerceros(array_map(static fn($row) => (int)$row['id'], $rows));

        foreach ($rows as &$row) {
            $id = (int)$row['id'];
            $row['zonas_exclusivas'] = $zonas[$id] ?? [];
            $row['zonas_exclusivas_resumen'] = implode(', ', array_filter(array_column($row['zonas_exclusivas'], 'label')));
        }

        return $rows;
    }

    private function hasColumn(string $tableName, string $columnName): bool
    {
        $cacheKey = $tableName . '.' . $columnName;
        if (array_key_exists($cacheKey, $this->columnCache)) {
            return $this->columnCache[$cacheKey];
        }

        $stmt = $this->db()->prepare(
            'SELECT 1
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            'table_name' => $tableName,
            'column_name' => $columnName,
        ]);

        $this->columnCache[$cacheKey] = (bool) $stmt->fetchColumn();
        return $this->columnCache[$cacheKey];
    }
}
