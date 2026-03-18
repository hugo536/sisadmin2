<?php
// =====================================================================================
// sidebar.php — Diseño mejorado con UX móvil, animaciones y barra inferior móvil
// - Desktop (>= lg): Sidebar fijo con colapso a iconos
// - Mobile (< lg): Offcanvas + Bottom Nav Bar
// =====================================================================================
 
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
 
$usuarioNombre = (string) ($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario');
$userInitial   = strtoupper(substr($usuarioNombre, 0, 1));
$userRole      = (string) ($_SESSION['rol_nombre'] ?? ('Rol #' . (int) ($_SESSION['id_rol'] ?? 0)));
 
$configEmpresa = is_array($configEmpresa ?? null) ? $configEmpresa : [];
$empresaNombre = trim((string) ($configEmpresa['razon_social'] ?? 'SISADMIN2')) ?: 'SISADMIN2';
 
$logoUrl = '';
if (!empty($configEmpresa['logo_path'])) {
    $logoUrl = base_url() . '/' . ltrim((string) $configEmpresa['logo_path'], '/');
}
 
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
    $menuRRHHId         = 'menuRRHH_' . $navId;
    $menuComercialId    = 'menuComercial_' . $navId;
    $menuTesoreriaId    = 'menuTesoreria_' . $navId;
    $menuContabilidadId = 'menuContabilidad_' . $navId;
    $menuConfiguracionId= 'menuConfiguracion_' . $navId;
    $menuCostosId       = 'menuCostos_' . $navId;
    $menuGastosId       = 'menuGastos_' . $navId;
 
    $menuRutasContabilidad = ['contabilidad', 'conciliacion', 'activos', 'cierre_contable'];
    $menuRutasCostos       = ['reportes/costos_produccion', 'costos/configuracion', 'costos/cierres', 'costos/alertas'];
 
    $renderBadge = static function (string $badgeKey) use ($sidebarBadges): void {
        if (!array_key_exists($badgeKey, $sidebarBadges)) return;
        $badgeValue = trim((string) $sidebarBadges[$badgeKey]);
        if ($badgeValue === '') return;
        echo '<span class="sb-badge ms-auto" aria-label="Pendientes ' . htmlspecialchars($badgeValue) . '">'
           . htmlspecialchars($badgeValue) . '</span>';
    };
?>
    <!-- ── HEADER ─────────────────────────────────────────── -->
    <div class="sb-header">
        <div class="sb-brand-row">
            <div class="sb-brand">
                <div class="sb-brand-icon">
                    <?php if ($logoUrl !== ''): ?>
                        <img src="<?= e($logoUrl) ?>" alt="Logo" class="sb-logo" loading="lazy">
                    <?php else: ?>
                        <span class="sb-brand-letter"><?= htmlspecialchars(strtoupper(substr($empresaNombre, 0, 1))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="sb-brand-meta">
                    <div class="sb-brand-name" title="<?= htmlspecialchars($empresaNombre) ?>">
                        <?= htmlspecialchars($empresaNombre) ?>
                    </div>
                    <div class="sb-brand-tag">ERP SYSTEM v2.0</div>
                </div>
            </div>
            <?php if ($navId === 'sidebarNavScrollDesktop'): ?>
            <button id="toggleSidebar" class="sb-icon-btn" aria-label="Contraer sidebar" title="Contraer">
                <i class="bi bi-layout-sidebar-inset"></i>
            </button>
            <?php endif; ?>
        </div>
 
        <!-- Usuario -->
        <div class="sb-user-card">
            <div class="sb-avatar"><?= htmlspecialchars($userInitial) ?></div>
            <div class="sb-user-info">
                <div class="sb-user-name"><?= htmlspecialchars($usuarioNombre) ?></div>
                <div class="sb-user-role"><?= htmlspecialchars($userRole) ?></div>
            </div>
            <a href="<?= e(route_url('login/logout')) ?>" class="sb-logout-btn" title="Cerrar sesión" aria-label="Cerrar sesión">
                <i class="bi bi-power"></i>
            </a>
        </div>
 
        <!-- Buscador -->
        <div class="sb-search" role="search">
            <i class="bi bi-search sb-search-icon" aria-hidden="true"></i>
            <input
                type="search"
                class="sb-search-input"
                placeholder="Buscar módulo…"
                data-sidebar-search="<?= htmlspecialchars($navId) ?>"
                aria-label="Buscar en el menú"
                autocomplete="off"
            >
            <kbd class="sb-search-hint d-none d-lg-flex">⌘K</kbd>
        </div>
 
        <!-- Favoritos -->
        <div class="sb-favorites" data-sidebar-favorites="<?= htmlspecialchars($navId) ?>" hidden>
            <div class="sb-section-label">Accesos rápidos</div>
            <div class="sb-favorites-list"></div>
        </div>
    </div>
 
    <!-- ── NAV ────────────────────────────────────────────── -->
    <nav class="sb-nav" id="<?= htmlspecialchars($navId) ?>" aria-label="Navegación principal">
 
        <!-- ·· Operación Diaria ·· -->
        <div class="sb-section-label">Operación diaria</div>
 
        <?php if (tiene_permiso('reportes.dashboard.ver')): ?>
        <a class="sb-link<?= $activo('reportes') ?>" href="<?= e(route_url('reportes/dashboard')) ?>" data-tooltip="Reportes y Control">
            <span class="sb-link-icon"><i class="bi bi-graph-up-arrow"></i></span>
            <span class="sb-link-text">Reportes y Control</span>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('items.ver')): ?>
        <a class="sb-link<?= $activo('items') ?>" href="<?= e(route_url('items')) ?>" data-tooltip="Ítems / Productos">
            <span class="sb-link-icon"><i class="bi bi-box-seam"></i></span>
            <span class="sb-link-text">Ítems / Productos</span>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('inventario.ver')): ?>
        <a class="sb-link<?= $activo('inventario') ?>" href="<?= e(route_url('inventario')) ?>" data-tooltip="Inventario">
            <span class="sb-link-icon"><i class="bi bi-clipboard-data"></i></span>
            <span class="sb-link-text">Inventario</span>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('reportes.produccion.ver')): ?>
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo($menuRutasCostos) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuCostosId) ?>"
            aria-expanded="<?= $grupoActivo($menuRutasCostos) ? 'true' : 'false' ?>"
            data-tooltip="Costos Industriales">
            <span class="sb-link-icon"><i class="bi bi-calculator"></i></span>
            <span class="sb-link-text">Costos Industriales</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo($menuRutasCostos) ?>" id="<?= htmlspecialchars($menuCostosId) ?>" data-menu-key="costos" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <a class="sb-link sb-sub<?= $activo('reportes/costos_produccion') ?>" href="<?= e(route_url('reportes/costos_produccion')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-graph-up-arrow"></i></span><span class="sb-link-text">Análisis de costos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('costos/configuracion') ?>" href="<?= e(route_url('costos/configuracion')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-sliders"></i></span><span class="sb-link-text">Tarifas de Planta</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('costos/cierres') ?>" href="<?= e(route_url('costos/cierres')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-calendar-check"></i></span><span class="sb-link-text">Cierres de costos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('costos/alertas') ?>" href="<?= e(route_url('costos/alertas')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-bell"></i></span><span class="sb-link-text">Alertas y variaciones</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
 
        <!-- ·· Producción ·· -->
        <?php if (tiene_permiso('inventario.ver')): ?>
        <div class="sb-section-label">Producción</div>
 
        <a class="sb-link<?= $activo('produccion/recetas') ?>" href="<?= e(route_url('produccion/recetas')) ?>" data-tooltip="Recetas (BOM)">
            <span class="sb-link-icon"><i class="bi bi-journal-check"></i></span>
            <span class="sb-link-text">Recetas (BOM)</span>
        </a>
        <a class="sb-link<?= $activo('produccion/ordenes') ?>" href="<?= e(route_url('produccion/ordenes')) ?>" data-tooltip="Órdenes de Producción">
            <span class="sb-link-icon"><i class="bi bi-gear-wide-connected"></i></span>
            <span class="sb-link-text">Órdenes de Producción</span>
            <?php $renderBadge('produccion/ordenes'); ?>
        </a>
        <?php endif; ?>
 
        <!-- ·· Relaciones y Talento ·· -->
        <div class="sb-section-label">Relaciones y talento</div>
 
        <?php if (tiene_permiso('terceros.ver') || tiene_permiso('items.ver')): ?>
        <a class="sb-link<?= $activo('terceros') ?>" href="<?= e(route_url('terceros')) ?>" data-tooltip="Terceros">
            <span class="sb-link-icon"><i class="bi bi-people"></i></span>
            <span class="sb-link-text">Terceros</span>
        </a>
 
        <?php if ($puedeVerRRHH): ?>
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo(['horario', 'asistencia', 'planillas', 'rrhh/config_rrhh']) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuRRHHId) ?>"
            aria-expanded="<?= $grupoActivo(['horario', 'asistencia', 'planillas', 'rrhh/config_rrhh']) ? 'true' : 'false' ?>"
            data-tooltip="RRHH">
            <span class="sb-link-icon"><i class="bi bi-people-fill"></i></span>
            <span class="sb-link-text">RRHH</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo(['horario', 'asistencia', 'planillas', 'rrhh/config_rrhh']) ?>" id="<?= htmlspecialchars($menuRRHHId) ?>" data-menu-key="rrhh" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <a class="sb-link sb-sub<?= $activo('horario') ?>" href="<?= e(route_url('horario')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-clock-history"></i></span><span class="sb-link-text">Asistencia y Horarios</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('asistencia/importar') ?>" href="<?= e(route_url('asistencia/importar')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-file-earmark-arrow-up"></i></span><span class="sb-link-text">Importar Biométrico</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('asistencia/dashboard') ?>" href="<?= e(route_url('asistencia/dashboard')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-bar-chart-line"></i></span><span class="sb-link-text">Dashboard RRHH</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('asistencia/incidencias') ?>" href="<?= e(route_url('asistencia/incidencias')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-clipboard2-pulse"></i></span><span class="sb-link-text">Incidencias</span>
                    <?php $renderBadge('asistencia/incidencias'); ?>
                </a>
                <a class="sb-link sb-sub<?= $activo('planillas') ?>" href="<?= e(route_url('planillas')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-cash-coin"></i></span><span class="sb-link-text">Planillas y Pagos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('rrhh/config_rrhh') ?>" href="<?= e(route_url('rrhh/config_rrhh')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-sliders"></i></span><span class="sb-link-text">Políticas y Reglas</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
 
        <a class="sb-link<?= $activo('distribuidores') ?>" href="<?= e(route_url('distribuidores')) ?>" data-tooltip="Distribuidores">
            <span class="sb-link-icon"><i class="bi bi-diagram-3"></i></span>
            <span class="sb-link-text">Distribuidores</span>
        </a>
        <?php endif; ?>
 
        <!-- ·· Comercial y Finanzas ·· -->
        <?php if ($puedeVerComercial): ?>
        <div class="sb-section-label">Comercial y finanzas</div>
 
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo(['comercial']) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuComercialId) ?>"
            aria-expanded="<?= $grupoActivo(['comercial']) ? 'true' : 'false' ?>"
            data-tooltip="Gestión Comercial">
            <span class="sb-link-icon"><i class="bi bi-tags"></i></span>
            <span class="sb-link-text">Gestión Comercial</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo(['comercial']) ?>" id="<?= htmlspecialchars($menuComercialId) ?>" data-menu-key="comercial" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <a class="sb-link sb-sub<?= $activo('comercial/listas') ?>" href="<?= e(route_url('comercial/listas')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-currency-dollar"></i></span><span class="sb-link-text">Listas de Precios</span>
                </a>
            </div>
        </div>
 
        <?php if (tiene_permiso('ventas.ver')): ?>
        <a class="sb-link<?= $activo('ventas') ?>" href="<?= e(route_url('ventas')) ?>" data-tooltip="Ventas">
            <span class="sb-link-icon"><i class="bi bi-bag-check"></i></span>
            <span class="sb-link-text">Ventas</span>
            <?php $renderBadge('ventas'); ?>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('compras.ver')): ?>
        <a class="sb-link<?= $activo('compras') ?>" href="<?= e(route_url('compras')) ?>" data-tooltip="Compras">
            <span class="sb-link-icon"><i class="bi bi-cart-check"></i></span>
            <span class="sb-link-text">Compras</span>
        </a>
 
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo(['gastos']) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuGastosId) ?>"
            aria-expanded="<?= $grupoActivo(['gastos']) ? 'true' : 'false' ?>"
            data-tooltip="Gastos">
            <span class="sb-link-icon"><i class="bi bi-wallet2"></i></span>
            <span class="sb-link-text">Gastos</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo(['gastos']) ?>" id="<?= htmlspecialchars($menuGastosId) ?>" data-menu-key="gastos" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <a class="sb-link sb-sub<?= $activo('gastos/conceptos') ?>" href="<?= e(route_url('gastos/conceptos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-tags"></i></span><span class="sb-link-text">Conceptos de Gasto</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('gastos/registros') ?>" href="<?= e(route_url('gastos/registros')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-receipt"></i></span><span class="sb-link-text">Registro de Gastos</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
 
        <?php if (tiene_permiso('tesoreria.ver') || tiene_permiso('tesoreria.cxc.ver') || tiene_permiso('tesoreria.cxp.ver')): ?>
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo(['tesoreria']) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuTesoreriaId) ?>"
            aria-expanded="<?= $grupoActivo(['tesoreria']) ? 'true' : 'false' ?>"
            data-tooltip="Tesorería">
            <span class="sb-link-icon"><i class="bi bi-cash-coin"></i></span>
            <span class="sb-link-text">Tesorería</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo(['tesoreria']) ?>" id="<?= htmlspecialchars($menuTesoreriaId) ?>" data-menu-key="tesoreria" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <?php if (tiene_permiso('tesoreria.ver')): ?>
                <a class="sb-link sb-sub<?= $activo('tesoreria/cuentas') ?>" href="<?= e(route_url('tesoreria/cuentas')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-bank"></i></span><span class="sb-link-text">Cuentas</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('tesoreria/movimientos') ?>" href="<?= e(route_url('tesoreria/movimientos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-arrow-left-right"></i></span><span class="sb-link-text">Movimientos</span>
                </a>
                <?php endif; ?>
                <?php if (tiene_permiso('tesoreria.cxc.ver')): ?>
                <a class="sb-link sb-sub<?= $activo('tesoreria/cxc') ?>" href="<?= e(route_url('tesoreria/cxc')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-cash-stack"></i></span><span class="sb-link-text">Ctas. por Cobrar</span>
                    <?php $renderBadge('tesoreria/cxc'); ?>
                </a>
                <?php endif; ?>
                <?php if (tiene_permiso('tesoreria.cxp.ver')): ?>
                <a class="sb-link sb-sub<?= $activo('tesoreria/cxp') ?>" href="<?= e(route_url('tesoreria/cxp')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-wallet"></i></span><span class="sb-link-text">Ctas. por Pagar</span>
                    <?php $renderBadge('tesoreria/cxp'); ?>
                </a>
                <a class="sb-link sb-sub<?= $activo('tesoreria/prestamos') ?>" href="<?= e(route_url('tesoreria/prestamos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-building-fill-check"></i></span><span class="sb-link-text">Préstamos Bancarios</span>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
 
        <?php if (tiene_permiso('conta.ver')): ?>
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo($menuRutasContabilidad) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuContabilidadId) ?>"
            aria-expanded="<?= $grupoActivo($menuRutasContabilidad) ? 'true' : 'false' ?>"
            data-tooltip="Contabilidad">
            <span class="sb-link-icon"><i class="bi bi-journal-bookmark"></i></span>
            <span class="sb-link-text">Contabilidad</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo($menuRutasContabilidad) ?>" id="<?= htmlspecialchars($menuContabilidadId) ?>" data-menu-key="contabilidad" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <a class="sb-link sb-sub<?= $activo('contabilidad/plan') ?>" href="<?= e(route_url('contabilidad/plan')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-diagram-3"></i></span><span class="sb-link-text">Plan Contable</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('contabilidad/periodos') ?>" href="<?= e(route_url('contabilidad/periodos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-calendar3"></i></span><span class="sb-link-text">Periodos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('contabilidad/asientos') ?>" href="<?= e(route_url('contabilidad/asientos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-journal-text"></i></span><span class="sb-link-text">Asientos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('contabilidad/reportes') ?>" href="<?= e(route_url('contabilidad/reportes')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-bar-chart"></i></span><span class="sb-link-text">Reportes</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('contabilidad/centros_costo') ?>" href="<?= e(route_url('contabilidad/centros_costo')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-bullseye"></i></span><span class="sb-link-text">Centros de costo</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('conciliacion/index') ?>" href="<?= e(route_url('conciliacion/index')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-link-45deg"></i></span><span class="sb-link-text">Conciliación bancaria</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('activos/index') ?>" href="<?= e(route_url('activos/index')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-building"></i></span><span class="sb-link-text">Activos fijos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('cierre_contable/index') ?>" href="<?= e(route_url('cierre_contable/index')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-lock"></i></span><span class="sb-link-text">Cierres contables</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('cierre_contable/estados_financieros') ?>" href="<?= e(route_url('cierre_contable/estados_financieros')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-file-earmark-bar-graph"></i></span><span class="sb-link-text">Estados financieros</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
 
        <!-- ·· Sistema ·· -->
        <div class="sb-section-label">Sistema</div>
 
        <?php if (tiene_permiso('usuarios.ver')): ?>
        <a class="sb-link<?= $activo('usuarios') ?>" href="<?= e(route_url('usuarios')) ?>" data-tooltip="Usuarios">
            <span class="sb-link-icon"><i class="bi bi-people"></i></span>
            <span class="sb-link-text">Usuarios</span>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('bitacora.ver')): ?>
        <a class="sb-link<?= $activo('bitacora') ?>" href="<?= e(route_url('bitacora')) ?>" data-tooltip="Bitácora">
            <span class="sb-link-icon"><i class="bi bi-journal-text"></i></span>
            <span class="sb-link-text">Bitácora</span>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('roles.ver')): ?>
        <a class="sb-link<?= $activo('roles') ?>" href="<?= e(route_url('roles')) ?>" data-tooltip="Roles y Permisos">
            <span class="sb-link-icon"><i class="bi bi-shield-lock"></i></span>
            <span class="sb-link-text">Roles y Permisos</span>
        </a>
        <?php endif; ?>
 
        <?php if (tiene_permiso('config.ver')): ?>
        <button class="sb-link sb-group-btn<?= $linkGrupoActivo(['config', 'almacenes', 'cajas_bancos', 'impuestos', 'series']) ?>"
            type="button" data-bs-toggle="collapse"
            data-bs-target="#<?= htmlspecialchars($menuConfiguracionId) ?>"
            aria-expanded="<?= $grupoActivo(['config', 'almacenes', 'cajas_bancos', 'impuestos', 'series']) ? 'true' : 'false' ?>"
            data-tooltip="Configuración">
            <span class="sb-link-icon"><i class="bi bi-gear"></i></span>
            <span class="sb-link-text">Configuración</span>
            <i class="bi bi-chevron-down sb-chevron ms-auto"></i>
        </button>
        <div class="collapse<?= $grupoActivo(['config', 'almacenes', 'cajas_bancos', 'impuestos', 'series']) ?>" id="<?= htmlspecialchars($menuConfiguracionId) ?>" data-menu-key="configuracion" data-bs-parent="#<?= htmlspecialchars($navId) ?>">
            <div class="sb-submenu">
                <a class="sb-link sb-sub<?= $activo('config/empresa') ?>" href="<?= e(route_url('config/empresa')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-building"></i></span><span class="sb-link-text">Datos Empresa</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('almacenes') ?>" href="<?= e(route_url('almacenes')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-archive"></i></span><span class="sb-link-text">Almacenes</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('cajas_bancos') ?>" href="<?= e(route_url('cajas_bancos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-safe"></i></span><span class="sb-link-text">Cajas y Bancos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('impuestos') ?>" href="<?= e(route_url('impuestos')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-percent"></i></span><span class="sb-link-text">Impuestos</span>
                </a>
                <a class="sb-link sb-sub<?= $activo('series') ?>" href="<?= e(route_url('series')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-123"></i></span><span class="sb-link-text">Series</span>
                </a>
            </div>
        </div>
        <?php endif; ?>
 
        <div class="sb-nav-bottom-spacer"></div>
    </nav>
<?php
}
?>
 
<!-- ============================================================
     CSS — Sidebar System
     ============================================================ -->
<style>
/* ── Variables ──────────────────────────────────────────────── */
:root {
    --sb-width:         260px;
    --sb-width-col:      64px;
    --sb-bg:            #0f1117;
    --sb-bg-2:          #161b27;
    --sb-border:        rgba(255,255,255,.07);
    --sb-accent:        #f97316;
    --sb-accent-dim:    rgba(249,115,22,.15);
    --sb-accent-glow:   rgba(249,115,22,.25);
    --sb-text:          #e2e8f0;
    --sb-text-muted:    #64748b;
    --sb-text-label:    #475569;
    --sb-link-hover:    rgba(255,255,255,.06);
    --sb-link-active-bg:rgba(249,115,22,.12);
    --sb-radius:        10px;
    --sb-transition:    .22s cubic-bezier(.4,0,.2,1);
    --sb-shadow:        0 0 0 1px var(--sb-border), 8px 0 32px rgba(0,0,0,.45);
    --sb-font:          'DM Sans', 'Segoe UI', system-ui, sans-serif;
    --sb-bottom-h:      64px; /* altura de la bottom bar móvil */
}
 
/* ── Font ───────────────────────────────────────────────────── */
@import url('https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap');
 
/* ── Reset base ─────────────────────────────────────────────── */
.sidebar, .offcanvas-body, .sb-header, .sb-nav, .sb-submenu,
.sb-link, .sb-user-card, .sb-search, .sb-brand {
    font-family: var(--sb-font);
    box-sizing: border-box;
}
 
/* ═══════════════════════════════════════════════════════════
   SIDEBAR DESKTOP
═══════════════════════════════════════════════════════════ */
.sidebar-desktop {
    width: var(--sb-width);
    background: var(--sb-bg);
    box-shadow: var(--sb-shadow);
    display: flex;
    flex-direction: column;
    transition: width var(--sb-transition);
    z-index: 1040;
    overflow: hidden;
}
 
/* ── Collapsed (icon-only) ──────────────────────────────────── */
body.sidebar-collapsed .sidebar-desktop {
    width: var(--sb-width-col);
}
body.sidebar-collapsed .sb-link-text,
body.sidebar-collapsed .sb-brand-meta,
body.sidebar-collapsed .sb-user-info,
body.sidebar-collapsed .sb-user-role,
body.sidebar-collapsed .sb-search,
body.sidebar-collapsed .sb-section-label,
body.sidebar-collapsed .sb-chevron,
body.sidebar-collapsed .sb-favorites,
body.sidebar-collapsed .sb-logout-btn span,
body.sidebar-collapsed .sb-badge {
    opacity: 0;
    pointer-events: none;
    width: 0;
    overflow: hidden;
    white-space: nowrap;
}
body.sidebar-collapsed .sb-brand-icon { margin: 0 auto; }
body.sidebar-collapsed .sb-avatar     { margin: 0 auto; }
body.sidebar-collapsed .sb-user-card  { justify-content: center; padding: .5rem .75rem; }
body.sidebar-collapsed .sb-link       { justify-content: center; padding: .625rem; }
body.sidebar-collapsed .sb-link-icon  { margin-right: 0; }
body.sidebar-collapsed .sb-group-btn.sb-link .collapse { display: none; }
 
/* Tooltip when collapsed */
body.sidebar-collapsed .sb-link[data-tooltip]:hover::after {
    content: attr(data-tooltip);
    position: fixed;
    left: calc(var(--sb-width-col) + 8px);
    background: var(--sb-bg-2);
    color: var(--sb-text);
    font-size: .75rem;
    padding: .35rem .65rem;
    border-radius: 6px;
    white-space: nowrap;
    border: 1px solid var(--sb-border);
    pointer-events: none;
    z-index: 9999;
    box-shadow: 0 4px 16px rgba(0,0,0,.4);
    animation: sbTooltipIn .12s ease;
}
@keyframes sbTooltipIn {
    from { opacity: 0; transform: translateX(-4px); }
    to   { opacity: 1; transform: translateX(0); }
}
 
/* ── Layout adjustments when sidebar is desktop ─────────────── */
body:not(.sidebar-collapsed) .main-content,
body:not(.sidebar-collapsed) #mainContent {
    margin-left: var(--sb-width);
    transition: margin-left var(--sb-transition);
}
body.sidebar-collapsed .main-content,
body.sidebar-collapsed #mainContent {
    margin-left: var(--sb-width-col);
    transition: margin-left var(--sb-transition);
}
 
/* ═══════════════════════════════════════════════════════════
   HEADER
═══════════════════════════════════════════════════════════ */
.sb-header {
    padding: 1rem .875rem .5rem;
    border-bottom: 1px solid var(--sb-border);
    flex-shrink: 0;
}
 
/* Brand row */
.sb-brand-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: .875rem;
}
.sb-brand {
    display: flex;
    align-items: center;
    gap: .625rem;
    min-width: 0;
    flex: 1;
}
.sb-brand-icon {
    width: 36px; height: 36px;
    background: linear-gradient(135deg, var(--sb-accent) 0%, #fb923c 100%);
    border-radius: 9px;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 10px var(--sb-accent-glow);
}
.sb-logo         { width: 28px; height: 28px; object-fit: contain; border-radius: 6px; }
.sb-brand-letter { color: #fff; font-weight: 700; font-size: .95rem; }
.sb-brand-meta   { min-width: 0; }
.sb-brand-name   {
    color: var(--sb-text);
    font-size: .8rem;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
    letter-spacing: .01em;
}
.sb-brand-tag    {
    font-size: .625rem;
    color: var(--sb-accent);
    font-weight: 500;
    letter-spacing: .08em;
    text-transform: uppercase;
    margin-top: 1px;
}
 
/* Toggle button */
.sb-icon-btn {
    background: transparent;
    border: 1px solid var(--sb-border);
    color: var(--sb-text-muted);
    width: 30px; height: 30px;
    border-radius: 7px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: all var(--sb-transition);
    flex-shrink: 0;
}
.sb-icon-btn:hover {
    background: var(--sb-link-hover);
    color: var(--sb-text);
    border-color: rgba(255,255,255,.15);
}
 
/* User card */
.sb-user-card {
    display: flex;
    align-items: center;
    gap: .625rem;
    padding: .625rem .5rem;
    border-radius: var(--sb-radius);
    background: var(--sb-bg-2);
    margin-bottom: .75rem;
    border: 1px solid var(--sb-border);
    position: relative;
}
.sb-avatar {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    color: #fff;
    font-weight: 700;
    font-size: .85rem;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(99,102,241,.3);
}
.sb-user-info  { flex: 1; min-width: 0; }
.sb-user-name  { color: var(--sb-text); font-size: .78rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-user-role  { color: var(--sb-text-muted); font-size: .68rem; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.sb-logout-btn {
    color: var(--sb-text-muted);
    width: 28px; height: 28px;
    display: flex; align-items: center; justify-content: center;
    border-radius: 6px;
    text-decoration: none;
    transition: all var(--sb-transition);
    flex-shrink: 0;
}
.sb-logout-btn:hover { color: #f87171; background: rgba(248,113,113,.12); }
 
/* Search */
.sb-search {
    position: relative;
    margin-bottom: .5rem;
}
.sb-search-icon {
    position: absolute;
    left: .65rem;
    top: 50%;
    transform: translateY(-50%);
    color: var(--sb-text-muted);
    font-size: .8rem;
    pointer-events: none;
}
.sb-search-input {
    width: 100%;
    background: var(--sb-bg-2);
    border: 1px solid var(--sb-border);
    border-radius: 8px;
    color: var(--sb-text);
    font-size: .78rem;
    padding: .5rem .625rem .5rem 2rem;
    outline: none;
    transition: border-color var(--sb-transition), box-shadow var(--sb-transition);
    font-family: var(--sb-font);
}
.sb-search-input::placeholder { color: var(--sb-text-muted); }
.sb-search-input:focus {
    border-color: var(--sb-accent);
    box-shadow: 0 0 0 3px var(--sb-accent-dim);
}
.sb-search-hint {
    position: absolute; right: .625rem; top: 50%; transform: translateY(-50%);
    background: rgba(255,255,255,.06); border: 1px solid var(--sb-border);
    color: var(--sb-text-muted); font-size: .6rem; padding: .1rem .3rem;
    border-radius: 4px; font-family: monospace; pointer-events: none;
}
 
/* Favorites */
.sb-favorites { padding: 0 0 .25rem; }
.sb-favorites-list { display: flex; flex-wrap: wrap; gap: .25rem; margin-top: .35rem; }
 
/* ═══════════════════════════════════════════════════════════
   NAV
═══════════════════════════════════════════════════════════ */
.sb-nav {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: .625rem .625rem 1rem;
    scrollbar-width: thin;
    scrollbar-color: rgba(255,255,255,.08) transparent;
}
.sb-nav::-webkit-scrollbar        { width: 4px; }
.sb-nav::-webkit-scrollbar-thumb  { background: rgba(255,255,255,.08); border-radius: 4px; }
 
/* Section labels */
.sb-section-label {
    color: var(--sb-text-label);
    font-size: .6rem;
    font-weight: 600;
    letter-spacing: .1em;
    text-transform: uppercase;
    padding: .75rem .5rem .25rem;
    margin: 0;
}
 
/* Links */
.sb-link {
    display: flex;
    align-items: center;
    gap: .5rem;
    padding: .5rem .625rem;
    border-radius: 8px;
    color: var(--sb-text-muted);
    text-decoration: none;
    font-size: .8rem;
    font-weight: 500;
    width: 100%;
    border: none;
    background: transparent;
    cursor: pointer;
    text-align: left;
    transition: color var(--sb-transition), background var(--sb-transition);
    position: relative;
    white-space: nowrap;
    overflow: hidden;
}
.sb-link:hover {
    background: var(--sb-link-hover);
    color: var(--sb-text);
}
.sb-link.active {
    background: var(--sb-link-active-bg);
    color: var(--sb-accent);
    font-weight: 600;
}
.sb-link.active::before {
    content: '';
    position: absolute;
    left: 0; top: 20%; bottom: 20%;
    width: 3px;
    background: var(--sb-accent);
    border-radius: 0 3px 3px 0;
}
 
/* Icon wrapper */
.sb-link-icon {
    width: 20px;
    display: flex; align-items: center; justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
    transition: color var(--sb-transition);
}
.sb-link.active .sb-link-icon { color: var(--sb-accent); }
 
/* Chevron */
.sb-chevron {
    font-size: .7rem;
    transition: transform var(--sb-transition);
    flex-shrink: 0;
    color: var(--sb-text-label);
}
.sb-group-btn[aria-expanded="true"] .sb-chevron { transform: rotate(180deg); }
 
/* Submenu */
.sb-submenu {
    padding: .125rem 0 .25rem .625rem;
    border-left: 1px solid var(--sb-border);
    margin: .125rem 0 .125rem .75rem;
}
.sb-sub {
    padding: .4rem .5rem;
    font-size: .77rem;
}
.sb-sub .sb-link-icon { font-size: .8rem; }
 
/* Collapse animation enhancement */
.collapse { transition: none; }
.collapsing {
    transition: height .2s ease;
}
 
/* Badge */
.sb-badge {
    background: var(--sb-accent);
    color: #fff;
    font-size: .6rem;
    font-weight: 700;
    padding: .1rem .4rem;
    border-radius: 20px;
    min-width: 18px;
    text-align: center;
    flex-shrink: 0;
    line-height: 1.4;
    margin-left: auto;
}
 
/* Bottom spacer */
.sb-nav-bottom-spacer { height: 1.5rem; }
 
/* ═══════════════════════════════════════════════════════════
   OFFCANVAS (Mobile)
═══════════════════════════════════════════════════════════ */
.sidebar-offcanvas {
    background: var(--sb-bg) !important;
    width: 285px !important;
    border-right: 1px solid var(--sb-border) !important;
    z-index: 1056 !important;
}
.sidebar-offcanvas .offcanvas-header {
    background: var(--sb-bg-2);
    border-bottom: 1px solid var(--sb-border);
    padding: 1rem 1.25rem;
}
.sidebar-offcanvas .offcanvas-title {
    color: var(--sb-text);
    font-family: var(--sb-font);
    font-weight: 600;
    font-size: .9rem;
}
.sidebar-offcanvas .btn-close {
    filter: invert(1) opacity(.5);
}
.sidebar-offcanvas .btn-close:hover { filter: invert(1) opacity(.9); }
.sidebar-offcanvas .offcanvas-body {
    padding: 0;
    background: var(--sb-bg);
}
 
/* ═══════════════════════════════════════════════════════════
   MOBILE BOTTOM NAV BAR
═══════════════════════════════════════════════════════════ */
.sb-bottom-nav {
    display: none;
    position: fixed;
    bottom: 0; left: 0; right: 0;
    height: var(--sb-bottom-h);
    background: var(--sb-bg);
    border-top: 1px solid var(--sb-border);
    z-index: 1045;
    padding: 0 .25rem;
    padding-bottom: env(safe-area-inset-bottom, 0px);
    backdrop-filter: blur(12px);
    -webkit-backdrop-filter: blur(12px);
    justify-content: space-around;
    align-items: center;
    transition: opacity var(--sb-transition), transform var(--sb-transition);
}
@media (max-width: 991.98px) {
    .sb-bottom-nav { display: flex; }
    body { padding-bottom: calc(var(--sb-bottom-h) + env(safe-area-inset-bottom, 0px)); }
    .sidebar-offcanvas .offcanvas-body {
        padding-bottom: calc(var(--sb-bottom-h) + env(safe-area-inset-bottom, 0px) + .75rem);
    }
    #appSidebarOffcanvas.show ~ .sb-bottom-nav {
        opacity: 0;
        transform: translateY(100%);
        pointer-events: none;
    }
}
 
.sb-bottom-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: .375rem .5rem;
    border-radius: 10px;
    color: var(--sb-text-muted);
    text-decoration: none;
    font-size: .58rem;
    font-weight: 500;
    font-family: var(--sb-font);
    background: transparent;
    border: none;
    cursor: pointer;
    min-width: 52px;
    transition: color var(--sb-transition), background var(--sb-transition);
    position: relative;
}
.sb-bottom-item i { font-size: 1.2rem; line-height: 1; }
.sb-bottom-item span { line-height: 1; }
.sb-bottom-item.active {
    color: var(--sb-accent);
}
.sb-bottom-item.active::before {
    content: '';
    position: absolute;
    top: 0; left: 25%; right: 25%;
    height: 2px;
    background: var(--sb-accent);
    border-radius: 0 0 4px 4px;
}
.sb-bottom-item:active { transform: scale(.92); }
.sb-bottom-item .sb-badge {
    position: absolute;
    top: 3px; right: 6px;
    font-size: .55rem;
    min-width: 15px; height: 15px;
    padding: 0 3px;
    display: flex; align-items: center; justify-content: center;
}
 
/* Hamburger button in bottom nav */
.sb-bottom-menu-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: .375rem .5rem;
    border-radius: 10px;
    color: var(--sb-text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    min-width: 52px;
    font-size: .58rem;
    font-weight: 500;
    font-family: var(--sb-font);
    transition: color var(--sb-transition);
}
.sb-bottom-menu-btn i { font-size: 1.2rem; line-height: 1; }
.sb-bottom-menu-btn span { line-height: 1; }
.sb-bottom-menu-btn:active { transform: scale(.92); }
 
/* ═══════════════════════════════════════════════════════════
   NO-RESULTS search
═══════════════════════════════════════════════════════════ */
.sb-no-results {
    color: var(--sb-text-muted);
    font-size: .75rem;
    text-align: center;
    padding: 1.5rem .5rem;
    display: none;
}
 
/* ═══════════════════════════════════════════════════════════
   ENTRY ANIMATION
═══════════════════════════════════════════════════════════ */
@keyframes sbFadeIn {
    from { opacity: 0; transform: translateX(-8px); }
    to   { opacity: 1; transform: translateX(0); }
}
.sidebar-desktop { animation: sbFadeIn .3s ease; }
 
/* ═══════════════════════════════════════════════════════════
   SEARCH HIGHLIGHT
═══════════════════════════════════════════════════════════ */
.sb-highlight {
    background: var(--sb-accent-dim);
    color: var(--sb-accent);
    border-radius: 3px;
    padding: 0 2px;
}
</style>
 
<!-- ============================================================
     DESKTOP SIDEBAR
     ============================================================ -->
<aside class="sidebar sidebar-desktop position-fixed top-0 start-0 h-100 d-none d-lg-flex flex-column"
       id="appSidebarDesktop"
       role="navigation"
       aria-label="Menú principal">
    <?php
    renderSidebarInner(
        'sidebarNavScrollDesktop',
        $empresaNombre, $logoUrl, $userInitial, $usuarioNombre, $userRole,
        $puedeVerComercial, $puedeVerRRHH, $sidebarBadges,
        $activo, $grupoActivo, $linkGrupoActivo
    );
    ?>
</aside>
 
<!-- ============================================================
     MOBILE OFFCANVAS
     ============================================================ -->
<div class="offcanvas offcanvas-start d-lg-none sidebar-offcanvas"
     tabindex="-1"
     id="appSidebarOffcanvas"
     aria-labelledby="appSidebarOffcanvasLabel">
    <div class="offcanvas-header">
        <h5 class="offcanvas-title" id="appSidebarOffcanvasLabel">
            <?= htmlspecialchars($empresaNombre) ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-0 d-flex flex-column">
        <?php
        renderSidebarInner(
            'sidebarNavScrollMobile',
            $empresaNombre, $logoUrl, $userInitial, $usuarioNombre, $userRole,
            $puedeVerComercial, $puedeVerRRHH, $sidebarBadges,
            $activo, $grupoActivo, $linkGrupoActivo
        );
        ?>
    </div>
</div>
 
<!-- ============================================================
     MOBILE BOTTOM NAV BAR
     ============================================================ -->
<nav class="sb-bottom-nav" aria-label="Navegación rápida">
    <?php if (tiene_permiso('reportes.dashboard.ver')): ?>
    <a class="sb-bottom-item<?= str_starts_with($rutaActual, 'reportes') ? ' active' : '' ?>"
       href="<?= e(route_url('reportes/dashboard')) ?>">
        <i class="bi bi-graph-up-arrow"></i>
        <span>Inicio</span>
    </a>
    <?php endif; ?>
 
    <?php if (tiene_permiso('inventario.ver')): ?>
    <a class="sb-bottom-item<?= str_starts_with($rutaActual, 'inventario') ? ' active' : '' ?>"
       href="<?= e(route_url('inventario')) ?>">
        <i class="bi bi-clipboard-data"></i>
        <span>Inventario</span>
    </a>
    <?php endif; ?>
 
    <?php if (tiene_permiso('ventas.ver')): ?>
    <a class="sb-bottom-item<?= str_starts_with($rutaActual, 'ventas') ? ' active' : '' ?>"
       href="<?= e(route_url('ventas')) ?>">
        <i class="bi bi-bag-check"></i>
        <span>Ventas</span>
        <?php if (!empty($sidebarBadges['ventas'])): ?>
        <span class="sb-badge"><?= htmlspecialchars($sidebarBadges['ventas']) ?></span>
        <?php endif; ?>
    </a>
    <?php endif; ?>
 
    <?php if (tiene_permiso('compras.ver')): ?>
    <a class="sb-bottom-item<?= str_starts_with($rutaActual, 'compras') ? ' active' : '' ?>"
       href="<?= e(route_url('compras')) ?>">
        <i class="bi bi-cart-check"></i>
        <span>Compras</span>
    </a>
    <?php endif; ?>
 
    <button class="sb-bottom-menu-btn"
            type="button"
            data-bs-toggle="offcanvas"
            data-bs-target="#appSidebarOffcanvas"
            aria-controls="appSidebarOffcanvas"
            aria-label="Abrir menú">
        <i class="bi bi-grid-3x3-gap"></i>
        <span>Menú</span>
    </button>
</nav>
 
<!-- ============================================================
     JAVASCRIPT
     ============================================================ -->
<script id="sidebar-core-script">
(function () {
  'use strict';
 
  // ── Toggle collapse sidebar (Desktop) ───────────────────────
  const toggleBtn = document.getElementById('toggleSidebar');
  if (toggleBtn) {
    if (localStorage.getItem('erp.sidebar.collapsed') === 'true') {
      document.body.classList.add('sidebar-collapsed');
    }
    toggleBtn.addEventListener('click', () => {
      document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem('erp.sidebar.collapsed', String(document.body.classList.contains('sidebar-collapsed')));
    });
  }
 
  // ── Scroll persistence ───────────────────────────────────────
  function setupScrollPersist(navId, key) {
    const nav = document.getElementById(navId);
    if (!nav) return;
    const saved = parseInt(sessionStorage.getItem(key) || '0', 10);
    if (!isNaN(saved)) nav.scrollTop = saved;
    let ticking = false;
    nav.addEventListener('scroll', () => {
      if (ticking) return;
      ticking = true;
      requestAnimationFrame(() => { sessionStorage.setItem(key, String(nav.scrollTop)); ticking = false; });
    }, { passive: true });
  }
  setupScrollPersist('sidebarNavScrollDesktop', 'erp.sb.ds');
 
  // ── Open menus persistence ───────────────────────────────────
  function setupMenuPersist(navId, key) {
    const nav = document.getElementById(navId);
    if (!nav || !window.bootstrap?.Collapse) return;
    let stored = [];
    try { stored = JSON.parse(localStorage.getItem(key) || '[]'); if (!Array.isArray(stored)) stored = []; } catch (_) {}
    nav.querySelectorAll('.collapse[data-menu-key]').forEach((el) => {
      const k = el.getAttribute('data-menu-key');
      if (stored.includes(k)) bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
      el.addEventListener('shown.bs.collapse', () => { stored = [...new Set([...stored, k])]; localStorage.setItem(key, JSON.stringify(stored)); });
      el.addEventListener('hidden.bs.collapse', () => { stored = stored.filter(x => x !== k); localStorage.setItem(key, JSON.stringify(stored)); });
    });
  }
  setupMenuPersist('sidebarNavScrollDesktop', 'erp.sb.dm');
  setupMenuPersist('sidebarNavScrollMobile',  'erp.sb.mm');
 
  // ── Search with highlight ────────────────────────────────────
  function setupSearch(navId) {
    const nav   = document.getElementById(navId);
    const input = document.querySelector(`[data-sidebar-search="${navId}"]`);
    if (!nav || !input) return;
 
    const allLinks = Array.from(nav.querySelectorAll('a.sb-link'));
    let noResultsEl = nav.querySelector('.sb-no-results');
    if (!noResultsEl) {
      noResultsEl = document.createElement('p');
      noResultsEl.className = 'sb-no-results';
      noResultsEl.textContent = 'Sin resultados';
      nav.appendChild(noResultsEl);
    }
 
    input.addEventListener('input', () => {
      const q = input.value.trim().toLowerCase();
      let anyVisible = false;
 
      nav.querySelectorAll('.sb-section-label').forEach(el => el.style.display = q ? 'none' : '');
      nav.querySelectorAll('.sb-group-btn').forEach(btn => {
        if (q) btn.style.display = 'none';
      });
 
      allLinks.forEach((link) => {
        const txt = (link.textContent || '').trim().toLowerCase();
        const match = !q || txt.includes(q);
        const li = link.closest('.nav-item') || link;
        li.style.display = match ? '' : 'none';
        if (match) {
          anyVisible = true;
          if (q) {
            // Expand parent collapse
            const col = link.closest('.collapse');
            if (col) {
              col.classList.add('show');
              const btn = nav.querySelector(`[data-bs-target="#${col.id}"]`);
              if (btn) { btn.setAttribute('aria-expanded', 'true'); btn.style.display = ''; }
            }
            // Highlight text
            const span = link.querySelector('.sb-link-text');
            if (span) {
              const orig = span.getAttribute('data-orig') || span.textContent;
              span.setAttribute('data-orig', orig);
              const re = new RegExp(`(${q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')})`, 'gi');
              span.innerHTML = orig.replace(re, '<mark class="sb-highlight">$1</mark>');
            }
          } else {
            const span = link.querySelector('.sb-link-text');
            if (span && span.getAttribute('data-orig')) {
              span.textContent = span.getAttribute('data-orig');
            }
          }
        }
      });
 
      noResultsEl.style.display = (q && !anyVisible) ? 'block' : 'none';
    });
 
    // ⌘K / Ctrl+K shortcut (desktop only)
    document.addEventListener('keydown', (e) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        input.focus();
        input.select();
      }
    });
  }
  setupSearch('sidebarNavScrollDesktop');
  setupSearch('sidebarNavScrollMobile');
 
  // ── Keyboard navigation ──────────────────────────────────────
  function setupKeyNav(navId) {
    const nav = document.getElementById(navId);
    if (!nav) return;
    nav.addEventListener('keydown', (e) => {
      const links = Array.from(nav.querySelectorAll('.sb-link')).filter(el => el.offsetParent !== null);
      const idx   = links.indexOf(document.activeElement);
      if (idx === -1) return;
      if (e.key === 'ArrowDown')  { e.preventDefault(); links[(idx + 1) % links.length].focus(); }
      if (e.key === 'ArrowUp')    { e.preventDefault(); links[(idx - 1 + links.length) % links.length].focus(); }
      if (e.key === 'ArrowRight' && links[idx].matches('button[data-bs-toggle="collapse"]') && links[idx].getAttribute('aria-expanded') === 'false') {
        e.preventDefault(); links[idx].click();
      }
      if (e.key === 'ArrowLeft'  && links[idx].matches('button[data-bs-toggle="collapse"]') && links[idx].getAttribute('aria-expanded') === 'true') {
        e.preventDefault(); links[idx].click();
      }
    });
  }
  setupKeyNav('sidebarNavScrollDesktop');
  setupKeyNav('sidebarNavScrollMobile');
 
  // ── Close offcanvas on link click (mobile) ───────────────────
  const offcanvasEl = document.getElementById('appSidebarOffcanvas');
  if (offcanvasEl && window.bootstrap?.Offcanvas) {
    const oc = bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl);
    offcanvasEl.addEventListener('click', (e) => {
      const a = e.target.closest('a.sb-link');
      if (!a || a.getAttribute('data-bs-toggle') === 'collapse') return;
      oc.hide();
    });
  }
 
  // ── Swipe to close offcanvas ─────────────────────────────────
  if (offcanvasEl) {
    let startX = 0;
    offcanvasEl.addEventListener('touchstart', e => { startX = e.changedTouches[0].pageX; }, { passive: true });
    offcanvasEl.addEventListener('touchend', e => {
      const dx = e.changedTouches[0].pageX - startX;
      if (dx < -60 && window.bootstrap?.Offcanvas) {
        bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).hide();
      }
    }, { passive: true });
  }
 
})();
</script>
