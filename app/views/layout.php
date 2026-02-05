<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISADMIN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo base_url(); ?>/assets/css/app.css">
    <link rel="stylesheet" href="<?php echo base_url(); ?>/assets/css/sidebar.css">
</head>
<body>
    <div class="app-container">
        <?php require_once BASE_PATH . '/app/views/sidebar.php'; ?>

        <main class="main-content">
            <header class="top-bar">
                <div class="toggle-menu">
                    <i class="fas fa-bars"></i>
                </div>
                <div class="user-info">
                    <span><?php echo $_SESSION['usuario_nombre'] ?? 'Usuario'; ?></span>
                    <a href="<?php echo route_url('logout'); ?>" class="btn-logout" title="Salir">
                        <i class="fas fa-power-off"></i>
                    </a>
                </div>
            </header>
            
            <div class="content-wrapper p-4">
                <?php 
                // Renderizar la vista correspondiente
                if (isset($vista) && is_file($vista)) {
                    require_once $vista;
                } else {
                    echo '<div class="alert alert-danger">Vista no encontrada.</div>';
                }
                ?>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="<?php echo base_url(); ?>/assets/js/app.js"></script>
    
    <?php if (!empty($mensaje)): ?>
    <script>
        Swal.fire({
            icon: '<?php echo str_contains(strtolower($mensaje), 'error') ? 'error' : 'success'; ?>',
            title: 'Notificación',
            text: '<?php echo $mensaje; ?>',
            timer: 3000,
            showConfirmButton: false
        });
    </script>
    <?php endif; ?>
</body>
</html>