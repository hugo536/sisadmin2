<?php
declare(strict_types=1);

class RubroModel extends Modelo
{
    public function listarRubrosActivos(): array
    {
        $sql = 'SELECT id, nombre
                FROM item_rubros
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarRubros(): array
    {
        $sql = 'SELECT id, nombre, descripcion, estado
                FROM item_rubros
                WHERE deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rubroExisteActivo(int $idRubro): bool
    {
        $sql = 'SELECT 1
                FROM item_rubros
                WHERE id = :id
                  AND estado = 1
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idRubro]);

        return (bool) $stmt->fetchColumn();
    }

    public function crearRubro(array $data, int $userId): int
    {
        $sql = 'INSERT INTO item_rubros (nombre, descripcion, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:nombre, :descripcion, :estado, :created_by, :updated_by, NOW(), NOW())';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')) !== '' ? trim((string) $data['descripcion']) : null,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarRubro(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE item_rubros
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')) !== '' ? trim((string) $data['descripcion']) : null,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId,
        ]);
    }

    public function eliminarRubro(int $id, int $userId): bool
    {
        $sql = 'UPDATE item_rubros
                SET estado = 0,
                    deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'deleted_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
