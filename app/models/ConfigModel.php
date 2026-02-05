<?php
declare(strict_types=1);

class ConfigModel extends Modelo
{
    public function obtener_config_activa(): ?array
    {
        // Se usan alias para que coincidan con lo que espera el Controlador y la Vista
        $sql = 'SELECT id, 
                       nombre_empresa as razon_social, 
                       ruc, 
                       direccion, 
                       telefono, 
                       email, 
                       ruta_logo as logo_path, 
                       color_sistema as tema, 
                       moneda
                FROM configuracion
                WHERE id = 1'; 

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        $fila = $stmt->fetch();
        return is_array($fila) ? $fila : null;
    }

    public function guardar_config(array $data, int $userId): void
    {
        // Actualizamos usando los nombres reales de las columnas en tu tabla 'configuracion'
        $sql = 'UPDATE configuracion
                SET nombre_empresa = :razon_social,
                    ruc = :ruc,
                    direccion = :direccion,
                    telefono = :telefono,
                    email = :email,
                    ruta_logo = :logo_path,
                    color_sistema = :tema,
                    moneda = :moneda,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = 1';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'razon_social' => $data['razon_social'],
            'ruc' => $data['ruc'],
            'direccion' => $data['direccion'],
            'telefono' => $data['telefono'],
            'email' => $data['email'],
            'logo_path' => $data['logo_path'],
            'tema' => $data['tema'],
            'moneda' => $data['moneda'],
            'updated_by' => $userId
        ]);
    }

    public function registrar_bitacora(
        int $createdBy,
        string $evento,
        string $descripcion,
        string $ip,
        string $userAgent
    ): void {
        $sql = 'INSERT INTO bitacora_seguridad (created_by, evento, descripcion, ip_address, user_agent, created_at)
                VALUES (:created_by, :evento, :descripcion, :ip_address, :user_agent, NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'created_by' => $createdBy,
            'evento' => $evento,
            'descripcion' => $descripcion,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
        ]);
    }
}