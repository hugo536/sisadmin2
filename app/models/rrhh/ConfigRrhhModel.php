<?php
declare(strict_types=1);

class ConfigRrhhModel extends Modelo
{
    public function obtenerConfiguracion(): array
    {
        try {
            $sql = "SELECT * FROM rrhh_configuracion WHERE id = 1 LIMIT 1";
            $stmt = $this->db()->query($sql);
            $config = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$config) {
                return $this->obtenerDefault();
            }
            return $config;
        } catch (Exception $e) {
            return $this->obtenerDefault();
        }
    }

    private function obtenerDefault(): array
    {
        return [
            'meta_horas_diarias' => 8,  // Meta diaria antes de generar horas extras
            'bloque_minutos'     => 30, // Bloques de redondeo (ej. cada 30 min)
            'tolerancia_entrada' => 14, // Gracia para entradas
            'tolerancia_salida'  => 14  // Gracia para salidas tempranas
        ];
    }

    public function guardarConfiguracion(array $datos): bool
    {
        $sql = "INSERT INTO rrhh_configuracion 
                (id, meta_horas_diarias, bloque_minutos, tolerancia_entrada, tolerancia_salida)
                VALUES (1, :meta_diaria, :bloque, :tol_entrada, :tol_salida)
                ON DUPLICATE KEY UPDATE
                    meta_horas_diarias = VALUES(meta_horas_diarias),
                    bloque_minutos = VALUES(bloque_minutos),
                    tolerancia_entrada = VALUES(tolerancia_entrada),
                    tolerancia_salida = VALUES(tolerancia_salida)";

        $stmt = $this->db()->prepare($sql);
        
        return $stmt->execute([
            'meta_diaria' => (float) ($datos['meta_horas_diarias'] ?? 8),
            'bloque'      => (int) ($datos['bloque_minutos'] ?? 30),
            'tol_entrada' => (int) ($datos['tolerancia_entrada'] ?? 14),
            'tol_salida'  => (int) ($datos['tolerancia_salida'] ?? 14)
        ]);
    }
}