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
            'meta_horas_diarias' => 8, // Meta diaria antes de generar horas extras
            'bloque_minutos'     => 30, // Bloques de redondeo (ej. cada 30 min)
            'minutos_tolerancia' => 14  // Minutos de gracia antes de saltar al siguiente bloque
        ];
    }

    public function guardarConfiguracion(array $datos): bool
    {
        $sql = "INSERT INTO rrhh_configuracion 
                (id, meta_horas_diarias, bloque_minutos, minutos_tolerancia)
                VALUES (1, :meta_diaria, :bloque, :tolerancia)
                ON DUPLICATE KEY UPDATE
                    meta_horas_diarias = VALUES(meta_horas_diarias),
                    bloque_minutos = VALUES(bloque_minutos),
                    minutos_tolerancia = VALUES(minutos_tolerancia)";

        $stmt = $this->db()->prepare($sql);
        
        return $stmt->execute([
            'meta_diaria' => (float) ($datos['meta_horas_diarias'] ?? 8),
            'bloque'      => (int) ($datos['bloque_minutos'] ?? 30),
            'tolerancia'  => (int) ($datos['minutos_tolerancia'] ?? 14)
        ]);
    }
}