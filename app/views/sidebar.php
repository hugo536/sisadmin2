<?php
// =====================================================================================
// sidebar.php (Bootstrap Offcanvas Responsive) — ACTUALIZADO CON GESTIÓN COMERCIAL
// - Desktop (>= lg): Sidebar fijo
// - Mobile (< lg): Sidebar OFFCANVAS
// =====================================================================================

// -----------------------------
// Lógica de rutas y estado activo
// -----------------------------
$rutaActual = (string) ($ruta_actual ?? (string) ($_GET['ruta'] ?? 'reportes/dashboard'));

$activo = static fn(string $ruta): string =>
    (str_starts_with($rutaActual, $ruta) || $rutaActual === $ruta . '/index') ? ' active' : '';

$grupoActivo = static fn(array $rutas): string => array_reduce(
    $rutas,
    static fn(bool $carry, string $ruta): bool => $carry || str_starts_with($rutaActual, $ruta),
    false
) ? ' show' : '';

$linkGrupoActivo = static fn(array $rutas): string => array_reduce(
    $rutas,
    static fn(bool $carry, string $ruta): bool => $carry || str_starts_with($rutaActual, $ruta),
    false
) ? ' active' : ' collapsed';

$puedeVerComercial = tiene_permiso('terceros.ver') || tiene_permiso('items.ver') || tiene_permiso('ventas.ver');
$puedeVerRRHH = tiene_permiso('terceros.ver');
$sidebarBadges = is_array($sidebarBadges ?? null) ? $sidebarBadges : [];

// -----------------------------
// Usuario
// -----------------------------
$usuarioNombre = (string) ($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario');
$userInitial   = strtoupper(substr($usuarioNombre, 0, 1));
$userRole      = (string) ($_SESSION['rol_nombre'] ?? ('Rol #' . (int) ($_SESSION['id_rol'] ?? 0)));

// -----------------------------
// Empresa
// -----------------------------
$configEmpresa = is_array($configEmpresa ?? null) ? $configEmpresa : [];

$empresaNombre = (string) ($configEmpresa['razon_social'] ?? 'SISADMIN2');
$empresaNombre = trim($empresaNombre) !== '' ? $empresaNombre : 'SISADMIN2';

$logoUrl = '';
if (!empty($configEmpresa['logo_path'])) {
    $logoUrl = base_url() . '/' . ltrim((string) $configEmpresa['logo_path'], '/');
}

/**
 * Renderiza el contenido interno (header + nav + footer)
 */
function renderSidebarInner(
    string $navId,
    string $empresaNombre,
    string $logoUrl,
    string $userInitial,
    string $usuarioNombre,
    string $userRole,
    bool $puedeVerComercial,
    bool $puedeVerRRHH,
    array $sidebarBadges,
    callable $activo,
    callable $grupoActivo,
    callable $linkGrupoActivo
): void {
    $menuRRHHId = 'menuRRHH_' . $navId;
    $menuComercialId = 'menuComercial_' . $navId;
    $menuTesoreriaId = 'menuTesoreria_' . $navId;
    $menuContabilidadId = 'menuContabilidad_' . $navId;
    $menuConfiguracionId = 'menuConfiguracion_' . $navId;
    $menuCostosId = 'menuCostos_' . $navId;
    $menuGastosId = 'menuGastos_' . $navId;
    $menuRutasContabilidad = ['contabilidad', 'conciliacion', 'activos', 'cierre_contable', 'auditoria'];
    $renderBadge = static function (string $badgeKey) use ($sidebarBadges): void {
        if (!array_key_exists($badgeKey, $sidebarBadges)) {
            return;
        }

        $badgeValue = trim((string) $sidebarBadges[$badgeKey]);
        if ($badgeValue === '') {
            return;
        }

        echo '<span class="sidebar-count-badge ms-auto" aria-label="Pendientes ' . htmlspecialchars($badgeValue) . '">' . htmlspecialchars($badgeValue) . '</span>';
    };
?>
    <div class="sidebar-header">

        <div class="sidebar-top-row">
        <div class="sidebar-brand" aria-label="Empresa">
            <div class="brand-icon">
                <?php if ($logoUrl !== ''): ?>
                    <img
                        src="<?php echo e($logoUrl); ?>"
                        alt="Logo Empresa"
                        class="brand-logo"
                        loading="lazy"
                    >
                <?php else: ?>
                    <span class="brand-fallback" aria-hidden="true">
                        <?php echo htmlspecialchars(strtoupper(substr($empresaNombre, 0, 1))); ?>
                    </span>
                <?php endif; ?>
            </div>

            <div class="brand-meta">
                <div class="brand-name" title="<?php echo htmlspecialchars($empresaNombre); ?>">
                    <?php echo htmlspecialchars($empresaNombre); ?>
                </div>
                <div class="brand-sub">
                    <span class="brand-badge">ERP SYSTEM v2.0</span>
                </div>
            </div>
        </div>
            <button
                type="button"
                <?php if ($navId === 'sidebarNavScrollDesktop'): ?>id="toggleSidebar"<?php endif; ?>
                class="sidebar-icon-btn d-none d-lg-inline-flex"
                aria-label="Contraer barra lateral"
                title="Contraer barra lateral"
            >
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
        </div>

        <div class="user-card" aria-label="Usuario">
            <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($usuarioNombre); ?></div>
                <div class="user-role small text-muted"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
        </div>

        <div class="sidebar-search-wrap" role="search">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input
                type="search"
                class="sidebar-search-input"
                placeholder="Buscar módulo o opción"
                data-sidebar-search="<?php echo htmlspecialchars($navId); ?>"
                aria-label="Buscar en el menú"
            >
        </div>

        <div class="sidebar-favorites" data-sidebar-favorites="<?php echo htmlspecialchars($navId); ?>" hidden>
            <div class="nav-label pt-2">Accesos rápidos</div>
            <div class="sidebar-favorites-list"></div>
        </div>


    </div>

    <nav class="sidebar-nav flex-grow-1" id="<?php echo htmlspecialchars($navId); ?>" aria-label="Navegación principal">

        <div class="nav-label">Operación diaria</div>
        <?php if (tiene_permiso('reportes.dashboard.ver')): ?>
            <a class="sidebar-link<?php echo $activo('reportes'); ?>" href="<?php echo e(route_url('reportes/dashboard')); ?>">
                <i class="bi bi-graph-up-arrow"></i> <span>Reportes y Control</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('items.ver')): ?>
            <a class="sidebar-link<?php echo $activo('items'); ?>" href="<?php echo e(route_url('items')); ?>">
                <i class="bi bi-box-seam"></i> <span>Ítems / Productos</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('inventario.ver')): ?>
            <a class="sidebar-link<?php echo $activo('inventario'); ?>" href="<?php echo e(route_url('inventario')); ?>">
                <i class="bi bi-clipboard-data"></i> <span>Inventario</span>
            </a>
        <?php endif; ?>


        <?php if (tiene_permiso('reportes.produccion.ver')): ?>
            <button class="sidebar-link<?php echo $linkGrupoActivo(['reportes/costos_produccion', 'reportes/costos_configuracion', 'reportes/costos_cierres', 'reportes/costos_alertas']); ?>"
               type="button"
               data-bs-toggle="collapse"
               data-bs-target="#<?php echo htmlspecialchars($menuCostosId); ?>"
               aria-expanded="<?php echo $grupoActivo(['reportes/costos_produccion', 'reportes/costos_configuracion', 'reportes/costos_cierres', 'reportes/costos_alertas']) ? 'true' : 'false'; ?>"
               aria-controls="<?php echo htmlspecialchars($menuCostosId); ?>">
                <i class="bi bi-calculator"></i> <span>Costos</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </button>

            <div class="collapse<?php echo $grupoActivo(['reportes/costos_produccion', 'reportes/costos_configuracion', 'reportes/costos_cierres', 'reportes/costos_alertas']); ?>" id="<?php echo htmlspecialchars($menuCostosId); ?>" data-menu-key="costos" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('reportes/costos_produccion'); ?>" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
                            <i class="bi bi-graph-up-arrow"></i> <span>Análisis de costos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('reportes/costos_configuracion'); ?>" href="<?php echo e(route_url('reportes/costos_configuracion')); ?>">
                            <i class="bi bi-sliders"></i> <span>Configuración</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('reportes/costos_cierres'); ?>" href="<?php echo e(route_url('reportes/costos_cierres')); ?>">
                            <i class="bi bi-calendar-check"></i> <span>Cierres de costos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('reportes/costos_alertas'); ?>" href="<?php echo e(route_url('reportes/costos_alertas')); ?>">
                            <i class="bi bi-bell"></i> <span>Alertas y variaciones</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (tiene_permiso('inventario.ver')): ?>
            <div class="nav-label mt-3">Producción</div>
            
            <a class="sidebar-link<?php echo $activo('produccion/recetas'); ?>" href="<?php echo e(route_url('produccion/recetas')); ?>">
                <i class="bi bi-journal-check"></i> <span>Recetas (BOM)</span>
            </a>
            
            <a class="sidebar-link<?php echo $activo('produccion/ordenes'); ?>" href="<?php echo e(route_url('produccion/ordenes')); ?>">
                <i class="bi bi-gear-wide-connected"></i> <span>Órdenes de Producción</span>
                <?php $renderBadge('produccion/ordenes'); ?>
            </a>
        <?php endif; ?>

        <div class="nav-label mt-3">Relaciones y talento</div>
        <?php if (tiene_permiso('terceros.ver') || tiene_permiso('items.ver')): ?>
            <a class="sidebar-link<?php echo $activo('terceros'); ?>" href="<?php echo e(route_url('terceros')); ?>">
                <i class="bi bi-people"></i> <span>Terceros</span>
            </a>

            <?php if ($puedeVerRRHH): ?>
            <button class="sidebar-link<?php echo $linkGrupoActivo(['horario', 'asistencia', 'planillas', 'rrhh/config_rrhh']); ?>"
               type="button"
               data-bs-toggle="collapse"
               data-bs-target="#<?php echo htmlspecialchars($menuRRHHId); ?>"
               aria-expanded="<?php echo $grupoActivo(['horario', 'asistencia', 'planillas', 'rrhh/config_rrhh']) ? 'true' : 'false'; ?>"
               aria-controls="<?php echo htmlspecialchars($menuRRHHId); ?>">
                <i class="bi bi-people-fill"></i> <span>RRHH</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </button>

            <div class="collapse<?php echo $grupoActivo(['horario', 'asistencia', 'planillas', 'rrhh/config_rrhh']); ?>" id="<?php echo htmlspecialchars($menuRRHHId); ?>" data-menu-key="rrhh" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('horario'); ?>" href="<?php echo e(route_url('horario')); ?>">
                            <i class="bi bi-clock-history"></i> <span>Asistencia y Horarios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('asistencia/importar'); ?>" href="<?php echo e(route_url('asistencia/importar')); ?>">
                            <i class="bi bi-file-earmark-arrow-up"></i> <span>Importar Biométrico</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('asistencia/dashboard'); ?>" href="<?php echo e(route_url('asistencia/dashboard')); ?>">
                            <i class="bi bi-bar-chart-line"></i> <span>Dashboard RRHH</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('asistencia/incidencias'); ?>" href="<?php echo e(route_url('asistencia/incidencias')); ?>">
                            <i class="bi bi-clipboard2-pulse"></i> <span>Incidencias</span>
                            <?php $renderBadge('asistencia/incidencias'); ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('planillas'); ?>" href="<?php echo e(route_url('planillas')); ?>">
                            <i class="bi bi-cash-coin"></i> <span>Planillas y Pagos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('rrhh/config_rrhh'); ?>" href="<?php echo e(route_url('rrhh/config_rrhh')); ?>">
                            <i class="bi bi-sliders"></i> <span>Políticas y Reglas</span>
                        </a>
                    </li>
                </ul>
            </div>

            <?php endif; ?>

            <a class="sidebar-link<?php echo $activo('distribuidores'); ?>" href="<?php echo e(route_url('distribuidores')); ?>">
                <i class="bi bi-diagram-3"></i> <span>Distribuidores</span>
            </a>
        <?php endif; ?>

        <?php if ($puedeVerComercial): ?>

            <div class="nav-label mt-3">Comercial y finanzas</div>
            <button class="sidebar-link<?php echo $linkGrupoActivo(['comercial']); ?>"
               type="button"
               data-bs-toggle="collapse"
               data-bs-target="#<?php echo htmlspecialchars($menuComercialId); ?>"
               aria-expanded="<?php echo $grupoActivo(['comercial']) ? 'true' : 'false'; ?>"
               aria-controls="<?php echo htmlspecialchars($menuComercialId); ?>">
                <i class="bi bi-tags"></i> <span>Gestión Comercial</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </button>

            <div class="collapse<?php echo $grupoActivo(['comercial']); ?>" id="<?php echo htmlspecialchars($menuComercialId); ?>" data-menu-key="comercial" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('comercial/listas'); ?>" href="<?php echo e(route_url('comercial/listas')); ?>">
                            <i class="bi bi-currency-dollar"></i> <span>Listas de Precios</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php if (tiene_permiso('ventas.ver')): ?>
                <a class="sidebar-link<?php echo $activo('ventas'); ?>" href="<?php echo e(route_url('ventas')); ?>">
                    <i class="bi bi-bag-check"></i> <span>Ventas</span>
                    <?php $renderBadge('ventas'); ?>
                </a>
            <?php endif; ?>

            <?php if (tiene_permiso('compras.ver')): ?>
                <a class="sidebar-link<?php echo $activo('compras'); ?>" href="<?php echo e(route_url('compras')); ?>">
                    <i class="bi bi-cart-check"></i> <span>Compras</span>
                </a>
            <?php endif; ?>


            <?php if (tiene_permiso('compras.ver')): ?>
                <button class="sidebar-link<?php echo $linkGrupoActivo(['gastos']); ?>"
                   type="button"
                   data-bs-toggle="collapse"
                   data-bs-target="#<?php echo htmlspecialchars($menuGastosId); ?>"
                   aria-expanded="<?php echo $grupoActivo(['gastos']) ? 'true' : 'false'; ?>"
                   aria-controls="<?php echo htmlspecialchars($menuGastosId); ?>">
                    <i class="bi bi-wallet2"></i> <span>Gastos</span>
                    <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
                </button>
                <div class="collapse<?php echo $grupoActivo(['gastos']); ?>" id="<?php echo htmlspecialchars($menuGastosId); ?>" data-menu-key="gastos" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                    <ul class="nav flex-column ps-3">
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('gastos/conceptos'); ?>" href="<?php echo e(route_url('gastos/conceptos')); ?>"><i class="bi bi-tags"></i><span>Conceptos de Gasto</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('gastos/registros'); ?>" href="<?php echo e(route_url('gastos/registros')); ?>"><i class="bi bi-receipt"></i><span>Registro de Gastos</span></a></li>
                    </ul>
                </div>
            <?php endif; ?>


            <?php if (tiene_permiso('tesoreria.ver') || tiene_permiso('tesoreria.cxc.ver') || tiene_permiso('tesoreria.cxp.ver')): ?>
                <button class="sidebar-link<?php echo $linkGrupoActivo(['tesoreria']); ?>"
                   type="button"
                   data-bs-toggle="collapse"
                   data-bs-target="#<?php echo htmlspecialchars($menuTesoreriaId); ?>"
                   aria-expanded="<?php echo $grupoActivo(['tesoreria']) ? 'true' : 'false'; ?>"
                   aria-controls="<?php echo htmlspecialchars($menuTesoreriaId); ?>">
                    <i class="bi bi-cash-coin"></i> <span>Tesorería</span>
                    <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
                </button>
                <div class="collapse<?php echo $grupoActivo(['tesoreria']); ?>" id="<?php echo htmlspecialchars($menuTesoreriaId); ?>" data-menu-key="tesoreria" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                    <ul class="nav flex-column ps-3">
                        <?php if (tiene_permiso('tesoreria.ver')): ?>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('tesoreria/cuentas'); ?>" href="<?php echo e(route_url('tesoreria/cuentas')); ?>"><i class="bi bi-bank"></i><span>Cuentas</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('tesoreria/movimientos'); ?>" href="<?php echo e(route_url('tesoreria/movimientos')); ?>"><i class="bi bi-arrow-left-right"></i><span>Movimientos</span></a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('tesoreria.cxc.ver')): ?>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('tesoreria/cxc'); ?>" href="<?php echo e(route_url('tesoreria/cxc')); ?>"><i class="bi bi-cash-stack"></i><span>Cuentas por Cobrar</span><?php $renderBadge('tesoreria/cxc'); ?></a></li>
                        <?php endif; ?>
                        <?php if (tiene_permiso('tesoreria.cxp.ver')): ?>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('tesoreria/cxp'); ?>" href="<?php echo e(route_url('tesoreria/cxp')); ?>"><i class="bi bi-wallet"></i><span>Cuentas por Pagar</span><?php $renderBadge('tesoreria/cxp'); ?></a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if (tiene_permiso('conta.ver')): ?>
                <button class="sidebar-link<?php echo $linkGrupoActivo($menuRutasContabilidad); ?>"
                   type="button"
                   data-bs-toggle="collapse"
                   data-bs-target="#<?php echo htmlspecialchars($menuContabilidadId); ?>"
                   aria-expanded="<?php echo $grupoActivo($menuRutasContabilidad) ? 'true' : 'false'; ?>"
                   aria-controls="<?php echo htmlspecialchars($menuContabilidadId); ?>">
                    <i class="bi bi-journal-bookmark"></i> <span>Contabilidad</span>
                    <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
                </button>
                <div class="collapse<?php echo $grupoActivo($menuRutasContabilidad); ?>" id="<?php echo htmlspecialchars($menuContabilidadId); ?>" data-menu-key="contabilidad" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                    <ul class="nav flex-column ps-3">
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('contabilidad/plan'); ?>" href="<?php echo e(route_url('contabilidad/plan')); ?>"><i class="bi bi-diagram-3"></i><span>Plan Contable</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('contabilidad/periodos'); ?>" href="<?php echo e(route_url('contabilidad/periodos')); ?>"><i class="bi bi-calendar3"></i><span>Periodos</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('contabilidad/asientos'); ?>" href="<?php echo e(route_url('contabilidad/asientos')); ?>"><i class="bi bi-journal-text"></i><span>Asientos</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('contabilidad/reportes'); ?>" href="<?php echo e(route_url('contabilidad/reportes')); ?>"><i class="bi bi-bar-chart"></i><span>Reportes</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('contabilidad/centros_costo'); ?>" href="<?php echo e(route_url('contabilidad/centros_costo')); ?>"><i class="bi bi-bullseye"></i><span>Centros de costo</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('conciliacion/index'); ?>" href="<?php echo e(route_url('conciliacion/index')); ?>"><i class="bi bi-link-45deg"></i><span>Conciliación bancaria</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('activos/index'); ?>" href="<?php echo e(route_url('activos/index')); ?>"><i class="bi bi-building"></i><span>Activos fijos</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('cierre_contable/index'); ?>" href="<?php echo e(route_url('cierre_contable/index')); ?>"><i class="bi bi-lock"></i><span>Cierres contables</span></a></li>
                        <li class="nav-item"><a class="sidebar-link<?php echo $activo('cierre_contable/estados_financieros'); ?>" href="<?php echo e(route_url('cierre_contable/estados_financieros')); ?>"><i class="bi bi-file-earmark-bar-graph"></i><span>Estados financieros</span></a></li>
                        <?php if (tiene_permiso('auditoria.ver')): ?><li class="nav-item"><a class="sidebar-link<?php echo $activo('auditoria/index'); ?>" href="<?php echo e(route_url('auditoria/index')); ?>"><i class="bi bi-shield-check"></i><span>Modo auditoría</span></a></li><?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>

        <?php endif; ?>
        <div class="nav-label mt-3">Sistema</div>

        <?php if (tiene_permiso('usuarios.ver')): ?>
            <a class="sidebar-link<?php echo $activo('usuarios'); ?>" href="<?php echo e(route_url('usuarios')); ?>">
                <i class="bi bi-people"></i> <span>Usuarios</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('bitacora.ver')): ?>
            <a class="sidebar-link<?php echo $activo('bitacora'); ?>" href="<?php echo e(route_url('bitacora')); ?>">
                <i class="bi bi-journal-text"></i> <span>Bitácora</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('roles.ver')): ?>
            <a class="sidebar-link<?php echo $activo('roles'); ?>" href="<?php echo e(route_url('roles')); ?>">
                <i class="bi bi-shield-lock"></i> <span>Roles y Permisos</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('config.ver')): ?>
            <button class="sidebar-link<?php echo $linkGrupoActivo(['config', 'almacenes', 'cajas_bancos', 'impuestos', 'series']); ?>"
               type="button"
               data-bs-toggle="collapse"
               data-bs-target="#<?php echo htmlspecialchars($menuConfiguracionId); ?>"
               aria-expanded="<?php echo $grupoActivo(['config', 'almacenes', 'cajas_bancos', 'impuestos', 'series']) ? 'true' : 'false'; ?>"
               aria-controls="<?php echo htmlspecialchars($menuConfiguracionId); ?>">
                <i class="bi bi-gear"></i> <span>Configuración</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </button>

            <div class="collapse<?php echo $grupoActivo(['config', 'almacenes', 'cajas_bancos', 'impuestos', 'series']); ?>" id="<?php echo htmlspecialchars($menuConfiguracionId); ?>" data-menu-key="configuracion" data-bs-parent="#<?php echo htmlspecialchars($navId); ?>">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('config/empresa'); ?>" href="<?php echo e(route_url('config/empresa')); ?>">
                            <span>Datos Empresa</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('almacenes'); ?>" href="<?php echo e(route_url('almacenes')); ?>">
                            <span>Almacenes</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('cajas_bancos'); ?>" href="<?php echo e(route_url('cajas_bancos')); ?>">
                            <span>Cajas y Bancos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('impuestos'); ?>" href="<?php echo e(route_url('impuestos')); ?>">
                            <span>Impuestos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('series'); ?>" href="<?php echo e(route_url('series')); ?>">
                            <span>Series</span>
                        </a>
                    </li>
                </ul>
            </div>
        <?php endif; ?>

    </nav>

    <div class="sidebar-footer">
        <a id="logoutLink" class="sidebar-link logout-link text-danger" href="<?php echo e(route_url('login/logout')); ?>">
            <i class="bi bi-box-arrow-left"></i> <span>Cerrar sesión</span>
        </a>
    </div>
<?php
}
?>

<aside class="sidebar sidebar-desktop position-fixed top-0 start-0 h-100 d-none d-lg-flex flex-column" id="appSidebarDesktop">
    <?php
      renderSidebarInner(
        'sidebarNavScrollDesktop',
        $empresaNombre,
        $logoUrl,
        $userInitial,
        $usuarioNombre,
        $userRole,
        $puedeVerComercial,
        $puedeVerRRHH,
        $sidebarBadges,
        $activo,
        $grupoActivo,
        $linkGrupoActivo
      );
    ?>
</aside>

<div class="offcanvas offcanvas-start d-lg-none sidebar-offcanvas" tabindex="-1" id="appSidebarOffcanvas" aria-labelledby="appSidebarOffcanvasLabel">
  <div class="offcanvas-header">
    <h5 class="offcanvas-title" id="appSidebarOffcanvasLabel"><?php echo htmlspecialchars($empresaNombre); ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
  </div>

  <div class="offcanvas-body p-0 d-flex flex-column">
    <?php
      renderSidebarInner(
        'sidebarNavScrollMobile',
        $empresaNombre,
        $logoUrl,
        $userInitial,
        $usuarioNombre,
        $userRole,
        $puedeVerComercial,
        $puedeVerRRHH,
        $sidebarBadges,
        $activo,
        $grupoActivo,
        $linkGrupoActivo
      );
    ?>
  </div>
</div>

<script>
(function () {
  const isDesktop = window.matchMedia('(min-width: 992px)').matches;

  // =========================================================
  // Scroll persist (Desktop)
  // =========================================================
  function setupScrollPersistence(navId, storageKey) {
    const nav = document.getElementById(navId);
    if (!nav) return;

    const saved = sessionStorage.getItem(storageKey);
    if (saved !== null) {
      const y = parseInt(saved, 10);
      if (!Number.isNaN(y)) nav.scrollTop = y;
    }

    let ticking = false;
    nav.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => {
        sessionStorage.setItem(storageKey, String(nav.scrollTop));
        ticking = false;
      });
    }, { passive: true });

    nav.addEventListener('click', function (e) {
      const a = e.target.closest('a.sidebar-link');
      if (!a) return;
      sessionStorage.setItem(storageKey, String(nav.scrollTop));
    });
  }

  setupScrollPersistence('sidebarNavScrollDesktop', 'erp.sidebar.desktop.scrollTop');

  // =========================================================
  // Persistencia de submenús
  // =========================================================
  function setupOpenMenusPersistence(navId, storageKey) {
    const nav = document.getElementById(navId);
    if (!nav || !window.bootstrap || !bootstrap.Collapse) return;

    const collapses = Array.from(nav.querySelectorAll('.collapse[data-menu-key]'));
    if (!collapses.length) return;

    let storedKeys = [];
    try {
      storedKeys = JSON.parse(localStorage.getItem(storageKey) || '[]');
      if (!Array.isArray(storedKeys)) storedKeys = [];
    } catch (_err) {
      storedKeys = [];
    }

    collapses.forEach((el) => {
      const key = el.getAttribute('data-menu-key');
      if (storedKeys.includes(key)) {
        bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
      }

      el.addEventListener('shown.bs.collapse', () => {
        const openKeys = new Set(storedKeys);
        openKeys.add(key);
        storedKeys = Array.from(openKeys);
        localStorage.setItem(storageKey, JSON.stringify(storedKeys));
      });

      el.addEventListener('hidden.bs.collapse', () => {
        storedKeys = storedKeys.filter((k) => k !== key);
        localStorage.setItem(storageKey, JSON.stringify(storedKeys));
      });
    });
  }

  setupOpenMenusPersistence('sidebarNavScrollDesktop', 'erp.sidebar.desktop.openMenus');
  setupOpenMenusPersistence('sidebarNavScrollMobile', 'erp.sidebar.mobile.openMenus');

  // =========================================================
  // Buscador de menú
  // =========================================================
  function setupSidebarSearch(navId) {
    const nav = document.getElementById(navId);
    const input = document.querySelector(`[data-sidebar-search="${navId}"]`);
    if (!nav || !input) return;
    const searchableLinks = Array.from(nav.querySelectorAll('a.sidebar-link')).filter((link) => !link.classList.contains('logout-link'));


    input.addEventListener('input', () => {
      const query = input.value.trim().toLowerCase();
      const labels = nav.querySelectorAll('.nav-label');
      const listItems = nav.querySelectorAll('.nav-item');

      labels.forEach((label) => {
        label.style.display = query === '' ? '' : 'none';
      });

      listItems.forEach((li) => {
        li.style.display = '';
      });

      searchableLinks.forEach((link) => {
        const text = (link.textContent || '').toLowerCase();
        const isMatch = query === '' || text.includes(query);
        const li = link.closest('.nav-item');

        if (li) {
          li.style.display = isMatch ? '' : 'none';
        } else {
          link.style.display = isMatch ? '' : 'none';
        }
      });
    });
  }

  setupSidebarSearch('sidebarNavScrollDesktop');
  setupSidebarSearch('sidebarNavScrollMobile');

  // =========================================================
  // Navegación por teclado
  // =========================================================
  function setupKeyboardNav(navId) {
    const nav = document.getElementById(navId);
    if (!nav) return;

    nav.addEventListener('keydown', (e) => {
      const links = Array.from(nav.querySelectorAll('.sidebar-link')).filter((el) => el.offsetParent !== null);
      const current = document.activeElement;
      const index = links.indexOf(current);
      if (index === -1) return;

      if (e.key === 'ArrowDown') {
        e.preventDefault();
        links[(index + 1) % links.length].focus();
      }

      if (e.key === 'ArrowUp') {
        e.preventDefault();
        links[(index - 1 + links.length) % links.length].focus();
      }
      if ((e.key === 'ArrowRight' || e.key === 'ArrowLeft') && current.matches('button.sidebar-link[data-bs-toggle="collapse"]')) {
        e.preventDefault();
        if (document.body.classList.contains('sidebar-collapsed')) return;
        const expanded = current.getAttribute('aria-expanded') === 'true';
        if (e.key === 'ArrowRight' && !expanded) current.click();
        if (e.key === 'ArrowLeft' && expanded) current.click();
      }

      if ((e.key === 'Enter' || e.key === ' ') && current.matches('button.sidebar-link[data-bs-toggle="collapse"]')) {
        e.preventDefault();
        if (document.body.classList.contains('sidebar-collapsed')) return;
        current.click();
      }
    });
  }

  setupKeyboardNav('sidebarNavScrollDesktop');
  setupKeyboardNav('sidebarNavScrollMobile');

  // =========================================================
  // Mobile UX: cerrar el offcanvas al click en link real
  // =========================================================
  const offcanvasEl = document.getElementById('appSidebarOffcanvas');
  if (offcanvasEl && window.bootstrap && bootstrap.Offcanvas) {
    const oc = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);

    offcanvasEl.addEventListener('click', function (e) {
      const a = e.target.closest('a.sidebar-link');
      if (!a) return;

      const href = a.getAttribute('href') || '';
      const isToggle = a.getAttribute('data-bs-toggle') === 'collapse';
      const isHashMenu = href.startsWith('#menu');

      if (isToggle || isHashMenu) return;

      oc.hide();
    });
  }

  // =========================================================
  // Soporte tooltip/colapsado profesional desktop
  // =========================================================
  if (isDesktop) {
    document.querySelectorAll('.sidebar-nav .sidebar-link span').forEach((span) => {
      const link = span.closest('.sidebar-link');
      if (link && !link.getAttribute('title')) {
        link.setAttribute('title', (span.textContent || '').trim());
      }
    });
  }
})();
</script>
