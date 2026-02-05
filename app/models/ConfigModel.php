<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener_config_activa(): ?array
    {
        $sql = 'SELECT id, nombre_empresa, ruc, direccion, telefono, email, ruta_logo, moneda, impuesto, slogan, color_sistema, estado
                FROM configuracion
                WHERE estado = 1
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

        $nombreEmpresa = trim((string) ($data['nombre_empresa'] ?? $data['razon_social'] ?? ''));
        $colorSistema = strtolower(trim((string) ($data['color_sistema'] ?? $data['tema'] ?? 'light')));
        if (!in_array($colorSistema, ['light', 'dark', 'blue'], true)) {
            $colorSistema = 'light';
        }

        $payload = [
            'nombre_empresa' => $nombreEmpresa,
            'ruc' => trim((string) ($data['ruc'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'ruta_logo' => trim((string) ($data['ruta_logo'] ?? $data['logo_path'] ?? '')),
            'moneda' => trim((string) ($data['moneda'] ?? 'PEN')),
            'impuesto' => (float) ($data['impuesto'] ?? 0),
            'slogan' => trim((string) ($data['slogan'] ?? '')),
            'color_sistema' => $colorSistema,
        ];

        if ($actual !== null) {
            $sql = 'UPDATE configuracion
                    SET nombre_empresa = :nombre_empresa,
                        ruc = :ruc,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email,
                        ruta_logo = :ruta_logo,
                        moneda = :moneda,
                        impuesto = :impuesto,
                        slogan = :slogan,
                        color_sistema = :color_sistema
                    WHERE id = :id';
            $payload['id'] = (int) $actual['id'];
        } else {
            $sql = 'INSERT INTO configuracion
                    (nombre_empresa, ruc, direccion, telefono, email, ruta_logo, moneda, impuesto, slogan, color_sistema, estado)
                    VALUES
                    (:nombre_empresa, :ruc, :direccion, :telefono, :email, :ruta_logo, :moneda, :impuesto, :slogan, :color_sistema, 1)';
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
