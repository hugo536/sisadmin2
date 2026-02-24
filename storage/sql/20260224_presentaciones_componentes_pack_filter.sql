-- Filtro de componentes permitidos para Contenido del Pack
-- Objetivo: asegurar que el buscador y validaciones trabajen únicamente con
-- ítems de tipo semielaborado o insumo.

-- 1) (Opcional, recomendado) índice para acelerar la consulta del selector
CREATE INDEX idx_items_componentes_pack
    ON items (tipo_item, estado, deleted_at, nombre);

-- 2) Consulta base usada por backend para poblar el selector
SELECT i.id,
       i.nombre,
       i.sku,
       i.unidad_base,
       i.tipo_item
FROM items i
WHERE i.estado = 1
  AND i.deleted_at IS NULL
  AND LOWER(i.tipo_item) IN ('semielaborado', 'insumo')
ORDER BY i.nombre ASC;
