ALTER TABLE produccion_recetas_detalle
    ADD COLUMN costo_unitario DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER merma_porcentaje;

UPDATE produccion_recetas_detalle d
INNER JOIN items i ON i.id = d.id_insumo
SET d.costo_unitario = COALESCE(i.costo_referencial, 0)
WHERE d.costo_unitario = 0;
