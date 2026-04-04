-- Packs y Combos (BOM Comercial)
-- Script idempotente para MySQL/MariaDB.

-- 0) Asegura tablas base si aún no existen.
CREATE TABLE IF NOT EXISTS precios_presentaciones (
    id INT NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(150) NOT NULL COMMENT 'Nombre del Pack o Combo',
    estado TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS precios_presentaciones_detalle (
    id INT NOT NULL AUTO_INCREMENT,
    id_presentacion INT NOT NULL COMMENT 'FK a precios_presentaciones',
    id_item INT NOT NULL COMMENT 'FK a items (El producto que va dentro del pack)',
    cantidad DECIMAL(14,4) NOT NULL DEFAULT 1.0000,
    es_bonificacion TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by INT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

-- 4) Foráneas principales de detalle.
SET @has_fk_ppd_presentacion := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND constraint_name = 'fk_ppd_presentacion'
);
SET @sql_fk_ppd_presentacion := IF(@has_fk_ppd_presentacion = 0,
    'ALTER TABLE precios_presentaciones_detalle ADD CONSTRAINT fk_ppd_presentacion FOREIGN KEY (id_presentacion) REFERENCES precios_presentaciones(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_ppd_presentacion FROM @sql_fk_ppd_presentacion;
EXECUTE stmt_fk_ppd_presentacion;
DEALLOCATE PREPARE stmt_fk_ppd_presentacion;

SET @has_fk_ppd_item := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND constraint_name = 'fk_ppd_item'
);
SET @sql_fk_ppd_item := IF(@has_fk_ppd_item = 0,
    'ALTER TABLE precios_presentaciones_detalle ADD CONSTRAINT fk_ppd_item FOREIGN KEY (id_item) REFERENCES items(id) ON DELETE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_ppd_item FROM @sql_fk_ppd_item;
EXECUTE stmt_fk_ppd_item;
DEALLOCATE PREPARE stmt_fk_ppd_item;

-- 5) Foráneas de auditoría a usuarios (solo si existe usuarios.id y se alinea el tipo).
SET @usuarios_id_coltype := (
    SELECT COLUMN_TYPE
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'usuarios'
      AND column_name = 'id'
    LIMIT 1
);

SET @sql_align_pp_created_by := IF(@usuarios_id_coltype IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE precios_presentaciones MODIFY COLUMN created_by ', @usuarios_id_coltype, ' NULL')
);
PREPARE stmt_align_pp_created_by FROM @sql_align_pp_created_by;
EXECUTE stmt_align_pp_created_by;
DEALLOCATE PREPARE stmt_align_pp_created_by;

SET @sql_align_pp_updated_by := IF(@usuarios_id_coltype IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE precios_presentaciones MODIFY COLUMN updated_by ', @usuarios_id_coltype, ' NULL')
);
PREPARE stmt_align_pp_updated_by FROM @sql_align_pp_updated_by;
EXECUTE stmt_align_pp_updated_by;
DEALLOCATE PREPARE stmt_align_pp_updated_by;

SET @sql_align_ppd_created_by := IF(@usuarios_id_coltype IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE precios_presentaciones_detalle MODIFY COLUMN created_by ', @usuarios_id_coltype, ' NULL')
);
PREPARE stmt_align_ppd_created_by FROM @sql_align_ppd_created_by;
EXECUTE stmt_align_ppd_created_by;
DEALLOCATE PREPARE stmt_align_ppd_created_by;

SET @sql_align_ppd_updated_by := IF(@usuarios_id_coltype IS NULL,
    'SELECT 1',
    CONCAT('ALTER TABLE precios_presentaciones_detalle MODIFY COLUMN updated_by ', @usuarios_id_coltype, ' NULL')
);
PREPARE stmt_align_ppd_updated_by FROM @sql_align_ppd_updated_by;
EXECUTE stmt_align_ppd_updated_by;
DEALLOCATE PREPARE stmt_align_ppd_updated_by;

SET @has_fk_pp_created_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones'
      AND constraint_name = 'fk_pp_created_by'
);
SET @sql_fk_pp_created_by := IF(@has_fk_pp_created_by = 0 AND @usuarios_id_coltype IS NOT NULL,
    'ALTER TABLE precios_presentaciones ADD CONSTRAINT fk_pp_created_by FOREIGN KEY (created_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_pp_created_by FROM @sql_fk_pp_created_by;
EXECUTE stmt_fk_pp_created_by;
DEALLOCATE PREPARE stmt_fk_pp_created_by;

SET @has_fk_pp_updated_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones'
      AND constraint_name = 'fk_pp_updated_by'
);
SET @sql_fk_pp_updated_by := IF(@has_fk_pp_updated_by = 0 AND @usuarios_id_coltype IS NOT NULL,
    'ALTER TABLE precios_presentaciones ADD CONSTRAINT fk_pp_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_pp_updated_by FROM @sql_fk_pp_updated_by;
EXECUTE stmt_fk_pp_updated_by;
DEALLOCATE PREPARE stmt_fk_pp_updated_by;

SET @has_fk_ppd_created_by := (
    SELECT COUNT(*)
    FROM information_schema.table_constraints
    WHERE table_schema = DATABASE()
      AND table_name = 'precios_presentaciones_detalle'
      AND constraint_name = 'fk_ppd_created_by'
);
SET @sql_fk_ppd_created_by := IF(@has_fk_ppd_created_by = 0 AND @usuarios_id_coltype IS NOT NULL,
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
SET @sql_fk_ppd_updated_by := IF(@has_fk_ppd_updated_by = 0 AND @usuarios_id_coltype IS NOT NULL,
    'ALTER TABLE precios_presentaciones_detalle ADD CONSTRAINT fk_ppd_updated_by FOREIGN KEY (updated_by) REFERENCES usuarios(id) ON DELETE SET NULL ON UPDATE CASCADE',
    'SELECT 1'
);
PREPARE stmt_fk_ppd_updated_by FROM @sql_fk_ppd_updated_by;
EXECUTE stmt_fk_ppd_updated_by;
DEALLOCATE PREPARE stmt_fk_ppd_updated_by;
