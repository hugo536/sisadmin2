-- Vincula terceros_cuentas_bancarias con configuracion_cajas_bancos por config_banco_id
-- y mejora índices para consultas de cuentas activas por tercero.

SET @db := DATABASE();

SET @idx_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'terceros_cuentas_bancarias'
      AND INDEX_NAME = 'idx_tcb_tercero_deleted'
);
SET @sql_idx := IF(
    @idx_exists = 0,
    'ALTER TABLE terceros_cuentas_bancarias ADD INDEX idx_tcb_tercero_deleted (tercero_id, deleted_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_main_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'terceros_cuentas_bancarias'
      AND INDEX_NAME = 'idx_tcb_tercero_principal_deleted'
);
SET @sql_idx_main := IF(
    @idx_main_exists = 0,
    'ALTER TABLE terceros_cuentas_bancarias ADD INDEX idx_tcb_tercero_principal_deleted (tercero_id, principal, deleted_at)',
    'SELECT 1'
);
PREPARE stmt FROM @sql_idx_main;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists := (
    SELECT COUNT(*)
    FROM information_schema.REFERENTIAL_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = @db
      AND CONSTRAINT_NAME = 'fk_tcb_config_banco'
);
SET @sql_fk := IF(
    @fk_exists = 0,
    'ALTER TABLE terceros_cuentas_bancarias ADD CONSTRAINT fk_tcb_config_banco FOREIGN KEY (config_banco_id) REFERENCES configuracion_cajas_bancos(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt FROM @sql_fk;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
