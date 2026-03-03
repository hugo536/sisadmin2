-- Extiende tesoreria_cuentas para soportar billeteras y metadata bancaria.

ALTER TABLE tesoreria_cuentas
  MODIFY tipo ENUM('CAJA','BANCO','BILLETERA') NOT NULL,
  ADD COLUMN config_banco_id BIGINT(20) UNSIGNED NULL AFTER tipo,
  ADD COLUMN titular VARCHAR(150) NULL AFTER config_banco_id,
  ADD COLUMN tipo_cuenta VARCHAR(30) NULL AFTER titular,
  ADD COLUMN numero_cuenta VARCHAR(80) NULL AFTER tipo_cuenta,
  ADD COLUMN cci VARCHAR(80) NULL AFTER numero_cuenta,
  ADD COLUMN permite_cobros TINYINT(1) NOT NULL DEFAULT 1 AFTER cci,
  ADD COLUMN permite_pagos TINYINT(1) NOT NULL DEFAULT 1 AFTER permite_cobros,
  ADD COLUMN saldo_inicial DECIMAL(14,4) NOT NULL DEFAULT 0.0000 AFTER permite_pagos,
  ADD COLUMN fecha_saldo_inicial DATE NULL AFTER saldo_inicial,
  ADD COLUMN principal TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_saldo_inicial,
  ADD COLUMN observaciones VARCHAR(255) NULL AFTER principal;

ALTER TABLE tesoreria_cuentas
  ADD KEY idx_tes_cuentas_config (config_banco_id);

ALTER TABLE tesoreria_cuentas
  ADD CONSTRAINT fk_tes_cuentas_config
    FOREIGN KEY (config_banco_id)
    REFERENCES configuracion_cajas_bancos(id)
    ON UPDATE CASCADE
    ON DELETE SET NULL;
