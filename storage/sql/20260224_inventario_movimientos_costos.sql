ALTER TABLE inventario_movimientos
  ADD COLUMN costo_unitario DECIMAL(14,4) NULL DEFAULT NULL AFTER cantidad,
  ADD COLUMN costo_total DECIMAL(14,4) NULL DEFAULT NULL AFTER costo_unitario;

-- Backfill opcional desde referencias históricas que guardaban costo en texto
UPDATE inventario_movimientos
SET costo_unitario = CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(referencia, 'C.Unit: ', -1), '|', 1)) AS DECIMAL(14,4))
WHERE costo_unitario IS NULL
  AND referencia LIKE '%C.Unit:%';

UPDATE inventario_movimientos
SET costo_total = CAST(TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(referencia, 'C.Total: ', -1), '|', 1)) AS DECIMAL(14,4))
WHERE costo_total IS NULL
  AND referencia LIKE '%C.Total:%';

-- Rollback sugerido
-- ALTER TABLE inventario_movimientos
--   DROP COLUMN costo_total,
--   DROP COLUMN costo_unitario;
