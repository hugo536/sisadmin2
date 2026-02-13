-- 1. Cabecera de Recetas (BOM)
CREATE TABLE `produccion_recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `codigo` varchar(30) NOT NULL UNIQUE,
  `version` int(11) NOT NULL DEFAULT 1,
  `descripcion` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_receta_producto` FOREIGN KEY (`id_producto`) REFERENCES `items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Detalle de Receta
CREATE TABLE `produccion_recetas_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_receta` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `cantidad_por_unidad` decimal(14,4) NOT NULL,
  `merma_porcentaje` decimal(5,2) DEFAULT 0.00,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_det_receta` FOREIGN KEY (`id_receta`) REFERENCES `produccion_recetas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_det_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Órdenes de Producción
CREATE TABLE `produccion_ordenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(30) NOT NULL UNIQUE,
  `id_receta` int(11) NOT NULL,
  `id_almacen_origen` int(11) NOT NULL,
  `id_almacen_destino` int(11) NOT NULL,
  `cantidad_planificada` decimal(14,4) NOT NULL,
  `cantidad_producida` decimal(14,4) DEFAULT 0.0000,
  `estado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=Borrador, 1=En Proceso, 2=Ejecutada, 9=Anulada',
  `fecha_inicio` datetime DEFAULT NULL,
  `fecha_fin` datetime DEFAULT NULL,
  `observaciones` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_op_receta` FOREIGN KEY (`id_receta`) REFERENCES `produccion_recetas` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_op_alm_origen` FOREIGN KEY (`id_almacen_origen`) REFERENCES `almacenes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_op_alm_destino` FOREIGN KEY (`id_almacen_destino`) REFERENCES `almacenes` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Consumos de Producción
CREATE TABLE `produccion_consumos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_lote` int(11) DEFAULT NULL,
  `cantidad` decimal(14,4) NOT NULL,
  `costo_unitario` decimal(14,4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_con_op` FOREIGN KEY (`id_orden_produccion`) REFERENCES `produccion_ordenes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_con_item` FOREIGN KEY (`id_item`) REFERENCES `items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Ingresos de Producción
CREATE TABLE `produccion_ingresos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_orden_produccion` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `id_lote` int(11) DEFAULT NULL,
  `cantidad` decimal(14,4) NOT NULL,
  `costo_unitario_calculado` decimal(14,4) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_ing_op` FOREIGN KEY (`id_orden_produccion`) REFERENCES `produccion_ordenes` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ing_item` FOREIGN KEY (`id_item`) REFERENCES `items` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
