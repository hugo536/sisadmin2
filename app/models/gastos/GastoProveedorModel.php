<?php

declare(strict_types=1);

class GastoProveedorModel extends Modelo
{
    public function listarActivos(): array
    {
        $stmt = $this->db()->query('SELECT id, nombre_completo FROM terceros WHERE es_proveedor = 1 AND estado = 1 AND deleted_at IS NULL ORDER BY nombre_completo ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
