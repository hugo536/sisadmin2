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
            'pagar_llegada_temprano' => 0,
            'pagar_salida_tarde' => 0,
            'minutos_gracia_salida' => 5, // Nueva variable
            'tipo_calculo_horas_extras' => 'EXACTO',
            'minutos_umbral_media_hora' => 15,
            'minutos_umbral_hora_completa' => 45
        ];
    }

    public function guardarConfiguracion(array $datos): bool
    {
        $sql = "INSERT INTO rrhh_configuracion 
                (id, pagar_llegada_temprano, pagar_salida_tarde, minutos_gracia_salida, tipo_calculo_horas_extras, minutos_umbral_media_hora, minutos_umbral_hora_completa)
                VALUES (1, :temprano, :tarde, :gracia, :tipo_calculo, :umbral_media, :umbral_hora)
                ON DUPLICATE KEY UPDATE
                    pagar_llegada_temprano = VALUES(pagar_llegada_temprano),
                    pagar_salida_tarde = VALUES(pagar_salida_tarde),
                    minutos_gracia_salida = VALUES(minutos_gracia_salida),
                    tipo_calculo_horas_extras = VALUES(tipo_calculo_horas_extras),
                    minutos_umbral_media_hora = VALUES(minutos_umbral_media_hora),
                    minutos_umbral_hora_completa = VALUES(minutos_umbral_hora_completa)";

        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'temprano' => (int) $datos['pagar_llegada_temprano'],
            'tarde' => (int) $datos['pagar_salida_tarde'],
            'gracia' => (int) $datos['minutos_gracia_salida'],
            'tipo_calculo' => $datos['tipo_calculo_horas_extras'],
            'umbral_media' => (int) $datos['minutos_umbral_media_hora'],
            'umbral_hora' => (int) $datos['minutos_umbral_hora_completa']
        ]);
    }
}