<?php
declare(strict_types=1);

class UsuarioModel extends Modelo
{
    public function buscar_por_usuario(string $usuario): ?array
    {
        $sql = 'SELECT id, usuario, clave, id_rol, estado
                FROM usuarios
                WHERE usuario = :usuario
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function listar_activos(): array
    {
        $sql = 'SELECT u.id, u.usuario, u.id_rol, u.estado, u.ultimo_login, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.id_rol
                WHERE u.deleted_at IS NULL
                ORDER BY u.id DESC';

        return $this->db()->query($sql)->fetchAll();
    }

    public function obtener_por_id(int $id): ?array
    {
        $sql = 'SELECT id, usuario, id_rol, estado
                FROM usuarios
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function crear(string $usuario, string $clave, int $idRol): bool
    {
        $sql = 'INSERT INTO usuarios (usuario, clave, id_rol, estado, ultimo_login, deleted_at)
                VALUES (:usuario, :clave, :id_rol, 1, NULL, NULL)';
        $stmt = $this->db()->prepare($sql);

        return $stmt->execute([
            'usuario' => $usuario,
            'clave' => password_hash($clave, PASSWORD_DEFAULT),
            'id_rol' => $idRol,
        ]);
    }

    public function actualizar(int $id, string $usuario, int $idRol, ?string $clave = null): bool
    {
        if ($clave !== null && $clave !== '') {
            $sql = 'UPDATE usuarios
                    SET usuario = :usuario,
                        id_rol = :id_rol,
                        clave = :clave
                    WHERE id = :id
                      AND deleted_at IS NULL';
            $params = [
                'id' => $id,
                'usuario' => $usuario,
                'id_rol' => $idRol,
                'clave' => password_hash($clave, PASSWORD_DEFAULT),
            ];
        } else {
            $sql = 'UPDATE usuarios
                    SET usuario = :usuario,
                        id_rol = :id_rol
                    WHERE id = :id
                      AND deleted_at IS NULL';
            $params = [
                'id' => $id,
                'usuario' => $usuario,
                'id_rol' => $idRol,
            ];
        }

        return $this->db()->prepare($sql)->execute($params);
    }

    public function cambiar_estado(int $id, int $estado): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = :estado
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado]);
    }

    public function listar_roles_activos(): array
    {
        $sql = 'SELECT id, nombre FROM roles WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre';
        return $this->db()->query($sql)->fetchAll();
    }

    public function actualizar_ultimo_login(int $id): void
    {
        $this->db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    public function insertar_bitacora(int $createdBy, string $evento, string $descripcion, string $ip, string $userAgent): void
    {
        $sql = 'INSERT INTO bitacora_seguridad (created_by, evento, descripcion, ip_address, user_agent, created_at)
                VALUES (:created_by, :evento, :descripcion, :ip_address, :user_agent, NOW())';
        $this->db()->prepare($sql)->execute([
            'created_by' => $createdBy,
            'evento' => $evento,
            'descripcion' => $descripcion,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}
