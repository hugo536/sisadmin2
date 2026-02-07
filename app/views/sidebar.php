<?php
// =====================================================================================
// SIDEBAR (LISTO PARA COPIAR)
// - Header empresa pulido (logo como app-icon + nombre clamp)
// - Persistencia de scroll (no se resetea al navegar)
// - Chevron rota cuando el collapse está abierto
// - Mantiene tu lógica de permisos y rutas
// =====================================================================================

// Lógica de rutas y estado activo
$rutaActual = (string) ($ruta_actual ?? (string) ($_GET['ruta'] ?? 'dashboard'));

// Helper para marcar enlace activo
$activo = static fn(string $ruta): string =>
    (str_starts_with($rutaActual, $ruta) || $rutaActual === $ruta . '/index') ? ' active' : '';

// Helper para marcar grupo (dropdown) activo
$grupoActivo = static fn(array $rutas): string => array_reduce(
    $rutas,
    static fn(bool $carry, string $ruta): bool => $carry || str_starts_with($rutaActual, $ruta),
    false
) ? ' show' : '';

// Helper para clase del link padre del grupo
$linkGrupoActivo = static fn(array $rutas): string => array_reduce(
    $rutas,
    static fn(bool $carry, string $ruta): bool => $carry || str_starts_with($rutaActual, $ruta),
    false
) ? ' active' : ' collapsed';

// Datos de Usuario
$usuarioNombre = (string) ($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario');
$userInitial = strtoupper(substr($usuarioNombre, 0, 1));
$userRole = (string) ($_SESSION['rol_nombre'] ?? ('Rol #' . (int) ($_SESSION['id_rol'] ?? 0)));

// Evitar error si no llega la configuración
$configEmpresa = is_array($configEmpresa ?? null) ? $configEmpresa : [];

$empresaNombre = (string) ($configEmpresa['razon_social'] ?? 'SISADMIN2');
$empresaNombre = trim($empresaNombre) !== '' ? $empresaNombre : 'SISADMIN2';

$logoUrl = '';
if (!empty($configEmpresa['logo_path'])) {
    $logoUrl = base_url() . '/' . ltrim((string) $configEmpresa['logo_path'], '/');
}
?>

<aside class="sidebar position-fixed top-0 start-0 h-100" id="appSidebar">

    <div class="sidebar-header">

        <!-- BRAND (Pulido) -->
        <div class="sidebar-brand" aria-label="Empresa">
            <div class="brand-icon">
                <?php if ($logoUrl !== ''): ?>
                    <img
                        id="sidebarCompanyLogo"
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
                <div class="brand-name" id="sidebarCompanyName" title="<?php echo htmlspecialchars($empresaNombre); ?>">
                    <?php echo htmlspecialchars($empresaNombre); ?>
                </div>
                <div class="brand-sub">
                    <span class="brand-badge">ERP SYSTEM v2.0</span>
                </div>
            </div>
        </div>

        <!-- USER CARD -->
        <div class="user-card" aria-label="Usuario">
            <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($usuarioNombre); ?></div>
                <div class="user-role small text-muted"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
        </div>

    </div>

    <!-- NAV (contenedor con scroll) -->
    <nav class="sidebar-nav flex-grow-1" id="sidebarNavScroll" aria-label="Navegación principal">

        <div class="nav-label">Principal</div>

        <a class="sidebar-link<?php echo $activo('dashboard'); ?>" href="<?php echo e(route_url('dashboard')); ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a>

        <?php if (tiene_permiso('items.ver')): ?>
            <a class="sidebar-link<?php echo $activo('items'); ?>" href="<?php echo e(route_url('items')); ?>">
                <i class="bi bi-box-seam"></i> <span>Ítems / Productos</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('terceros.ver') || tiene_permiso('items.ver')): ?>
            <a class="sidebar-link<?php echo $activo('terceros'); ?>" href="<?php echo e(route_url('terceros')); ?>">
                <i class="bi bi-people"></i> <span>Terceros</span>
            </a>
        <?php endif; ?>

        <?php if (tiene_permiso('inventario.ver')): ?>
            <div class="nav-label mt-3">Operaciones</div>

            <!-- Grupo Inventario -->
            <a class="sidebar-link<?php echo $linkGrupoActivo(['inventario', 'stock', 'movimientos']); ?>"
               data-bs-toggle="collapse"
               href="#menuInventario"
               role="button"
               aria-expanded="<?php echo $grupoActivo(['inventario', 'stock', 'movimientos']) ? 'true' : 'false'; ?>"
               aria-controls="menuInventario">
                <i class="bi bi-clipboard-data"></i> <span>Inventario</span>
                <span class="ms-auto chevron"><i class="bi bi-chevron-down small"></i></span>
            </a>

            <div class="collapse<?php echo $grupoActivo(['inventario', 'stock', 'movimientos']); ?>" id="menuInventario">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('stock'); ?>" href="<?php echo e(route_url('stock')); ?>">
                            <span>Stock Actual</span>
                        </a>
                    </li>

                    <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
                        <li class="nav-item">
                            <a class="sidebar-link<?php echo $activo('movimientos'); ?>" href="<?php echo e(route_url('movimientos')); ?>">
                                <span>Movimientos</span>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="nav-label">Sistema</div>

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
            <!-- Grupo Configuración -->
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
</aside>

<!-- Persistencia de scroll del sidebar + rotación del chevron según estado -->
<script>
(function () {
  const nav = document.getElementById('sidebarNavScroll');
  const sidebar = document.getElementById('appSidebar');
  if (!nav || !sidebar) return;

  // ====== 1) Persistencia de scroll (tu lógica) ======
  const KEY = 'erp.sidebar.scrollTop';

  const saved = sessionStorage.getItem(KEY);
  if (saved !== null) {
    const y = parseInt(saved, 10);
    if (!Number.isNaN(y)) nav.scrollTop = y;
  }

  let ticking = false;
  nav.addEventListener('scroll', function () {
    if (ticking) return;
    ticking = true;
    requestAnimationFrame(() => {
      sessionStorage.setItem(KEY, String(nav.scrollTop));
      ticking = false;
    });
  }, { passive: true });

  sidebar.addEventListener('click', function (e) {
    const a = e.target.closest('a.sidebar-link');
    if (!a) return;
    sessionStorage.setItem(KEY, String(nav.scrollTop));
  });

  // ====== 2) AUTO-SCROLL AL ABRIR UN COLLAPSE ======
  function ensureVisibleInsideNav(el) {
    if (!el) return;

    const navRect = nav.getBoundingClientRect();
    const elRect  = el.getBoundingClientRect();

    // margen visual para que no quede pegado al borde
    const padding = 12;

    // Si el elemento queda cortado por abajo, bajamos
    if (elRect.bottom > navRect.bottom - padding) {
      const delta = elRect.bottom - navRect.bottom + padding;
      nav.scrollTop += delta;
    }

    // Si quedara cortado por arriba (menos común), subimos
    if (elRect.top < navRect.top + padding) {
      const delta = (navRect.top + padding) - elRect.top;
      nav.scrollTop -= delta;
    }
  }

  // Cuando se abre un collapse, hacemos que su último item sea visible
  sidebar.querySelectorAll('.collapse').forEach((col) => {
    col.addEventListener('shown.bs.collapse', function () {
      // Elegimos el último link dentro del submenu para garantizar que se vea todo
      const lastLink = col.querySelector('a.sidebar-link:last-of-type') || col;
      // Espera 1 frame por si Bootstrap aún está terminando el layout
      requestAnimationFrame(() => ensureVisibleInsideNav(lastLink));
    });
  });

})();
</script>
