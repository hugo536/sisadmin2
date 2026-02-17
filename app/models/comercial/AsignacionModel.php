<?php
class AsignacionModel extends Modelo {

    // Trae solo a los clientes (es_cliente = 1) y su lista actual
    public function listarClientes() {
        // SOLUCIÓN: Agregamos 'COLLATE utf8mb4_general_ci' en el JOIN de distritos
        // para evitar el error de mezcla de intercalación.
        $sql = "SELECT 
                    t.id,
                    t.nombre_completo,
                    t.numero_documento,
                    t.email,
                    d.nombre as distrito,
                    tc.id_lista_precios,
                    lp.nombre as nombre_lista
                FROM terceros t
                INNER JOIN terceros_clientes tc ON t.id = tc.id_tercero
                LEFT JOIN distritos d ON t.distrito = d.id COLLATE utf8mb4_general_ci
                LEFT JOIN listas_precios lp ON tc.id_lista_precios = lp.id
                WHERE t.estado = 1 AND t.es_cliente = 1
                ORDER BY t.nombre_completo ASC";
        
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    // Actualiza la lista asignada a un cliente
    public function actualizarListaCliente($id_cliente, $id_lista) {
        // Si id_lista viene vacío, lo ponemos como NULL (Precio Base)
        $id_lista = empty($id_lista) ? null : $id_lista;

        $sql = "UPDATE terceros_clientes SET id_lista_precios = :lista, updated_at = NOW() WHERE id_tercero = :cliente";
        $stmt = $this->db->prepare($sql);
        
        return $stmt->execute([
            ':lista' => $id_lista,
            ':cliente' => $id_cliente
        ]);
    }
}