<?php

declare(strict_types=1);

class TesoreriaCuentaModel extends Modelo
{
    public function listarActivas(): array
    {
        $sql = 'SELECT id, codigo, nombre, tipo, moneda
                FROM tesoreria_cuentas
                WHERE estado = 1 
                  AND deleted_at IS NULL
                ORDER BY tipo ASC, nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerPorId(int $id): ?array
    {
        $sql = 'SELECT * FROM tesoreria_cuentas 
                WHERE id = :id 
                  AND deleted_at IS NULL 
                LIMIT 1';
                
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ?: null;
    }
}