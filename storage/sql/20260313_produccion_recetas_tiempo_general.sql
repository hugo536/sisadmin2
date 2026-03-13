-- Recetas: tiempo general de producción y estandarización de costos por lote diseñado
ALTER TABLE produccion_recetas
    ADD COLUMN IF NOT EXISTS tiempo_produccion_horas DECIMAL(14,4) NOT NULL DEFAULT 1.0000
    AFTER unidad_rendimiento;

-- Normalizar valores previos nulos o cero
UPDATE produccion_recetas
SET tiempo_produccion_horas = 1.0000
WHERE tiempo_produccion_horas IS NULL OR tiempo_produccion_horas <= 0;
