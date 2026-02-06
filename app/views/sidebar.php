<?php
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
) ? ' active' : ' collapsed'; // Bootstrap requiere 'collapsed' cuando está cerrado

// Datos de Usuario
$usuarioNombre = (string) ($_SESSION['usuario_nombre'] ?? $_SESSION['usuario'] ?? 'Usuario');
$userInitial = strtoupper(substr($usuarioNombre, 0, 1));
$userRole = (string) ($_SESSION['rol_nombre'] ?? ('Rol #' . (int) ($_SESSION['id_rol'] ?? 0)));
?>

<aside class="sidebar position-fixed top-0 start-0 h-100">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-box-seam-fill text-primary"></i> SISADMIN2
        </div>

        <div class="user-card">
            <div class="user-avatar"><?php echo htmlspecialchars($userInitial); ?></div>
            <div class="user-info">
                <div class="user-name"><?php echo htmlspecialchars($usuarioNombre); ?></div>
                <div class="user-role small text-muted"><?php echo htmlspecialchars($userRole); ?></div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav flex-grow-1">
        <div class="nav-label">Principal</div>

        <a class="sidebar-link<?php echo $activo('dashboard'); ?>" href="<?php echo e(route_url('dashboard')); ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a>

        <?php if (tiene_permiso('inventario.ver')): ?>
            <a class="sidebar-link<?php echo $linkGrupoActivo(['inventario', 'stock', 'movimientos']); ?>" data-bs-toggle="collapse" href="#menuInventario" role="button" aria-expanded="false" aria-controls="menuInventario">
                <i class="bi bi-box-seam"></i> <span>Inventario</span>
                <span class="ms-auto"><i class="bi bi-chevron-down small"></i></span>
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

        <?php if (tiene_permiso('items.ver')): // PLURAL: Coincide con el slug de la BD ?>
            <a class="sidebar-link<?php echo $linkGrupoActivo(['item', 'terceros', 'categorias']); // SINGULAR: Coincide con el controlador ?>" 
            data-bs-toggle="collapse" href="#menuMaestros" role="button" aria-expanded="false" aria-controls="menuMaestros">
                <i class="bi bi-collection"></i> <span>Maestros</span>
                <span class="ms-auto"><i class="bi bi-chevron-down small"></i></span>
            </a>
            <div class="collapse<?php echo $grupoActivo(['item', 'tercero', 'categorias']); ?>" id="menuMaestros">
                <ul class="nav flex-column ps-3">
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('item'); ?>" 
                        href="<?php echo route_url('item'); ?>">
                            <span>Ítems / Productos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="sidebar-link<?php echo $activo('tercero'); ?>" 
                        href="<?php echo route_url('tercero'); ?>">
                            <span>Terceros</span>
                        </a>
                    </li>
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
            <a class="sidebar-link<?php echo $linkGrupoActivo(['config']); ?>" data-bs-toggle="collapse" href="#menuConfiguracion" role="button" aria-expanded="false" aria-controls="menuConfiguracion">
                <i class="bi bi-gear"></i> <span>Configuración</span>
                <span class="ms-auto"><i class="bi bi-chevron-down small"></i></span>
            </a>
            <div class="collapse<?php echo $grupoActivo(['config']); ?>" id="menuConfiguracion">
                <ul class="nav flex-column ps-3">
                    <?php if (tiene_permiso('config.ver')): ?>
                        <li class="nav-item">
                            <a class="sidebar-link<?php echo $activo('config/empresa'); ?>" href="<?php echo e(route_url('config/empresa')); ?>">
                                <span>Datos Empresa</span>
                            </a>
                        </li>
                    <?php endif; ?>
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
