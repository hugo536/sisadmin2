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
     * 1. Obtiene los packs desde la tabla correcta: precios_presentaciones
     */
    public function obtenerTodosLosPacks(): array
    {
        // ¡Ahora sí traemos el precio_venta real de la base de datos!
        $sql = "SELECT id, 'SIN-SKU' as sku, nombre, precio_venta 
                FROM precios_presentaciones 
                WHERE estado = 1 
                  AND deleted_at IS NULL 
                ORDER BY nombre ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 2. Guarda el Pack Padre en precios_presentaciones
     */
    public function guardarPackPadre(array $payload): int
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $id = (int) ($payload['id'] ?? 0);
        $nombre = trim((string) ($payload['nombre'] ?? ''));
        // ¡Ya activamos la variable del precio!
        $precioVenta = (float) ($payload['precio_venta'] ?? 0); 
        $usuarioId = (int) ($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 1);

        if ($nombre === '') {
            throw new RuntimeException('El nombre del combo es obligatorio.');
        }

        try {
            if ($id > 0) {
                // Actualizar pack existente incluyendo el precio
                $sql = "UPDATE precios_presentaciones 
                        SET nombre = :nombre, precio_venta = :precio_venta, updated_by = :updated_by 
                        WHERE id = :id";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre,
                    'precio_venta' => $precioVenta,
                    'updated_by' => $usuarioId,
                    'id' => $id
                ]);
                return $id;
            } else {
                // Insertar nuevo pack incluyendo el precio
                $sql = "INSERT INTO precios_presentaciones (nombre, precio_venta, estado, created_by, updated_by) 
                        VALUES (:nombre, :precio_venta, 1, :created_by, :updated_by)";
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    'nombre' => $nombre,
                    'precio_venta' => $precioVenta,
                    'created_by' => $usuarioId,
                    'updated_by' => $usuarioId
                ]);
                return (int) $this->db->lastInsertId();
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Error en Base de Datos: " . $e->getMessage());
        }
    }

    /**
     * 3. Busca los productos, envases e insumos para añadir al combo
     */
    public function buscarComponentes(string $termino): array
    {
        // Traemos TODO menos los que sean tipo 'pack'
        $sql = "SELECT id, nombre, sku, unidad_base, tipo_item
                FROM items
                WHERE estado = 1 
                  AND LOWER(tipo_item) != 'pack'";
                
        if ($termino !== '') {
            $sql .= " AND (nombre LIKE :termino OR sku LIKE :termino OR tipo_item LIKE :termino)";
        }
        $sql .= ' ORDER BY nombre ASC LIMIT 100'; // Traeremos hasta 100 para que TomSelect fluya bien

        $stmt = $this->db->prepare($sql);
        if ($termino !== '') {
            $stmt->bindValue(':termino', '%' . $termino . '%', PDO::PARAM_STR);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 4. Obtiene los componentes usando TU tabla precios_presentaciones_detalle
     */
    public function obtenerComponentesPorPack(int $idPack): array
    {
        $sql = "SELECT d.id AS id_detalle,
                       d.id_item,
                       i.nombre AS nombre_item,
                       d.cantidad,
                       COALESCE(d.es_bonificacion, 0) AS es_bonificacion,
                       i.unidad_base
                FROM precios_presentaciones_detalle d
                INNER JOIN items i ON i.id = d.id_item
                WHERE d.id_presentacion = :id_pack
                ORDER BY d.id ASC";
                
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_pack', $idPack, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * 5. Añade un componente a TU tabla precios_presentaciones_detalle
     */
    public function agregarComponente(array $payload): int
    {
        $idPack = (int) ($payload['id_pack'] ?? 0);
        $idItem = (int) ($payload['id_item'] ?? 0);
        $cantidad = (float) ($payload['cantidad'] ?? 0);
        $esBonificacion = (int) ($payload['es_bonificacion'] ?? 0);
        $usuarioId = (int) ($_SESSION['id'] ?? $_SESSION['usuario_id'] ?? 1);

        if ($idPack <= 0 || $idItem <= 0 || $cantidad <= 0) {
            throw new RuntimeException('Datos inválidos para agregar el componente.');
        }

        $sqlExiste = 'SELECT id FROM precios_presentaciones_detalle WHERE id_presentacion = :id_pack AND id_item = :id_item LIMIT 1';
        $stmtExiste = $this->db->prepare($sqlExiste);
        $stmtExiste->execute(['id_pack' => $idPack, 'id_item' => $idItem]);
        if ($stmtExiste->fetchColumn()) {
            throw new RuntimeException('El componente ya existe en este combo.');
        }

        $sql = 'INSERT INTO precios_presentaciones_detalle (id_presentacion, id_item, cantidad, es_bonificacion, created_by, updated_by)
                VALUES (:id_pack, :id_item, :cantidad, :es_bonificacion, :created_by, :updated_by)';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'id_pack' => $idPack,
            'id_item' => $idItem,
            'cantidad' => $cantidad,
            'es_bonificacion' => $esBonificacion,
            'created_by' => $usuarioId,
            'updated_by' => $usuarioId
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * 6. Elimina un componente de TU tabla
     */
    public function eliminarComponente(int $idDetalle): bool
    {
        $sql = 'DELETE FROM precios_presentaciones_detalle WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $idDetalle, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    }
}