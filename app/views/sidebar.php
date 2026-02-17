<?php
// =====================================================================================
// sidebar.php (Bootstrap Offcanvas Responsive) — ACTUALIZADO CON GESTIÓN COMERCIAL
// - Desktop (>= lg): Sidebar fijo
// - Mobile (< lg): Sidebar OFFCANVAS
// =====================================================================================

// -----------------------------
// Lógica de rutas y estado activo
// -----------------------------
$rutaActual = (string) ($ruta_actual ?? (string) ($_GET['ruta'] ?? 'dashboard'));

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
    callable $activo,
    callable $grupoActivo,
    callable $linkGrupoActivo
): void {
?>
    <div class="sidebar-header">

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

        <div class="user-card" aria-label="Usuario">
            <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($usuarioNombre); ?></div>
                <div class="user-role small text-muted"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
        </div>

    </div>

    <nav class="sidebar-nav flex-grow-1" id="<?php echo htmlspecialchars($navId); ?>" aria-label="Navegación principal">

        <div class="nav-label">Principal</div>

        <a class="sidebar-link<?php echo $activo('dashboard'); ?>" href="<?php echo e(route_url('dashboard')); ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a>

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

        <?php if (tiene_permiso('inventario.ver')): ?>
            <div class="nav-label mt-3">Producción</div>
            
            <a class="sidebar-link<?php echo $activo('produccion/recetas'); ?>" href="<?php echo e(route_url('produccion/recetas')); ?>">
                <i class="bi bi-journal-check"></i> <span>Recetas (BOM)</span>
            </a>
            
            <a class="sidebar-link<?php echo $activo('produccion/ordenes'); ?>" href="<?php echo e(route_url('produccion/ordenes')); ?>">
                <i class="bi bi-gear-wide-connected"></i> <span>Órdenes de Producción</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('terceros.ver') || tiene_permiso('items.ver')): ?>
            <a class="sidebar-link<?php echo $activo('terceros'); ?>" href="<?php echo e(route_url('terceros')); ?>">
                <i class="bi bi-people"></i> <span>Terceros</span>
            </a>

            <a class="sidebar-link<?php echo $activo('distribuidores'); ?>" href="<?php echo e(route_url('distribuidores')); ?>">
                <i class="bi bi-diagram-3"></i> <span>Distribuidores</span>
            </a>
        <?php endif; ?>

        <?php if ($puedeVerComercial): ?>

            <div class="nav-label mt-3">Estrategia</div>
            <a class="sidebar-link<?php echo $linkGrupoActivo(['comercial']); ?>"
               data-bs-toggle="collapse"
               href="#menuComercial"
               role="button"
               aria-expanded="<?php echo $grupoActivo(['comercial']) ? 'true' : 'false'; ?>"
               aria-controls="menuComercial">
                <i class="bi bi-tags"></i> <span>Gestión Comercial</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </a>

            <div class="collapse<?php echo $grupoActivo(['comercial']); ?>" id="menuComercial">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('comercial/presentaciones'); ?>" href="<?php echo e(route_url('comercial/presentaciones')); ?>">
                            <i class="bi bi-box"></i> <span>Presentaciones y Packs</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('comercial/listas'); ?>" href="<?php echo e(route_url('comercial/listas')); ?>">
                            <i class="bi bi-currency-dollar"></i> <span>Listas de Precios</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('comercial/asignacion'); ?>" href="<?php echo e(route_url('comercial/asignacion')); ?>">
                            <i class="bi bi-person-check"></i> <span>Asignación Masiva</span>
                        </a>
                    </li>
                </ul>
            </div>
            
            <?php if (tiene_permiso('ventas.ver')): ?>
                <a class="sidebar-link<?php echo $activo('ventas'); ?>" href="<?php echo e(route_url('ventas')); ?>">
                    <i class="bi bi-bag-check"></i> <span>Ventas</span>
                </a>
            <?php endif; ?>

            <?php if (tiene_permiso('compras.ver')): ?>
                <a class="sidebar-link<?php echo $activo('compras'); ?>" href="<?php echo e(route_url('compras')); ?>">
                    <i class="bi bi-cart-check"></i> <span>Compras</span>
                </a>
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
            <a class="sidebar-link<?php echo $linkGrupoActivo(['config']); ?>"
               data-bs-toggle="collapse"
               href="#menuConfiguracion"
               role="button"
               aria-expanded="<?php echo $grupoActivo(['config']) ? 'true' : 'false'; ?>"
               aria-controls="menuConfiguracion">
                <i class="bi bi-gear"></i> <span>Configuración</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </a>

            <div class="collapse<?php echo $grupoActivo(['config']); ?>" id="menuConfiguracion">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('config/empresa'); ?>" href="<?php echo e(route_url('config/empresa')); ?>">
                            <span>Datos Empresa</span>
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
        $activo,
        $grupoActivo,
        $linkGrupoActivo
      );
    ?>
  </div>
</div>

<script>
(function () {
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
})();
</script>
