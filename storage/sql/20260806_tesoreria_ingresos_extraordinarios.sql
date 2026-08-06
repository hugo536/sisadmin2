ALTER TABLE tesoreria_movimientos
  MODIFY tipo ENUM('COBRO','PAGO','INGRESO','EGRESO') NOT NULL,
  MODIFY origen ENUM('CXC','CXP','INGRESO_EXTRA') NOT NULL,
  MODIFY id_tercero INT NULL;

CREATE TABLE IF NOT EXISTS tesoreria_ingresos_extraordinarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_cuenta INT NOT NULL,
  fecha DATE NOT NULL,
  moneda ENUM('PEN','USD') NOT NULL DEFAULT 'PEN',
  monto DECIMAL(14,4) NOT NULL,
  concepto VARCHAR(200) NOT NULL,
  referencia VARCHAR(120) NULL,
  estado TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  deleted_at DATETIME NULL,
  created_by INT NULL,
  updated_by INT NULL,
  deleted_by INT NULL,
  KEY idx_tes_ing_extra_cuenta_fecha (id_cuenta, fecha),
  KEY idx_tes_ing_extra_estado (estado),
  CONSTRAINT fk_tes_ing_extra_cuenta FOREIGN KEY (id_cuenta) REFERENCES tesoreria_cuentas(id),
  CONSTRAINT chk_tes_ing_extra_monto CHECK (monto > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
