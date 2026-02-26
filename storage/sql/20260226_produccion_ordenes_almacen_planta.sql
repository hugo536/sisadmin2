ALTER TABLE `produccion_ordenes`
  ADD COLUMN `id_almacen_planta` INT(11) NULL COMMENT 'Almacén de tipo Planta seleccionado en la planificación' AFTER `id_receta`,
  ADD INDEX `idx_op_almacen_planta` (`id_almacen_planta`),
  ADD CONSTRAINT `fk_op_almacen_planta` FOREIGN KEY (`id_almacen_planta`) REFERENCES `almacenes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
