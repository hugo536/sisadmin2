<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/core/Modelo.php';

class CostosModel extends Modelo
{
    /**
     * Obtiene la lista de plantas con sus tarifas.
     */
    public function obtenerPlantas(): array
    {
        $sql = "SELECT id, nombre, tarifa_mod_hora, tarifa_cif_hora 
                FROM almacenes 
                WHERE tipo = 'Planta' AND estado = 1 AND deleted_at IS NULL 
                ORDER BY nombre ASC";
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Actualiza las tarifas de una planta específica.
     */
    public function actualizarTarifasPlanta(int $idPlanta, float $tarifaMod, float $tarifaCif): bool
    {
        $sql = "UPDATE almacenes 
                SET tarifa_mod_hora = :mod, 
                    tarifa_cif_hora = :cif 
                WHERE id = :id AND tipo = 'Planta'";
                
        $stmt = $this->db()->prepare($sql);
        return $stmt->execute([
            'mod' => number_format($tarifaMod, 4, '.', ''),
            'cif' => number_format($tarifaCif, 4, '.', ''),
            'id' => $idPlanta
        ]);
    }
}