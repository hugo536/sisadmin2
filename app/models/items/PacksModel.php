<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/config/Conexion.php';

class PacksModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexion::get();
    }

    /**
     * 1. Obtiene los Combos/Packs para la lista izquierda
     * Ahora lee de la tabla 'items' donde el tipo sea 'pack'
     */
    public function obtenerTodosLosPacks(): array
    {
        $sql = "SELECT id, sku, nombre, precio_venta 
                FROM items 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                  AND LOWER(tipo_item) = 'pack'
                ORDER BY nombre ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 2. Guarda el Pack Padre (Nombre y Precio) en la tabla items
     */
    public function guardarPackPadre(array $payload): int
    {
        $id = (int) ($payload['id'] ?? 0);
        $nombre = trim((string) ($payload['nombre'] ?? ''));
        $precioVenta = (float) ($payload['precio_venta'] ?? 0);

        if ($nombre === '') {
            throw new RuntimeException('El nombre del combo es obligatorio.');
        }

        if ($id > 0) {
            // Actualizar pack existente
            $sql = "UPDATE items SET nombre = :nombre, precio_venta = :precio_venta WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre' => $nombre,
                'precio_venta' => $precioVenta,
                'id' => $id
            ]);
            return $id;
        } else {
            // Crear pack nuevo (se genera un SKU temporal)
            $skuNuevo = 'PACK-' . time();
            $sql = "INSERT INTO items (nombre, sku, precio_venta, tipo_item, estado) 
                    VALUES (:nombre, :sku, :precio_venta, 'pack', 1)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'nombre' => $nombre,
                'sku' => $skuNuevo,
                'precio_venta' => $precioVenta
            ]);
            return (int) $this->db->lastInsertId();
        }
    }

    /**
     * 3. Busca los productos para añadir al combo
     * CORRECCIÓN: Permite buscar productos terminados y material de empaque
     */
    public function buscarComponentes(string $termino): array
    {
        // Excluimos los que son tipo 'pack' para no meter un combo dentro de otro combo
        $sql = "SELECT id, nombre, sku, unidad_base, tipo_item
                FROM items
                WHERE estado = 1
                  AND deleted_at IS NULL
                  AND LOWER(tipo_item) != 'pack'";

        if ($termino !== '') {
            $sql .= " AND (nombre LIKE :termino OR sku LIKE :termino OR tipo_item LIKE :termino)";
        }

        $sql .= ' ORDER BY nombre ASC LIMIT 50';

        $stmt = $this->db->prepare($sql);
        if ($termino !== '') {
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 4. Obtiene la receta interna del pack
     */
    public function obtenerComponentesPorPack(int $idPack): array
    {
        // Asumimos que crearemos una tabla 'items_pack_detalle'
        $sql = "SELECT d.id AS id_detalle,
                       d.id_item_componente AS id_item,
                       i.nombre AS nombre_item,
                       d.cantidad,
                       COALESCE(d.es_bonificacion, 0) AS es_bonificacion,
                       i.unidad_base
                FROM items_pack_detalle d
                INNER JOIN items i ON i.id = d.id_item_componente
                WHERE d.id_item_pack = :id_pack
                ORDER BY d.id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_pack', $idPack, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 5. Añade un componente a la receta
     */
    public function agregarComponente(array $payload): int
    {
        $idPack = (int) ($payload['id_pack'] ?? 0);
        $idItem = (int) ($payload['id_item'] ?? 0);
        $cantidad = (float) ($payload['cantidad'] ?? 0);
        $esBonificacion = (int) ($payload['es_bonificacion'] ?? 0);

        if ($idPack <= 0 || $idItem <= 0 || $cantidad <= 0) {
            throw new RuntimeException('Datos inválidos para agregar el componente.');
        }

        // Verificamos que el componente no esté ya en este pack
        $sqlExiste = 'SELECT id FROM items_pack_detalle WHERE id_item_pack = :id_pack AND id_item_componente = :id_item LIMIT 1';
        $stmtExiste = $this->db->prepare($sqlExiste);
        $stmtExiste->execute(['id_pack' => $idPack, 'id_item' => $idItem]);
        
        if ($stmtExiste->fetchColumn()) {
            throw new RuntimeException('El componente ya existe en este combo.');
        }

        // Insertamos
        $sql = 'INSERT INTO items_pack_detalle (id_item_pack, id_item_componente, cantidad, es_bonificacion)
                VALUES (:id_pack, :id_item, :cantidad, :es_bonificacion)';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_pack' => $idPack,
            'id_item' => $idItem,
            'cantidad' => $cantidad,
            'es_bonificacion' => $esBonificacion
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * 6. Elimina un componente de la receta
     */
    public function eliminarComponente(int $idDetalle): bool
    {
        $sql = 'DELETE FROM items_pack_detalle WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $idDetalle, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }
}