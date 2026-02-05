<?php
declare(strict_types=1);

$ruta_actual = (string) ($ruta_actual ?? ($_GET['ruta'] ?? 'dashboard/index'));

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
        $archivo = BASE_PATH . '/app/controllers/' . $controlador . '.php';
        if (!is_file($archivo)) {
            return false;
        }

        require_once $archivo;
        return class_exists($controlador) && method_exists($controlador, $accion);
    }
}

$menuConfig = [
    [
        'tipo' => 'link',
        'label' => 'Dashboard',
        'ruta' => 'dashboard/index',
        'slug' => null,
    ],
    [
        'tipo' => 'dropdown',
        'label' => 'Inventario',
        'items' => [
            ['label' => 'Stock', 'ruta' => 'inventario_stock/index', 'slug' => 'inventario_stock.ver'],
            ['label' => 'Movimientos', 'ruta' => 'inventario_movimientos/index', 'slug' => 'inventario_movimientos.ver'],
        ],
    ],
    [
        'tipo' => 'dropdown',
        'label' => 'Maestros',
        'items' => [
            ['label' => 'Ítems', 'ruta' => 'items/index', 'slug' => 'items.ver'],
            ['label' => 'Terceros', 'ruta' => 'terceros/index', 'slug' => 'terceros.ver'],
            ['label' => 'Categorías', 'ruta' => 'categorias/index', 'slug' => 'categorias.ver'],
        ],
    ],
    [
        'tipo' => 'section',
        'label' => 'Sistema',
    ],
    [
        'tipo' => 'link',
        'label' => 'Usuarios',
        'ruta' => 'usuarios/index',
        'slug' => 'usuarios.ver',
    ],
    [
        'tipo' => 'dropdown',
        'label' => 'Configuración',
        'items' => [
            ['label' => 'Datos Empresa', 'ruta' => 'config/empresa', 'slug' => null],
            ['label' => 'Bitácora', 'ruta' => 'bitacora/index', 'slug' => 'bitacora.ver'],
            ['label' => 'Roles y Permisos', 'ruta' => 'roles/index', 'slug' => 'roles.ver'],
        ],
    ],
];
?>
<aside class="sidebar" aria-label="Navegación principal">
  <div class="sidebar-brand">SISADMIN2</div>
  <nav class="sidebar-nav">
    <?php foreach ($menuConfig as $nodo): ?>
      <?php if ($nodo['tipo'] === 'section'): ?>
        <div class="sidebar-section"><?php echo e($nodo['label']); ?></div>
        <?php continue; ?>
      <?php endif; ?>

      <?php if ($nodo['tipo'] === 'link'): ?>
        <?php
        $slug = $nodo['slug'];
        if ($slug !== null && !tiene_permiso($slug)) {
            continue;
        }
        $ruta = (string) $nodo['ruta'];
        $disponible = ruta_esta_disponible($ruta);
        $url = $disponible
            ? route_url($ruta)
            : route_url('construccion/index') . '&destino=' . rawurlencode($ruta);
        $activo = $ruta_actual === $ruta;
        ?>
        <a href="<?php echo e($url); ?>" class="sidebar-link<?php echo $activo ? ' active' : ''; ?>">
          <span><?php echo e($nodo['label']); ?></span>
        </a>
        <?php continue; ?>
      <?php endif; ?>

      <?php
      $itemsVisibles = [];
      foreach ($nodo['items'] as $item) {
          if ($item['slug'] === null || tiene_permiso((string) $item['slug'])) {
              $itemsVisibles[] = $item;
          }
      }
      if ($itemsVisibles === []) {
          continue;
      }

      $abierto = false;
      foreach ($itemsVisibles as $itemVisible) {
          if ($ruta_actual === $itemVisible['ruta']) {
              $abierto = true;
              break;
          }
      }
      ?>
      <details class="sidebar-dropdown"<?php echo $abierto ? ' open' : ''; ?>>
        <summary><?php echo e($nodo['label']); ?></summary>
        <div class="sidebar-submenu">
          <?php foreach ($itemsVisibles as $item): ?>
            <?php
            $ruta = (string) $item['ruta'];
            $disponible = ruta_esta_disponible($ruta);
            $url = $disponible
                ? route_url($ruta)
                : route_url('construccion/index') . '&destino=' . rawurlencode($ruta);
            $activo = $ruta_actual === $ruta;
            ?>
            <a href="<?php echo e($url); ?>" class="sidebar-sublink<?php echo $activo ? ' active' : ''; ?>">
              <?php echo e($item['label']); ?>
            </a>
          <?php endforeach; ?>
        </div>
      </details>
    <?php endforeach; ?>
  </nav>
</aside>
