<?php
declare(strict_types=1);

class AlmacenModel extends Modelo
{
    public function listarActivos(): array
    {
        $sql = 'SELECT * FROM almacenes WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC';
        $stmt = $this->db()->query($sql);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}
