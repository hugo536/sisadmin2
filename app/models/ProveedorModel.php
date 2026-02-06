<?php
declare(strict_types=1);

class ProveedorModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, tipo_documento, numero_documento, nombre_completo, direccion,
                       telefono, email, estado
                FROM terceros
                WHERE es_proveedor = 1
                  AND deleted_at IS NULL
                ORDER BY id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT id, tipo_documento, numero_documento, nombre_completo, direccion,
                       telefono, email, estado
                FROM terceros
                WHERE id = :id
                  AND es_proveedor = 1
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function crear(array $data): int
    {
        $payload = $this->mapPayload($data);
        $sql = 'INSERT INTO terceros (tipo_documento, numero_documento, nombre_completo, direccion,
                                     telefono, email, es_cliente, es_proveedor, estado)
                VALUES (:tipo_documento, :numero_documento, :nombre_completo, :direccion,
                        :telefono, :email, 0, 1, :estado)';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($payload);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizar(int $id, array $data): bool
    {
        $payload = $this->mapPayload($data);
        $payload['id'] = $id;
        $sql = 'UPDATE terceros
                SET tipo_documento = :tipo_documento,
                    numero_documento = :numero_documento,
                    nombre_completo = :nombre_completo,
                    direccion = :direccion,
                    telefono = :telefono,
                    email = :email,
                    estado = :estado,
                    es_proveedor = 1
                WHERE id = :id
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute($payload);
    }

    public function eliminar(int $id): bool
    {
        $sql = 'UPDATE terceros
                SET estado = 0,
                    deleted_at = NOW()
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute(['id' => $id]);
    }

    private function mapPayload(array $data): array
    {
        return [
            'tipo_documento' => trim((string) ($data['tipo_documento'] ?? '')),
            'numero_documento' => trim((string) ($data['numero_documento'] ?? '')),
            'nombre_completo' => trim((string) ($data['nombre_completo'] ?? '')),
            'direccion' => trim((string) ($data['direccion'] ?? '')),
            'telefono' => trim((string) ($data['telefono'] ?? '')),
            'email' => trim((string) ($data['email'] ?? '')),
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }
}