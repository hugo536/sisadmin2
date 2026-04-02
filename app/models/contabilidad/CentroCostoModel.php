<?php

declare(strict_types=1);

class CentroCostoModel extends Modelo
{
    public function existe(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $stmt = $this->db()->prepare('SELECT 1 FROM conta_centros_costo WHERE id = :id AND estado = 1 AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        return (bool)$stmt->fetchColumn();
    }

    public function listar(): array
    {
        $stmt = $this->db()->query('SELECT * FROM conta_centros_costo WHERE deleted_at IS NULL ORDER BY estado DESC, codigo ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene la lista de todos los centros de costo activos
     * para usarlos en selects (como en el Inventario o Compras).
     */
    public function listarActivos(): array
    {
        $sql = "SELECT id, codigo, nombre 
                FROM conta_centros_costo 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                ORDER BY codigo ASC, nombre ASC";
                
        $stmt = $this->db()->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data, int $userId): int
    {
        $id = (int)($data['id'] ?? 0);
        $codigo = strtoupper(trim((string)($data['codigo'] ?? '')));
        $nombre = trim((string)($data['nombre'] ?? ''));
        $estado = (int)($data['estado'] ?? 1) === 1 ? 1 : 0;

        if ($codigo === '' || $nombre === '') {
            throw new RuntimeException('El código y el nombre son obligatorios.');
        }

        // 👇 NUEVA MEJORA: Validación de códigos duplicados 👇
        // Verificamos si existe otro centro de costo con el mismo código,
        // excluyendo el ID actual (por si estamos editando el mismo registro)
        $stmtCheck = $this->db()->prepare('SELECT 1 FROM conta_centros_costo WHERE codigo = :codigo AND id != :id AND deleted_at IS NULL LIMIT 1');
        $stmtCheck->execute([
            'codigo' => $codigo, 
            'id' => $id
        ]);
        
        if ($stmtCheck->fetchColumn()) {
            throw new RuntimeException("El código '{$codigo}' ya está siendo utilizado por otro Centro de Costo.");
        }
        // 👆 FIN DE LA MEJORA 👆

        if ($id > 0) {
            $stmt = $this->db()->prepare('UPDATE conta_centros_costo SET codigo = :codigo, nombre = :nombre, estado = :estado, updated_by = :user, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
            $stmt->execute(['codigo' => $codigo, 'nombre' => $nombre, 'estado' => $estado, 'user' => $userId, 'id' => $id]);
            return $id;
        }

        $stmt = $this->db()->prepare('INSERT INTO conta_centros_costo (codigo, nombre, estado, created_by, updated_by, created_at, updated_at) VALUES (:codigo, :nombre, :estado, :user_created, :user_updated, NOW(), NOW())');
        
        $stmt->execute([
            'codigo' => $codigo, 
            'nombre' => $nombre, 
            'estado' => $estado, 
            'user_created' => $userId, 
            'user_updated' => $userId  
        ]);
        
        return (int)$this->db()->lastInsertId();
    }
}