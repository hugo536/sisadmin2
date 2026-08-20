-- Separa el despacho de la aprobación sin quitar acceso a los roles existentes.
-- Script idempotente: puede ejecutarse más de una vez.
INSERT INTO permisos_def (slug, nombre, modulo)
SELECT 'ventas.despachar', 'Despachar Ventas', 'VENTAS'
WHERE NOT EXISTS (
    SELECT 1 FROM permisos_def WHERE slug = 'ventas.despachar'
);

-- Restaura una asociación borrada si el rol ya podía aprobar ventas.
UPDATE roles_permisos destino
INNER JOIN permisos_def permiso_despacho
    ON permiso_despacho.id = destino.id_permiso
   AND permiso_despacho.slug = 'ventas.despachar'
INNER JOIN roles_permisos permiso_previo
    ON permiso_previo.id_rol = destino.id_rol
   AND permiso_previo.deleted_at IS NULL
INNER JOIN permisos_def permiso_aprobar
    ON permiso_aprobar.id = permiso_previo.id_permiso
   AND permiso_aprobar.slug = 'ventas.aprobar'
SET destino.deleted_at = NULL;

-- Conserva el comportamiento histórico para roles que tenían ventas.aprobar.
INSERT INTO roles_permisos (id_rol, id_permiso, created_at, created_by)
SELECT permiso_previo.id_rol, permiso_despacho.id, NOW(), permiso_previo.created_by
FROM roles_permisos permiso_previo
INNER JOIN permisos_def permiso_aprobar
    ON permiso_aprobar.id = permiso_previo.id_permiso
   AND permiso_aprobar.slug = 'ventas.aprobar'
CROSS JOIN permisos_def permiso_despacho
WHERE permiso_despacho.slug = 'ventas.despachar'
  AND permiso_previo.deleted_at IS NULL
  AND NOT EXISTS (
      SELECT 1
      FROM roles_permisos existente
      WHERE existente.id_rol = permiso_previo.id_rol
        AND existente.id_permiso = permiso_despacho.id
  );
