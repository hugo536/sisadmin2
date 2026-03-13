-- =========================================
-- Módulo Gastos: Conceptos + Registro
-- =========================================

CREATE TABLE IF NOT EXISTS gastos_conceptos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL,
    nombre VARCHAR(180) NOT NULL,
    id_centro_costo INT UNSIGNED NOT NULL,
    id_cuenta_contable INT UNSIGNED NULL,
    es_recurrente TINYINT(1) NOT NULL DEFAULT 0,
    dia_vencimiento TINYINT UNSIGNED NULL,
    dias_anticipacion TINYINT UNSIGNED NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    UNIQUE KEY uk_gastos_conceptos_codigo (codigo),
    KEY idx_gastos_conceptos_centro (id_centro_costo),
    KEY idx_gastos_conceptos_cuenta (id_cuenta_contable),
    CONSTRAINT fk_gastos_conceptos_centro FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id),
    CONSTRAINT fk_gastos_conceptos_cuenta FOREIGN KEY (id_cuenta_contable) REFERENCES conta_cuentas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS gastos_registros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    id_proveedor INT UNSIGNED NOT NULL,
    id_concepto INT UNSIGNED NOT NULL,
    monto DECIMAL(14,4) NOT NULL DEFAULT 0,
    impuesto_tipo VARCHAR(10) NOT NULL DEFAULT 'NINGUNO',
    impuesto_monto DECIMAL(14,4) NOT NULL DEFAULT 0,
    total DECIMAL(14,4) NOT NULL DEFAULT 0,
    estado VARCHAR(20) NOT NULL DEFAULT 'PENDIENTE',
    id_cxp INT UNSIGNED NULL,
    id_asiento INT UNSIGNED NULL,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at DATETIME NULL,
    KEY idx_gastos_registros_fecha (fecha),
    KEY idx_gastos_registros_proveedor (id_proveedor),
    KEY idx_gastos_registros_concepto (id_concepto),
    KEY idx_gastos_registros_cxp (id_cxp),
    KEY idx_gastos_registros_asiento (id_asiento),
    CONSTRAINT fk_gastos_registros_proveedor FOREIGN KEY (id_proveedor) REFERENCES terceros(id),
    CONSTRAINT fk_gastos_registros_concepto FOREIGN KEY (id_concepto) REFERENCES gastos_conceptos(id),
    CONSTRAINT fk_gastos_registros_cxp FOREIGN KEY (id_cxp) REFERENCES tesoreria_cxp(id),
    CONSTRAINT fk_gastos_registros_asiento FOREIGN KEY (id_asiento) REFERENCES conta_asientos(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tesoreria_cxp
    ADD COLUMN IF NOT EXISTS id_gasto INT UNSIGNED NULL AFTER id_recepcion,
    ADD KEY idx_tesoreria_cxp_gasto (id_gasto),
    ADD CONSTRAINT fk_tesoreria_cxp_gasto FOREIGN KEY (id_gasto) REFERENCES gastos_registros(id);

-- Opcional: claves iniciales en conta_parametros no son necesarias.
-- Se crearán al asignar desde Contabilidad > Configurar Parámetros.
