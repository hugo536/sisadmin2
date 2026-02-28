ALTER TABLE `terceros_empleados`
ADD COLUMN `codigo_biometrico` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Código EnNo del reloj biométrico' AFTER `id_tercero`;
