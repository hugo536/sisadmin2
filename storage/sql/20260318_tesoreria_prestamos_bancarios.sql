-- Módulo Tesorería > Préstamos Bancarios
-- Fecha: 2026-03-18

CREATE TABLE IF NOT EXISTS tesoreria_prestamos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_cxp INT NOT NULL,
    numero_contrato VARCHAR(80) NULL,
    entidad_financiera VARCHAR(160) NOT NULL,
    fecha_desembolso DATE NOT NULL,
    fecha_primera_cuota DATE NULL,
    tipo_tasa ENUM('FIJA', 'VARIABLE') NOT NULL DEFAULT 'FIJA',
    tasa_anual DECIMAL(10,4) NOT NULL DEFAULT 0,
    numero_cuotas INT NOT NULL DEFAULT 1,
    observaciones VARCHAR(255) NULL,
    created_by INT NULL,
    updated_by INT NULL,
    deleted_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uq_tesoreria_prestamos_id_cxp (id_cxp),
    KEY idx_tesoreria_prestamos_deleted (deleted_at),
    CONSTRAINT fk_tesoreria_prestamos_cxp FOREIGN KEY (id_cxp) REFERENCES tesoreria_cxp(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
