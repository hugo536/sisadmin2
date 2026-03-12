ALTER TABLE `produccion_ordenes`
  ADD COLUMN `id_producto_snapshot` INT(11) NULL COMMENT 'Producto final asociado a la receta al momento de planificar' AFTER `id_receta`,
  ADD COLUMN `receta_codigo_snapshot` VARCHAR(30) NULL COMMENT 'Código de receta usado al crear la orden' AFTER `id_producto_snapshot`,
  ADD COLUMN `receta_version_snapshot` INT(11) NULL COMMENT 'Versión de receta usada al crear la orden' AFTER `receta_codigo_snapshot`,
  ADD COLUMN `costo_teorico_unitario_snapshot` DECIMAL(14,4) NULL COMMENT 'Costo teórico unitario congelado al crear la orden' AFTER `receta_version_snapshot`,
  ADD COLUMN `costo_teorico_total_snapshot` DECIMAL(14,4) NULL COMMENT 'Costo teórico total congelado según cantidad planificada' AFTER `costo_teorico_unitario_snapshot`,
  ADD COLUMN `costo_real_unitario` DECIMAL(14,4) NULL COMMENT 'Costo real unitario calculado al ejecutar la orden' AFTER `costo_teorico_total_snapshot`,
  ADD COLUMN `costo_real_total` DECIMAL(14,4) NULL COMMENT 'Costo real total de consumos al ejecutar la orden' AFTER `costo_real_unitario`,
  ADD INDEX `idx_op_producto_snapshot` (`id_producto_snapshot`),
  ADD INDEX `idx_op_fecha_estado_costos` (`fecha_programada`, `estado`, `id_receta`),
  ADD CONSTRAINT `fk_op_producto_snapshot` FOREIGN KEY (`id_producto_snapshot`) REFERENCES `items` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
