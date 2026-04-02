ALTER TABLE `produccion_recetas`
  ADD COLUMN `id_almacen_planta` INT(11) NULL COMMENT 'Planta de trabajo predeterminada para planificar OP desde la receta' AFTER `id_centro_costo`,
  ADD KEY `idx_recetas_almacen_planta` (`id_almacen_planta`),
  ADD CONSTRAINT `fk_recetas_almacen_planta` FOREIGN KEY (`id_almacen_planta`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
