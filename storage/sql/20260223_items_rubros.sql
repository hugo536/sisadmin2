CREATE TABLE IF NOT EXISTS `item_rubros` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(120) NOT NULL,
  `descripcion` VARCHAR(255) DEFAULT NULL,
  `estado` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  `created_by` INT UNSIGNED DEFAULT NULL,
  `updated_by` INT UNSIGNED DEFAULT NULL,
  `deleted_by` INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item_rubros_estado` (`estado`),
  KEY `idx_item_rubros_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `items`
  ADD COLUMN `id_rubro` INT UNSIGNED NULL AFTER `tipo_item`;

ALTER TABLE `items`
  ADD CONSTRAINT `fk_items_rubro`
  FOREIGN KEY (`id_rubro`) REFERENCES `item_rubros` (`id`)
  ON UPDATE CASCADE
  ON DELETE SET NULL;

CREATE INDEX `idx_items_id_rubro` ON `items` (`id_rubro`);
