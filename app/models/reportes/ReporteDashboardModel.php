<?php
declare(strict_types=1);

class ReporteDashboardModel extends Modelo
{
    /**
     * Obtiene los productos con stock crítico o agotado.
     * Suma el stock de todos los almacenes y lo compara con el stock_minimo del ítem.
     */
    public function obtenerProductosCriticos(int $limite = 10): array
    {
        $sql = "SELECT 
                    i.id, 
                    i.sku AS codigo, 
                    i.nombre, 
                    COALESCE(SUM(s.stock_actual), 0) AS stock_actual, 
                    i.stock_minimo 
                FROM items i
                LEFT JOIN inventario_stock s ON s.id_item = i.id
                WHERE i.deleted_at IS NULL 
                  AND i.estado = 1 
                  AND i.stock_minimo > 0 
                GROUP BY i.id, i.sku, i.nombre, i.stock_minimo
                HAVING stock_actual <= i.stock_minimo
                ORDER BY stock_actual ASC 
                LIMIT :limite";
                
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}