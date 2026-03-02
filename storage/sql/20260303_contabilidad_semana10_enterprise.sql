-- Semana 10: Cierre avanzado y funcionalidades enterprise

CREATE TABLE IF NOT EXISTS conta_centros_costo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(120) NOT NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_conta_centros_codigo (codigo),
  KEY idx_conta_centros_estado (estado, deleted_at)
) ENGINE=InnoDB;

ALTER TABLE conta_asientos_detalle
  ADD COLUMN id_centro_costo INT NULL AFTER id_cuenta,
  ADD KEY idx_conta_det_centro (id_centro_costo),
  ADD CONSTRAINT fk_conta_det_centro FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id);

CREATE TABLE IF NOT EXISTS tesoreria_conciliaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cuenta_bancaria INT NOT NULL,
  periodo CHAR(7) NOT NULL,
  saldo_estado_cuenta DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  saldo_sistema DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  diferencia DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  estado ENUM('ABIERTA','CERRADA') NOT NULL DEFAULT 'ABIERTA',
  observaciones VARCHAR(255) NULL,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_tes_conciliacion_periodo (id_cuenta_bancaria, periodo),
  KEY idx_tes_conciliaciones_estado (estado, deleted_at),
  CONSTRAINT fk_tes_conciliacion_cuenta FOREIGN KEY (id_cuenta_bancaria) REFERENCES tesoreria_cuentas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS tesoreria_conciliaciones_detalle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_conciliacion INT NOT NULL,
  fecha DATE NOT NULL,
  descripcion VARCHAR(180) NOT NULL,
  monto DECIMAL(14,4) NOT NULL,
  referencia VARCHAR(120) NULL,
  conciliado TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  KEY idx_tes_conc_det_conciliado (id_conciliacion, conciliado),
  CONSTRAINT fk_tes_conc_det_conciliacion FOREIGN KEY (id_conciliacion) REFERENCES tesoreria_conciliaciones(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS activos_fijos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo_activo VARCHAR(30) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  fecha_adquisicion DATE NOT NULL,
  costo_adquisicion DECIMAL(14,4) NOT NULL,
  vida_util_meses INT NOT NULL,
  valor_residual DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  depreciacion_acumulada DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  valor_libros DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  id_cuenta_activo INT NOT NULL,
  id_cuenta_depreciacion INT NOT NULL,
  estado ENUM('ACTIVO','DEPRECIADO','BAJA') NOT NULL DEFAULT 'ACTIVO',
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_activos_codigo (codigo_activo),
  KEY idx_activos_estado (estado, deleted_at),
  CONSTRAINT fk_activos_cta_activo FOREIGN KEY (id_cuenta_activo) REFERENCES conta_cuentas(id),
  CONSTRAINT fk_activos_cta_dep FOREIGN KEY (id_cuenta_depreciacion) REFERENCES conta_cuentas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_depreciaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_activo_fijo INT NOT NULL,
  periodo CHAR(7) NOT NULL,
  monto DECIMAL(14,4) NOT NULL,
  id_asiento INT NULL,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_dep_activo_periodo (id_activo_fijo, periodo),
  KEY idx_dep_periodo (periodo, deleted_at),
  CONSTRAINT fk_dep_activo FOREIGN KEY (id_activo_fijo) REFERENCES activos_fijos(id),
  CONSTRAINT fk_dep_asiento FOREIGN KEY (id_asiento) REFERENCES conta_asientos(id)
) ENGINE=InnoDB;

ALTER TABLE conta_periodos
  ADD COLUMN cierre_mensual_at DATETIME NULL,
  ADD COLUMN cierre_mensual_by INT NULL,
  ADD COLUMN cierre_anual_at DATETIME NULL,
  ADD COLUMN cierre_anual_by INT NULL,
  ADD COLUMN cierre_observaciones VARCHAR(255) NULL;

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.conciliacion.gestionar', 'Contabilidad: Gestionar conciliación bancaria', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.conciliacion.gestionar');
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.centros_costo.gestionar', 'Contabilidad: Gestionar centros de costo', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.centros_costo.gestionar');
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'activos.gestionar', 'Activos: Gestionar activos fijos', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'activos.gestionar');
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.depreciacion.ejecutar', 'Contabilidad: Ejecutar depreciación mensual', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.depreciacion.ejecutar');
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.cierre.mensual', 'Contabilidad: Ejecutar cierre mensual', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.cierre.mensual');
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.cierre.anual', 'Contabilidad: Ejecutar cierre anual', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.cierre.anual');
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'auditoria.ver', 'Auditoría: Modo solo lectura', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'auditoria.ver');
