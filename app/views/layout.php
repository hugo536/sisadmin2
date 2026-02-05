<?php
declare(strict_types=1);

$usuario_actual = (string) ($_SESSION['usuario'] ?? '');
$ruta_actual = (string) ($ruta_actual ?? ($_GET['ruta'] ?? 'dashboard/index'));
$sesion_activa = isset($_SESSION['id'], $_SESSION['usuario'], $_SESSION['id_rol']);

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

$menu = [
    'Dashboard' => [
        ['label' => 'Inicio', 'ruta' => 'dashboard/index', 'slug' => null],
    ],
    'Seguridad' => [
        ['label' => 'Usuarios', 'ruta' => 'usuarios/index', 'slug' => 'usuarios.ver'],
        ['label' => 'Roles', 'ruta' => 'roles/index', 'slug' => 'roles.ver'],
        ['label' => 'Permisos', 'ruta' => 'permisos/index', 'slug' => 'permisos.ver'],
    ],
    'Maestros' => [
        ['label' => 'Ítems', 'ruta' => 'items/index', 'slug' => 'items.ver'],
        ['label' => 'Terceros', 'ruta' => 'terceros/index', 'slug' => 'terceros.ver'],
        ['label' => 'Categorías', 'ruta' => 'categorias/index', 'slug' => 'categorias.ver'],
        ['label' => 'Almacenes', 'ruta' => 'almacenes/index', 'slug' => 'almacenes.ver'],
    ],
    'Inventario' => [
        ['label' => 'Stock', 'ruta' => 'inventario_stock/index', 'slug' => 'inventario_stock.ver'],
        ['label' => 'Movimientos', 'ruta' => 'inventario_movimientos/index', 'slug' => 'inventario_movimientos.ver'],
        ['label' => 'Lotes', 'ruta' => 'inventario_lotes/index', 'slug' => 'inventario_lotes.ver'],
    ],
];
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SISADMIN2</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body { margin:0; font-family: Arial, sans-serif; background:#f4f6f8; color:#1f2937; }
    .app { display:flex; min-height:100vh; }
    .sidebar { width:250px; background:#111827; color:#e5e7eb; padding:18px 14px; }
    .sidebar h2 { margin:0 0 20px 0; font-size:18px; color:#f9fafb; }
    .menu-group { margin-bottom:18px; }
    .menu-group h3 { font-size:13px; text-transform:uppercase; color:#9ca3af; margin:0 0 8px 0; }
    .menu-group ul { margin:0; padding:0; list-style:none; }
    .menu-group li { margin:3px 0; }
    .menu-group a, .menu-group span { display:block; padding:8px 10px; border-radius:6px; font-size:14px; text-decoration:none; }
    .menu-group a { color:#e5e7eb; }
    .menu-group a:hover, .menu-group a.active { background:#374151; }
    .menu-group span.disabled { color:#6b7280; background:#1f2937; }
    .content { flex:1; display:flex; flex-direction:column; }
    .header { display:flex; justify-content:space-between; align-items:center; background:#fff; padding:14px 20px; border-bottom:1px solid #e5e7eb; }
    .header a { color:#b91c1c; text-decoration:none; font-weight:bold; }
    .main { padding:20px; }
  </style>
</head>
<body>
<?php if (!$sesion_activa): ?>
  <?php if (isset($contenido_vista) && is_file($contenido_vista)) { require $contenido_vista; } ?>
<?php else: ?>
<div class="app">
  <aside class="sidebar">
    <h2>SISADMIN2</h2>
    <?php foreach ($menu as $grupo => $opciones): ?>
      <?php
      $visibles = [];
      foreach ($opciones as $opcion) {
          if ($opcion['slug'] === null || tiene_permiso($opcion['slug'])) {
              $visibles[] = $opcion;
          }
      }
      if ($visibles === []) {
          continue;
      }
      ?>
      <div class="menu-group">
        <h3><?php echo e($grupo); ?></h3>
        <ul>
          <?php foreach ($visibles as $item): ?>
            <?php
            $disponible = ruta_esta_disponible($item['ruta']);
            $url = $disponible
                ? route_url($item['ruta'])
                : route_url('construccion/index') . '&destino=' . rawurlencode($item['ruta']);
            ?>
            <li>
              <a href="<?php echo e($url); ?>" class="<?php echo $ruta_actual === $item['ruta'] ? 'active' : ''; ?>">
                <?php echo e($item['label']); ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endforeach; ?>
  </aside>

  <div class="content">
    <header class="header">
      <div>Usuario: <strong><?php echo e($usuario_actual); ?></strong></div>
      <a href="<?php echo e(route_url('login/logout')); ?>">Cerrar sesión</a>
    </header>

    <main class="main">
      <?php if (isset($contenido_vista) && is_file($contenido_vista)): ?>
        <?php require $contenido_vista; ?>
      <?php endif; ?>
    </main>
  </div>
</div>
<?php endif; ?>
</body>
</html>
