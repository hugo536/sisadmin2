-- Packs y Combos (BOM Comercial)
-- Script idempotente para MySQL/MariaDB.

-- 1) Columnas requeridas en detalle de packs.
ALTER TABLE precios_presentaciones_detalle
    ADD COLUMN IF NOT EXISTS es_bonificacion TINYINT(1) NOT NULL DEFAULT 0 AFTER cantidad,
    ADD COLUMN IF NOT EXISTS created_by INT NULL AFTER created_at,
    ADD COLUMN IF NOT EXISTS updated_by INT NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_by;

-- 2) Único por pack+item (evita duplicados).
SET @has_uq_pack_item := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND index_name = 'uq_pack_item'
);
SET @sql_uq_pack_item := IF(@has_uq_pack_item = 0,
    'ALTER TABLE precios_presentaciones_detalle ADD UNIQUE KEY uq_pack_item (id_presentacion, id_item)',
    'SELECT 1'
);
PREPARE stmt_uq_pack_item FROM @sql_uq_pack_item;
EXECUTE stmt_uq_pack_item;
DEALLOCATE PREPARE stmt_uq_pack_item;

-- 3) Índices de performance.
SET @has_idx_pp_estado_deleted := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones'
      AND index_name = 'idx_pp_estado_deleted'
);
SET @sql_idx_pp_estado_deleted := IF(@has_idx_pp_estado_deleted = 0,
    'CREATE INDEX idx_pp_estado_deleted ON precios_presentaciones (estado, deleted_at)',
    'SELECT 1'
);
PREPARE stmt_idx_pp_estado_deleted FROM @sql_idx_pp_estado_deleted;
EXECUTE stmt_idx_pp_estado_deleted;
DEALLOCATE PREPARE stmt_idx_pp_estado_deleted;

SET @has_idx_ppd_presentacion := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND index_name = 'idx_ppd_presentacion'
);
SET @sql_idx_ppd_presentacion := IF(@has_idx_ppd_presentacion = 0,
    'CREATE INDEX idx_ppd_presentacion ON precios_presentaciones_detalle (id_presentacion)',
    'SELECT 1'
);
PREPARE stmt_idx_ppd_presentacion FROM @sql_idx_ppd_presentacion;
EXECUTE stmt_idx_ppd_presentacion;
DEALLOCATE PREPARE stmt_idx_ppd_presentacion;

SET @has_idx_ppd_item := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND index_name = 'idx_ppd_item'
);
SET @sql_idx_ppd_item := IF(@has_idx_ppd_item = 0,
    'CREATE INDEX idx_ppd_item ON precios_presentaciones_detalle (id_item)',
    'SELECT 1'
);
PREPARE stmt_idx_ppd_item FROM @sql_idx_ppd_item;
EXECUTE stmt_idx_ppd_item;
DEALLOCATE PREPARE stmt_idx_ppd_item;

SET @has_idx_items_componentes_pack := (
    SELECT COUNT(*)
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = 'items'
      AND index_name = 'idx_items_componentes_pack'
);
SET @sql_idx_items_componentes_pack := IF(@has_idx_items_componentes_pack = 0,
    'CREATE INDEX idx_items_componentes_pack ON items (tipo_item, estado, deleted_at, nombre)',
    'SELECT 1'
);
PREPARE stmt_idx_items_componentes_pack FROM @sql_idx_items_componentes_pack;
EXECUTE stmt_idx_items_componentes_pack;
DEALLOCATE PREPARE stmt_idx_items_componentes_pack;

-- 4) Foráneas de auditoría a usuarios (opcional, si existe tabla usuarios.id).
SET @has_fk_ppd_created_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND constraint_name = 'fk_ppd_created_by'
);
SET @sql_fk_ppd_created_by := IF(@has_fk_ppd_created_by = 0,
    'ALTER TABLE precios_presentaciones_detalle ADD CONSTRAINT fk_ppd_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_ppd_created_by FROM @sql_fk_ppd_created_by;
EXECUTE stmt_fk_ppd_created_by;
DEALLOCATE PREPARE stmt_fk_ppd_created_by;

SET @has_fk_ppd_updated_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND constraint_name = 'fk_ppd_updated_by'
);
SET @sql_fk_ppd_updated_by := IF(@has_fk_ppd_updated_by = 0,
    'ALTER TABLE precios_presentaciones_detalle ADD CONSTRAINT fk_ppd_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_ppd_updated_by FROM @sql_fk_ppd_updated_by;
EXECUTE stmt_fk_ppd_updated_by;
DEALLOCATE PREPARE stmt_fk_ppd_updated_by;
