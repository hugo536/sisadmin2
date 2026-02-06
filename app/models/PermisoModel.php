<?php
declare(strict_types=1);

class PermisoModel extends Modelo
{
    public function obtener_slugs_por_rol(int $idRol): array
    {
        // CORREGIDO: 
        // 1. Cambiado rp.id_permiso_def por rp.id_permiso
        // 2. Eliminados los filtros de 'estado' porque esas tablas no tienen esa columna en tu SQL
        $sql = 'SELECT pd.slug
                FROM roles_permisos rp
                INNER JOIN permisos_def pd ON pd.id = rp.id_permiso
                INNER JOIN roles r ON r.id = rp.id_rol
                WHERE rp.id_rol = :id_rol
                  AND rp.deleted_at IS NULL
                  AND pd.deleted_at IS NULL
                  AND r.estado = 1
                  AND r.deleted_at IS NULL';
                  
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $idRol]);
        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_values(array_unique(array_map('strval', $slugs ?: [])));
    }

    public function listar_agrupados_modulo(): array
    {
        // CORREGIDO: Quitamos 'estado' del SELECT
        $sql = 'SELECT id, slug, nombre, modulo
                FROM permisos_def
                WHERE deleted_at IS NULL
                ORDER BY modulo, nombre';
                
        $rows = $this->db()->query($sql)->fetchAll();
        $grupos = [];

        foreach ($rows as $row) {
            $modulo = (string) ($row['modulo'] ?? 'General');
            $grupos[$modulo][] = $row;
        }

        return $grupos;
    }

    public function listar_activos(): array
    {
        // CORREGIDO: Quitamos 'estado' del SELECT
        $sql = 'SELECT id, slug, nombre, modulo
                FROM permisos_def
                WHERE deleted_at IS NULL
                ORDER BY modulo, nombre';
                
        return $this->db()->query($sql)->fetchAll();
    }
}