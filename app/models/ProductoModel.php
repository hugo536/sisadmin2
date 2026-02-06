<?php
declare(strict_types=1);

class ProductoModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, sku, nombre, descripcion, tipo_item, id_categoria, marca,
                       unidad_base, controla_stock, stock_minimo, precio_venta,
                       costo_referencial, estado
                FROM items
                WHERE deleted_at IS NULL
                ORDER BY id DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obtener(int $id): array
    {
        $sql = 'SELECT id, sku, nombre, descripcion, tipo_item, id_categoria, marca,
                       unidad_base, controla_stock, stock_minimo, precio_venta,
                       costo_referencial, estado
                FROM items
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function skuExiste(string $sku, ?int $excludeId = null): bool
    {
        $sql = 'SELECT 1 FROM items WHERE sku = :sku AND deleted_at IS NULL';
        $params = ['sku' => $sku];

        if ($excludeId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    public function crear(array $data): int
    {
        $sql = 'INSERT INTO items (sku, nombre, descripcion, tipo_item, id_categoria, marca,
                                  unidad_base, controla_stock, stock_minimo, precio_venta,
                                  costo_referencial, estado)
                VALUES (:sku, :nombre, :descripcion, :tipo_item, :id_categoria, :marca,
                        :unidad_base, :controla_stock, :stock_minimo, :precio_venta,
                        :costo_referencial, :estado)';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($this->mapPayload($data));

        return (int) $this->db()->lastInsertId();
    }

    public function actualizar(int $id, array $data): bool
    {
        $sql = 'UPDATE items
                SET sku = :sku,
                    nombre = :nombre,
                    descripcion = :descripcion,
                    tipo_item = :tipo_item,
                    id_categoria = :id_categoria,
                    marca = :marca,
                    unidad_base = :unidad_base,
                    controla_stock = :controla_stock,
                    stock_minimo = :stock_minimo,
                    precio_venta = :precio_venta,
                    costo_referencial = :costo_referencial,
                    estado = :estado
                WHERE id = :id
                  AND deleted_at IS NULL';
        $payload = $this->mapPayload($data);
        $payload['id'] = $id;

        return $this->db()->prepare($sql)->execute($payload);
    }

    public function eliminar(int $id): bool
    {
        $sql = 'UPDATE items
                SET estado = 0,
                    deleted_at = NOW()
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute(['id' => $id]);
    }

    public function datatable(): array
    {
        $items = $this->listar();
        $data = array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'],
                'sku' => (string) $item['sku'],
                'nombre' => (string) $item['nombre'],
                'descripcion' => (string) ($item['descripcion'] ?? ''),
                'tipo_item' => (string) $item['tipo_item'],
                'id_categoria' => $item['id_categoria'] !== null ? (int) $item['id_categoria'] : null,
                'marca' => (string) ($item['marca'] ?? ''),
                'unidad_base' => (string) ($item['unidad_base'] ?? ''),
                'controla_stock' => (int) ($item['controla_stock'] ?? 0),
                'stock_minimo' => (float) ($item['stock_minimo'] ?? 0),
                'precio_venta' => (float) ($item['precio_venta'] ?? 0),
                'costo_referencial' => (float) ($item['costo_referencial'] ?? 0),
                'estado' => (int) ($item['estado'] ?? 1),
            ];
        }, $items);

        return [
            'data' => $data,
            'recordsTotal' => count($data),
            'recordsFiltered' => count($data),
        ];
    }

    private function mapPayload(array $data): array
    {
        return [
            'sku' => trim((string) ($data['sku'] ?? '')),
            'nombre' => trim((string) ($data['nombre'] ?? '')),
            'descripcion' => trim((string) ($data['descripcion'] ?? '')),
            'tipo_item' => trim((string) ($data['tipo_item'] ?? '')),
            'id_categoria' => $data['id_categoria'] !== '' ? $data['id_categoria'] : null,
            'marca' => trim((string) ($data['marca'] ?? '')),
            'unidad_base' => trim((string) ($data['unidad_base'] ?? 'UND')),
            'controla_stock' => !empty($data['controla_stock']) ? 1 : 0,
            'stock_minimo' => (float) ($data['stock_minimo'] ?? 0),
            'precio_venta' => (float) ($data['precio_venta'] ?? 0),
            'costo_referencial' => (float) ($data['costo_referencial'] ?? 0),
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }
}