ALTER TABLE tesoreria_cxc
  MODIFY COLUMN estado ENUM('PENDIENTE','PARCIAL','PAGADA','VENCIDA','ANULADA','ABIERTA') NOT NULL DEFAULT 'PENDIENTE';

ALTER TABLE tesoreria_cxp
  MODIFY COLUMN estado ENUM('PENDIENTE','PARCIAL','PAGADA','VENCIDA','ANULADA','ABIERTA') NOT NULL DEFAULT 'PENDIENTE';

UPDATE tesoreria_cxc
SET estado = CASE
    WHEN estado = 'ANULADA' THEN 'ANULADA'
    WHEN ROUND(monto_total - monto_pagado, 4) <= 0 THEN 'PAGADA'
    WHEN monto_pagado > 0 THEN 'PARCIAL'
    WHEN DATE(fecha_vencimiento) >= CURDATE() THEN 'PENDIENTE'
    ELSE 'VENCIDA'
END
WHERE deleted_at IS NULL;

UPDATE tesoreria_cxp
SET estado = CASE
    WHEN estado = 'ANULADA' THEN 'ANULADA'
    WHEN ROUND(monto_total - monto_pagado, 4) <= 0 THEN 'PAGADA'
    WHEN monto_pagado > 0 THEN 'PARCIAL'
    WHEN DATE(fecha_vencimiento) >= CURDATE() THEN 'PENDIENTE'
    ELSE 'VENCIDA'
END
WHERE deleted_at IS NULL;
