<?php
declare(strict_types=1);

class BitacoraModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT *
                FROM bitacora_seguridad
                WHERE deleted_at IS NULL
                ORDER BY id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        return is_array($rows) ? $rows : [];
    }
}
