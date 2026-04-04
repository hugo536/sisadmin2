<?php
declare(strict_types=1);

require_once BASE_PATH . '/app/core/Database.php';

class PacksModel
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    /**
     * Obtiene todos los ítems de tipo "pack" para llenar la lista izquierda
     */
    public function obtenerTodosLosPacks(): array
    {
        try {
            // Asumiendo que en tu tabla 'items' tienes un campo que dice si es pack
            // Si el campo se llama distinto (ej. 'tipo_item'), ajusta el WHERE.
            $sql = "SELECT id, nombre, sku, precio_venta 
                    FROM items 
                    WHERE tipo_item = 'pack' AND estado = 1 
                    ORDER BY nombre ASC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Error en obtenerTodosLosPacks: " . $e->getMessage());
            return [];
        }
    }
}