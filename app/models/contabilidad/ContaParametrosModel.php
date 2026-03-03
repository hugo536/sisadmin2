<?php

declare(strict_types=1);

class ContaParametrosModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT p.id, p.clave, p.id_cuenta, c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre
                FROM conta_parametros p
                INNER JOIN conta_cuentas c ON c.id = p.id_cuenta
                WHERE p.deleted_at IS NULL
                ORDER BY p.clave ASC';
        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerMapa(): array
    {
        $rows = $this->listar();
        $map = [];
        foreach ($rows as $row) {
            $map[(string)$row['clave']] = (int)$row['id_cuenta'];
        }
        return $map;
    }

    public function guardar(string $clave, int $idCuenta, int $userId): void
    {
        $stmt = $this->db()->prepare('SELECT id FROM conta_parametros WHERE clave = :clave AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['clave' => $clave]);
        $id = (int)$stmt->fetchColumn();

        if ($id > 0) {
            $upd = $this->db()->prepare('UPDATE conta_parametros SET id_cuenta = :id_cuenta, updated_by = :user, updated_at = NOW() WHERE id = :id');
            $upd->execute(['id_cuenta' => $idCuenta, 'user' => $userId, 'id' => $id]);
            return;
        }

        $ins = $this->db()->prepare('INSERT INTO conta_parametros (clave, id_cuenta, created_by, updated_by, created_at, updated_at)
                                     VALUES (:clave, :id_cuenta, :created_by, :updated_by, NOW(), NOW())');
        $ins->execute([
            'clave' => $clave,
            'id_cuenta' => $idCuenta,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);
    }
}
