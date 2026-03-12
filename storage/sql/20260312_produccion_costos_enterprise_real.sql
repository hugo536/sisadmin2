-- Fase Enterprise de costos reales de producción (MOD, CIF, merma y totales fotográficos).

CREATE TABLE IF NOT EXISTS `produccion_ordenes_mod` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_orden` INT(11) NOT NULL,
  `id_empleado` INT(11) NOT NULL,
  `horas_reales` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `costo_hora_real` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `costo_total_mod` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_mod_orden` (`id_orden`),
  KEY `idx_prod_mod_empleado` (`id_empleado`),
  CONSTRAINT `fk_prod_mod_orden` FOREIGN KEY (`id_orden`) REFERENCES `produccion_ordenes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_mod_empleado` FOREIGN KEY (`id_empleado`) REFERENCES `terceros` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `produccion_ordenes_cif` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_orden` INT(11) NOT NULL,
  `concepto` VARCHAR(120) NOT NULL,
  `id_activo` INT(11) DEFAULT NULL,
  `base_distribucion` VARCHAR(80) DEFAULT NULL,
  `costo_aplicado` DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_prod_cif_orden` (`id_orden`),
  KEY `idx_prod_cif_activo` (`id_activo`),
  CONSTRAINT `fk_prod_cif_orden` FOREIGN KEY (`id_orden`) REFERENCES `produccion_ordenes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_prod_cif_activo` FOREIGN KEY (`id_activo`) REFERENCES `activos_fijos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `inventario_movimientos`
  ADD COLUMN IF NOT EXISTS `tipo_movimiento` VARCHAR(40) NOT NULL DEFAULT 'AJ+' AFTER `id_almacen_destino`;

ALTER TABLE `produccion_ordenes`
  ADD COLUMN IF NOT EXISTS `total_md_real` DECIMAL(14,4) NULL AFTER `costo_cif_real`,
  ADD COLUMN IF NOT EXISTS `total_mod_real` DECIMAL(14,4) NULL AFTER `total_md_real`,
  ADD COLUMN IF NOT EXISTS `total_cif_real` DECIMAL(14,4) NULL AFTER `total_mod_real`,
  ADD COLUMN IF NOT EXISTS `costo_unitario_real` DECIMAL(14,4) NULL AFTER `total_cif_real`;

-- Backfill de columnas fotográficas para entornos que ya tenían costo_md_real/costo_mod_real/costo_cif_real/costo_real_unitario.
UPDATE `produccion_ordenes`
SET `total_md_real` = COALESCE(`total_md_real`, `costo_md_real`),
    `total_mod_real` = COALESCE(`total_mod_real`, `costo_mod_real`),
    `total_cif_real` = COALESCE(`total_cif_real`, `costo_cif_real`),
    `costo_unitario_real` = COALESCE(`costo_unitario_real`, `costo_real_unitario`)
WHERE 1 = 1;
