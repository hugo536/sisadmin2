<?php

declare(strict_types=1);

class CentroCostoModel extends Modelo
{
    public function listar(): array
    {
        $stmt = $this->db()->query('SELECT * FROM conta_centros_costo WHERE deleted_at IS NULL ORDER BY estado DESC, codigo ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data, int $userId): int
    {
        $id = (int)($data['id'] ?? 0);
        $codigo = strtoupper(trim((string)($data['codigo'] ?? '')));
        $nombre = trim((string)($data['nombre'] ?? ''));
        $estado = (int)($data['estado'] ?? 1) === 1 ? 1 : 0;

        if ($codigo === '' || $nombre === '') {
            throw new RuntimeException('Código y nombre son obligatorios.');
        }

        if ($id > 0) {
            $stmt = $this->db()->prepare('UPDATE conta_centros_costo SET codigo = :codigo, nombre = :nombre, estado = :estado, updated_by = :user, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(['codigo' => $codigo, 'nombre' => $nombre, 'estado' => $estado, 'user' => $userId, 'id' => $id]);
            return $id;
        }

        // 👇 ESTA ES LA PARTE QUE CORREGIMOS 👇
        $stmt = $this->db()->prepare('INSERT INTO conta_centros_costo (codigo, nombre, estado, created_by, updated_by, created_at, updated_at) VALUES (:codigo, :nombre, :estado, :user_created, :user_updated, NOW(), NOW())');
        
        $stmt->execute([
            'codigo' => $codigo, 
            'nombre' => $nombre, 
            'estado' => $estado, 
            'user_created' => $userId, // Pasamos el valor para created_by
            'user_updated' => $userId  // Pasamos el valor para updated_by
        ]);
        
        return (int)$this->db()->lastInsertId();
    }
}
