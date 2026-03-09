<?php
declare(strict_types=1);

class ConfigRrhhModel extends Modelo
{
    public function obtenerConfiguracion(): array
    {
        $sql = "SELECT * FROM rrhh_configuracion WHERE id = 1 LIMIT 1";
        $stmt = $this->db()->query($sql);
        $config = $stmt->fetch(PDO::FETCH_ASSOC);

        // Valores por defecto por si la tabla está vacía
        if (!$config) {
            return [
                'pagar_llegada_temprano' => 0,
                'pagar_salida_tarde' => 0,
                'minutos_gracia_salida' => 15,
                'minutos_minimos_extra' => 30
            ];
        }

        return $config;
    }

    public function guardarConfiguracion(array $datos): bool
    {
        $sql = "UPDATE rrhh_configuracion 
                SET pagar_llegada_temprano = :temprano,
                    pagar_salida_tarde = :tarde,
                    minutos_gracia_salida = :gracia,
                    minutos_minimos_extra = :minimo
                WHERE id = 1";

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'temprano' => (int) $datos['pagar_llegada_temprano'],
            'tarde' => (int) $datos['pagar_salida_tarde'],
            'gracia' => (int) $datos['minutos_gracia_salida'],
            'minimo' => (int) $datos['minutos_minimos_extra']
        ]);
    }
}