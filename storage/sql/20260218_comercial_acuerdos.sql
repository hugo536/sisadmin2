-- Migración: Acuerdos Comerciales (Matriz de Tarifas por Cliente)

CREATE TABLE IF NOT EXISTS `comercial_acuerdos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tercero` int(11) NOT NULL COMMENT 'Relación con tabla terceros',
  `observaciones` text DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=Activo, 0=Inactivo',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_cliente_unico` (`id_tercero`),
  CONSTRAINT `fk_acuerdo_tercero` FOREIGN KEY (`id_tercero`) REFERENCES `terceros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `comercial_acuerdos_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_acuerdo` int(11) NOT NULL,
  `id_presentacion` int(11) NOT NULL,
  `precio_pactado` decimal(14,4) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_producto_en_acuerdo` (`id_acuerdo`, `id_presentacion`),
  CONSTRAINT `fk_precio_acuerdo` FOREIGN KEY (`id_acuerdo`) REFERENCES `comercial_acuerdos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_precio_pres` FOREIGN KEY (`id_presentacion`) REFERENCES `precios_presentaciones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
