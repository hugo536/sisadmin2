<?php
declare(strict_types=1);

class PermisoModel extends Modelo
{
    /** @var array<string, array<int, string>> */
    private array $cacheColumnas = [];
    private bool $catalogoBaseSincronizado = false;

    /**
     * Obtiene los slugs de permisos activos para un rol específico.
     * Utilizado por el AuthMiddleware y Helpers para validar acceso.
     * * @param int $idRol
     * @return array Lista de slugs (ej. ['ventas.ver', 'compras.crear'])
     */
    public function obtener_slugs_por_rol(int $idRol): array
    {
        // Se une con roles para asegurar que el rol esté activo (estado=1)
        // Se valida deleted_at en todas las tablas por integridad
        $sql = 'SELECT pd.slug
                FROM roles_permisos rp
                INNER JOIN permisos_def pd ON pd.id = rp.id_permiso
                INNER JOIN roles r ON r.id = rp.id_rol
                WHERE rp.id_rol = :id_rol
                  AND rp.deleted_at IS NULL
                  AND pd.deleted_at IS NULL
                  AND r.estado = 1
                  AND r.deleted_at IS NULL';
                  
        $stmt = $this->db()->prepare($sql);
        $stmt->execute(['id_rol' => $idRol]);
        
        $slugs = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Retornamos array limpio de strings únicos
        return array_values(array_unique(array_map('strval', $slugs ?: [])));
    }

    /**
     * Lista todos los permisos agrupados por módulo.
     * Utilizado en la vista de Matriz de Permisos.
     * * @return array [ 'Ventas' => [...permisos], 'Logística' => [...] ]
     */
    public function listar_agrupados_modulo(): array
    {
        $rows = $this->listar_activos();
        $grupos = [];

        foreach ($rows as $row) {
            $modulo = (string) ($row['modulo'] ?? 'General');
            $grupos[$modulo][] = $row;
        }

        return $grupos;
    }

    /**
     * Lista plana de permisos activos.
     * Utilizado para validaciones o listados simples.
     */
    public function listar_activos(): array
    {
        $this->sincronizarCatalogoBase();

        $select = [
            'pd.id',
            'pd.slug',
            'pd.nombre',
        ];

        if ($this->tablaTieneColumna('permisos_def', 'descripcion')) {
            $select[] = 'pd.descripcion';
        }

        $select[] = $this->tablaTieneColumna('permisos_def', 'modulo') ? 'pd.modulo' : "'General' AS modulo";
        $select[] = $this->tablaTieneColumna('permisos_def', 'estado') ? 'pd.estado' : '1 AS estado';

        if ($this->tablaTieneColumna('permisos_def', 'created_at')) {
            $select[] = 'pd.created_at';
        }

        if ($this->tablaTieneColumna('permisos_def', 'updated_at')) {
            $select[] = 'pd.updated_at';
        }

        if ($this->tablaTieneColumna('permisos_def', 'created_by')) {
            $select[] = 'pd.created_by';
            $select[] = 'uc.nombre_completo AS created_by_nombre';
        }

        if ($this->tablaTieneColumna('permisos_def', 'updated_by')) {
            $select[] = 'pd.updated_by';
            $select[] = 'uu.nombre_completo AS updated_by_nombre';
        }

        $sql = 'SELECT ' . implode(', ', $select) . '
                FROM permisos_def pd';

        if ($this->tablaTieneColumna('permisos_def', 'created_by')) {
            $sql .= ' LEFT JOIN usuarios uc ON uc.id = pd.created_by';
        }

        if ($this->tablaTieneColumna('permisos_def', 'updated_by')) {
            $sql .= ' LEFT JOIN usuarios uu ON uu.id = pd.updated_by';
        }

        $sql .= ' WHERE pd.deleted_at IS NULL
                  ORDER BY pd.modulo ASC, pd.nombre ASC';

        return $this->db()->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function tablaTieneColumna(string $tabla, string $columna): bool
    {
        if (!isset($this->cacheColumnas[$tabla])) {
            $stmt = $this->db()->prepare('SHOW COLUMNS FROM ' . $tabla);
            $stmt->execute();
            $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $this->cacheColumnas[$tabla] = array_map('strtolower', $cols ?: []);
        }

        return in_array(strtolower($columna), $this->cacheColumnas[$tabla], true);
    }

    /**
     * Asegura que el catálogo base de permisos del sidebar y controladores
     * exista en la tabla permisos_def.
     */
    private function sincronizarCatalogoBase(): void
    {
        if ($this->catalogoBaseSincronizado) {
            return;
        }
        $this->catalogoBaseSincronizado = true;

        $catalogoBase = [
            ['slug' => 'bitacora.ver', 'nombre' => 'Ver Bitácora', 'modulo' => 'BITÁCORA'],
            ['slug' => 'config.ver', 'nombre' => 'Ver Configuración', 'modulo' => 'CONFIGURACIÓN'],
            ['slug' => 'config.editar', 'nombre' => 'Editar Configuración', 'modulo' => 'CONFIGURACIÓN'],
            ['slug' => 'items.ver', 'nombre' => 'Ver Ítems', 'modulo' => 'CATÁLOGO'],
            ['slug' => 'items.crear', 'nombre' => 'Crear Ítems', 'modulo' => 'CATÁLOGO'],
            ['slug' => 'items.editar', 'nombre' => 'Editar Ítems', 'modulo' => 'CATÁLOGO'],
            ['slug' => 'items.eliminar', 'nombre' => 'Eliminar Ítems', 'modulo' => 'CATÁLOGO'],
            ['slug' => 'inventario.ver', 'nombre' => 'Ver Inventario', 'modulo' => 'INVENTARIO'],
            ['slug' => 'inventario.movimiento.crear', 'nombre' => 'Registrar Movimientos de Inventario', 'modulo' => 'INVENTARIO'],
            ['slug' => 'terceros.ver', 'nombre' => 'Ver Terceros', 'modulo' => 'TERCEROS'],
            ['slug' => 'terceros.crear', 'nombre' => 'Crear Terceros', 'modulo' => 'TERCEROS'],
            ['slug' => 'terceros.editar', 'nombre' => 'Editar Terceros', 'modulo' => 'TERCEROS'],
            ['slug' => 'terceros.eliminar', 'nombre' => 'Eliminar Terceros', 'modulo' => 'TERCEROS'],
            ['slug' => 'distribuidores.ver', 'nombre' => 'Ver Distribuidores', 'modulo' => 'TERCEROS'],
            ['slug' => 'asistencia.importar', 'nombre' => 'Importar Biométrico', 'modulo' => 'RRHH'],
            ['slug' => 'ventas.ver', 'nombre' => 'Ver Ventas', 'modulo' => 'VENTAS'],
            ['slug' => 'ventas.crear', 'nombre' => 'Crear Ventas', 'modulo' => 'VENTAS'],
            ['slug' => 'ventas.aprobar', 'nombre' => 'Aprobar Ventas', 'modulo' => 'VENTAS'],
            ['slug' => 'ventas.eliminar', 'nombre' => 'Eliminar Ventas', 'modulo' => 'VENTAS'],
            ['slug' => 'compras.ver', 'nombre' => 'Ver Compras', 'modulo' => 'COMPRAS'],
            ['slug' => 'compras.crear', 'nombre' => 'Crear Compras', 'modulo' => 'COMPRAS'],
            ['slug' => 'compras.aprobar', 'nombre' => 'Aprobar Compras', 'modulo' => 'COMPRAS'],
            ['slug' => 'compras.recepcionar', 'nombre' => 'Recepcionar Compras', 'modulo' => 'COMPRAS'],
            ['slug' => 'compras.eliminar', 'nombre' => 'Eliminar Compras', 'modulo' => 'COMPRAS'],
            ['slug' => 'tesoreria.ver', 'nombre' => 'Ver Tesorería', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.cxc.ver', 'nombre' => 'Ver Cuentas por Cobrar', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.cxp.ver', 'nombre' => 'Ver Cuentas por Pagar', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.cobros.registrar', 'nombre' => 'Registrar Cobros', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.pagos.registrar', 'nombre' => 'Registrar Pagos', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.movimientos.anular', 'nombre' => 'Anular Movimientos de Tesorería', 'modulo' => 'TESORERÍA'],
            ['slug' => 'conta.ver', 'nombre' => 'Ver Contabilidad', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.plan_contable.gestionar', 'nombre' => 'Gestionar Plan Contable', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.asientos.crear', 'nombre' => 'Crear Asientos Contables', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.asientos.anular', 'nombre' => 'Anular Asientos Contables', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.periodos.ver', 'nombre' => 'Ver Períodos Contables', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.periodos.cerrar', 'nombre' => 'Cerrar Períodos Contables', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.reportes.ver', 'nombre' => 'Ver Reportes Contables', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.conciliacion.gestionar', 'nombre' => 'Gestionar Conciliación Bancaria', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.centros_costo.gestionar', 'nombre' => 'Gestionar Centros de Costo', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'activos.gestionar', 'nombre' => 'Gestionar Activos Fijos', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.depreciacion.ejecutar', 'nombre' => 'Ejecutar Depreciación', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.cierre.mensual', 'nombre' => 'Ejecutar Cierre Mensual', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.cierre.anual', 'nombre' => 'Ejecutar Cierre Anual', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'reportes.dashboard.ver', 'nombre' => 'Ver Dashboard de Reportes', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.inventario.ver', 'nombre' => 'Ver Reporte de Inventario', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.compras.ver', 'nombre' => 'Ver Reporte de Compras', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.ventas.ver', 'nombre' => 'Ver Reporte de Ventas', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.produccion.ver', 'nombre' => 'Ver Reporte de Producción', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.tesoreria.ver', 'nombre' => 'Ver Reporte de Tesorería', 'modulo' => 'REPORTES'],
            ['slug' => 'roles.ver', 'nombre' => 'Ver Roles y Permisos', 'modulo' => 'ROLES Y PERMISOS'],
            ['slug' => 'roles.crear', 'nombre' => 'Crear Roles', 'modulo' => 'ROLES Y PERMISOS'],
            ['slug' => 'roles.editar', 'nombre' => 'Editar Roles', 'modulo' => 'ROLES Y PERMISOS'],
            ['slug' => 'roles.eliminar', 'nombre' => 'Eliminar Roles', 'modulo' => 'ROLES Y PERMISOS'],
            ['slug' => 'usuarios.ver', 'nombre' => 'Ver Usuarios', 'modulo' => 'USUARIOS'],
            ['slug' => 'usuarios.crear', 'nombre' => 'Crear Usuarios', 'modulo' => 'USUARIOS'],
            ['slug' => 'usuarios.editar', 'nombre' => 'Editar Usuarios', 'modulo' => 'USUARIOS'],
            ['slug' => 'usuarios.eliminar', 'nombre' => 'Eliminar Usuarios', 'modulo' => 'USUARIOS'],
        ];

        $insertStmt = $this->db()->prepare(
            'INSERT INTO permisos_def (slug, nombre, modulo)
             SELECT :slug, :nombre, :modulo
             WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = :slug_check)'
        );

        foreach ($catalogoBase as $permiso) {
            $insertStmt->execute([
                'slug' => $permiso['slug'],
                'nombre' => $permiso['nombre'],
                'modulo' => $permiso['modulo'],
                'slug_check' => $permiso['slug'],
            ]);
        }
    }
}
