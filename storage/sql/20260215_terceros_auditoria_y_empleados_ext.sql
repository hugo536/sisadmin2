-- Extiende auditoría y estructura laboral para Catálogo de Terceros.
-- Compatible con MySQL 8+ (usa ADD COLUMN IF NOT EXISTS).

ALTER TABLE terceros_empleados
    ADD COLUMN IF NOT EXISTS created_by INT(11) NULL AFTER id_tercero,
    ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER created_by,
    ADD COLUMN IF NOT EXISTS deleted_by INT(11) NULL AFTER deleted_at,
    ADD COLUMN IF NOT EXISTS genero ENUM('Masculino','Femenino','Otro') NULL AFTER fecha_nacimiento,
    ADD COLUMN IF NOT EXISTS estado_civil ENUM('Soltero','Casado','Divorciado','Viudo','Conviviente') NULL AFTER genero,
    ADD COLUMN IF NOT EXISTS nivel_educativo VARCHAR(50) NULL AFTER estado_civil,
    ADD COLUMN IF NOT EXISTS contacto_emergencia_nombre VARCHAR(100) NULL AFTER essalud,
    ADD COLUMN IF NOT EXISTS contacto_emergencia_telf VARCHAR(20) NULL AFTER contacto_emergencia_nombre,
    ADD COLUMN IF NOT EXISTS tipo_sangre VARCHAR(5) NULL AFTER contacto_emergencia_telf;

ALTER TABLE terceros_roles
    ADD COLUMN IF NOT EXISTS created_by INT(11) NULL AFTER rol,
    ADD COLUMN IF NOT EXISTS updated_by INT(11) NULL AFTER created_by,
    ADD COLUMN IF NOT EXISTS updated_at DATETIME NULL AFTER created_at,
    ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER updated_at,
    ADD COLUMN IF NOT EXISTS deleted_by INT(11) NULL AFTER deleted_at,
    ADD COLUMN IF NOT EXISTS estado TINYINT(1) NOT NULL DEFAULT 1 AFTER deleted_by;

ALTER TABLE distribuidores
    ADD COLUMN IF NOT EXISTS deleted_by INT(11) NULL AFTER deleted_at,
    ADD COLUMN IF NOT EXISTS estado TINYINT(1) NOT NULL DEFAULT 1 AFTER deleted_by;
