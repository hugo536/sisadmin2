-- Elimina columna residual 'principal' de tesoreria_cuentas.
ALTER TABLE tesoreria_cuentas
  DROP COLUMN principal;
