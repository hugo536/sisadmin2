-- Presentaciones mixtas (aditivo, no rompe presentaciones simples)
ALTER TABLE precios_presentaciones
ADD COLUMN es_mixto TINYINT(1) DEFAULT 0 COMMENT '1 = Presentación con múltiples productos/sabores';

CREATE TABLE precios_presentaciones_detalle (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_presentacion INT NOT NULL,
    id_item INT NOT NULL COMMENT 'Producto/sabor incluido (FK a items.id)',
    cantidad DECIMAL(14,4) NOT NULL COMMENT 'Ej. 5 botellas de Piña',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_presentacion) REFERENCES precios_presentaciones(id) ON DELETE CASCADE,
    FOREIGN KEY (id_item) REFERENCES items(id)
);
