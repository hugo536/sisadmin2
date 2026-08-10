-- Permite que los adelantos de personal queden identificados en el ledger de tesorería.
-- Los valores existentes se conservan al ampliar ambos ENUM.
ALTER TABLE tesoreria_movimientos
  MODIFY COLUMN tipo ENUM('COBRO','PAGO','INGRESO','EGRESO') NOT NULL,
  MODIFY COLUMN origen ENUM('CXC','CXP','ADELANTO') NOT NULL;
