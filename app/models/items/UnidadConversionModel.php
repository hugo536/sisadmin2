<?php
declare(strict_types=1);

class UnidadConversionModel extends Modelo
{
    public function listarUnidadesConversion(): array
    {
        $sql = 'SELECT i.id, i.sku, i.nombre, i.unidad_base, i.requiere_factor_conversion,
                       COUNT(u.id) AS total_unidades
                FROM items i
                LEFT JOIN items_unidades u ON u.id_item = i.id AND u.deleted_at IS NULL AND u.estado = 1
                WHERE i.deleted_at IS NULL
                  AND i.requiere_factor_conversion = 1
                GROUP BY i.id, i.sku, i.nombre, i.unidad_base, i.requiere_factor_conversion
                ORDER BY (COUNT(u.id) = 0) DESC, i.nombre ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarDetalleUnidadesConversion(int $idItem): array
    {
        if ($idItem <= 0) {
            return [];
        }

        // AGREGADO: Se incluyó 'es_predeterminada' en el SELECT
        $sql = 'SELECT id, id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado, es_predeterminada
                FROM items_unidades
                WHERE id_item = :id_item
                  AND deleted_at IS NULL
                ORDER BY nombre ASC, id ASC';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $idItem]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearUnidadConversion(array $data, int $userId): int
    {
        $sql = 'INSERT INTO items_unidades (id_item, nombre, codigo_unidad, factor_conversion, peso_kg, estado, created_by, updated_by, created_at, updated_at)
                VALUES (:id_item, :nombre, :codigo_unidad, :factor_conversion, :peso_kg, :estado, :created_by, :updated_by, NOW(), NOW())';

        $stmt = $this->db()->prepare($sql);
        $stmt->execute([
            'id_item' => (int) $data['id_item'],
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
            'created_by' => $userId,
            'updated_by' => $userId
        ]);

        return (int) $this->db()->lastInsertId();
    }

    public function actualizarUnidadConversion(int $id, array $data, int $userId): bool
    {
        $sql = 'UPDATE items_unidades
                SET nombre = :nombre,
                    codigo_unidad = :codigo_unidad,
                    factor_conversion = :factor_conversion,
                    peso_kg = :peso_kg,
                    estado = :estado,
                    updated_at = NOW(),
                    updated_by = :updated_by
                WHERE id = :id
                  AND id_item = :id_item
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'nombre' => trim((string) $data['nombre']),
            'codigo_unidad' => trim((string) ($data['codigo_unidad'] ?? '')) !== '' ? trim((string) $data['codigo_unidad']) : null,
            'factor_conversion' => (float) ($data['factor_conversion'] ?? 1),
            'peso_kg' => (float) ($data['peso_kg'] ?? 0),
            'estado' => (int) ($data['estado'] ?? 1),
            'updated_by' => $userId,
            'id' => $id,
            'id_item' => (int) $data['id_item']
        ]);
    }

    public function eliminarUnidadConversion(int $id, int $idItem, int $userId): bool
    {
        $bloqueos = $this->obtenerBloqueosEliminacionUnidad($id);
        if ($bloqueos !== []) {
            throw new RuntimeException('No se puede eliminar esta unidad porque ya tiene uso en: ' . implode(', ', $bloqueos) . '.');
        }

        $sql = 'UPDATE items_unidades
                SET deleted_at = NOW(),
                    deleted_by = :deleted_by,
                    updated_at = NOW(),
                    updated_by = :updated_by,
                    estado = 0
                WHERE id = :id
                  AND id_item = :id_item
                  AND deleted_at IS NULL';

        return $this->db()->prepare($sql)->execute([
            'deleted_by' => $userId,
            'updated_by' => $userId,
            'id' => $id,
            'id_item' => $idItem
        ]);
    }

    private function obtenerBloqueosEliminacionUnidad(int $idUnidad): array
    {
        $db = $this->db();
        $usos = [];

        $checks = [
            'compras_ordenes_detalle' => 'SELECT COUNT(*) FROM compras_ordenes_detalle WHERE id_item_unidad = :id',
            'compras_recepciones_detalle' => 'SELECT COUNT(*) FROM compras_recepciones_detalle WHERE id_item_unidad = :id',
            'movimientos_inventario_detalle' => 'SELECT COUNT(*) FROM movimientos_inventario_detalle WHERE id_item_unidad = :id',
            'comercial_acuerdos_proveedor_precios' => 'SELECT COUNT(*) FROM comercial_acuerdos_proveedor_precios WHERE id_unidad_conversion = :id',
        ];

        foreach ($checks as $tabla => $sql) {
            if (!$this->tablaExiste($tabla)) {
                continue;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute(['id' => $idUnidad]);
            if ((int) $stmt->fetchColumn() > 0) {
                $usos[] = $tabla;
            }
        }

        return $usos;
    }

    private function tablaExiste(string $tabla): bool
    {
        try {
            $stmt = $this->db()->prepare('SHOW TABLES LIKE :tabla');
            $stmt->execute(['tabla' => $tabla]);
            return (bool) $stmt->fetch(PDO::FETCH_NUM);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * =========================================================================
     * NUEVA FUNCIÓN: Marca una unidad como predeterminada y apaga las demás
     * =========================================================================
     */
    public function marcarComoPredeterminada(int $idUnidad, int $idItem, int $userId): bool
    {
        $db = $this->db();
        $db->beginTransaction();

        try {
            // 1. Apagamos todas las unidades de este ítem (es_predeterminada = 0)
            $stmt1 = $db->prepare('UPDATE items_unidades 
                                   SET es_predeterminada = 0, updated_by = :user, updated_at = NOW() 
                                   WHERE id_item = :id_item');
            $stmt1->execute(['user' => $userId, 'id_item' => $idItem]);

            // 2. Encendemos SOLO la unidad que el usuario seleccionó (es_predeterminada = 1)
            $stmt2 = $db->prepare('UPDATE items_unidades 
                                   SET es_predeterminada = 1, updated_by = :user, updated_at = NOW() 
                                   WHERE id = :id AND id_item = :id_item');
            $stmt2->execute(['user' => $userId, 'id' => $idUnidad, 'id_item' => $idItem]);

            $db->commit();
            return true;

        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
