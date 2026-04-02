-- Nuevos switches de Configuración Avanzada para items
ALTER TABLE items
  ADD COLUMN requiere_formula_bom TINYINT(1) NOT NULL DEFAULT 0 AFTER controla_stock,
  ADD COLUMN requiere_factor_conversion TINYINT(1) NOT NULL DEFAULT 0 AFTER requiere_formula_bom,
  ADD COLUMN es_envase_retornable TINYINT(1) NOT NULL DEFAULT 0 AFTER requiere_factor_conversion;

-- Índices opcionales para consultas de alertas/listados
CREATE INDEX idx_items_requiere_formula_bom ON items (requiere_formula_bom);
CREATE INDEX idx_items_requiere_factor_conversion ON items (requiere_factor_conversion);
CREATE INDEX idx_items_es_envase_retornable ON items (es_envase_retornable);

-- Rollback sugerido:
-- DROP INDEX idx_items_requiere_formula_bom ON items;
-- DROP INDEX idx_items_requiere_factor_conversion ON items;
-- DROP INDEX idx_items_es_envase_retornable ON items;
-- ALTER TABLE items
--   DROP COLUMN es_envase_retornable,
--   DROP COLUMN requiere_factor_conversion,
--   DROP COLUMN requiere_formula_bom;
