<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>403 - Acceso denegado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height:100vh;">
<div class="container">
    <div class="card shadow-sm mx-auto" style="max-width:560px;">
        <div class="card-body p-4 text-center">
            <h1 class="h3 mb-3">403 - No autorizado</h1>
            <p class="text-muted">No tienes permisos para acceder a este módulo.</p>
            <a class="btn btn-primary" href="<?php echo e(route_url('dashboard/index')); ?>">Volver al dashboard</a>
        </div>
    </div>
</div>
</body>
</html>
