<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <title>SISADMIN2</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <?php if (isset($contenido_vista) && is_file($contenido_vista)): ?>
    <?php require $contenido_vista; ?>
  <?php else: ?>
    <h1>Layout base</h1>
  <?php endif; ?>
</body>
</html>
