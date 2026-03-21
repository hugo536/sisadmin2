<?php
// =====================================================================================
// sidebar.php — Diseño mejorado con UX móvil y animaciones
// - Desktop (>= lg): Sidebar fijo con colapso a iconos
// - Mobile (< lg): Offcanvas
// =====================================================================================
 
$rutaActual = (string) ($ruta_actual ?? (string) ($_GET['ruta'] ?? 'reportes/dashboard'));
 
$activo = static fn(string $ruta): string =>
    ($rutaActual === $ruta || $rutaActual === $ruta . '/index') ? ' active' : '';
 
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
$puedeVerDistribuidores = tiene_permiso('distribuidores.ver') || tiene_permiso('terceros.ver');
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
    bool $puedeVerDistribuidores,
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
 
        <div class="sb-favorites" data-sidebar-favorites="<?= htmlspecialchars($navId) ?>" hidden>
            <div class="sb-section-label">Accesos rápidos</div>
            <div class="sb-favorites-list"></div>
        </div>
    </div>
 
    <nav class="sb-nav" id="<?= htmlspecialchars($navId) ?>" aria-label="Navegación principal">
 
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
 
        <?php if ($puedeVerDistribuidores): ?>
            <a class="sb-link<?= $activo('distribuidores') ?>" href="<?= e(route_url('distribuidores')) ?>" data-tooltip="Distribuidores">
                <span class="sb-link-icon"><i class="bi bi-diagram-3"></i></span>
                <span class="sb-link-text">Distribuidores</span>
            </a>
        <?php endif; ?>
        <?php endif; ?>
 
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
                <a class="sb-link sb-sub<?= $activo('tesoreria/saldos_iniciales') ?>" href="<?= e(route_url('tesoreria/saldos_iniciales')) ?>">
                    <span class="sb-link-icon"><i class="bi bi-hourglass-split"></i></span><span class="sb-link-text">Saldos Iniciales</span>
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
 
<aside class="sidebar sidebar-desktop position-fixed top-0 start-0 h-100 d-none d-lg-flex flex-column"
       id="appSidebarDesktop"
       role="navigation"
       aria-label="Menú principal">
    <?php
    renderSidebarInner(
        'sidebarNavScrollDesktop',
        $empresaNombre, $logoUrl, $userInitial, $usuarioNombre, $userRole,
        $puedeVerComercial, $puedeVerRRHH, $puedeVerDistribuidores, $sidebarBadges,
        $activo, $grupoActivo, $linkGrupoActivo
    );
    ?>
</aside>
 
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
        $puedeVerComercial, $puedeVerRRHH, $puedeVerDistribuidores, $sidebarBadges,
        $activo, $grupoActivo, $linkGrupoActivo
    );
        ?>
    </div>
</div>
 
<script id="sidebar-core-script">
(function () {
  'use strict';
 
  // ── Toggle collapse sidebar (Desktop) ───────────────────────
  const toggleBtn = document.getElementById('toggleSidebar');
  if (toggleBtn) {
    toggleBtn.addEventListener('click', () => {
      document.documentElement.classList.toggle('sidebar-collapsed');
      const isCollapsed = document.documentElement.classList.contains('sidebar-collapsed');
      localStorage.setItem('erp.sidebar.collapsed', String(isCollapsed));
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
