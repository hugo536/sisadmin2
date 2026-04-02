<?php
declare(strict_types=1);

class UnidadConversionModel extends Modelo
{
    public function listarUnidadesConversion(): array
    {
        $sql = 'SELECT i.id, i.sku, i.nombre, i.unidad_base, i.requiere_factor_conversion,
                       COUNT(u.id) AS total_unidades
                FROM items i
                LEFT JOIN items_unidades u ON u.id_item = i.id AND u.deleted_at IS NULL AND u.estado = 1
                WHERE i.deleted_at IS NULL
                  AND i.requiere_factor_conversion = 1
                GROUP BY i.id, i.sku, i.nombre, i.unidad_base, i.requiere_factor_conversion
                ORDER BY (COUNT(u.id) = 0) DESC, i.nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDetalleUnidadesConversion(int $idItem): array
    {
        if ($idItem <= 0) {
            return [];
        }

        $sql = 'SELECT id, id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado
                FROM items_unidades
                WHERE id_item = :id_item
                  AND deleted_at IS NULL
                ORDER BY nombre ASC, id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearUnidadConversion(array $data, int $userId): int
    {
        $sql = 'INSERT INTO items_unidades (id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:id_item, :nombre, :codigo_unidad, :factor_conversion, :peso_kg, :estado, :created_by, :updated_by, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => (int) $data['id_item'],
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
            'created_by' => $userId,
            'updated_by' => $userId
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarUnidadConversion(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE items_unidades
                SET nombre = :nombre,
                    codigo_unidad = :codigo_unidad,
                    factor_conversion = :factor_conversion,
                    peso_kg = :peso_kg,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND id_item = :id_item
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
            'updated_by' => $userId,
            'id' => $id,
            'id_item' => (int) $data['id_item']
        ]);
    }

    public function eliminarUnidadConversion(int $id, int $idItem, int $userId): bool
    {
        $sql = 'UPDATE items_unidades
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    estado = 0
                WHERE id = :id
                  AND id_item = :id_item
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'deleted_by' => $userId,
            'updated_by' => $userId,
            'id' => $id,
            'id_item' => $idItem
        ]);
    }
}
