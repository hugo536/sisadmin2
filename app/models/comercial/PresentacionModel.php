<?php
class PresentacionModel extends Modelo {

    public function __construct() {
        parent::__construct();
        $this->table = 'precios_presentaciones';
    }

    // Listar todas las presentaciones unidas con el nombre del producto base
    public function listarTodo() {
        $sql = "SELECT p.*, i.nombre as item_nombre, i.sku 
                FROM {$this->table} p
                INNER JOIN items i ON p.id_item = i.id
                WHERE p.estado = 1 AND p.deleted_at IS NULL
                ORDER BY i.nombre ASC, p.factor ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Guardar (Crear o Editar)
    public function guardar($datos) {
        try {
            if (empty($datos['id'])) {
                // Insertar
                $sql = "INSERT INTO {$this->table} 
                        (id_item, nombre, factor, precio_x_menor, precio_x_mayor, cantidad_minima_mayor, created_by) 
                        VALUES (:id_item, :nombre, :factor, :precio_x_menor, :precio_x_mayor, :cantidad_minima_mayor, :created_by)";
            } else {
                // Actualizar
                $sql = "UPDATE {$this->table} SET 
                        id_item = :id_item, nombre = :nombre, factor = :factor, 
                        precio_x_menor = :precio_x_menor, precio_x_mayor = :precio_x_mayor, 
                        cantidad_minima_mayor = :cantidad_minima_mayor, updated_by = :created_by
                        WHERE id = :id";
            }

            $stmt = $this->db->prepare($sql);
            
            // Vincular parámetros comunes
            $stmt->bindValue(':id_item', $datos['id_item']);
            $stmt->bindValue(':nombre', $datos['nombre']);
            $stmt->bindValue(':factor', $datos['factor']);
            $stmt->bindValue(':precio_x_menor', $datos['precio_x_menor']);
            $stmt->bindValue(':precio_x_mayor', !empty($datos['precio_x_mayor']) ? $datos['precio_x_mayor'] : null);
            $stmt->bindValue(':cantidad_minima_mayor', !empty($datos['cantidad_minima_mayor']) ? $datos['cantidad_minima_mayor'] : null);
            $stmt->bindValue(':created_by', $_SESSION['id_usuario'] ?? 1);

            if (!empty($datos['id'])) {
                $stmt->bindValue(':id', $datos['id']);
            }

            return $stmt->execute();

        } catch (PDOException $e) {
            return false;
        }
    }

    // Eliminado lógico (Soft Delete)
    public function eliminar($id) {
        $sql = "UPDATE {$this->table} SET estado = 0, deleted_at = NOW(), deleted_by = :user WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':user', $_SESSION['id_usuario'] ?? 1);
        return $stmt->execute();
    }
}