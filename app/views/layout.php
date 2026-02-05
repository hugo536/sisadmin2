<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISADMIN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/app.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/sidebar.css')); ?>">
</head>
<body>
<div class="app-container">
    <?php require BASE_PATH . '/app/views/sidebar.php'; ?>

    <main class="main-content">
        <header class="top-bar">
            <button class="btn btn-sm btn-outline-secondary d-md-none" id="toggleSidebar" type="button">
                <i class="fas fa-bars"></i>
            </button>
            <div class="user-info ms-auto">
                <span><?php echo e((string) ($_SESSION['usuario'] ?? 'Usuario')); ?></span>
            </div>
        </header>

        <div class="p-4">
            <?php if (isset($vista) && is_file($vista)) {
                require $vista;
            } ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo e(asset_url('js/main.js')); ?>"></script>
<?php if (!empty($flash['texto'])): ?>
<script>
Swal.fire({icon:'<?php echo e($flash['tipo'] === 'error' ? 'error' : 'success'); ?>',text:'<?php echo e($flash['texto']); ?>',confirmButtonText:'Aceptar'});
</script>
<?php endif; ?>
</body>
</html>
