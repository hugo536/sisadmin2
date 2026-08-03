<?php
declare(strict_types=1);

class ReporteDashboardModel extends Modelo
{
    public function obtenerProductosCriticos(int $limite = 10): array
    {
        $sql = "SELECT id, sku AS codigo, nombre, stock_actual, stock_minimo 
                FROM items 
                WHERE deleted_at IS NULL AND estado = 1 AND stock_minimo > 0 AND stock_actual <= stock_minimo
                ORDER BY stock_actual ASC LIMIT :limite";
                
        $stmt = $this->db()->prepare($sql);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}