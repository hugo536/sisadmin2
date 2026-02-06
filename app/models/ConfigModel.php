<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener_config_activa(): ?array
    {
        $sql = 'SELECT id, razon_social, ruc, direccion, telefono, email, logo_path, moneda, tema, estado
                FROM configuracion
                WHERE deleted_at IS NULL
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

        $tema = strtolower(trim((string) ($data['tema'] ?? 'light')));
        if (!in_array($tema, ['light', 'dark', 'blue'], true)) {
            $tema = 'light';
        }

        $payload = [
            'razon_social' => trim((string) ($data['razon_social'] ?? '')),
            'ruc' => trim((string) ($data['ruc'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'logo_path' => trim((string) ($data['logo_path'] ?? '')),
            'moneda' => trim((string) ($data['moneda'] ?? 'PEN')),
            'tema' => $tema,
        ];

        if ($actual !== null) {
            $sql = 'UPDATE configuracion
                    SET razon_social = :razon_social,
                        ruc = :ruc,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email,
                        logo_path = :logo_path,
                        moneda = :moneda,
                        tema = :tema,
                        estado = 1,
                        updated_at = NOW(),
                        updated_by = :updated_by,
                        deleted_at = NULL
                    WHERE id = :id';
            $payload['id'] = (int) $actual['id'];
            $payload['updated_by'] = $userId;
        } else {
            $sql = 'INSERT INTO configuracion
                    (razon_social, ruc, direccion, telefono, email, logo_path, moneda, tema, estado, created_at, created_by, deleted_at)
                    VALUES
                    (:razon_social, :ruc, :direccion, :telefono, :email, :logo_path, :moneda, :tema, 1, NOW(), :created_by, NULL)';
            $payload['created_by'] = $userId;
        }

        $this->db()->prepare($sql)->execute($payload);
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
