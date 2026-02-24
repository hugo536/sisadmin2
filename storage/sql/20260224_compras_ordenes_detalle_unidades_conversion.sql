-- Trazabilidad de unidad de compra y conversión aplicada en detalle de OC
ALTER TABLE compras_ordenes_detalle
  ADD COLUMN id_item_unidad INT NULL AFTER id_item,
  ADD COLUMN unidad_nombre VARCHAR(120) NULL AFTER id_item_unidad,
  ADD COLUMN factor_conversion_aplicado DECIMAL(14,4) NOT NULL DEFAULT 1.0000 AFTER unidad_nombre,
  ADD COLUMN cantidad_conversion DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER factor_conversion_aplicado,
  ADD COLUMN cantidad_base_solicitada DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER cantidad_conversion;

ALTER TABLE compras_ordenes_detalle
  ADD INDEX idx_compras_ordenes_detalle_item_unidad (id_item_unidad);

ALTER TABLE compras_ordenes_detalle
  ADD CONSTRAINT fk_compras_ordenes_detalle_item_unidad
  FOREIGN KEY (id_item_unidad) REFERENCES items_unidades(id)
  ON UPDATE RESTRICT
  ON DELETE SET NULL;

-- Backfill sugerido para datos históricos
UPDATE compras_ordenes_detalle
SET cantidad_conversion = cantidad_solicitada,
    cantidad_base_solicitada = cantidad_solicitada
WHERE cantidad_conversion = 0
   OR cantidad_base_solicitada = 0;

-- Rollback sugerido:
-- ALTER TABLE compras_ordenes_detalle DROP FOREIGN KEY fk_compras_ordenes_detalle_item_unidad;
-- ALTER TABLE compras_ordenes_detalle DROP INDEX idx_compras_ordenes_detalle_item_unidad;
-- ALTER TABLE compras_ordenes_detalle
--   DROP COLUMN cantidad_base_solicitada,
--   DROP COLUMN cantidad_conversion,
--   DROP COLUMN factor_conversion_aplicado,
--   DROP COLUMN unidad_nombre,
--   DROP COLUMN id_item_unidad;
