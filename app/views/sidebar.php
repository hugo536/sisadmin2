<?php
declare(strict_types=1);

$ruta_actual = (string) ($ruta_actual ?? ($_GET['ruta'] ?? 'dashboard'));
$usuarioNombre = trim((string) ($_SESSION['usuario_nombre'] ?? ''));
$rolNombre = trim((string) ($_SESSION['rol_nombre'] ?? ''));

if ($usuarioNombre == '') {
    $usuarioNombre = 'Invitado';
}

if ($rolNombre == '') {
    $rolNombre = 'Invitado';
}

if (!function_exists('ruta_esta_disponible')) {
    function ruta_esta_disponible(string $ruta): bool
    {
        $partes = array_values(array_filter(explode('/', trim($ruta, '/'))));
        $modulo = $partes[0] ?? '';
        $accion = $partes[1] ?? 'index';
        if ($modulo === '') {
            return false;
        }

        $controlador = ucfirst($modulo) . 'Controller';

        // =========================================================
        // MAPEO DE ALIAS (Igual que en el Router)
        // Agregamos esto para que el sidebar sepa encontrar el archivo real
        // =========================================================
        if ($controlador === 'ConfiguracionController') {
            $controlador = 'ConfigController';
        }
        if ($controlador === 'LoginController') {
            $controlador = 'AuthController';
        }
        // =========================================================

        // Buscamos el archivo (intentando ser compatible con la lógica del Router)
        $archivo = BASE_PATH . '/app/controllers/' . $controlador . '.php';
        
        // Si no está en controllers, probamos controladores (por consistencia)
        if (!is_file($archivo)) {
            $archivo = BASE_PATH . '/app/controladores/' . $controlador . '.php';
        }

        if (!is_file($archivo)) {
            return false;
        }

        // Importante: usar require_once para no redeclarar si ya se cargó
        require_once $archivo;
        return class_exists($controlador) && method_exists($controlador, $accion);
    }
}

if (!function_exists('sidebar_item_url')) {
    function sidebar_item_url(string $ruta): string
    {
        if (ruta_esta_disponible($ruta)) {
            return route_url($ruta);
        }

        return route_url('construccion') . '&destino=' . rawurlencode($ruta);
    }
}

if (!function_exists('sidebar_es_activa')) {
    function sidebar_es_activa(string $rutaActual, string $rutaMenu): bool
    {
        return $rutaActual === $rutaMenu || str_starts_with($rutaActual, $rutaMenu . '/');
    }
}
?>
<aside class="sidebar" aria-label="Navegación principal">
  <div class="sidebar-brand">
    <div class="sidebar-logo">SISADMIN2</div>
    <div class="sidebar-profile">
      <strong><?php echo e($usuarioNombre); ?></strong>
      <small><?php echo e($rolNombre); ?></small>
    </div>
  </div>

  <nav class="sidebar-nav">
    <?php if (tiene_permiso('dashboard.ver')): ?>
      <a href="<?php echo e(sidebar_item_url('dashboard')); ?>" class="sidebar-link<?php echo sidebar_es_activa($ruta_actual, 'dashboard') ? ' active' : ''; ?>">
        Dashboard
      </a>
    <?php endif; ?>

    <?php if (tiene_permiso('inventario.ver')): ?>
      <details class="sidebar-dropdown"<?php echo (sidebar_es_activa($ruta_actual, 'inventario_stock') || sidebar_es_activa($ruta_actual, 'inventario_movimientos')) ? ' open' : ''; ?>>
        <summary>Inventario</summary>
        <div class="sidebar-submenu">
          <?php if (tiene_permiso('inventario_stock.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('inventario_stock')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'inventario_stock') ? ' active' : ''; ?>">Stock</a>
          <?php endif; ?>

          <?php if (tiene_permiso('inventario_movimientos.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('inventario_movimientos')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'inventario_movimientos') ? ' active' : ''; ?>">Movimientos</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

    <?php if (tiene_permiso('maestros.ver')): ?>
      <details class="sidebar-dropdown"<?php echo (sidebar_es_activa($ruta_actual, 'items') || sidebar_es_activa($ruta_actual, 'terceros')) ? ' open' : ''; ?>>
        <summary>Maestros</summary>
        <div class="sidebar-submenu">
          <?php if (tiene_permiso('items.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('items')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'items') ? ' active' : ''; ?>">Ítems</a>
          <?php endif; ?>

          <?php if (tiene_permiso('terceros.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('terceros')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'terceros') ? ' active' : ''; ?>">Terceros</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>

    <div class="sidebar-section">Sistema</div>

    <?php if (tiene_permiso('usuarios.ver')): ?>
      <a href="<?php echo e(sidebar_item_url('usuarios')); ?>" class="sidebar-link<?php echo sidebar_es_activa($ruta_actual, 'usuarios') ? ' active' : ''; ?>">
        Usuarios
      </a>
    <?php endif; ?>

    <?php if (tiene_permiso('config.ver') || tiene_permiso('configuracion.ver')): ?>
      <details class="sidebar-dropdown"<?php echo (sidebar_es_activa($ruta_actual, 'config/empresa') || sidebar_es_activa($ruta_actual, 'bitacora') || sidebar_es_activa($ruta_actual, 'roles')) ? ' open' : ''; ?>>
        <summary>Configuración</summary>
        <div class="sidebar-submenu">
          <?php if (tiene_permiso('config.ver') || tiene_permiso('configuracion.empresa.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('config/empresa')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'config/empresa') ? ' active' : ''; ?>">Datos Empresa</a>
          <?php endif; ?>

          <?php if (tiene_permiso('bitacora.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('bitacora')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'bitacora') ? ' active' : ''; ?>">Bitácora</a>
          <?php endif; ?>

          <?php if (tiene_permiso('roles.ver')): ?>
            <a href="<?php echo e(sidebar_item_url('roles')); ?>" class="sidebar-sublink<?php echo sidebar_es_activa($ruta_actual, 'roles') ? ' active' : ''; ?>">Roles y Permisos</a>
          <?php endif; ?>
        </div>
      </details>
    <?php endif; ?>
  </nav>
</aside>