<?php
declare(strict_types=1);

class UsuariosModel extends Modelo
{
    public function buscar_por_usuario(string $usuario): ?array
    {
        $sql = 'SELECT id, nombre_completo, usuario, email, clave, id_rol, estado
                FROM usuarios
                WHERE usuario = :usuario
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function listar_activos(): array
    {
        $sql = 'SELECT u.id, u.nombre_completo, u.usuario, u.email, u.id_rol, u.estado, u.ultimo_login, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.id_rol
                ORDER BY u.id DESC';

        return $this->db()->query($sql)->fetchAll();
    }

    public function obtener_por_id(int $id): ?array
    {
        $sql = 'SELECT id, nombre_completo, usuario, email, id_rol, estado
                FROM usuarios
                WHERE id = :id
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function crear(string $nombreCompleto, string $usuario, string $email, string $clave, int $idRol): bool
    {
        $sql = 'INSERT INTO usuarios (nombre_completo, usuario, email, clave, id_rol, estado, ultimo_login, created_at, updated_at)
                VALUES (:nombre_completo, :usuario, :email, :clave, :id_rol, 1, NULL, NOW(), NOW())';
        $stmt = $this->db()->prepare($sql);

        return $stmt->execute([
            'nombre_completo' => $nombreCompleto,
            'usuario' => $usuario,
            'email' => $email,
            'clave' => password_hash($clave, PASSWORD_DEFAULT),
            'id_rol' => $idRol,
        ]);
    }

    public function actualizar(int $id, string $nombreCompleto, string $usuario, string $email, int $idRol, ?string $clave = null): bool
    {
        if ($clave !== null && $clave !== '') {
            $sql = 'UPDATE usuarios
                    SET nombre_completo = :nombre_completo,
                        usuario = :usuario,
                        email = :email,
                        id_rol = :id_rol,
                        clave = :clave,
                        updated_at = NOW()
                    WHERE id = :id';
            $params = [
                'id' => $id,
                'nombre_completo' => $nombreCompleto,
                'usuario' => $usuario,
                'email' => $email,
                'id_rol' => $idRol,
                'clave' => password_hash($clave, PASSWORD_DEFAULT),
            ];
        } else {
            $sql = 'UPDATE usuarios
                    SET nombre_completo = :nombre_completo,
                        usuario = :usuario,
                        email = :email,
                        id_rol = :id_rol,
                        updated_at = NOW()
                    WHERE id = :id';
            $params = [
                'id' => $id,
                'nombre_completo' => $nombreCompleto,
                'usuario' => $usuario,
                'email' => $email,
                'id_rol' => $idRol,
            ];
        }

        return $this->db()->prepare($sql)->execute($params);
    }

    public function cambiar_estado(int $id, int $estado): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = :estado,
                    updated_at = NOW()
                WHERE id = :id';

        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado]);
    }

    public function listar_roles_activos(): array
    {
        $sql = 'SELECT id, nombre FROM roles WHERE estado = 1 ORDER BY nombre';
        return $this->db()->query($sql)->fetchAll();
    }

    public function actualizar_ultimo_login(int $id): void
    {
        $this->db()->prepare('UPDATE usuarios SET ultimo_login = NOW(), updated_at = NOW() WHERE id = :id')->execute(['id' => $id]);
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
