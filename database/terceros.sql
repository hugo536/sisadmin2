CREATE TABLE terceros (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_persona VARCHAR(20) NOT NULL,
    tipo_documento VARCHAR(20) NOT NULL,
    numero_documento VARCHAR(20) NOT NULL,
    nombre_completo VARCHAR(200) NOT NULL,
    direccion VARCHAR(255) DEFAULT NULL,
    telefono VARCHAR(20) DEFAULT NULL,
    email VARCHAR(120) DEFAULT NULL,
    departamento VARCHAR(80) DEFAULT NULL,
    provincia VARCHAR(80) DEFAULT NULL,
    distrito VARCHAR(80) DEFAULT NULL,
    rubro_sector VARCHAR(80) DEFAULT NULL,
    observaciones TEXT DEFAULT NULL,
    es_cliente TINYINT(1) NOT NULL DEFAULT 0,
    es_proveedor TINYINT(1) NOT NULL DEFAULT 0,
    es_empleado TINYINT(1) NOT NULL DEFAULT 0,
    condicion_pago VARCHAR(80) DEFAULT NULL,
    dias_credito INT DEFAULT NULL,
    limite_credito DECIMAL(12,2) DEFAULT NULL,
    estado TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    deleted_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY terceros_documento_unique (tipo_documento, numero_documento)
);

CREATE TABLE terceros_empleados (
    id_tercero INT UNSIGNED PRIMARY KEY,
    cargo VARCHAR(80) DEFAULT NULL,
    area VARCHAR(80) DEFAULT NULL,
    fecha_ingreso DATE DEFAULT NULL,
    estado_laboral VARCHAR(40) DEFAULT NULL,
    sueldo_basico DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tipo_pago VARCHAR(20) DEFAULT NULL,
    pago_diario DECIMAL(12,2) DEFAULT NULL,
    regimen_pensionario VARCHAR(10) DEFAULT NULL,
    essalud TINYINT(1) NOT NULL DEFAULT 0,
    updated_by INT DEFAULT NULL,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_terceros_empleados_tercero
        FOREIGN KEY (id_tercero) REFERENCES terceros (id)
);

CREATE TABLE terceros_roles (
    tercero_id INT UNSIGNED NOT NULL,
    rol VARCHAR(20) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (tercero_id, rol),
    CONSTRAINT fk_terceros_roles_tercero
        FOREIGN KEY (tercero_id) REFERENCES terceros (id)
);

CREATE TABLE terceros_telefonos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tercero_id INT UNSIGNED NOT NULL,
    telefono VARCHAR(20) NOT NULL,
    tipo VARCHAR(30) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_terceros_telefonos_tercero
        FOREIGN KEY (tercero_id) REFERENCES terceros (id)
);

CREATE TABLE terceros_cuentas_bancarias (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tercero_id INT UNSIGNED NOT NULL,
    tipo_entidad VARCHAR(30) NOT NULL,
    entidad VARCHAR(80) NOT NULL,
    tipo_cuenta VARCHAR(30) DEFAULT NULL,
    numero_cuenta VARCHAR(40) NOT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT fk_terceros_cuentas_tercero
        FOREIGN KEY (tercero_id) REFERENCES terceros (id)
);
