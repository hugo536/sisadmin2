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

        $resultado = $stmt->fetch();
        return is_array($resultado) ? $resultado : null;
    }

    // --- NUEVOS MÉTODOS PARA EL CRUD ---

    public function obtener_todos(): array
    {
        $sql = "SELECT u.id, u.nombre_completo, u.usuario, u.email, u.estado, r.nombre as rol
                FROM usuarios u
                LEFT JOIN roles r ON u.id_rol = r.id
                WHERE u.deleted_at IS NULL
                ORDER BY u.id DESC";
        return $this->db()->query($sql)->fetchAll();
    }

    public function obtener_roles(): array
    {
        return $this->db()->query("SELECT id, nombre FROM roles WHERE estado = 1 AND deleted_at IS NULL")->fetchAll();
    }

    public function guardar(array $data): bool
    {
        try {
            if (!empty($data['id'])) {
                // Actualizar usuario existente
                $sql = "UPDATE usuarios SET nombre_completo=?, usuario=?, email=?, id_rol=?, estado=?, updated_by=?, updated_at=NOW() WHERE id=?";
                $params = [$data['nombre'], $data['usuario'], $data['email'], $data['id_rol'], $data['estado'], $_SESSION['id'] ?? 1, $data['id']];
                
                // Si se envió contraseña nueva, actualizarla
                if (!empty($data['clave'])) {
                    $this->db()->prepare("UPDATE usuarios SET clave=? WHERE id=?")->execute([password_hash($data['clave'], PASSWORD_DEFAULT), $data['id']]);
                }
            } else {
                // Crear nuevo usuario
                $sql = "INSERT INTO usuarios (nombre_completo, usuario, email, clave, id_rol, estado, created_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $params = [$data['nombre'], $data['usuario'], $data['email'], password_hash($data['clave'], PASSWORD_DEFAULT), $data['id_rol'], 1, $_SESSION['id'] ?? 1];
            }
            
            $stmt = $this->db()->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            return false;
        }
    }

    // --- FIN NUEVOS MÉTODOS ---

    public function actualizar_ultimo_login(int $id): void
    {
        $sql = 'UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id';
        $stmt = $this->db()->prepare($sql);
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

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'created_by' => $created_by,
            'evento' => $evento,
            'descripcion' => $descripcion,
            'ip_address' => $ip,
            'user_agent' => $user_agent,
        ]);
    }
}