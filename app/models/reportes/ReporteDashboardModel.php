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

    /**
     * Obtiene los empleados que cumplen años en el mes actual.
     * Solo retorna aquellos cuya fecha de cumpleaños aún no ha pasado en el mes.
     */
    public function obtenerCumpleanosMes(): array
    {
        // Hacemos JOIN entre terceros_empleados (fecha) y terceros (nombre_completo)
        $sql = "SELECT 
                    t.nombre_completo, 
                    DATE_FORMAT(te.fecha_nacimiento, '%d/%m') as fecha_cumple,
                    DATEDIFF(
                        DATE(CONCAT(YEAR(CURDATE()), '-', MONTH(te.fecha_nacimiento), '-', DAY(te.fecha_nacimiento))), 
                        CURDATE()
                    ) as dias_restantes
                FROM terceros_empleados te
                INNER JOIN terceros t ON t.id = te.id_tercero
                WHERE te.estado_laboral = 'activo' 
                  AND te.recordar_cumpleanos = 1 
                  AND MONTH(te.fecha_nacimiento) = MONTH(CURDATE())
                HAVING dias_restantes >= 0
                ORDER BY dias_restantes ASC";
                
        $stmt = $this->db()->prepare($sql);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}