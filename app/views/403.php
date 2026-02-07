<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Acceso denegado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container text-center mt-5">
    <h1 class="text-danger">403 - Acceso Denegado</h1>
    <p>No tienes permisos para ver esta sección.</p>
    
    <?php if (isset($_GET['ruta'])): ?>
        <small class="text-muted">Ruta bloqueada: <?php echo htmlspecialchars($_GET['ruta']); ?></small>
    <?php endif; ?>
    
    <pre class="text-start bg-light p-3 mt-3 border">
        Permisos actuales en sesión:
        <?php print_r($_SESSION['permisos'] ?? 'VACÍO'); ?>
    </pre>
</div>
</body>
</html>
