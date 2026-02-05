<link rel="stylesheet" href="<?php echo e(asset_url('css/login.css')); ?>">

<div class="dashboard-shell">
  <h1>Dashboard</h1>
  <p class="dashboard-meta">Bienvenido, <strong><?php echo e($usuario ?? 'Usuario'); ?></strong>.</p>
  <p class="dashboard-meta">Rol actual: <?php echo (int) ($idRol ?? 0); ?></p>
  <p class="dashboard-meta">Este es un panel base para continuar con los módulos del sistema.</p>

  <div class="dashboard-actions">
    <a href="<?php echo e(route_url('auth/logout')); ?>">Cerrar sesión</a>
  </div>
</div>
