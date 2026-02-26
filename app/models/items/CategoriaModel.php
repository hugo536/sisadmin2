<?php
declare(strict_types=1);

class CategoriaModel extends Modelo
{
    public function listarCategoriasActivas(): array
    {
        $sql = 'SELECT id, nombre
                FROM categorias
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarCategorias(): array
    {
        $sql = 'SELECT id, nombre, descripcion, estado
                FROM categorias
                WHERE deleted_at IS NULL
                ORDER BY nombre ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function categoriaExisteActiva(int $idCategoria): bool
    {
        $sql = 'SELECT 1
                FROM categorias
                WHERE id = :id
                  AND estado = 1
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $idCategoria]);

        return (bool) $stmt->fetchColumn();
    }

    public function crearCategoria(array $data, int $userId): int
    {
        $sql = 'INSERT INTO categorias (nombre, descripcion, estado, created_by, updated_by, fecha_creacion)
                VALUES (:nombre, :descripcion, :estado, :created_by, :updated_by, NOW())';
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

    public function actualizarCategoria(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE categorias
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

    public function eliminarCategoria(int $id, int $userId): bool
    {
        $sql = 'UPDATE categorias
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
