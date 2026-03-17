-- Semana 7 - Tesorería (CxC / CxP + Cobros y Pagos)

CREATE TABLE IF NOT EXISTS tesoreria_cuentas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  nombre VARCHAR(120) NOT NULL,
  tipo ENUM('CAJA','BANCO') NOT NULL,
  moneda ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  INDEX idx_tesoreria_cuentas_estado (estado, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tesoreria_metodos_pago (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(60) NOT NULL UNIQUE,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  INDEX idx_tesoreria_metodos_estado (estado, deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tesoreria_cxc (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cliente INT NOT NULL,
  id_documento_venta INT NOT NULL,
  fecha_emision DATE NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  moneda ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',
  monto_total DECIMAL(14,4) NOT NULL,
  monto_pagado DECIMAL(14,4) NOT NULL DEFAULT 0,
  saldo DECIMAL(14,4) NOT NULL,
  estado ENUM('ABIERTA','PARCIAL','PAGADA','VENCIDA','ANULADA') NOT NULL DEFAULT 'ABIERTA',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  UNIQUE KEY uq_tesoreria_cxc_documento (id_documento_venta),
  INDEX idx_tesoreria_cxc_cliente (id_cliente, estado),
  INDEX idx_tesoreria_cxc_vencimiento (fecha_vencimiento, estado),
  CONSTRAINT fk_tes_cxc_cliente FOREIGN KEY (id_cliente) REFERENCES terceros(id),
  CONSTRAINT fk_tes_cxc_venta FOREIGN KEY (id_documento_venta) REFERENCES ventas_documentos(id),
  CONSTRAINT chk_tes_cxc_saldos CHECK (monto_pagado >= 0 AND saldo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tesoreria_cxp (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_proveedor INT NOT NULL,
  id_orden_compra INT NULL,
  id_recepcion INT NOT NULL,
  fecha_emision DATE NOT NULL,
  fecha_vencimiento DATE NOT NULL,
  moneda ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',
  monto_total DECIMAL(14,4) NOT NULL,
  monto_pagado DECIMAL(14,4) NOT NULL DEFAULT 0,
  saldo DECIMAL(14,4) NOT NULL,
  estado ENUM('ABIERTA','PARCIAL','PAGADA','VENCIDA','ANULADA') NOT NULL DEFAULT 'ABIERTA',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  UNIQUE KEY uq_tesoreria_cxp_recepcion (id_recepcion),
  INDEX idx_tesoreria_cxp_proveedor (id_proveedor, estado),
  INDEX idx_tesoreria_cxp_vencimiento (fecha_vencimiento, estado),
  CONSTRAINT fk_tes_cxp_proveedor FOREIGN KEY (id_proveedor) REFERENCES terceros(id),
  CONSTRAINT fk_tes_cxp_orden FOREIGN KEY (id_orden_compra) REFERENCES compras_ordenes(id) ON DELETE SET NULL,
  CONSTRAINT fk_tes_cxp_recepcion FOREIGN KEY (id_recepcion) REFERENCES compras_recepciones(id),
  CONSTRAINT chk_tes_cxp_saldos CHECK (monto_pagado >= 0 AND saldo >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tesoreria_movimientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo ENUM('COBRO','PAGO') NOT NULL,
  id_tercero INT NOT NULL,
  origen ENUM('CXC','CXP') NOT NULL,
  id_origen INT NOT NULL,
  id_cuenta INT NOT NULL,
  id_metodo_pago INT NOT NULL,
  fecha DATE NOT NULL,
  moneda ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',
  monto DECIMAL(14,4) NOT NULL,
  naturaleza_pago ENUM('DOCUMENTO','CAPITAL','INTERES','MIXTO') NOT NULL DEFAULT 'DOCUMENTO',
  monto_capital DECIMAL(14,4) NOT NULL DEFAULT 0,
  monto_interes DECIMAL(14,4) NOT NULL DEFAULT 0,
  id_centro_costo INT NULL,
  referencia VARCHAR(120) NULL,
  observaciones VARCHAR(255) NULL,
  estado ENUM('CONFIRMADO','ANULADO') NOT NULL DEFAULT 'CONFIRMADO',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  INDEX idx_tes_mov_origen (origen, id_origen, estado),
  INDEX idx_tes_mov_tercero (id_tercero, fecha),
  INDEX idx_tes_mov_cuenta (id_cuenta, fecha),
  INDEX idx_tes_mov_naturaleza (naturaleza_pago),
  INDEX idx_tes_mov_centro_costo (id_centro_costo),
  CONSTRAINT fk_tes_mov_tercero FOREIGN KEY (id_tercero) REFERENCES terceros(id),
  CONSTRAINT fk_tes_mov_cuenta FOREIGN KEY (id_cuenta) REFERENCES tesoreria_cuentas(id),
  CONSTRAINT fk_tes_mov_metodo FOREIGN KEY (id_metodo_pago) REFERENCES tesoreria_metodos_pago(id),
  CONSTRAINT fk_tes_mov_centro_costo FOREIGN KEY (id_centro_costo) REFERENCES conta_centros_costo(id),
  CONSTRAINT chk_tes_mov_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO tesoreria_metodos_pago (nombre, estado, created_at, updated_at)
SELECT 'Efectivo', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM tesoreria_metodos_pago WHERE nombre = 'Efectivo');
INSERT INTO tesoreria_metodos_pago (nombre, estado, created_at, updated_at)
SELECT 'Transferencia', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM tesoreria_metodos_pago WHERE nombre = 'Transferencia');
INSERT INTO tesoreria_metodos_pago (nombre, estado, created_at, updated_at)
SELECT 'Yape/Plin', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM tesoreria_metodos_pago WHERE nombre = 'Yape/Plin');
INSERT INTO tesoreria_metodos_pago (nombre, estado, created_at, updated_at)
SELECT 'Tarjeta', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM tesoreria_metodos_pago WHERE nombre = 'Tarjeta');
INSERT INTO tesoreria_metodos_pago (nombre, estado, created_at, updated_at)
SELECT 'Cheque', 1, NOW(), NOW() WHERE NOT EXISTS (SELECT 1 FROM tesoreria_metodos_pago WHERE nombre = 'Cheque');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'tesoreria.ver', 'Tesorería: Ver módulo', 'Tesorería'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'tesoreria.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'tesoreria.cxc.ver', 'Tesorería: Ver CxC', 'Tesorería'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'tesoreria.cxc.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'tesoreria.cxp.ver', 'Tesorería: Ver CxP', 'Tesorería'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'tesoreria.cxp.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'tesoreria.cobros.registrar', 'Tesorería: Registrar cobros', 'Tesorería'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'tesoreria.cobros.registrar');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'tesoreria.pagos.registrar', 'Tesorería: Registrar pagos', 'Tesorería'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'tesoreria.pagos.registrar');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'tesoreria.movimientos.anular', 'Tesorería: Anular movimientos', 'Tesorería'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'tesoreria.movimientos.anular');
