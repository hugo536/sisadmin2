<?php
declare(strict_types=1);

class UsuariosModel extends Modelo
{
    /**
     * Busca un usuario por su nombre de usuario (login).
     */
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

    /**
     * Lista todos los usuarios con su nombre de rol.
     */
    public function listar_activos(): array
    {
        $sql = 'SELECT u.id, u.nombre_completo, u.usuario, u.email, u.id_rol, u.estado, u.ultimo_login, r.nombre AS rol
                FROM usuarios u
                LEFT JOIN roles r ON r.id = u.id_rol
                ORDER BY u.id DESC';

        return $this->db()->query($sql)->fetchAll();
    }

    /**
     * Obtiene los datos de un usuario por su ID.
     */
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

    /**
     * Crea un nuevo usuario en la base de datos.
     * Se incluye 'created_by' para cumplir con la FK fk_usuarios_created.
     */
    public function crear(string $nombre, string $usuario, string $email, string $clave, int $idRol, int $createdBy): bool
    {
        // Encriptar clave
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

    /**
     * Actualiza la información de un usuario existente.
     */
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
                'clave' => password_hash($clave, PASSWORD_BCRYPT),
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

    /**
     * Cambia el estado (Activo/Inactivo) de un usuario.
     */
    public function cambiar_estado(int $id, int $estado): bool
    {
        $sql = 'UPDATE usuarios
                SET estado = :estado,
                    updated_at = NOW()
                WHERE id = :id';

        return $this->db()->prepare($sql)->execute(['id' => $id, 'estado' => $estado]);
    }

    /**
     * Lista los roles que están marcados como activos.
     */
    public function listar_roles_activos(): array
    {
        $sql = 'SELECT id, nombre FROM roles WHERE estado = 1 ORDER BY nombre';
        return $this->db()->query($sql)->fetchAll();
    }

    /**
     * Registra la fecha y hora del último acceso exitoso.
     */
    public function actualizar_ultimo_login(int $id): void
    {
        $this->db()->prepare('UPDATE usuarios SET ultimo_login = NOW(), updated_at = NOW() WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Inserta un registro en la bitácora de seguridad.
     */
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