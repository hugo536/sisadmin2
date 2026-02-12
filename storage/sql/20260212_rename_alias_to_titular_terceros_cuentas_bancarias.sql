ALTER TABLE `terceros_cuentas_bancarias`
    CHANGE COLUMN `alias` `titular` VARCHAR(150) DEFAULT NULL COMMENT 'Nombre del beneficiario/titular de la cuenta';
