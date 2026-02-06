<?php
declare(strict_types=1);

class TerceroModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, tipo_persona, tipo_documento, numero_documento, nombre_completo,
                       direccion, telefono, email, es_cliente, es_proveedor, es_empleado, estado
                FROM terceros
                WHERE deleted_at IS NULL
                ORDER BY id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT id, tipo_persona, tipo_documento, numero_documento, nombre_completo,
                       direccion, telefono, email, es_cliente, es_proveedor, es_empleado, estado
                FROM terceros
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function crear(array $data, int $userId): int
    {
        $payload = $this->mapPayload($data);
        $existente = $this->buscarPorDocumento($payload['tipo_documento'], $payload['numero_documento']);

        if ($existente !== []) {
            $payload['id'] = (int) $existente['id'];
            $payload['es_cliente'] = max((int) $existente['es_cliente'], $payload['es_cliente']);
            $payload['es_proveedor'] = max((int) $existente['es_proveedor'], $payload['es_proveedor']);
            $payload['es_empleado'] = max((int) $existente['es_empleado'], $payload['es_empleado']);
            $payload['updated_by'] = $userId;

            $sql = 'UPDATE terceros
                    SET tipo_persona = :tipo_persona,
                        nombre_completo = :nombre_completo,
                        direccion = :direccion,
                        telefono = :telefono,
                        email = :email,
                        es_cliente = :es_cliente,
                        es_proveedor = :es_proveedor,
                        es_empleado = :es_empleado,
                        estado = :estado,
                        updated_by = :updated_by,
                        deleted_at = NULL
                    WHERE id = :id';
            $this->db()->prepare($sql)->execute($payload);

            return (int) $existente['id'];
        }

        $sql = 'INSERT INTO terceros (tipo_persona, tipo_documento, numero_documento, nombre_completo,
                                     direccion, telefono, email, es_cliente, es_proveedor, es_empleado,
                                     estado, created_by, updated_by)
                VALUES (:tipo_persona, :tipo_documento, :numero_documento, :nombre_completo,
                        :direccion, :telefono, :email, :es_cliente, :es_proveedor, :es_empleado,
                        :estado, :created_by, :updated_by)';
        $payload['created_by'] = $userId;
        $payload['updated_by'] = $userId;
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($payload);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizar(int $id, array $data, int $userId): bool
    {
        $payload = $this->mapPayload($data);
        $payload['id'] = $id;
        $payload['updated_by'] = $userId;

        $sql = 'UPDATE terceros
                SET tipo_persona = :tipo_persona,
                    tipo_documento = :tipo_documento,
                    numero_documento = :numero_documento,
                    nombre_completo = :nombre_completo,
                    direccion = :direccion,
                    telefono = :telefono,
                    email = :email,
                    es_cliente = :es_cliente,
                    es_proveedor = :es_proveedor,
                    es_empleado = :es_empleado,
                    estado = :estado,
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute($payload);
    }

    public function eliminar(int $id, int $userId): bool
    {
        $sql = 'UPDATE terceros
                SET estado = 0,
                    deleted_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    public function documentoExiste(string $tipo, string $numero, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM terceros WHERE tipo_documento = :tipo AND numero_documento = :numero AND deleted_at IS NULL';
        $params = [
            'tipo' => $tipo,
            'numero' => $numero,
        ];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function buscarPorDocumento(string $tipo, string $numero): array
    {
        $sql = 'SELECT id, es_cliente, es_proveedor, es_empleado
                FROM terceros
                WHERE tipo_documento = :tipo
                  AND numero_documento = :numero
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'tipo' => $tipo,
            'numero' => $numero,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    private function mapPayload(array $data): array
    {
        return [
            'tipo_persona' => trim((string) ($data['tipo_persona'] ?? 'NATURAL')),
            'tipo_documento' => trim((string) ($data['tipo_documento'] ?? '')),
            'numero_documento' => trim((string) ($data['numero_documento'] ?? '')),
            'nombre_completo' => trim((string) ($data['nombre_completo'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'es_cliente' => !empty($data['es_cliente']) ? 1 : 0,
            'es_proveedor' => !empty($data['es_proveedor']) ? 1 : 0,
            'es_empleado' => !empty($data['es_empleado']) ? 1 : 0,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }
}
