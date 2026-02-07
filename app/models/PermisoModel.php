<?php
declare(strict_types=1);

class PermisoModel extends Modelo
{
    /**
     * Obtiene los slugs de permisos activos para un rol específico.
     * Utilizado por el AuthMiddleware y Helpers para validar acceso.
     * * @param int $idRol
     * @return array Lista de slugs (ej. ['ventas.ver', 'compras.crear'])
     */
    public function obtener_slugs_por_rol(int $idRol): array
    {
        // Se une con roles para asegurar que el rol esté activo (estado=1)
        // Se valida deleted_at en todas las tablas por integridad
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

        // Retornamos array limpio de strings únicos
        return array_values(array_unique(array_map('strval', $slugs ?: [])));
    }

    /**
     * Lista todos los permisos agrupados por módulo.
     * Utilizado en la vista de Matriz de Permisos.
     * * @return array [ 'Ventas' => [...permisos], 'Logística' => [...] ]
     */
    public function listar_agrupados_modulo(): array
    {
        // Nota: Como la tabla no tiene 'estado', inyectamos '1 as estado' 
        // para que la vista los renderice como "Activos" visualmente.
        $sql = 'SELECT id, slug, nombre, modulo, 1 as estado
                FROM permisos_def
                WHERE deleted_at IS NULL
                ORDER BY modulo ASC, nombre ASC';
                
        $rows = $this->db()->query($sql)->fetchAll();
        $grupos = [];

        foreach ($rows as $row) {
            $modulo = (string) ($row['modulo'] ?? 'General');
            $grupos[$modulo][] = $row;
        }

        return $grupos;
    }

    /**
     * Lista plana de permisos activos.
     * Utilizado para validaciones o listados simples.
     */
    public function listar_activos(): array
    {
        $sql = 'SELECT id, slug, nombre, modulo, 1 as estado
                FROM permisos_def
                WHERE deleted_at IS NULL
                ORDER BY modulo ASC, nombre ASC';
                
        return $this->db()->query($sql)->fetchAll();
    }
}