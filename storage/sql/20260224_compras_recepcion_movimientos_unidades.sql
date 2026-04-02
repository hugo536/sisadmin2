ALTER TABLE `compras_recepciones_detalle`
    ADD COLUMN `id_item_unidad` INT NULL AFTER `id_item`,
    ADD CONSTRAINT `fk_recdet_unidad` FOREIGN KEY (`id_item_unidad`) REFERENCES `items_unidades` (`id`);

ALTER TABLE `inventario_movimientos`
    ADD COLUMN `id_item_unidad` INT NULL AFTER `id_item`,
    ADD CONSTRAINT `fk_mov_unidad` FOREIGN KEY (`id_item_unidad`) REFERENCES `items_unidades` (`id`);
