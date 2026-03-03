-- Fix: autocompletar parámetros contables mínimos para tesorería/cuentas por pagar.
-- Ejecutar una sola vez en el ambiente afectado.

INSERT INTO conta_parametros (clave, id_cuenta, created_by, updated_by, created_at, updated_at)
SELECT 'CTA_CAJA_DEFECTO', c.id, 1, 1, NOW(), NOW()
FROM conta_cuentas c
LEFT JOIN conta_parametros p
  ON p.clave = 'CTA_CAJA_DEFECTO'
 AND p.deleted_at IS NULL
WHERE p.id IS NULL
  AND c.deleted_at IS NULL
  AND c.estado = 1
  AND c.permite_movimiento = 1
  AND (
      UPPER(c.nombre) LIKE '%CAJA%'
      OR UPPER(c.nombre) LIKE '%EFECTIVO%'
      OR UPPER(c.nombre) LIKE '%BANCO%'
      OR c.codigo LIKE '10%'
      OR c.tipo = 'ACTIVO'
  )
ORDER BY
  CASE
      WHEN UPPER(c.nombre) LIKE '%CAJA%' THEN 0
      WHEN UPPER(c.nombre) LIKE '%EFECTIVO%' THEN 0
      WHEN UPPER(c.nombre) LIKE '%BANCO%' THEN 1
      WHEN c.codigo LIKE '10%' THEN 2
      WHEN c.tipo = 'ACTIVO' THEN 3
      ELSE 9
  END,
  c.codigo ASC,
  c.id ASC
LIMIT 1;

INSERT INTO conta_parametros (clave, id_cuenta, created_by, updated_by, created_at, updated_at)
SELECT 'CTA_CXP', c.id, 1, 1, NOW(), NOW()
FROM conta_cuentas c
LEFT JOIN conta_parametros p
  ON p.clave = 'CTA_CXP'
 AND p.deleted_at IS NULL
WHERE p.id IS NULL
  AND c.deleted_at IS NULL
  AND c.estado = 1
  AND c.permite_movimiento = 1
  AND (
      UPPER(c.nombre) LIKE '%CUENTAS POR PAGAR%'
      OR UPPER(c.nombre) LIKE '%PROVEEDOR%'
      OR c.codigo LIKE '42%'
      OR c.tipo = 'PASIVO'
  )
ORDER BY
  CASE
      WHEN UPPER(c.nombre) LIKE '%CUENTAS POR PAGAR%' THEN 0
      WHEN UPPER(c.nombre) LIKE '%PROVEEDOR%' THEN 1
      WHEN c.codigo LIKE '42%' THEN 2
      WHEN c.tipo = 'PASIVO' THEN 3
      ELSE 9
  END,
  c.codigo ASC,
  c.id ASC
LIMIT 1;
