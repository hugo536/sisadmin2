ALTER TABLE terceros_empleados
    ADD COLUMN recordar_cumpleanos TINYINT(1) NOT NULL DEFAULT 0 AFTER essalud,
    ADD COLUMN fecha_nacimiento DATE NULL AFTER recordar_cumpleanos;
