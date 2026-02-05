<?php
declare(strict_types=1);

class RolModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, nombre, estado FROM roles WHERE deleted_at IS NULL ORDER BY id DESC';
        return $this->db()->query($sql)->fetchAll();
    }

    public function crear(string $nombre): bool
    {
        return $this->db()->prepare('INSERT INTO roles (nombre, estado, deleted_at) VALUES (:nombre, 1, NULL)')
            ->execute(['nombre' => $nombre]);
    }

    public function actualizar(int $id, string $nombre, int $estado): bool
    {
        $sql = 'UPDATE roles SET nombre = :nombre, estado = :estado WHERE id = :id AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute(['id' => $id, 'nombre' => $nombre, 'estado' => $estado]);
    }

    public function obtener_por_id(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, nombre, estado FROM roles WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function permisos_por_rol(int $idRol): array
    {
        $sql = 'SELECT rp.id_permiso_def
                FROM roles_permisos rp
                WHERE rp.id_rol = :id_rol
                  AND rp.estado = 1
                  AND rp.deleted_at IS NULL';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $idRol]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function guardar_permisos(int $idRol, array $permisosIds): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare('UPDATE roles_permisos SET estado = 0, deleted_at = NOW() WHERE id_rol = :id_rol AND deleted_at IS NULL')
                ->execute(['id_rol' => $idRol]);

            $insert = $db->prepare('INSERT INTO roles_permisos (id_rol, id_permiso_def, estado, deleted_at) VALUES (:id_rol, :id_permiso_def, 1, NULL)');
            foreach ($permisosIds as $idPermiso) {
                $insert->execute([
                    'id_rol' => $idRol,
                    'id_permiso_def' => (int) $idPermiso,
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
