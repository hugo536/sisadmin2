<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/core/Modelo.php';

class CierresModel extends Modelo
{
    /**
     * Obtiene el listado de cierres ya realizados
     */
    public function listarCierres(): array
    {
        $sql = "SELECT c.*, u.usuario AS usuario_nombre 
                FROM produccion_cierres_costos c
                LEFT JOIN usuarios u ON u.id = c.created_by
                ORDER BY c.periodo DESC";
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Suma toda la MOD y CIF que las órdenes de producción absorbieron en un mes específico
     */
    public function obtenerCostosAbsorbidosPorPeriodo(string $periodoYYYYMM): array
    {
        $sql = "SELECT COALESCE(SUM(costo_mod_real), 0) AS total_mod_absorbida,
                       COALESCE(SUM(costo_cif_real), 0) AS total_cif_absorbido,
                       COUNT(id) AS total_ordenes
                FROM produccion_ordenes
                WHERE DATE_FORMAT(fecha_fin, '%Y-%m') = :periodo
                  AND estado = 2 
                  AND deleted_at IS NULL";
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['periodo' => $periodoYYYYMM]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_mod_absorbida' => 0, 'total_cif_absorbido' => 0, 'total_ordenes' => 0];
    }

    /**
     * Guarda el Cierre de Costos en la base de datos
     */
    public function registrarCierre(array $datos): int
    {
        $sql = "INSERT INTO produccion_cierres_costos 
                    (periodo, mod_absorbida, mod_real_pagada, mod_variacion, cif_absorbido, cif_real_pagado, cif_variacion, observaciones, created_by)
                VALUES 
                    (:periodo, :mod_abs, :mod_real, :mod_var, :cif_abs, :cif_real, :cif_var, :obs, :created_by)";
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'periodo' => $datos['periodo'],
            'mod_abs' => $datos['mod_absorbida'],
            'mod_real' => $datos['mod_real_pagada'],
            'mod_var' => $datos['mod_variacion'],
            'cif_abs' => $datos['cif_absorbido'],
            'cif_real' => $datos['cif_real_pagado'],
            'cif_var' => $datos['cif_variacion'],
            'obs' => empty($datos['observaciones']) ? null : $datos['observaciones'],
            'created_by' => $datos['created_by']
        ]);

        return (int) $this->db()->lastInsertId();
    }
}