<?php
class ListaPrecioModel extends Modelo {

    // 1. GESTIÓN DE ENCABEZADOS DE LISTAS
    public function listarListas() {
        return $this->db->query("SELECT * FROM listas_precios WHERE activo = 1")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function crearLista($nombre, $descripcion) {
        $sql = "INSERT INTO listas_precios (nombre, descripcion, created_by) VALUES (:nombre, :desc, :user)";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':nombre' => $nombre,
            ':desc' => $descripcion,
            ':user' => $_SESSION['id_usuario'] ?? 1
        ]);
    }

    // 2. GESTIÓN DE LA MATRIZ DE PRECIOS
    // Esta consulta es CLAVE: Trae todas las presentaciones y, si existe, el precio especial de esa lista
    public function obtenerMatrizPrecios($id_lista) {
        $sql = "SELECT 
                    p.id as id_presentacion,
                    p.nombre as nombre_presentacion,
                    p.precio_x_menor as precio_base,
                    i.nombre as item_nombre,
                    lpd.precio_especial,
                    lpd.id as id_detalle
                FROM precios_presentaciones p
                INNER JOIN items i ON p.id_item = i.id
                LEFT JOIN listas_precios_detalle lpd 
                    ON p.id = lpd.id_presentacion AND lpd.id_lista = :id_lista
                WHERE p.estado = 1 AND p.deleted_at IS NULL
                ORDER BY i.nombre ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':id_lista', $id_lista);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Guarda o actualiza un precio especial
    public function guardarPrecioEspecial($id_lista, $id_presentacion, $precio) {
        // Usamos INSERT ... ON DUPLICATE KEY UPDATE para no hacer selects previos
        $sql = "INSERT INTO listas_precios_detalle (id_lista, id_presentacion, precio_especial, created_by)
                VALUES (:lista, :presentacion, :precio, :user)
                ON DUPLICATE KEY UPDATE precio_especial = :precio_update";
        
        $stmt = $this->db->prepare($sql);
        $user = $_SESSION['id_usuario'] ?? 1;
        
        return $stmt->execute([
            ':lista' => $id_lista,
            ':presentacion' => $id_presentacion,
            ':precio' => $precio,
            ':user' => $user,
            ':precio_update' => $precio
        ]);
    }
    
    // Si dejan el campo vacío, borramos el precio especial para que use el base
    public function eliminarPrecioEspecial($id_lista, $id_presentacion) {
        $sql = "DELETE FROM listas_precios_detalle WHERE id_lista = :lista AND id_presentacion = :presentacion";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':lista' => $id_lista, ':presentacion' => $id_presentacion]);
    }
}