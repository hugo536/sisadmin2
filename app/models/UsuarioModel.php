<?php
declare(strict_types=1);

class UsuarioModel extends Modelo
{
    public function buscar_por_usuario(string $usuario): ?array
    {
        $sql = 'SELECT id, usuario, clave, id_rol, estado FROM usuarios WHERE usuario = :usuario LIMIT 1';
        $stmt = $this->get_pdo()->prepare($sql);
        $stmt->execute(['usuario' => $usuario]);

        $resultado = $stmt->fetch();
        return is_array($resultado) ? $resultado : null;
    }

    public function actualizar_ultimo_login(int $id): void
    {
        $sql = 'UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id';
        $stmt = $this->get_pdo()->prepare($sql);
        $stmt->execute(['id' => $id]);
    }

    public function insertar_bitacora(
        int $created_by,
        string $evento,
        string $descripcion,
        string $ip,
        string $user_agent
    ): void {
        $sql = 'INSERT INTO bitacora_seguridad (created_by, evento, descripcion, ip_address, user_agent, created_at)
                VALUES (:created_by, :evento, :descripcion, :ip_address, :user_agent, NOW())';

        $stmt = $this->get_pdo()->prepare($sql);
        $stmt->execute([
            'created_by' => $created_by,
            'evento' => $evento,
            'descripcion' => $descripcion,
            'ip_address' => $ip,
            'user_agent' => $user_agent,
        ]);
    }
}