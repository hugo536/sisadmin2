-- Semana 8: Reportes y Control (Operativo + Gerencial)
-- Solo lectura (reportes) + performance + RBAC

-- Permisos RBAC
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'reportes.dashboard.ver', 'Reportes: Ver dashboard operativo', 'Reportes'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'reportes.dashboard.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'reportes.inventario.ver', 'Reportes: Ver inventario', 'Reportes'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'reportes.inventario.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'reportes.compras.ver', 'Reportes: Ver compras', 'Reportes'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'reportes.compras.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'reportes.ventas.ver', 'Reportes: Ver ventas', 'Reportes'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'reportes.ventas.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'reportes.produccion.ver', 'Reportes: Ver producción', 'Reportes'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'reportes.produccion.ver');

INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'reportes.tesoreria.ver', 'Reportes: Ver tesorería', 'Reportes'
WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = 'reportes.tesoreria.ver');

-- Índices recomendados para consultas de reporte por fecha/entidad
-- Nota: ejecutar una sola vez en entorno donde no existan aún estos nombres de índice.
CREATE INDEX idx_inv_mov_created_item_almacen ON inventario_movimientos (created_at, id_item, id_almacen_origen, id_almacen_destino);
CREATE INDEX idx_inv_stock_item_almacen ON inventario_stock (id_item, id_almacen, stock_actual);
CREATE INDEX idx_inv_lotes_vencimiento ON inventario_lotes (fecha_vencimiento, id_item, id_almacen);

CREATE INDEX idx_comp_orden_fecha_proveedor ON compras_ordenes (fecha_emision, id_proveedor, estado);
CREATE INDEX idx_comp_recep_fecha_almacen ON compras_recepciones (fecha_recepcion, id_almacen);

CREATE INDEX idx_venta_doc_fecha_cliente ON ventas_documentos (fecha_emision, id_cliente, estado);
CREATE INDEX idx_venta_det_doc_despachada ON ventas_documentos_detalle (id_documento_venta, cantidad, cantidad_despachada);

CREATE INDEX idx_prod_orden_fecha_estado ON produccion_ordenes (fecha_programada, estado, id_receta);
CREATE INDEX idx_prod_consumo_created_item ON produccion_consumos (created_at, id_item);
CREATE INDEX idx_prod_ingreso_created_item ON produccion_ingresos (created_at, id_item);

CREATE INDEX idx_tes_cxc_fecha_venc ON tesoreria_cxc (fecha_emision, fecha_vencimiento, id_cliente, saldo);
CREATE INDEX idx_tes_cxp_fecha_venc ON tesoreria_cxp (fecha_emision, fecha_vencimiento, id_proveedor, saldo);
CREATE INDEX idx_tes_mov_fecha_cuenta ON tesoreria_movimientos (fecha, id_cuenta, tipo);
