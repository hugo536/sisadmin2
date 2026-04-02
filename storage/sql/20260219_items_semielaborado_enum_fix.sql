-- Ajuste de catálogo de tipos de ítem para soportar semielaborado.
-- 1) Amplía el ENUM de items.tipo_item.
-- 2) Repara filas históricas que quedaron con tipo_item vacío por coerción de ENUM.

ALTER TABLE items
MODIFY COLUMN tipo_item ENUM(
  'producto',
  'producto_terminado',
  'semielaborado',
  'materia_prima',
  'insumo',
  'material_empaque',
  'servicio'
) NOT NULL;

UPDATE items
SET tipo_item = 'semielaborado'
WHERE tipo_item = '';
