<?php
class PresentacionModel extends Modelo {

    public function __construct() {
        parent::__construct();
        $this->table = 'precios_presentaciones';
    }

    // 1. LISTAR TODO (Ahora incluye el código generado)
    public function listarTodo() {
        $sql = "SELECT p.*, 
                       p.codigo_presentacion, -- Aseguramos traer el nuevo código
                       CONCAT(
                           i.nombre,
                           CASE 
                               WHEN s.nombre IS NOT NULL AND s.nombre != 'Ninguno' THEN CONCAT(' ', s.nombre)
                               ELSE ''
                           END,
                           CASE 
                               WHEN ip.nombre IS NOT NULL THEN CONCAT(' ', ip.nombre)
                               ELSE ''
                           END,
                           ' x ', CAST(p.factor AS UNSIGNED) -- Agregado para que el nombre visual sea completo
                       ) as item_nombre_full
                FROM {$this->table} p
                INNER JOIN items i ON p.id_item = i.id
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                WHERE p.deleted_at IS NULL AND p.estado = 1
                ORDER BY i.nombre ASC, p.factor ASC";
                
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // 2. OBTENER UN SOLO REGISTRO (¡NUEVO! Vital para el botón Editar)
    public function obtener($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // 3. LISTAR PRODUCTOS PARA SELECT (Sin cambios mayores, solo optimización visual)
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
                       ) as nombre_completo,
                       i.sku -- Necesitamos el SKU base por si lo quieres mostrar en el select
                FROM items i
                LEFT JOIN item_sabores s ON i.id_sabor = s.id
                LEFT JOIN item_presentaciones ip ON i.id_presentacion = ip.id
                WHERE i.estado = 1 
                  AND i.deleted_at IS NULL
                  AND i.tipo_item = 'producto'
                ORDER BY i.nombre ASC";

        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. GUARDAR (Lógica corregida: Sin nombre manual, con SKU automático)
    public function guardar($datos) {
        try {
            // A. Valores por defecto
            $precioMayor = !empty($datos['precio_x_mayor']) ? $datos['precio_x_mayor'] : null;
            $cantMinima = !empty($datos['cantidad_minima_mayor']) ? $datos['cantidad_minima_mayor'] : null;
            $usuario = $_SESSION['id_usuario'] ?? 1;

            // B. Generar SKU Automático (SKU_BASE + -X + FACTOR)
            // Primero obtenemos el SKU del item padre
            $stmtItem = $this->db->prepare("SELECT sku FROM items WHERE id = :id");
            $stmtItem->execute([':id' => $datos['id_item']]);
            $skuBase = $stmtItem->fetchColumn(); 
            
            // Si no tiene SKU base, usamos un genérico, si tiene, armamos el código
            $codigoAuto = ($skuBase ? $skuBase : 'GEN') . '-X' . (int)$datos['factor'];

            if (empty($datos['id'])) {
                // --- INSERTAR ---
                // Eliminamos 'nombre' y agregamos 'codigo_presentacion'
                $sql = "INSERT INTO {$this->table} 
                        (id_item, codigo_presentacion, factor, precio_x_menor, precio_x_mayor, cantidad_minima_mayor, created_by) 
                        VALUES (:id_item, :codigo, :factor, :precio_x_menor, :precio_x_mayor, :cantidad_minima_mayor, :created_by)";
                $params = [
                    ':id_item' => $datos['id_item'],
                    ':codigo'  => $codigoAuto, // SKU Automático
                    ':factor'  => $datos['factor'],
                    ':precio_x_menor' => $datos['precio_x_menor'],
                    ':precio_x_mayor' => $precioMayor,
                    ':cantidad_minima_mayor' => $cantMinima,
                    ':created_by' => $usuario
                ];
            } else {
                // --- ACTUALIZAR ---
                // Eliminamos 'nombre' y actualizamos código por si cambió el factor
                $sql = "UPDATE {$this->table} SET 
                        id_item = :id_item, 
                        codigo_presentacion = :codigo,
                        factor = :factor, 
                        precio_x_menor = :precio_x_menor, 
                        precio_x_mayor = :precio_x_mayor, 
                        cantidad_minima_mayor = :cantidad_minima_mayor, 
                        updated_by = :updated_by
                        WHERE id = :id";
                $params = [
                    ':id_item' => $datos['id_item'],
                    ':codigo'  => $codigoAuto, // Recalculamos el SKU por si cambió el factor
                    ':factor'  => $datos['factor'],
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
            // Tip: Descomenta esto si quieres ver el error en pantalla mientras pruebas
            // die($e->getMessage()); 
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

    public function actualizarEstado(int $id, int $estado): bool {
        $sql = "UPDATE {$this->table}
                SET estado = :estado,
                    updated_by = :user,
                    updated_at = NOW()
                WHERE id = :id AND deleted_at IS NULL";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':estado' => $estado,
            ':user' => $_SESSION['id_usuario'] ?? 1,
            ':id' => $id,
        ]);
    }
}