-- Agrega campo de fecha operativa para movimientos de envases
-- y lo indexa para el historial/ordenamiento.

SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'cta_cte_envases'
      AND COLUMN_NAME = 'fecha_movimiento'
);

SET @sql_add_col := IF(
    @col_exists = 0,
    'ALTER TABLE cta_cte_envases ADD COLUMN fecha_movimiento DATETIME NULL AFTER cantidad',
    'SELECT "fecha_movimiento ya existe"'
);
PREPARE stmt_add_col FROM @sql_add_col;
EXECUTE stmt_add_col;
DEALLOCATE PREPARE stmt_add_col;

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'cta_cte_envases'
      AND INDEX_NAME = 'idx_cta_cte_envases_fecha_movimiento'
);

SET @sql_add_idx := IF(
    @idx_exists = 0,
    'ALTER TABLE cta_cte_envases ADD INDEX idx_cta_cte_envases_fecha_movimiento (fecha_movimiento)',
    'SELECT "idx_cta_cte_envases_fecha_movimiento ya existe"'
);
PREPARE stmt_add_idx FROM @sql_add_idx;
EXECUTE stmt_add_idx;
DEALLOCATE PREPARE stmt_add_idx;

-- Backfill opcional para registros antiguos sin fecha.
UPDATE cta_cte_envases
SET fecha_movimiento = COALESCE(fecha_movimiento, created_at, NOW())
WHERE fecha_movimiento IS NULL;
