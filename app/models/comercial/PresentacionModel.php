<?php
class PresentacionModel extends Modelo {

    public function __construct() {
        parent::__construct();
        $this->table = 'precios_presentaciones';
    }

    // 1. PARA LA TABLA (Listar packs creados)
    public function listarTodo() {
        // Agregamos los LEFT JOIN a sabores y presentaciones base para armar el nombre
        $sql = "SELECT p.*, 
                       i.sku,
                       CONCAT(
                           i.nombre,
                           CASE 
                               WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre)
                               ELSE ''
                           END,
                           CASE 
                               WHEN ip.nombre IS NOT NULL THEN CONCAT(' ', ip.nombre)
                               ELSE ''
                           END
                       ) as item_nombre_full
                FROM {$this->table} p
                INNER JOIN items i ON p.id_item = i.id
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                WHERE p.estado = 1 AND p.deleted_at IS NULL
                ORDER BY i.nombre ASC, p.factor ASC";
                
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. PARA EL FORMULARIO (El Dropdown "Producto Base")
    // Esta función llena el <select> en tu modal "Nueva Presentación"
    public function listarProductosParaSelect() {
        $sql = "SELECT i.id, 
                       CONCAT(
                           i.nombre,
                           CASE 
                               WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre)
                               ELSE ''
                           END,
                           CASE 
                               WHEN ip.nombre IS NOT NULL THEN CONCAT(' ', ip.nombre)
                               ELSE ''
                           END
                       ) as nombre_completo
                FROM items i
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                WHERE i.estado = 1 
                  AND i.deleted_at IS NULL
                  AND i.tipo_item = 'producto' -- Solo mostramos productos terminados
                ORDER BY i.nombre ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Guardar (Sin cambios, tu lógica estaba bien)
    public function guardar($datos) {
        try {
            // Preparamos los valores nulos para evitar errores SQL
            $precioMayor = !empty($datos['precio_x_mayor']) ? $datos['precio_x_mayor'] : null;
            $cantMinima = !empty($datos['cantidad_minima_mayor']) ? $datos['cantidad_minima_mayor'] : null;
            $usuario = $_SESSION['id_usuario'] ?? 1;

            if (empty($datos['id'])) {
                // Insertar
                $sql = "INSERT INTO {$this->table} 
                        (id_item, nombre, factor, precio_x_menor, precio_x_mayor, cantidad_minima_mayor, created_by) 
                        VALUES (:id_item, :nombre, :factor, :precio_x_menor, :precio_x_mayor, :cantidad_minima_mayor, :created_by)";
                $params = [
                    ':id_item' => $datos['id_item'],
                    ':nombre' => $datos['nombre'],
                    ':factor' => $datos['factor'],
                    ':precio_x_menor' => $datos['precio_x_menor'],
                    ':precio_x_mayor' => $precioMayor,
                    ':cantidad_minima_mayor' => $cantMinima,
                    ':created_by' => $usuario
                ];
            } else {
                // Actualizar
                $sql = "UPDATE {$this->table} SET 
                        id_item = :id_item, nombre = :nombre, factor = :factor, 
                        precio_x_menor = :precio_x_menor, precio_x_mayor = :precio_x_mayor, 
                        cantidad_minima_mayor = :cantidad_minima_mayor, updated_by = :updated_by
                        WHERE id = :id";
                $params = [
                    ':id_item' => $datos['id_item'],
                    ':nombre' => $datos['nombre'],
                    ':factor' => $datos['factor'],
                    ':precio_x_menor' => $datos['precio_x_menor'],
                    ':precio_x_mayor' => $precioMayor,
                    ':cantidad_minima_mayor' => $cantMinima,
                    ':updated_by' => $usuario,
                    ':id' => $datos['id']
                ];
            }

            $stmt = $this->db->prepare($sql);
            return $stmt->execute($params);

        } catch (PDOException $e) {
            return false;
        }
    }

    // Eliminado lógico (Sin cambios)
    public function eliminar($id) {
        $sql = "UPDATE {$this->table} SET estado = 0, deleted_at = NOW(), deleted_by = :user WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->bindValue(':user', $_SESSION['id_usuario'] ?? 1);
        return $stmt->execute();
    }
}