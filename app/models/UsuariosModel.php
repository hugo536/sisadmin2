<?php
declare(strict_types=1);

class UsuariosModel extends Modelo
{
    public function buscar_por_usuario(string $usuario): ?array
    {
        $sql = 'SELECT id, nombre_completo, usuario, email, clave, id_rol, estado
                FROM usuarios
                WHERE usuario = :usuario
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    // Nuevo auxiliar para validar duplicados
    public function existe_usuario(string $usuario): bool
    {
        $sql = 'SELECT 1 FROM usuarios WHERE usuario = :usuario AND deleted_at IS NULL LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);
        return (bool) $stmt->fetchColumn();
    }

    public function listar_activos(): array
    {
        $sql = 'SELECT u.id, u.nombre_completo, u.usuario, u.email, u.id_rol, u.estado, u.ultimo_login, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.id_rol
                WHERE u.deleted_at IS NULL
                ORDER BY COALESCE(u.updated_at, u.created_at) DESC, u.id DESC';

        return $this->db()->query($sql)->fetchAll();
    }

    public function obtener_por_id(int $id): ?array
    {
        $sql = 'SELECT id, nombre_completo, usuario, email, id_rol, estado
                FROM usuarios
                WHERE id = :id AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function crear(string $nombre, string $usuario, string $email, string $clave, int $idRol, int $createdBy): bool
    {
        $hash = password_hash($clave, PASSWORD_BCRYPT);

        $sql = "INSERT INTO usuarios (nombre_completo, usuario, email, clave, id_rol, created_by, created_at, estado) 
                VALUES (:nombre, :usuario, :email, :clave, :rol, :creator, NOW(), 1)";

        return $this->db()->prepare($sql)->execute([
            'nombre'  => $nombre,
            'usuario' => $usuario,
            'email'   => $email,
            'clave'   => $hash,
            'rol'     => $idRol,
            'creator' => $createdBy
        ]);
    }

    public function actualizar(int $id, string $nombreCompleto, string $usuario, string $email, int $idRol, ?string $clave = null): bool
    {
        $params = [
            'id' => $id,
            'nombre_completo' => $nombreCompleto,
            'usuario' => $usuario,
            'email' => $email,
            'id_rol' => $idRol,
        ];

        if ($clave !== null && $clave !== '') {
            $sql = 'UPDATE usuarios
                    SET nombre_completo = :nombre_completo,
                        usuario = :usuario,
                        email = :email,
                        id_rol = :id_rol,
                        clave = :clave,
                        updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL';
            $params['clave'] = password_hash($clave, PASSWORD_BCRYPT);
        } else {
            $sql = 'UPDATE usuarios
                    SET nombre_completo = :nombre_completo,
                        usuario = :usuario,
                        email = :email,
                        id_rol = :id_rol,
                        updated_at = NOW()
                    WHERE id = :id AND deleted_at IS NULL';
        }

        return $this->db()->prepare($sql)->execute($params);
    }

    public function cambiar_estado(int $id, int $estado): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = :estado,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado]);
    }

    // NUEVO MÉTODO PARA ELIMINADO LÓGICO
    public function eliminar(int $id, int $deletedBy): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = 0,
                    deleted_at = NOW(),
                    updated_by = :deletedBy
                WHERE id = :id';
        
        return $this->db()->prepare($sql)->execute(['id' => $id, 'deletedBy' => $deletedBy]);
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