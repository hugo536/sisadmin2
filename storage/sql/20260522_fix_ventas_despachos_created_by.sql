-- Corrige incompatibilidades entre ambientes cuando created_by existe sin default
-- en tablas de despacho y el INSERT no lo enviaba.

ALTER TABLE ventas_despachos
    MODIFY COLUMN created_by INT NULL;

ALTER TABLE ventas_despachos_detalle
    MODIFY COLUMN created_by INT NULL;
