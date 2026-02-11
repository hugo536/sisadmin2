<?php
declare(strict_types=1);

class PermisoModel extends Modelo
{
    /** @var array<string, array<int, string>> */
    private array $cacheColumnas = [];

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
        $rows = $this->listar_activos();
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
        $select = [
            'pd.id',
            'pd.slug',
            'pd.nombre',
        ];

        if ($this->tablaTieneColumna('permisos_def', 'descripcion')) {
            $select[] = 'pd.descripcion';
        }

        $select[] = $this->tablaTieneColumna('permisos_def', 'modulo') ? 'pd.modulo' : "'General' AS modulo";
        $select[] = $this->tablaTieneColumna('permisos_def', 'estado') ? 'pd.estado' : '1 AS estado';

        if ($this->tablaTieneColumna('permisos_def', 'created_at')) {
            $select[] = 'pd.created_at';
        }

        if ($this->tablaTieneColumna('permisos_def', 'updated_at')) {
            $select[] = 'pd.updated_at';
        }

        if ($this->tablaTieneColumna('permisos_def', 'created_by')) {
            $select[] = 'pd.created_by';
            $select[] = 'uc.nombre_completo AS created_by_nombre';
        }

        if ($this->tablaTieneColumna('permisos_def', 'updated_by')) {
            $select[] = 'pd.updated_by';
            $select[] = 'uu.nombre_completo AS updated_by_nombre';
        }

        $sql = 'SELECT ' . implode(', ', $select) . '
                FROM permisos_def pd';

        if ($this->tablaTieneColumna('permisos_def', 'created_by')) {
            $sql .= ' LEFT JOIN usuarios uc ON uc.id = pd.created_by';
        }

        if ($this->tablaTieneColumna('permisos_def', 'updated_by')) {
            $sql .= ' LEFT JOIN usuarios uu ON uu.id = pd.updated_by';
        }

        $sql .= ' WHERE pd.deleted_at IS NULL
                  ORDER BY pd.modulo ASC, pd.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tablaTieneColumna(string $tabla, string $columna): bool
    {
        if (!isset($this->cacheColumnas[$tabla])) {
            $stmt = $this->db()->prepare('SHOW COLUMNS FROM ' . $tabla);
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->cacheColumnas[$tabla] = array_map('strtolower', $cols ?: []);
        }

        return in_array(strtolower($columna), $this->cacheColumnas[$tabla], true);
    }
}
