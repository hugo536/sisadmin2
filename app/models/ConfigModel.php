<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener_config_activa(): ?array
    {
        $sql = 'SELECT id, nombre_empresa, ruc, direccion, telefono, email, ruta_logo, moneda, impuesto, slogan, color_sistema
                FROM configuracion
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
            $params = [
                'id' => (int) $actual['id'],
            ];
        } else {
            $sql = 'INSERT INTO configuracion
                    (nombre_empresa, ruc, direccion, telefono, email, ruta_logo, moneda, impuesto, slogan, color_sistema)
                    VALUES
                    (:nombre_empresa, :ruc, :direccion, :telefono, :email, :ruta_logo, :moneda, :impuesto, :slogan, :color_sistema)';
            $params = [];
        }

        $params = array_merge($params, [
            'nombre_empresa' => $data['nombre_empresa'],
            'ruc' => $data['ruc'],
            'direccion' => $data['direccion'],
            'telefono' => $data['telefono'],
            'email' => $data['email'],
            'ruta_logo' => $data['ruta_logo'],
            'moneda' => $data['moneda'],
            'impuesto' => $data['impuesto'],
            'slogan' => $data['slogan'],
            'color_sistema' => $data['color_sistema'],
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
