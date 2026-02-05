<?php
declare(strict_types=1);

class UsuarioModel extends Modelo
{
    public function buscar_por_usuario(string $usuario): ?array
    {
        $pdo = $this->db();
        $st = $pdo->prepare("
            SELECT id, usuario, clave, id_rol, estado
            FROM usuarios
            WHERE usuario = :usuario
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $st->execute([':usuario' => $usuario]);
        $row = $st->fetch();
        return $row ?: null;
    }

    public function actualizar_ultimo_login(int $idUsuario): void
    {
        $pdo = $this->db();
        $st = $pdo->prepare("
            UPDATE usuarios
            SET ultimo_login = NOW(),
                updated_at = NOW(),
                updated_by = :id
            WHERE id = :id
        ");
        $st->execute([':id' => $idUsuario]);
    }

    public function insertar_bitacora(int $createdBy, string $evento, string $descripcion, string $ip, string $ua): void
    {
        $pdo = $this->db();
        $st = $pdo->prepare("
            INSERT INTO bitacora_seguridad (created_by, evento, descripcion, ip_address, user_agent, created_at)
            VALUES (:created_by, :evento, :descripcion, :ip_address, :user_agent, NOW())
        ");
        $st->execute([
            ':created_by'  => $createdBy,
            ':evento'      => $evento,
            ':descripcion' => $descripcion,
            ':ip_address'  => $ip,
            ':user_agent'  => $ua,
        ]);
    }
}
