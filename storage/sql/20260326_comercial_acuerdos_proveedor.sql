CREATE TABLE IF NOT EXISTS `comercial_acuerdos_proveedor` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_tercero` int(11) NOT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_comercial_acuerdo_proveedor` (`id_tercero`),
  CONSTRAINT `fk_comercial_acuerdo_proveedor_tercero`
    FOREIGN KEY (`id_tercero`) REFERENCES `terceros` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `comercial_acuerdos_proveedor_precios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_acuerdo_proveedor` int(11) NOT NULL,
  `id_item` int(11) NOT NULL,
  `precio_recomendado` decimal(12,4) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_item_en_acuerdo_proveedor` (`id_acuerdo_proveedor`, `id_item`),
  CONSTRAINT `fk_acuerdo_proveedor_precio_acuerdo`
    FOREIGN KEY (`id_acuerdo_proveedor`) REFERENCES `comercial_acuerdos_proveedor` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_acuerdo_proveedor_precio_item`
    FOREIGN KEY (`id_item`) REFERENCES `items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
