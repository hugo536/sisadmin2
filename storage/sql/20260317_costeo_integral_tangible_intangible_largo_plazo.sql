-- Ajustes para costeo integral por naturaleza: Tangible, Intangible y Largo Plazo.

-- 1) Inventario: Centro de costo por movimiento
ALTER TABLE inventario_movimientos
  ADD COLUMN IF NOT EXISTS id_centro_costo INT NULL AFTER id_almacen_destino;

SET @idx_inv_mov_cc := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventario_movimientos' AND INDEX_NAME = 'idx_inv_mov_centro_costo'
);
SET @sql_idx_inv_mov_cc := IF(
  @idx_inv_mov_cc = 0,
  'ALTER TABLE inventario_movimientos ADD KEY idx_inv_mov_centro_costo (id_centro_costo)',
  'SELECT "idx_inv_mov_centro_costo ya existe" AS info'
);
PREPARE stmt_idx_inv_mov_cc FROM @sql_idx_inv_mov_cc;
EXECUTE stmt_idx_inv_mov_cc;
DEALLOCATE PREPARE stmt_idx_inv_mov_cc;

SET @fk_inv_mov_cc := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'inventario_movimientos' AND CONSTRAINT_NAME = 'fk_inv_mov_centro_costo'
);
SET @sql_fk_inv_mov_cc := IF(
  @fk_inv_mov_cc = 0,
  'ALTER TABLE inventario_movimientos ADD CONSTRAINT fk_inv_mov_centro_costo FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id)',
  'SELECT "fk_inv_mov_centro_costo ya existe" AS info'
);
PREPARE stmt_fk_inv_mov_cc FROM @sql_fk_inv_mov_cc;
EXECUTE stmt_fk_inv_mov_cc;
DEALLOCATE PREPARE stmt_fk_inv_mov_cc;

-- 2) Gastos: centro de costo por registro
ALTER TABLE gastos_registros
  ADD COLUMN IF NOT EXISTS id_centro_costo INT UNSIGNED NULL AFTER id_concepto;

SET @idx_gastos_reg_cc := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos_registros' AND INDEX_NAME = 'idx_gastos_registros_centro'
);
SET @sql_idx_gastos_reg_cc := IF(
  @idx_gastos_reg_cc = 0,
  'ALTER TABLE gastos_registros ADD KEY idx_gastos_registros_centro (id_centro_costo)',
  'SELECT "idx_gastos_registros_centro ya existe" AS info'
);
PREPARE stmt_idx_gastos_reg_cc FROM @sql_idx_gastos_reg_cc;
EXECUTE stmt_idx_gastos_reg_cc;
DEALLOCATE PREPARE stmt_idx_gastos_reg_cc;

SET @fk_gastos_reg_cc := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'gastos_registros' AND CONSTRAINT_NAME = 'fk_gastos_registros_centro'
);
SET @sql_fk_gastos_reg_cc := IF(
  @fk_gastos_reg_cc = 0,
  'ALTER TABLE gastos_registros ADD CONSTRAINT fk_gastos_registros_centro FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id)',
  'SELECT "fk_gastos_registros_centro ya existe" AS info'
);
PREPARE stmt_fk_gastos_reg_cc FROM @sql_fk_gastos_reg_cc;
EXECUTE stmt_fk_gastos_reg_cc;
DEALLOCATE PREPARE stmt_fk_gastos_reg_cc;

UPDATE gastos_registros gr
INNER JOIN gastos_conceptos gc ON gc.id = gr.id_concepto
SET gr.id_centro_costo = gc.id_centro_costo
WHERE gr.id_centro_costo IS NULL;

-- 3) Tesorería: separar capital e interés en pagos
ALTER TABLE tesoreria_movimientos
  ADD COLUMN IF NOT EXISTS naturaleza_pago ENUM('DOCUMENTO','CAPITAL','INTERES','MIXTO') NOT NULL DEFAULT 'DOCUMENTO' AFTER monto,
  ADD COLUMN IF NOT EXISTS monto_capital DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER naturaleza_pago,
  ADD COLUMN IF NOT EXISTS monto_interes DECIMAL(14,4) NOT NULL DEFAULT 0 AFTER monto_capital,
  ADD COLUMN IF NOT EXISTS id_centro_costo INT NULL AFTER monto_interes;

SET @idx_tes_mov_nat := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tesoreria_movimientos' AND INDEX_NAME = 'idx_tes_mov_naturaleza'
);
SET @sql_idx_tes_mov_nat := IF(
  @idx_tes_mov_nat = 0,
  'ALTER TABLE tesoreria_movimientos ADD KEY idx_tes_mov_naturaleza (naturaleza_pago)',
  'SELECT "idx_tes_mov_naturaleza ya existe" AS info'
);
PREPARE stmt_idx_tes_mov_nat FROM @sql_idx_tes_mov_nat;
EXECUTE stmt_idx_tes_mov_nat;
DEALLOCATE PREPARE stmt_idx_tes_mov_nat;

SET @idx_tes_mov_cc := (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tesoreria_movimientos' AND INDEX_NAME = 'idx_tes_mov_centro_costo'
);
SET @sql_idx_tes_mov_cc := IF(
  @idx_tes_mov_cc = 0,
  'ALTER TABLE tesoreria_movimientos ADD KEY idx_tes_mov_centro_costo (id_centro_costo)',
  'SELECT "idx_tes_mov_centro_costo ya existe" AS info'
);
PREPARE stmt_idx_tes_mov_cc FROM @sql_idx_tes_mov_cc;
EXECUTE stmt_idx_tes_mov_cc;
DEALLOCATE PREPARE stmt_idx_tes_mov_cc;

SET @fk_tes_mov_cc := (
  SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
  WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'tesoreria_movimientos' AND CONSTRAINT_NAME = 'fk_tes_mov_centro_costo'
);
SET @sql_fk_tes_mov_cc := IF(
  @fk_tes_mov_cc = 0,
  'ALTER TABLE tesoreria_movimientos ADD CONSTRAINT fk_tes_mov_centro_costo FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id)',
  'SELECT "fk_tes_mov_centro_costo ya existe" AS info'
);
PREPARE stmt_fk_tes_mov_cc FROM @sql_fk_tes_mov_cc;
EXECUTE stmt_fk_tes_mov_cc;
DEALLOCATE PREPARE stmt_fk_tes_mov_cc;

-- 4) Activos fijos: asegurar centro de costo asignado
UPDATE activos_fijos a
JOIN (
  SELECT id
  FROM conta_centros_costo
  WHERE deleted_at IS NULL AND estado = 1
  ORDER BY id ASC
  LIMIT 1
) cc ON 1 = 1
SET a.id_centro_costo = cc.id
WHERE a.id_centro_costo IS NULL OR a.id_centro_costo = 0;
