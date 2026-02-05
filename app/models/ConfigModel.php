<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener_config_activa(): ?array
    {
        $sql = 'SELECT id, razon_social, ruc, direccion, telefono, email, logo_path, tema, moneda
                FROM configuracion
                WHERE estado = 1
                  AND deleted_at IS NULL
                ORDER BY id DESC
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function guardar_config(array $data, int $userId): void
    {
        $actual = $this->obtener_config_activa();

        if ($actual !== null) {
            $sql = 'UPDATE configuracion
                    SET razon_social = :razon_social,
                        ruc = :ruc,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email,
                        logo_path = :logo_path,
                        tema = :tema,
                        moneda = :moneda,
                        updated_at = NOW(),
                        updated_by = :updated_by
                    WHERE id = :id';
            $params = [
                'id' => (int) $actual['id'],
                'updated_by' => $userId,
            ];
        } else {
            $sql = 'INSERT INTO configuracion
                    (razon_social, ruc, direccion, telefono, email, logo_path, tema, moneda, estado, created_at, created_by, updated_at, updated_by, deleted_at)
                    VALUES
                    (:razon_social, :ruc, :direccion, :telefono, :email, :logo_path, :tema, :moneda, 1, NOW(), :created_by, NOW(), :updated_by, NULL)';
            $params = [
                'created_by' => $userId,
                'updated_by' => $userId,
            ];
        }

        $params = array_merge($params, [
            'razon_social' => $data['razon_social'],
            'ruc' => $data['ruc'],
            'direccion' => $data['direccion'],
            'telefono' => $data['telefono'],
            'email' => $data['email'],
            'logo_path' => $data['logo_path'],
            'tema' => $data['tema'],
            'moneda' => $data['moneda'],
        ]);

        $this->db()->prepare($sql)->execute($params);
    }

    public function registrar_bitacora(int $createdBy, string $evento, string $descripcion, string $ip, string $userAgent): void
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
