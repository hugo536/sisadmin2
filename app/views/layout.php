<?php
declare(strict_types=1);

$usuario_actual = (string) ($_SESSION['usuario'] ?? '');
$ruta_actual = (string) ($ruta_actual ?? ($_GET['ruta'] ?? 'dashboard/index'));
$sesion_activa = isset($_SESSION['id'], $_SESSION['usuario'], $_SESSION['id_rol']);
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SISADMIN2</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="<?php echo e(asset_url('css/app.css')); ?>">
  <?php if ($sesion_activa): ?>
    <link rel="stylesheet" href="<?php echo e(asset_url('css/sidebar.css')); ?>">
  <?php endif; ?>
</head>
<body>
<?php if (!$sesion_activa): ?>
  <?php if (isset($contenido_vista) && is_file($contenido_vista)) { require $contenido_vista; } ?>
<?php else: ?>
<div class="app-layout">
  <?php require BASE_PATH . '/app/views/sidebar.php'; ?>

  <div class="app-main">
    <header class="topbar">
      <div>Usuario: <strong><?php echo e($usuario_actual); ?></strong></div>
      <a class="btn btn-danger" href="<?php echo e(route_url('login/logout')); ?>">Cerrar sesión</a>
    </header>

    <main class="app-content">
      <?php if (isset($contenido_vista) && is_file($contenido_vista)): ?>
        <?php require $contenido_vista; ?>
      <?php endif; ?>
    </main>
  </div>
</div>
<?php endif; ?>
</body>
</html>
