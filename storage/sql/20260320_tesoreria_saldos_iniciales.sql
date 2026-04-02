-- Permitir registros migrados sin documento de origen del sistema.
ALTER TABLE `tesoreria_cxc`
    MODIFY `id_documento_venta` int(11) NULL;

ALTER TABLE `tesoreria_cxp`
    MODIFY `id_recepcion` int(11) NULL;

-- Trazabilidad del origen y referencia física.
ALTER TABLE `tesoreria_cxc`
    ADD COLUMN `origen` VARCHAR(20) NOT NULL DEFAULT 'SISTEMA' COMMENT 'SISTEMA o MIGRACION' AFTER `id_documento_venta`,
    ADD COLUMN `documento_referencia` VARCHAR(50) NULL COMMENT 'Ej: Factura Excel F001-2023' AFTER `origen`;

ALTER TABLE `tesoreria_cxp`
    ADD COLUMN `origen` VARCHAR(20) NOT NULL DEFAULT 'SISTEMA' COMMENT 'SISTEMA o MIGRACION' AFTER `id_recepcion`,
    ADD COLUMN `documento_referencia` VARCHAR(50) NULL COMMENT 'Ej: Factura Proveedor 001-999' AFTER `origen`;
