-- Permite marcar una única unidad de conversión como predeterminada por ítem.
-- Ejecutar antes de habilitar esta funcionalidad en instalaciones existentes.
ALTER TABLE items_unidades
    ADD COLUMN es_predeterminada TINYINT(1) NOT NULL DEFAULT 0 AFTER estado;

-- Rollback sugerido:
-- ALTER TABLE items_unidades DROP COLUMN es_predeterminada;
