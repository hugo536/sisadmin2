<?php
declare(strict_types=1);

class ItemModel extends Modelo
{
    public function listar(): array
    {
        $sql = 'SELECT id, sku, nombre, descripcion, tipo_item, id_categoria, marca,
                       unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                       controla_stock, stock_minimo, precio_venta, costo_referencial,
                       moneda, impuesto, estado
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
                       unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                       controla_stock, stock_minimo, precio_venta, costo_referencial,
                       moneda, impuesto, estado
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
        $sql = 'SELECT 1 FROM items WHERE sku = :sku';
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

    public function crear(array $data, int $userId): int
    {
        $payload = $this->mapPayload($data);

        if ($payload['sku'] === '') {
            $payload['sku'] = $this->generarSku();
        }

        $payload['created_by'] = $userId;
        $payload['updated_by'] = $userId;

        $sql = 'INSERT INTO items (sku, nombre, descripcion, tipo_item, id_categoria, marca,
                                  unidad_base, permite_decimales, requiere_lote, requiere_vencimiento,
                                  controla_stock, stock_minimo, precio_venta, costo_referencial,
                                  moneda, impuesto, estado, created_by, updated_by)
                VALUES (:sku, :nombre, :descripcion, :tipo_item, :id_categoria, :marca,
                        :unidad_base, :permite_decimales, :requiere_lote, :requiere_vencimiento,
                        :controla_stock, :stock_minimo, :precio_venta, :costo_referencial,
                        :moneda, :impuesto, :estado, :created_by, :updated_by)';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($payload);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizar(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE items
                SET nombre = :nombre,
                    descripcion = :descripcion,
                    tipo_item = :tipo_item,
                    id_categoria = :id_categoria,
                    marca = :marca,
                    unidad_base = :unidad_base,
                    permite_decimales = :permite_decimales,
                    requiere_lote = :requiere_lote,
                    requiere_vencimiento = :requiere_vencimiento,
                    controla_stock = :controla_stock,
                    stock_minimo = :stock_minimo,
                    precio_venta = :precio_venta,
                    costo_referencial = :costo_referencial,
                    moneda = :moneda,
                    impuesto = :impuesto,
                    estado = :estado,
                    updated_by = :updated_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        $payload = $this->mapPayload($data);
        $payload['id'] = $id;
        $payload['updated_by'] = $userId;

        return $this->db()->prepare($sql)->execute($payload);
    }

    public function eliminar(int $id, int $userId): bool
    {
        $sql = 'UPDATE items
                SET estado = 0,
                    deleted_at = NOW(),
                    updated_by = :updated_by,
                    deleted_by = :deleted_by
                WHERE id = :id
                  AND deleted_at IS NULL';
        return $this->db()->prepare($sql)->execute([
            'id' => $id,
            'updated_by' => $userId,
            'deleted_by' => $userId,
        ]);
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
                'permite_decimales' => (int) ($item['permite_decimales'] ?? 0),
                'requiere_lote' => (int) ($item['requiere_lote'] ?? 0),
                'requiere_vencimiento' => (int) ($item['requiere_vencimiento'] ?? 0),
                'controla_stock' => (int) ($item['controla_stock'] ?? 0),
                'stock_minimo' => (float) ($item['stock_minimo'] ?? 0),
                'precio_venta' => (float) ($item['precio_venta'] ?? 0),
                'costo_referencial' => (float) ($item['costo_referencial'] ?? 0),
                'moneda' => (string) ($item['moneda'] ?? ''),
                'impuesto' => (float) ($item['impuesto'] ?? 0),
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
            'id_categoria' => (isset($data['id_categoria']) && $data['id_categoria'] !== '') ? $data['id_categoria'] : null,
            'marca' => trim((string) ($data['marca'] ?? '')),
            'unidad_base' => trim((string) ($data['unidad_base'] ?? 'UND')),
            'permite_decimales' => !empty($data['permite_decimales']) ? 1 : 0,
            'requiere_lote' => !empty($data['requiere_lote']) ? 1 : 0,
            'requiere_vencimiento' => !empty($data['requiere_vencimiento']) ? 1 : 0,
            'controla_stock' => !empty($data['controla_stock']) ? 1 : 0,
            'stock_minimo' => (float) ($data['stock_minimo'] ?? 0),
            'precio_venta' => (float) ($data['precio_venta'] ?? 0),
            'costo_referencial' => (float) ($data['costo_referencial'] ?? 0),
            'moneda' => trim((string) ($data['moneda'] ?? 'PEN')),
            'impuesto' => (float) ($data['impuesto'] ?? 0),
            'estado' => isset($data['estado']) ? (int) $data['estado'] : 1,
        ];
    }

    private function generarSku(): string
    {
        do {
            $sku = 'SKU-' . strtoupper(bin2hex(random_bytes(4)));
        } while ($this->skuExiste($sku));

        return $sku;
    }
}
