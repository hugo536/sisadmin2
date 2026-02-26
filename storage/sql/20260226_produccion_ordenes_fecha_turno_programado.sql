ALTER TABLE `produccion_ordenes`
  ADD COLUMN `fecha_programada` date DEFAULT NULL COMMENT 'Fecha planificada para ejecutar la producción' AFTER `estado`,
  ADD COLUMN `turno_programado` varchar(30) DEFAULT NULL COMMENT 'Turno que ejecutará la orden (Mañana/Tarde/Noche)' AFTER `fecha_programada`;
