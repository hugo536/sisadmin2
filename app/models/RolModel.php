<?php
declare(strict_types=1);

class RolModel extends Modelo
{
    /**
     * Lista todos los roles activos (no eliminados).
     */
    public function listar(): array
    {
        $sql = 'SELECT id, nombre, estado, created_at, updated_at 
                FROM roles 
                WHERE deleted_at IS NULL 
                ORDER BY id DESC';
        return $this->db()->query($sql)->fetchAll();
    }

    /**
     * Crea un nuevo rol.
     */
    public function crear(string $nombre, int $createdBy): bool
    {
        $slugBase = $this->slugify($nombre);
        $slug = $this->slugDisponible($slugBase);

        $sql = 'INSERT INTO roles (nombre, slug, estado, created_at, created_by) 
                VALUES (:nombre, :slug, 1, NOW(), :created_by)';
        
        return $this->db()->prepare($sql)->execute([
            'nombre'     => $nombre,
            'slug'       => $slug,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * Actualiza datos básicos del rol.
     */
    public function actualizar(int $id, string $nombre, int $estado, int $updatedBy): bool
    {
        $slugBase = $this->slugify($nombre);
        $slug = $this->slugDisponible($slugBase, $id);

        $sql = 'UPDATE roles 
                SET nombre = :nombre, 
                    slug = :slug,
                    estado = :estado, 
                    updated_at = NOW(), 
                    updated_by = :updated_by 
                WHERE id = :id 
                  AND deleted_at IS NULL';
        
        return $this->db()->prepare($sql)->execute([
            'id'         => $id,
            'nombre'     => $nombre,
            'slug'       => $slug,
            'estado'     => $estado,
            'updated_by' => $updatedBy,
        ]);
    }

    /**
     * Cambia solo el estado (Activo/Inactivo).
     */
    public function cambiar_estado(int $id, int $estado, int $updatedBy): bool
    {
        $sql = 'UPDATE roles 
                SET estado = :estado, 
                    updated_at = NOW(), 
                    updated_by = :updated_by 
                WHERE id = :id 
                  AND deleted_at IS NULL';
        
        return $this->db()->prepare($sql)->execute([
            'id'         => $id,
            'estado'     => $estado,
            'updated_by' => $updatedBy,
        ]);
    }

    /**
     * Soft Delete del rol y sus permisos asociados.
     */
    public function eliminar_logico(int $id, int $updatedBy): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Eliminar Rol (Soft Delete)
            $sqlRol = 'UPDATE roles 
                       SET deleted_at = NOW(), 
                           estado = 0, 
                           updated_at = NOW(), 
                           updated_by = :updated_by 
                       WHERE id = :id 
                         AND deleted_at IS NULL';
            $db->prepare($sqlRol)->execute(['id' => $id, 'updated_by' => $updatedBy]);

            // 2. Eliminar Permisos Asociados (Soft Delete)
            // Nota: roles_permisos usa PK compuesta, borramos por grupo id_rol
            $sqlPermisos = 'UPDATE roles_permisos 
                            SET deleted_at = NOW() 
                            WHERE id_rol = :id_rol 
                              AND deleted_at IS NULL';
            $db->prepare($sqlPermisos)->execute(['id_rol' => $id]);

            $db->commit();
            return true;

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene los IDs de permisos activos asignados a un rol.
     */
    public function permisos_por_rol(int $idRol): array
    {
        $sql = 'SELECT id_permiso 
                FROM roles_permisos 
                WHERE id_rol = :id_rol 
                  AND deleted_at IS NULL';
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $idRol]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Sincroniza los permisos del rol (Estrategia Upsert/Restore).
     * Corrige el uso de PK compuesta (id_rol, id_permiso) en lugar de ID autoincremental.
     */
    public function guardar_permisos(int $idRol, array $permisosIds, int $usuarioAuditId = 1): void
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Obtener estado actual de permisos (incluyendo eliminados soft-delete)
            // Se usa PK compuesta para mapear
            $sql = 'SELECT id_permiso, deleted_at 
                    FROM roles_permisos 
                    WHERE id_rol = :id_rol';
            $stmt = $db->prepare($sql);
            $stmt->execute(['id_rol' => $idRol]);
            $actuales = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapa: [id_permiso => 'deleted'|'active']
            $mapaEstado = [];
            foreach ($actuales as $row) {
                $mapaEstado[$row['id_permiso']] = $row['deleted_at'] ? 'deleted' : 'active';
            }

            // IDs que deben quedar activos
            $idsParaActivar = array_unique(array_map('intval', $permisosIds));

            // Sentencias preparadas
            $insertStmt = $db->prepare('INSERT INTO roles_permisos (id_rol, id_permiso, created_at, created_by) VALUES (:id_rol, :id_permiso, NOW(), :created_by)');
            
            $restoreStmt = $db->prepare('UPDATE roles_permisos SET deleted_at = NULL WHERE id_rol = :id_rol AND id_permiso = :id_permiso');
            
            $deleteStmt = $db->prepare('UPDATE roles_permisos SET deleted_at = NOW() WHERE id_rol = :id_rol AND id_permiso = :id_permiso');

            // 2. Procesar inserciones y restauraciones
            foreach ($idsParaActivar as $pId) {
                if (isset($mapaEstado[$pId])) {
                    // Existe en BD
                    if ($mapaEstado[$pId] === 'deleted') {
                        // Estaba borrado -> Restaurar (Upsert lógico)
                        $restoreStmt->execute(['id_rol' => $idRol, 'id_permiso' => $pId]);
                    }
                    // Si ya estaba 'active', no hacemos nada
                } else {
                    // No existe -> Insertar
                    $insertStmt->execute([
                        'id_rol'     => $idRol,
                        'id_permiso' => $pId,
                        'created_by' => $usuarioAuditId
                    ]);
                }
                // Marcar como procesado para no borrarlo después
                unset($mapaEstado[$pId]);
            }

            // 3. Procesar eliminaciones (lo que sobró en el mapa y estaba activo)
            foreach ($mapaEstado as $pId => $status) {
                if ($status === 'active') {
                    $deleteStmt->execute(['id_rol' => $idRol, 'id_permiso' => $pId]);
                }
            }

            $db->commit();

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    private function slugify(string $nombre): string
    {
        $slug = mb_strtolower($nombre);
        $slug = preg_replace('/[^a-z0-9]+/u', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'rol';
    }

    private function slugDisponible(string $base, ?int $ignoreId = null): string
    {
        $db = $this->db();
        $slug = $base;
        $suffix = 1;

        while (true) {
            $sql = 'SELECT id FROM roles WHERE slug = :slug';
            $params = ['slug' => $slug];

            if ($ignoreId !== null) {
                $sql .= ' AND id <> :ignore_id';
                $params['ignore_id'] = $ignoreId;
            }

            $sql .= ' LIMIT 1';

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return $slug;
            }

            $slug = $base . '-' . $suffix;
            $suffix++;
        }
    }
}
