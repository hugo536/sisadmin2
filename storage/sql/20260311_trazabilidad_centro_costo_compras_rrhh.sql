-- Trazabilidad Centro de Costo en Compras (por ítem) y RRHH (fijo por empleado)

ALTER TABLE compras_ordenes_detalle
    ADD COLUMN IF NOT EXISTS id_centro_costo INT NULL AFTER costo_unitario_pactado,
    ADD KEY idx_compra_det_centro_costo (id_centro_costo);

SET @fk_compra_det_cc := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'compras_ordenes_detalle'
      AND CONSTRAINT_NAME = 'fk_compra_det_centro_costo'
);

SET @sql_fk_compra_det_cc := IF(
    @fk_compra_det_cc = 0,
    'ALTER TABLE compras_ordenes_detalle ADD CONSTRAINT fk_compra_det_centro_costo FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id)',
    'SELECT "fk_compra_det_centro_costo ya existe" AS info'
);
PREPARE stmt_fk_compra_det_cc FROM @sql_fk_compra_det_cc;
EXECUTE stmt_fk_compra_det_cc;
DEALLOCATE PREPARE stmt_fk_compra_det_cc;

ALTER TABLE terceros_empleados
    ADD COLUMN IF NOT EXISTS id_centro_costo INT NULL AFTER sueldo_basico,
    ADD KEY idx_tercero_emp_centro_costo (id_centro_costo);

SET @fk_tercero_emp_cc := (
    SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'terceros_empleados'
      AND CONSTRAINT_NAME = 'fk_tercero_emp_centro_costo'
);

SET @sql_fk_tercero_emp_cc := IF(
    @fk_tercero_emp_cc = 0,
    'ALTER TABLE terceros_empleados ADD CONSTRAINT fk_tercero_emp_centro_costo FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id)',
    'SELECT "fk_tercero_emp_centro_costo ya existe" AS info'
);
PREPARE stmt_fk_tercero_emp_cc FROM @sql_fk_tercero_emp_cc;
EXECUTE stmt_fk_tercero_emp_cc;
DEALLOCATE PREPARE stmt_fk_tercero_emp_cc;
