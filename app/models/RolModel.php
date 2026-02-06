<?php
declare(strict_types=1);

class RolModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, nombre, estado, created_at
                FROM roles
                WHERE deleted_at IS NULL
                ORDER BY id DESC';
        return $this->db()->query($sql)->fetchAll();
    }

    public function crear(string $nombre, int $createdBy): bool
    {
        $sql = 'INSERT INTO roles (nombre, estado, created_at, created_by)
                VALUES (:nombre, 1, NOW(), :created_by)';
        return $this->db()->prepare($sql)->execute([
            'nombre' => $nombre,
            'created_by' => $createdBy,
        ]);
    }

    public function actualizar(int $id, string $nombre, int $estado, int $updatedBy): bool
    {
        $sql = 'UPDATE roles
                SET nombre = :nombre,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => $nombre,
            'estado' => $estado,
            'updated_by' => $updatedBy,
        ]);
    }

    public function cambiar_estado(int $id, int $estado, int $updatedBy): bool
    {
        $sql = 'UPDATE roles
                SET estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'estado' => $estado,
            'updated_by' => $updatedBy,
        ]);
    }

    public function eliminar_logico(int $id, int $updatedBy): bool
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // Eliminar rol
            $db->prepare('UPDATE roles
                          SET deleted_at = NOW(), estado = 0, updated_at = NOW(), updated_by = :updated_by
                          WHERE id = :id AND deleted_at IS NULL')
                ->execute(['id' => $id, 'updated_by' => $updatedBy]);

            // Eliminar permisos asociados (ojo: verificamos si tu tabla tiene columna estado)
            // Si tu tabla roles_permisos no tiene 'estado', elimina "estado = 0," de la consulta abajo.
            // Basado en tu SQL previo, asumo que quieres mantener la lógica, pero corregí el nombre de la tabla si fuera necesario.
            $db->prepare('UPDATE roles_permisos
                          SET deleted_at = NOW() 
                          WHERE id_rol = :id_rol AND deleted_at IS NULL')
                ->execute(['id_rol' => $id]);

            $db->commit();
            return true;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    public function permisos_por_rol(int $idRol): array
    {
        // CORREGIDO: id_permiso_def -> id_permiso
        $sql = 'SELECT rp.id_permiso
                FROM roles_permisos rp
                WHERE rp.id_rol = :id_rol
                  AND rp.deleted_at IS NULL';
        
        // Nota: He quitado "AND rp.estado = 1" porque en tu SQL Dump la tabla roles_permisos 
        // no parece tener la columna 'estado'. Si la tiene, agrégalo de nuevo.
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $idRol]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function guardar_permisos(int $idRol, array $permisosIds): void
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            // CORREGIDO: id_permiso_def -> id_permiso
            $existentesStmt = $db->prepare('SELECT id, id_permiso FROM roles_permisos WHERE id_rol = :id_rol');
            $existentesStmt->execute(['id_rol' => $idRol]);
            $existentes = $existentesStmt->fetchAll();

            $mapExistentes = [];
            foreach ($existentes as $row) {
                // CORREGIDO: key id_permiso
                $mapExistentes[(int) $row['id_permiso']] = (int) $row['id'];
            }

            $activar = array_values(array_unique(array_map('intval', $permisosIds)));
            $activarSet = array_flip($activar);

            // Reactivar (quitar deleted_at)
            $activarStmt = $db->prepare('UPDATE roles_permisos
                                         SET deleted_at = NULL
                                         WHERE id = :id');
            
            // Desactivar (poner deleted_at)
            $desactivarStmt = $db->prepare('UPDATE roles_permisos
                                            SET deleted_at = NOW()
                                            WHERE id = :id');
            
            // Insertar nuevos
            // CORREGIDO: id_permiso_def -> id_permiso y removido columna 'estado' si no existe en SQL
            $insertStmt = $db->prepare('INSERT INTO roles_permisos (id_rol, id_permiso, created_at, created_by, deleted_at)
                                        VALUES (:id_rol, :id_permiso, NOW(), 1, NULL)');

            foreach ($mapExistentes as $idPermiso => $idRelacion) {
                if (isset($activarSet[$idPermiso])) {
                    $activarStmt->execute(['id' => $idRelacion]);
                } else {
                    $desactivarStmt->execute(['id' => $idRelacion]);
                }
            }

            foreach ($activar as $idPermiso) {
                if (!isset($mapExistentes[$idPermiso])) {
                    $insertStmt->execute([
                        'id_rol' => $idRol,
                        'id_permiso' => $idPermiso, // CORREGIDO: key correcta
                    ]);
                }
            }

            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}