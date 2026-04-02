-- RRHH: eliminar columna pago_diario para unificar cálculos con sueldo_basico + tipo_pago
SET @db_name := DATABASE();

SET @col_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db_name
      AND TABLE_NAME = 'terceros_empleados'
      AND COLUMN_NAME = 'pago_diario'
);

SET @sql := IF(
    @col_exists > 0,
    'ALTER TABLE terceros_empleados DROP COLUMN pago_diario',
    'SELECT "pago_diario ya no existe en terceros_empleados" AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
