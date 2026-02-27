<?php
declare(strict_types=1);

class ItemPerfilModel extends Modelo
{
    public function obtenerPerfil(int $id): array
    {
        $sql = 'SELECT i.id, i.sku, i.nombre, i.descripcion, i.tipo_item, i.id_rubro, i.id_categoria,
                       r.nombre AS rubro_nombre,
                       c.nombre AS categoria_nombre,
                       i.id_marca, i.id_sabor, i.id_presentacion,
                       i.marca, i.unidad_base, i.peso_kg, i.permite_decimales, i.requiere_lote, i.requiere_vencimiento,
                       i.dias_alerta_vencimiento, i.controla_stock, i.requiere_formula_bom,
                       i.requiere_factor_conversion, i.es_envase_retornable, i.stock_minimo, i.precio_venta,
                       i.costo_referencial, i.moneda, i.impuesto_porcentaje AS impuesto, i.estado,
                       i.created_at, i.updated_at
                FROM items i
                LEFT JOIN item_rubros r ON r.id = i.id_rubro
                LEFT JOIN categorias c ON c.id = i.id_categoria
                WHERE i.id = :id
                  AND i.deleted_at IS NULL
                LIMIT 1';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: [];
    }

    public function listarDocumentos(int $itemId): array
    {
        $sql = 'SELECT * FROM item_documentos WHERE id_item = :id_item AND estado = 1 ORDER BY created_at DESC';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_item' => $itemId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function guardarDocumento(array $docData): bool
    {
        $sql = 'INSERT INTO item_documentos (id_item, tipo_documento, nombre_archivo, ruta_archivo, extension, created_by, created_at, updated_at)
                VALUES (:id_item, :tipo_documento, :nombre_archivo, :ruta_archivo, :extension, :created_by, NOW(), NOW())';

        return $this->db()->prepare($sql)->execute($docData);
    }

    public function actualizarDocumento(int $id, string $tipo): bool
    {
        $sql = 'UPDATE item_documentos SET tipo_documento = :tipo, updated_at = NOW() WHERE id = :id';

        return $this->db()->prepare($sql)->execute([
            'tipo' => $tipo,
            'id' => $id,
        ]);
    }

    public function eliminarDocumento(int $docId): bool
    {
        $stmt = $this->db()->prepare('SELECT ruta_archivo FROM item_documentos WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (is_array($row) && !empty($row['ruta_archivo'])) {
            $rutaPublica = ltrim((string) $row['ruta_archivo'], '/');
            $rutaFisica = BASE_PATH . '/public/' . $rutaPublica;
            if (is_file($rutaFisica)) {
                @unlink($rutaFisica);
            }
        }

        return $this->db()->prepare('DELETE FROM item_documentos WHERE id = :id')->execute(['id' => $docId]);
    }
}
