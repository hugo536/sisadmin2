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
            // =========================================================
            // PERMISOS GRANULARES DE VISTA (1:1 CON EL SIDEBAR)
            // =========================================================

            // CATÁLOGO E ÍTEMS
            ['slug' => 'items.maestro.ver', 'nombre' => 'Maestro de Ítems', 'modulo' => 'CATÁLOGO'],
            ['slug' => 'items.packs.ver', 'nombre' => 'Packs y Combos', 'modulo' => 'CATÁLOGO'],

            // INVENTARIO
            ['slug' => 'inventario.almacen.ver', 'nombre' => 'Inventario General', 'modulo' => 'INVENTARIO'],
            ['slug' => 'inventario.envases.ver', 'nombre' => 'Control de Envases', 'modulo' => 'INVENTARIO'],

            // COSTOS INDUSTRIALES
            ['slug' => 'costos.analisis.ver', 'nombre' => 'Análisis de costos', 'modulo' => 'COSTOS INDUSTRIALES'],
            ['slug' => 'costos.tarifas.ver', 'nombre' => 'Tarifas de Planta', 'modulo' => 'COSTOS INDUSTRIALES'],
            ['slug' => 'costos.cierres.ver', 'nombre' => 'Cierres de costos', 'modulo' => 'COSTOS INDUSTRIALES'],
            ['slug' => 'costos.alertas.ver', 'nombre' => 'Alertas y variaciones', 'modulo' => 'COSTOS INDUSTRIALES'],

            // PRODUCCIÓN
            ['slug' => 'produccion.recetas.ver', 'nombre' => 'Recetas (BOM)', 'modulo' => 'PRODUCCIÓN'],
            ['slug' => 'produccion.ordenes.ver', 'nombre' => 'Órdenes de Producción', 'modulo' => 'PRODUCCIÓN'],

            // RRHH
            ['slug' => 'rrhh.biometrico.ver', 'nombre' => 'Importar Biométrico', 'modulo' => 'RRHH'],
            ['slug' => 'rrhh.asistencia.ver', 'nombre' => 'Gestión de Asistencia', 'modulo' => 'RRHH'],
            ['slug' => 'rrhh.planillas.ver', 'nombre' => 'Planillas y Pagos', 'modulo' => 'RRHH'],
            ['slug' => 'rrhh.politicas.ver', 'nombre' => 'Políticas y Reglas', 'modulo' => 'RRHH'],

            // GESTIÓN COMERCIAL
            ['slug' => 'comercial.clientes.ver', 'nombre' => 'Precios Clientes', 'modulo' => 'COMERCIAL'],
            ['slug' => 'comercial.proveedores.ver', 'nombre' => 'Precios Proveedor', 'modulo' => 'COMERCIAL'],

            // GASTOS (Tal cual solicitaste)
            ['slug' => 'gastos.conceptos.ver', 'nombre' => 'Conceptos de Gasto', 'modulo' => 'GASTOS'],
            ['slug' => 'gastos.registros.ver', 'nombre' => 'Registro de Gastos', 'modulo' => 'GASTOS'],

            // TESORERÍA
            ['slug' => 'tesoreria.cuentas.ver', 'nombre' => 'Cuentas', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.movimientos.ver', 'nombre' => 'Movimientos', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.adelantos.ver', 'nombre' => 'Adelantos a Personal', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.cxc.ver', 'nombre' => 'Ctas. por Cobrar', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.cxp.ver', 'nombre' => 'Ctas. por Pagar', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.saldos_iniciales.ver', 'nombre' => 'Saldos Iniciales', 'modulo' => 'TESORERÍA'],
            ['slug' => 'tesoreria.prestamos.ver', 'nombre' => 'Préstamos Bancarios', 'modulo' => 'TESORERÍA'],

            // CONTABILIDAD
            ['slug' => 'conta.plan.ver', 'nombre' => 'Plan Contable', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.periodos.ver', 'nombre' => 'Periodos', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.asientos.ver', 'nombre' => 'Asientos', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.reportes.ver', 'nombre' => 'Reportes', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.centros_costo.ver', 'nombre' => 'Centros de costo', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.prorrateos.ver', 'nombre' => 'Reglas de prorrateo', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.conciliacion.ver', 'nombre' => 'Conciliación bancaria', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.activos.ver', 'nombre' => 'Activos fijos', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.cierres.ver', 'nombre' => 'Cierres contables', 'modulo' => 'CONTABILIDAD'],
            ['slug' => 'conta.estados_financieros.ver', 'nombre' => 'Estados financieros', 'modulo' => 'CONTABILIDAD'],

            // CONFIGURACIÓN
            ['slug' => 'config.empresa.ver', 'nombre' => 'Datos Empresa', 'modulo' => 'CONFIGURACIÓN'],
            ['slug' => 'config.almacenes.ver', 'nombre' => 'Almacenes', 'modulo' => 'CONFIGURACIÓN'],
            ['slug' => 'config.cajas.ver', 'nombre' => 'Cajas y Bancos', 'modulo' => 'CONFIGURACIÓN'],
            ['slug' => 'config.impuestos.ver', 'nombre' => 'Impuestos', 'modulo' => 'CONFIGURACIÓN'],
            ['slug' => 'config.series.ver', 'nombre' => 'Series', 'modulo' => 'CONFIGURACIÓN'],

            // REPORTES Y ANÁLISIS
            ['slug' => 'reportes.dashboard.ver', 'nombre' => 'Dashboard Principal', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.ventas.ver', 'nombre' => 'Ventas', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.compras.ver', 'nombre' => 'Compras', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.inventario.ver', 'nombre' => 'Inventario', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.produccion.ver', 'nombre' => 'Producción', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.cxc.ver', 'nombre' => 'Cuentas por Cobrar', 'modulo' => 'REPORTES'],
            ['slug' => 'reportes.cxp.ver', 'nombre' => 'Cuentas por Pagar', 'modulo' => 'REPORTES'],
        ];

        $usaCreatedBy = $this->tablaTieneColumna('permisos_def', 'created_by');
        $idUsuarioAuditoria = $usaCreatedBy ? $this->resolverUsuarioAuditoriaId() : null;

        if ($usaCreatedBy && $idUsuarioAuditoria !== null) {
            $insertStmt = $this->db()->prepare(
                'INSERT INTO permisos_def (slug, nombre, modulo, created_by)
                 SELECT :slug, :nombre, :modulo, :created_by
                 WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = :slug_check)'
            );
        } else {
            $insertStmt = $this->db()->prepare(
                'INSERT INTO permisos_def (slug, nombre, modulo)
                 SELECT :slug, :nombre, :modulo
                 WHERE NOT EXISTS (SELECT 1 FROM permisos_def WHERE slug = :slug_check)'
            );
        }

        foreach ($catalogoBase as $permiso) {
            $params = [
                'slug' => $permiso['slug'],
                'nombre' => $permiso['nombre'],
                'modulo' => $permiso['modulo'],
                'slug_check' => $permiso['slug'],
            ];

            if ($usaCreatedBy && $idUsuarioAuditoria !== null) {
                $params['created_by'] = $idUsuarioAuditoria;
            }

            $insertStmt->execute($params);
        }
    }

    private function resolverUsuarioAuditoriaId(): ?int
    {
        $idSesion = (int) ($_SESSION['id'] ?? 0);
        if ($idSesion > 0) {
            $stmtSesion = $this->db()->prepare('SELECT id FROM usuarios WHERE id = :id LIMIT 1');
            $stmtSesion->execute(['id' => $idSesion]);
            $idValido = $stmtSesion->fetchColumn();

            if ($idValido !== false) {
                return (int) $idValido;
            }
        }

        $stmtFallback = $this->db()->query('SELECT id FROM usuarios ORDER BY id ASC LIMIT 1');
        $idFallback = $stmtFallback ? $stmtFallback->fetchColumn() : false;

        return $idFallback !== false ? (int) $idFallback : null;
    }
}
