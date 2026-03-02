<?php

declare(strict_types=1);

class ContaPeriodoModel extends Modelo
{
    public function obtenerPorId(int $id): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM conta_periodos WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listarPorAnio(int $anio): array
    {
        $stmt = $this->db()->prepare('SELECT * FROM conta_periodos WHERE anio = :anio AND deleted_at IS NULL ORDER BY mes ASC');
        $stmt->execute(['anio' => $anio]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function obtenerPeriodoPorFecha(string $fecha): ?array
    {
        $stmt = $this->db()->prepare('SELECT * FROM conta_periodos WHERE :fecha BETWEEN fecha_inicio AND fecha_fin AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['fecha' => $fecha]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function crearSiNoExiste(int $anio, int $mes, int $userId): int
    {
        if ($anio < 2000 || $anio > 2100 || $mes < 1 || $mes > 12) {
            throw new RuntimeException('Año o mes inválido para periodo contable.');
        }
        $stmt = $this->db()->prepare('SELECT id FROM conta_periodos WHERE anio = :anio AND mes = :mes AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['anio' => $anio, 'mes' => $mes]);
        $id = (int)$stmt->fetchColumn();
        if ($id > 0) {
            return $id;
        }

        $inicio = sprintf('%04d-%02d-01', $anio, $mes);
        $fin = date('Y-m-t', strtotime($inicio));

        $stmtIns = $this->db()->prepare('INSERT INTO conta_periodos (anio, mes, fecha_inicio, fecha_fin, estado, created_by, updated_by, created_at, updated_at)
                                         VALUES (:anio, :mes, :fecha_inicio, :fecha_fin, "ABIERTO", :user, :user, NOW(), NOW())');
        $stmtIns->execute([
            'anio' => $anio,
            'mes' => $mes,
            'fecha_inicio' => $inicio,
            'fecha_fin' => $fin,
            'user' => $userId,
        ]);

        return (int)$this->db()->lastInsertId();
    }

    public function cambiarEstado(int $idPeriodo, string $estado, int $userId): void
    {
        $estado = strtoupper($estado);
        if (!in_array($estado, ['ABIERTO', 'CERRADO'], true)) {
            throw new RuntimeException('Estado de periodo inválido.');
        }

        $sql = 'UPDATE conta_periodos
                SET estado = :estado,
                    cerrado_at = ' . ($estado === 'CERRADO' ? 'NOW()' : 'NULL') . ',
                    cerrado_by = ' . ($estado === 'CERRADO' ? ':cerrado_by' : 'NULL') . ',
                    updated_by = :updated_by,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'estado' => $estado,
            'cerrado_by' => $userId,
            'updated_by' => $userId,
            'id' => $idPeriodo,
        ]);
    }
}
