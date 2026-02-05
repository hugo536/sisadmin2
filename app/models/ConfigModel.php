<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener_config_activa(): ?array
    {
        $sql = 'SELECT id, razon_social, ruc, direccion, telefono, email, logo_path, tema, moneda, estado
                FROM configuracion
                WHERE deleted_at IS NULL AND estado = 1
                ORDER BY id DESC
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();

        return is_array($row) ? $row : null;
    }

    public function guardar_config(array $data): void
    {
        $actual = $this->obtener_config_activa();

        $razonSocial = trim((string) ($data['razon_social'] ?? $data['nombre_empresa'] ?? ''));
        $tema = strtolower(trim((string) ($data['tema'] ?? $data['color_sistema'] ?? 'light')));
        if (!in_array($tema, ['light', 'dark', 'blue'], true)) {
            $tema = 'light';
        }

        $payload = [
            'razon_social' => $razonSocial,
            'ruc' => trim((string) ($data['ruc'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'logo_path' => trim((string) ($data['logo_path'] ?? $data['ruta_logo'] ?? '')),
            'tema' => $tema,
            'moneda' => trim((string) ($data['moneda'] ?? 'PEN')),
        ];

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
                        updated_at = NOW()
                    WHERE id = :id';
            $payload['id'] = (int) $actual['id'];
        } else {
            $sql = 'INSERT INTO configuracion
                    (razon_social, ruc, direccion, telefono, email, logo_path, tema, moneda, estado, created_at)
                    VALUES
                    (:razon_social, :ruc, :direccion, :telefono, :email, :logo_path, :tema, :moneda, 1, NOW())';
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
