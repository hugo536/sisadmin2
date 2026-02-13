-- 1. Cabecera de Recetas (BOM)
-- Permite versionar las fórmulas de jarabes, soplado, llenado y empaquetado.
CREATE TABLE `produccion_recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL COMMENT 'El item que se va a producir (ej: Jarabe, Botella Soplada, Pack)',
  `codigo` varchar(30) NOT NULL UNIQUE,
  `version` int(11) NOT NULL DEFAULT 1,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1: Activa, 0: Inactiva',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_receta_producto` FOREIGN KEY (`id_producto`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Detalle de Receta
-- Aquí se listan los insumos o semielaborados necesarios.
CREATE TABLE `produccion_recetas_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_receta` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL COMMENT 'Puede ser materia prima o un semielaborado',
  `cantidad_por_unidad` decimal(14,4) NOT NULL,
  `merma_porcentaje` decimal(5,2) DEFAULT 0.00,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_det_receta` FOREIGN KEY (`id_receta`) REFERENCES `produccion_recetas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_det_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Órdenes de Producción (La Ejecución)
CREATE TABLE `produccion_ordenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL UNIQUE,
  `id_receta` int(11) NOT NULL,
  `id_almacen_origen` int(11) NOT NULL COMMENT 'De donde salen los insumos',
  `id_almacen_destino` int(11) NOT NULL COMMENT 'A donde entra lo producido',
  `cantidad_planificada` decimal(14,4) NOT NULL,
  `cantidad_producida` decimal(14,4) DEFAULT 0.00,
  `estado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0: Borrador, 1: En Proceso, 2: Ejecutada, 9: Anulada',
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_op_receta` FOREIGN KEY (`id_receta`) REFERENCES `produccion_recetas` (`id`),
  CONSTRAINT `fk_op_alm_origen` FOREIGN KEY (`id_almacen_origen`) REFERENCES `almacenes` (`id`),
  CONSTRAINT `fk_op_alm_destino` FOREIGN KEY (`id_almacen_destino`) REFERENCES `almacenes` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Consumos Reales (Salidas de Inventario)
-- Registra qué se usó realmente, permitiendo ajustes manuales si hubo más gasto.
CREATE TABLE `produccion_consumos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_lote` int(11) DEFAULT NULL COMMENT 'Si el insumo requiere lote',
  `cantidad` decimal(14,4) NOT NULL,
  `costo_unitario` decimal(14,4) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_con_op` FOREIGN KEY (`id_orden_produccion`) REFERENCES `produccion_ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_con_item` FOREIGN KEY (`id_item`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Ingresos Reales (Entradas de Inventario)
-- Registra el producto final o semielaborado que entra al stock.
CREATE TABLE `produccion_ingresos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_lote` int(11) DEFAULT NULL COMMENT 'Nuevo lote generado para el producto',
  `cantidad` decimal(14,4) NOT NULL,
  `costo_unitario_calculado` decimal(14,4) NOT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_ing_op` FOREIGN KEY (`id_orden_produccion`) REFERENCES `produccion_ordenes` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ing_item` FOREIGN KEY (`id_item`) REFERENCES `items` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
