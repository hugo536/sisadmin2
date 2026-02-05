<?php
declare(strict_types=1);

class RolModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, nombre, slug, descripcion, estado FROM roles ORDER BY id DESC';
        return $this->db()->query($sql)->fetchAll();
    }

    public function crear(string $nombre, string $descripcion = ''): bool
    {
        $slug = $this->slugify($nombre);
        return $this->db()->prepare('INSERT INTO roles (nombre, slug, descripcion, estado) VALUES (:nombre, :slug, :descripcion, 1)')
            ->execute(['nombre' => $nombre, 'slug' => $slug, 'descripcion' => $descripcion]);
    }

    public function actualizar(int $id, string $nombre, int $estado, string $descripcion = ''): bool
    {
        $sql = 'UPDATE roles SET nombre = :nombre, slug = :slug, descripcion = :descripcion, estado = :estado WHERE id = :id';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => $nombre,
            'slug' => $this->slugify($nombre),
            'descripcion' => $descripcion,
            'estado' => $estado,
        ]);
    }

    public function obtener_por_id(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT id, nombre, slug, descripcion, estado FROM roles WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return is_array($row) ? $row : null;
    }

    public function permisos_por_rol(int $idRol): array
    {
        $sql = 'SELECT rp.id_permiso
                FROM roles_permisos rp
                WHERE rp.id_rol = :id_rol';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $idRol]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function guardar_permisos(int $idRol, array $permisosIds): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $db->prepare('DELETE FROM roles_permisos WHERE id_rol = :id_rol')->execute(['id_rol' => $idRol]);

            $insert = $db->prepare('INSERT INTO roles_permisos (id_rol, id_permiso) VALUES (:id_rol, :id_permiso)');
            foreach ($permisosIds as $idPermiso) {
                $insert->execute([
                    'id_rol' => $idRol,
                    'id_permiso' => (int) $idPermiso,
                ]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function slugify(string $valor): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $valor) ?? ''));
        return trim($slug, '-') !== '' ? trim($slug, '-') : 'rol';
    }
}
