<?php
declare(strict_types=1);

class PermisoModel extends Modelo
{
    public function obtener_slugs_por_rol(int $id_rol): array
    {
        $sql = 'SELECT pd.slug
                FROM roles_permisos rp
                INNER JOIN permisos_def pd ON pd.id = rp.id_permiso_def
                INNER JOIN roles r ON r.id = rp.id_rol
                WHERE rp.id_rol = :id_rol
                  AND rp.estado = 1
                  AND rp.deleted_at IS NULL
                  AND pd.estado = 1
                  AND pd.deleted_at IS NULL
                  AND r.estado = 1
                  AND r.deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $id_rol]);

        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        return array_values(array_unique(array_map('strval', $slugs ?: [])));
    }
}
