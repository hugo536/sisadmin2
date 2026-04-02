<?php

declare(strict_types=1);

class ContaCuentaModel extends Modelo
{
    // Busca tu función listar() y cámbiala por esta:
    public function listar(array $filtros = []): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        $estado = (string)($filtros['estado'] ?? '');
        if ($estado !== '' && in_array($estado, ['1', '0'], true)) {
            $where[] = 'estado = :estado';
            $params['estado'] = (int)$estado;
        }

        $sql = 'SELECT id, codigo, nombre, tipo, nivel, id_padre, permite_movimiento, estado
                FROM conta_cuentas
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY codigo ASC';
        
        $stmt = $this->db()->prepare($sql);

        // Esta validación es la que evita el error HY093
        if (empty($params)) {
            $stmt->execute();
        } else {
            $stmt->execute($params);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarMovimientoActivas(): array
    {
        $sql = 'SELECT id, codigo, nombre
                FROM conta_cuentas
                WHERE deleted_at IS NULL AND estado = 1 AND permite_movimiento = 1
                ORDER BY codigo ASC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function guardar(array $data, int $userId): int
    {
        $id = (int)($data['id'] ?? 0);
        $idPadre = (isset($data['id_padre']) && (int)$data['id_padre'] > 0) ? (int)$data['id_padre'] : null;
        $permiteMovimiento = (int)($data['permite_movimiento'] ?? 0);

        if ($idPadre === $id && $id > 0) {
            throw new RuntimeException('Una cuenta no puede ser padre de sí misma.');
        }

        if ($id > 0 && $permiteMovimiento === 1) {
            $stmtHijos = $this->db()->prepare('SELECT COUNT(1) FROM conta_cuentas WHERE id_padre = :id_padre AND deleted_at IS NULL');
            $stmtHijos->execute(['id_padre' => $id]);
            if ((int)$stmtHijos->fetchColumn() > 0) {
                throw new RuntimeException('Una cuenta con hijos no puede marcarse como cuenta de movimiento.');
            }
        }

        if ($id > 0) {
            // --- BLOQUE UPDATE ---
            $sql = 'UPDATE conta_cuentas 
                    SET codigo = :codigo, 
                        nombre = :nombre, 
                        tipo = :tipo, 
                        nivel = :nivel, 
                        id_padre = :id_padre, 
                        permite_movimiento = :permite_movimiento, 
                        estado = :estado, 
                        updated_by = :updated_by, 
                        updated_at = NOW() 
                    WHERE id = :id AND deleted_at IS NULL';
            
            $stmt = $this->db()->prepare($sql);
            $stmt->execute([
                'id'                 => $id,
                'codigo'             => trim((string)$data['codigo']),
                'nombre'             => trim((string)$data['nombre']),
                'tipo'               => trim((string)$data['tipo']),
                'nivel'              => max(1, (int)$data['nivel']),
                'id_padre'           => $idPadre,
                'permite_movimiento' => $permiteMovimiento,
                'estado'             => (int)($data['estado'] ?? 1),
                'updated_by'         => $userId
            ]);
            return $id;
        }

        // --- BLOQUE INSERT ---
        $sql = 'INSERT INTO conta_cuentas (codigo, nombre, tipo, nivel, id_padre, permite_movimiento, estado, created_by, updated_by, created_at, updated_at) 
                VALUES (:codigo, :nombre, :tipo, :nivel, :id_padre, :permite_movimiento, :estado, :created_by, :updated_by, NOW(), NOW())';
        
        $stmt = $this->db()->prepare($sql);
        
        // Asegúrate de que este array tenga exactamente las llaves que pusiste con ":" en el SQL
        $stmt->execute([
            'codigo'             => trim((string)$data['codigo']),
            'nombre'             => trim((string)$data['nombre']),
            'tipo'               => trim((string)$data['tipo']),
            'nivel'              => max(1, (int)$data['nivel']),
            'id_padre'           => $idPadre,
            'permite_movimiento' => $permiteMovimiento,
            'estado'             => (int)($data['estado'] ?? 1),
            'created_by'         => $userId,
            'updated_by'         => $userId
        ]);

        return (int)$this->db()->lastInsertId();
    }

    public function inactivar(int $id, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE conta_cuentas SET estado = 0, updated_by = :user, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id, 'user' => $userId]);
    }

   public function cambiarEstado(int $id, int $estado, int $userId): void
    {
        // 1. Forzamos que el estado sea estrictamente 0 o 1
        $nuevoEstado = ($estado >= 1) ? 1 : 0;

        $sql = 'UPDATE conta_cuentas 
                SET estado = :estado, 
                    updated_by = :user, 
                    updated_at = NOW() 
                WHERE id = :id';
        
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id'     => $id,
            'estado' => $nuevoEstado, // Enviamos el entero limpio
            'user'   => $userId
        ]);
    }
}
