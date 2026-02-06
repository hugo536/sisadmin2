<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener(): array
    {
        $sql = 'SELECT id, nombre_empresa, ruc, direccion, telefono, email, 
                       ruta_logo, moneda, impuesto, slogan, color_sistema
                FROM configuracion
                WHERE id = 1
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function guardar(array $data, int $userId): bool
    {
        // Preparar payload
        $payload = [
            'nombre_empresa' => trim((string) ($data['nombre_empresa'] ?? '')),
            'ruc'            => trim((string) ($data['ruc'] ?? '')),
            'direccion'      => trim((string) ($data['direccion'] ?? '')),
            'telefono'       => trim((string) ($data['telefono'] ?? '')),
            'email'          => trim((string) ($data['email'] ?? '')),
            'moneda'         => trim((string) ($data['moneda'] ?? 'S/')),
            'impuesto'       => (float) ($data['impuesto'] ?? 18.00),
            'slogan'         => trim((string) ($data['slogan'] ?? '')),
            'color_sistema'  => trim((string) ($data['color_sistema'] ?? 'light')),
            'updated_by'     => $userId
        ];

        // SQL Update
        $sql = 'UPDATE configuracion
                SET nombre_empresa = :nombre_empresa,
                    ruc = :ruc,
                    direccion = :direccion,
                    telefono = :telefono,
                    email = :email,
                    moneda = :moneda,
                    impuesto = :impuesto,
                    slogan = :slogan,
                    color_sistema = :color_sistema,
                    updated_at = NOW(),
                    updated_by = :updated_by';

        // Solo actualizamos logo si viene uno nuevo
        if (!empty($data['ruta_logo'])) {
            $sql .= ', ruta_logo = :ruta_logo';
            $payload['ruta_logo'] = trim((string) $data['ruta_logo']);
        }

        $sql .= ' WHERE id = 1';

        return $this->db()->prepare($sql)->execute($payload);
    }

    public function registrar_bitacora(int $createdBy, string $evento, string $descripcion, string $ip, string $userAgent): void
    {
        $sql = 'INSERT INTO bitacora_seguridad (created_by, evento, descripcion, ip_address, user_agent, created_at)
                VALUES (:created_by, :evento, :descripcion, :ip_address, :user_agent, NOW())';
        $this->db()->prepare($sql)->execute([
            'created_by'  => $createdBy,
            'evento'      => $evento,
            'descripcion' => $descripcion,
            'ip_address'  => $ip,
            'user_agent'  => $userAgent,
        ]);
    }
}