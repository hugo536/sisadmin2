ALTER TABLE produccion_recetas
    ADD COLUMN IF NOT EXISTS id_centro_costo INT NULL AFTER version;

SET @idx_receta_centro := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'produccion_recetas'
      AND index_name = 'idx_recetas_centro_costo'
);
SET @sql_idx_receta_centro := IF(
    @idx_receta_centro = 0,
    'ALTER TABLE produccion_recetas ADD KEY idx_recetas_centro_costo (id_centro_costo)',
    'SELECT "idx_recetas_centro_costo ya existe" AS info'
);
PREPARE stmt_idx_receta_centro FROM @sql_idx_receta_centro;
EXECUTE stmt_idx_receta_centro;
DEALLOCATE PREPARE stmt_idx_receta_centro;

SET @fk_receta_centro := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE constraint_schema = DATABASE()
      AND table_name = 'produccion_recetas'
      AND constraint_name = 'fk_recetas_centro_costo'
      AND constraint_type = 'FOREIGN KEY'
);
SET @sql_fk_receta_centro := IF(
    @fk_receta_centro = 0,
    'ALTER TABLE produccion_recetas ADD CONSTRAINT fk_recetas_centro_costo FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id)',
    'SELECT "fk_recetas_centro_costo ya existe" AS info'
);
PREPARE stmt_fk_receta_centro FROM @sql_fk_receta_centro;
EXECUTE stmt_fk_receta_centro;
DEALLOCATE PREPARE stmt_fk_receta_centro;
