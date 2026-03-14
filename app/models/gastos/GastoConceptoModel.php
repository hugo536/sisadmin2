<?php

declare(strict_types=1);

class GastoConceptoModel extends Modelo
{
    public function listar(array $filtros = []): array
    {
        $where = ['gc.deleted_at IS NULL'];
        $params = [];

        if (!empty($filtros['estado'])) {
            $where[] = 'gc.estado = :estado';
            $params['estado'] = (int) $filtros['estado'];
        }

        if (!empty($filtros['q'])) {
            $where[] = '(gc.codigo LIKE :q OR gc.nombre LIKE :q OR cc.nombre LIKE :q)';
            $params['q'] = '%' . trim((string) $filtros['q']) . '%';
        }

        $sql = 'SELECT gc.*, cc.codigo AS centro_costo_codigo, cc.nombre AS centro_costo_nombre,
                       (SELECT COUNT(*) FROM gastos_registros gr WHERE gr.id_concepto = gc.id AND gr.deleted_at IS NULL) AS total_relaciones
                FROM gastos_conceptos gc
                LEFT JOIN conta_centros_costo cc ON cc.id = gc.id_centro_costo
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY gc.id DESC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listarActivos(): array
    {
        $stmt = $this->db()->query('SELECT id, codigo, nombre, id_cuenta_contable FROM gastos_conceptos WHERE estado = 1 AND deleted_at IS NULL ORDER BY nombre ASC');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function siguienteCodigo(): string
    {
        $stmt = $this->db()->query('SELECT COALESCE(MAX(id), 0) + 1 FROM gastos_conceptos');
        $num = (int) $stmt->fetchColumn();
        return 'GAS-' . str_pad((string) $num, 5, '0', STR_PAD_LEFT);
    }

    public function crear(array $data, int $userId): int
    {
        $codigo = trim((string) ($data['codigo'] ?? ''));
        $nombre = trim((string) ($data['nombre'] ?? ''));
        $idCentroCosto = (int) ($data['id_centro_costo'] ?? 0);
        $recurrente = (int) ($data['es_recurrente'] ?? 0) === 1 ? 1 : 0;
        $diaVencimiento = $recurrente ? (int) ($data['dia_vencimiento'] ?? 0) : null;
        $diasAnticipacion = $recurrente ? (int) ($data['dias_anticipacion'] ?? 0) : null;

        if ($codigo === '' || $nombre === '' || $idCentroCosto <= 0) {
            throw new RuntimeException('Código, nombre y centro de costo son obligatorios.');
        }

        $stmt = $this->db()->prepare('INSERT INTO gastos_conceptos
            (codigo, nombre, id_centro_costo, es_recurrente, dia_vencimiento, dias_anticipacion, estado, created_by, updated_by, created_at, updated_at)
            VALUES
            (:codigo, :nombre, :id_centro_costo, :es_recurrente, :dia_vencimiento, :dias_anticipacion, 1, :created_by, :updated_by, NOW(), NOW())');

        $stmt->execute([
            'codigo' => $codigo,
            'nombre' => $nombre,
            'id_centro_costo' => $idCentroCosto,
            'es_recurrente' => $recurrente,
            'dia_vencimiento' => $diaVencimiento,
            'dias_anticipacion' => $diasAnticipacion,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function obtenerPorId(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db()->prepare('SELECT * FROM gastos_conceptos WHERE id = :id AND deleted_at IS NULL LIMIT 1');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function actualizar(int $id, array $data, int $userId): void
    {
        $concepto = $this->obtenerPorId($id);
        if (!$concepto) {
            throw new RuntimeException('El concepto no existe o fue eliminado.');
        }

        $nombre = trim((string) ($data['nombre'] ?? ''));
        $idCentroCosto = (int) ($data['id_centro_costo'] ?? 0);
        $recurrente = (int) ($data['es_recurrente'] ?? 0) === 1 ? 1 : 0;
        $diaVencimiento = $recurrente ? (int) ($data['dia_vencimiento'] ?? 0) : null;
        $diasAnticipacion = $recurrente ? (int) ($data['dias_anticipacion'] ?? 0) : null;

        if ($nombre === '' || $idCentroCosto <= 0) {
            throw new RuntimeException('Nombre y centro de costo son obligatorios.');
        }

        $stmt = $this->db()->prepare('UPDATE gastos_conceptos
            SET nombre = :nombre,
                id_centro_costo = :id_centro_costo,
                es_recurrente = :es_recurrente,
                dia_vencimiento = :dia_vencimiento,
                dias_anticipacion = :dias_anticipacion,
                updated_by = :updated_by,
                updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');

        $stmt->execute([
            'id' => $id,
            'nombre' => $nombre,
            'id_centro_costo' => $idCentroCosto,
            'es_recurrente' => $recurrente,
            'dia_vencimiento' => $diaVencimiento,
            'dias_anticipacion' => $diasAnticipacion,
            'updated_by' => $userId,
        ]);
    }

    public function tieneRegistrosRelacionados(int $id): bool
    {
        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM gastos_registros WHERE id_concepto = :id AND deleted_at IS NULL');
        $stmt->execute(['id' => $id]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public function desactivar(int $id, int $userId): void
    {
        $stmt = $this->db()->prepare('UPDATE gastos_conceptos
            SET estado = 0, updated_by = :user, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'user' => $userId,
        ]);
    }

    public function eliminar(int $id, int $userId): void
    {
        if ($this->tieneRegistrosRelacionados($id)) {
            throw new RuntimeException('No se puede eliminar el concepto porque tiene datos relacionados.');
        }

        $stmt = $this->db()->prepare('UPDATE gastos_conceptos
            SET deleted_at = NOW(), updated_by = :user, updated_at = NOW()
            WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id' => $id,
            'user' => $userId,
        ]);
    }

    public function vincularCuentaContablePorClave(string $clave, int $idCuenta, int $userId): void
    {
        if (!preg_match('/^GASTO_CONCEPTO_(\d+)$/', $clave, $matches)) {
            return;
        }

        $idConcepto = (int) $matches[1];
        if ($idConcepto <= 0) {
            return;
        }

        $stmt = $this->db()->prepare('UPDATE gastos_conceptos SET id_cuenta_contable = :id_cuenta, updated_by = :user, updated_at = NOW() WHERE id = :id AND deleted_at IS NULL');
        $stmt->execute([
            'id_cuenta' => $idCuenta,
            'user' => $userId,
            'id' => $idConcepto,
        ]);
    }
}
