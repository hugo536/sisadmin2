<?php
$rutaActual = (string) ($ruta_actual ?? (string) ($_GET['ruta'] ?? 'dashboard/index'));
$activo = static fn(string $ruta) => str_starts_with($rutaActual, $ruta) ? ' active' : '';
?>
<aside class="sidebar position-fixed top-0 start-0 h-100">
    <div class="sidebar-brand">
        <div class="sidebar-logo">SISADMIN2</div>
        <div class="sidebar-profile">
            <strong><?php echo e((string) ($_SESSION['usuario'] ?? 'Usuario')); ?></strong>
            <small>ROL #<?php echo (int) ($_SESSION['id_rol'] ?? 0); ?></small>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a class="sidebar-link<?php echo $activo('dashboard'); ?>" href="<?php echo e(route_url('dashboard/index')); ?>">Dashboard</a>

        <div class="sidebar-section">Seguridad</div>
        <?php if (tiene_permiso('usuarios.ver')): ?><a class="sidebar-link<?php echo $activo('usuarios'); ?>" href="<?php echo e(route_url('usuarios/index')); ?>">Usuarios</a><?php endif; ?>
        <?php if (tiene_permiso('roles.ver')): ?><a class="sidebar-link<?php echo $activo('roles'); ?>" href="<?php echo e(route_url('roles/index')); ?>">Roles</a><?php endif; ?>
        <?php if (tiene_permiso('permisos.ver')): ?><a class="sidebar-link<?php echo $activo('permisos'); ?>" href="<?php echo e(route_url('permisos/index')); ?>">Permisos</a><?php endif; ?>
        <?php if (tiene_permiso('bitacora.ver')): ?><a class="sidebar-link<?php echo $activo('bitacora'); ?>" href="<?php echo e(route_url('bitacora/index')); ?>">Bitácora</a><?php endif; ?>

        <div class="sidebar-section">Configuración</div>
        <?php if (tiene_permiso('config.empresa.ver')): ?><a class="sidebar-link<?php echo $activo('config/empresa'); ?>" href="<?php echo e(route_url('config/empresa')); ?>">Empresa</a><?php endif; ?>

        <a class="sidebar-link mt-3" id="logoutLink" href="<?php echo e(route_url('login/logout')); ?>">Cerrar sesión</a>
    </nav>
</aside>
