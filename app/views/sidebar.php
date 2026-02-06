<?php
$rutaActual = (string) ($ruta_actual ?? (string) ($_GET['ruta'] ?? 'dashboard/index'));
// Helper para verificar activo
$activo = static fn(string $ruta) => str_starts_with($rutaActual, $ruta) ? ' active' : '';
?>
<aside class="sidebar position-fixed top-0 start-0 h-100">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="bi bi-grid-1x2-fill text-primary"></i> SISADMIN2
        </div>
        
        <div class="user-card">
            <div class="user-avatar">
                <?php 
                    // Obtener inicial del usuario
                    $userInitial = strtoupper(substr((string)($_SESSION['usuario'] ?? 'U'), 0, 1)); 
                    echo $userInitial;
                ?>
            </div>
            <div class="user-info">
                <div class="user-name"><?php echo e((string) ($_SESSION['usuario'] ?? 'Usuario')); ?></div>
                <div class="user-role">ROL #<?php echo (int) ($_SESSION['id_rol'] ?? 0); ?></div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav flex-grow-1">
        
        <div class="nav-label">Principal</div>
        <a class="sidebar-link<?php echo $activo('dashboard'); ?>" href="<?php echo e(route_url('dashboard/index')); ?>">
            <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
        </a>

        <div class="nav-label">Administración</div>
        
        <?php if (tiene_permiso('usuarios.ver')): ?>
        <a class="sidebar-link<?php echo $activo('usuarios'); ?>" href="<?php echo e(route_url('usuarios/index')); ?>">
            <i class="bi bi-people"></i> <span>Usuarios</span>
        </a>
        <?php endif; ?>

        <?php if (tiene_permiso('roles.ver')): ?>
        <a class="sidebar-link<?php echo $activo('roles'); ?>" href="<?php echo e(route_url('roles/index')); ?>">
            <i class="bi bi-shield-lock"></i> <span>Roles</span>
        </a>
        <?php endif; ?>

        <?php if (tiene_permiso('permisos.ver')): ?>
        <a class="sidebar-link<?php echo $activo('permisos'); ?>" href="<?php echo e(route_url('permisos/index')); ?>">
            <i class="bi bi-key"></i> <span>Permisos</span>
        </a>
        <?php endif; ?>

        <?php if (tiene_permiso('bitacora.ver')): ?>
        <a class="sidebar-link<?php echo $activo('bitacora'); ?>" href="<?php echo e(route_url('bitacora/index')); ?>">
            <i class="bi bi-journal-text"></i> <span>Bitácora</span>
        </a>
        <?php endif; ?>

        <div class="nav-label">Sistema</div>
        <?php if (tiene_permiso('config.empresa.ver')): ?>
        <a class="sidebar-link<?php echo $activo('config/empresa'); ?>" href="<?php echo e(route_url('config/empresa')); ?>">
            <i class="bi bi-building-gear"></i> <span>Empresa</span>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <a id="logoutLink" class="sidebar-link logout-link" href="<?php echo e(route_url('login/logout')); ?>">
            <i class="bi bi-box-arrow-left"></i> <span>Cerrar sesión</span>
        </a>
    </div>
</aside>
