<?php
declare(strict_types=1);

class AtributoModel extends Modelo
{
    private const TABLA_MARCAS = 'item_marcas';
    private const TABLA_SABORES = 'item_sabores';
    private const TABLA_PRESENTACIONES = 'item_presentaciones';

    public function listarMarcas(bool $soloActivos = false): array
    {
        return $this->listarAtributos(self::TABLA_MARCAS, $soloActivos);
    }

    public function listarSabores(bool $soloActivos = false): array
    {
        return $this->listarAtributos(self::TABLA_SABORES, $soloActivos);
    }

    public function listarPresentaciones(bool $soloActivos = false): array
    {
        return $this->listarAtributos(self::TABLA_PRESENTACIONES, $soloActivos);
    }

    public function crearMarca(array $data, int $userId): int
    {
        return $this->crearAtributo(self::TABLA_MARCAS, $data, $userId);
    }

    public function crearSabor(array $data, int $userId): int
    {
        return $this->crearAtributo(self::TABLA_SABORES, $data, $userId);
    }

    public function crearPresentacion(array $data, int $userId): int
    {
        return $this->crearAtributo(self::TABLA_PRESENTACIONES, $data, $userId);
    }

    public function actualizarMarca(int $id, array $data, int $userId): bool
    {
        return $this->actualizarAtributo(self::TABLA_MARCAS, $id, $data, $userId);
    }

    public function actualizarSabor(int $id, array $data, int $userId): bool
    {
        return $this->actualizarAtributo(self::TABLA_SABORES, $id, $data, $userId);
    }

    public function actualizarPresentacion(int $id, array $data, int $userId): bool
    {
        return $this->actualizarAtributo(self::TABLA_PRESENTACIONES, $id, $data, $userId);
    }

    public function eliminarMarca(int $id, int $userId): bool
    {
        return $this->eliminarAtributo(self::TABLA_MARCAS, $id, $userId);
    }

    public function eliminarSabor(int $id, int $userId): bool
    {
        return $this->eliminarAtributo(self::TABLA_SABORES, $id, $userId);
    }

    public function eliminarPresentacion(int $id, int $userId): bool
    {
        return $this->eliminarAtributo(self::TABLA_PRESENTACIONES, $id, $userId);
    }

    private function listarAtributos(string $tabla, bool $soloActivos = false): array
    {
        $sql = "SELECT id, nombre, estado\n                FROM {$tabla}\n                WHERE deleted_at IS NULL";

        if ($soloActivos) {
            $sql .= ' AND estado = 1';
        }

        $sql .= ' ORDER BY nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function crearAtributo(string $tabla, array $data, int $userId): int
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($this->atributoDuplicado($tabla, $nombre)) {
            throw new RuntimeException('Ya existe un registro con ese nombre.');
        }

        $sql = "INSERT INTO {$tabla} (nombre, estado, updated_by, created_at, updated_at)\n                VALUES (:nombre, :estado, :updated_by, NOW(), NOW())";

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId
        ]);

        return (int) $this->db()->lastInsertId();
    }

    private function actualizarAtributo(string $tabla, int $id, array $data, int $userId): bool
    {
        $nombre = trim((string) ($data['nombre'] ?? ''));
        if ($this->atributoDuplicado($tabla, $nombre, $id)) {
            throw new RuntimeException('Ya existe un registro con ese nombre.');
        }

        $sql = "UPDATE {$tabla}\n                SET nombre = :nombre,\n                    estado = :estado,\n                    updated_at = NOW(),\n                    updated_by = :updated_by\n                WHERE id = :id\n                  AND deleted_at IS NULL";

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'nombre' => $nombre,
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
            'updated_by' => $userId,
        ]);
    }

    private function eliminarAtributo(string $tabla, int $id, int $userId): bool
    {
        $sql = "UPDATE {$tabla}\n                SET estado = 0,\n                    deleted_at = NOW(),\n                    updated_at = NOW(),\n                    updated_by = :updated_by\n                WHERE id = :id\n                  AND deleted_at IS NULL";

        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'updated_by' => $userId,
        ]);
    }

    private function atributoDuplicado(string $tabla, string $nombre, ?int $excludeId = null): bool
    {
        $sql = "SELECT 1\n                FROM {$tabla}\n                WHERE LOWER(nombre) = LOWER(:nombre)\n                  AND deleted_at IS NULL";
        $params = ['nombre' => $nombre];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }
}
