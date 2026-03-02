-- Semana 9: Contabilidad básica

CREATE TABLE IF NOT EXISTS conta_cuentas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(20) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  tipo ENUM('ACTIVO','PASIVO','PATRIMONIO','INGRESO','GASTO') NOT NULL,
  nivel INT NOT NULL,
  id_padre INT NULL,
  permite_movimiento TINYINT(1) NOT NULL DEFAULT 0,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_conta_cuentas_codigo (codigo),
  KEY idx_conta_cuentas_padre (id_padre),
  CONSTRAINT fk_conta_cuentas_padre FOREIGN KEY (id_padre) REFERENCES conta_cuentas(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_periodos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  anio INT NOT NULL,
  mes TINYINT NOT NULL,
  fecha_inicio DATE NOT NULL,
  fecha_fin DATE NOT NULL,
  estado ENUM('ABIERTO','CERRADO') NOT NULL DEFAULT 'ABIERTO',
  cerrado_at DATETIME NULL,
  cerrado_by INT NULL,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_conta_periodos_anio_mes (anio, mes)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_asientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL,
  fecha DATE NOT NULL,
  id_periodo INT NOT NULL,
  glosa VARCHAR(255) NOT NULL,
  origen_modulo ENUM('MANUAL','TESORERIA','COMPRAS','VENTAS','PRODUCCION') NOT NULL DEFAULT 'MANUAL',
  id_origen INT NULL,
  estado ENUM('REGISTRADO','ANULADO') NOT NULL DEFAULT 'REGISTRADO',
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_conta_asientos_codigo (codigo),
  KEY idx_conta_asientos_periodo (id_periodo),
  KEY idx_conta_asientos_origen (origen_modulo, id_origen),
  KEY idx_conta_asientos_estado_fecha (estado, fecha),
  KEY idx_conta_asientos_deleted_at (deleted_at),
  CONSTRAINT fk_conta_asientos_periodo FOREIGN KEY (id_periodo) REFERENCES conta_periodos(id)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_asientos_detalle (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_asiento INT NOT NULL,
  id_cuenta INT NOT NULL,
  debe DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  haber DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
  id_tercero INT NULL,
  referencia VARCHAR(120) NULL,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  KEY idx_conta_det_asiento (id_asiento),
  KEY idx_conta_det_cuenta (id_cuenta),
  KEY idx_conta_det_deleted_at (deleted_at),
  CONSTRAINT fk_conta_det_asiento FOREIGN KEY (id_asiento) REFERENCES conta_asientos(id),
  CONSTRAINT fk_conta_det_cuenta FOREIGN KEY (id_cuenta) REFERENCES conta_cuentas(id),
  CONSTRAINT chk_conta_det_debe_haber CHECK ((debe > 0 AND haber = 0) OR (debe = 0 AND haber > 0))
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS conta_parametros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clave VARCHAR(80) NOT NULL,
  id_cuenta INT NOT NULL,
  created_at DATETIME NULL,
  created_by INT NULL,
  updated_at DATETIME NULL,
  updated_by INT NULL,
  deleted_at DATETIME NULL,
  deleted_by INT NULL,
  UNIQUE KEY uk_conta_parametros_clave (clave),
  KEY idx_conta_param_deleted_at (deleted_at),
  CONSTRAINT fk_conta_param_cuenta FOREIGN KEY (id_cuenta) REFERENCES conta_cuentas(id)
) ENGINE=InnoDB;

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.ver', 'Contabilidad: Ver módulo', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.plan_contable.gestionar', 'Contabilidad: Gestionar plan contable', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.plan_contable.gestionar');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.asientos.crear', 'Contabilidad: Crear asientos', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.asientos.crear');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.asientos.anular', 'Contabilidad: Anular asientos', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.asientos.anular');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.periodos.ver', 'Contabilidad: Ver periodos', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.periodos.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.periodos.cerrar', 'Contabilidad: Abrir/Cerrar periodos', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.periodos.cerrar');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'conta.reportes.ver', 'Contabilidad: Ver reportes', 'Contabilidad'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'conta.reportes.ver');
