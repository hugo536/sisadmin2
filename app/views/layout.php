<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISADMIN</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/app.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/sidebar.css')); ?>">
    <?php
    $baseUrl = base_url();
    $baseUrl = $baseUrl === '' ? '/' : rtrim($baseUrl, '/') . '/';
    ?>
    <script>
        window.BASE_URL = "<?php echo e($baseUrl); ?>";
    </script>
</head>
<?php
$colorSistema = strtolower((string) ($configEmpresa['color_sistema'] ?? 'light'));
$esHex = (bool) preg_match('/^#([a-f0-9]{6})$/i', $colorSistema);
$temaSistema = $esHex ? 'custom' : $colorSistema;
$bodyStyle = $esHex ? "--primary-color: {$colorSistema}; --primary-hover: {$colorSistema};" : '';
?>
<body data-theme="<?php echo e($temaSistema); ?>" style="<?php echo e($bodyStyle); ?>">
<div class="app-container">
    <?php require BASE_PATH . '/app/views/sidebar.php'; ?>

    <main class="main-content">
        <div class="p-3 p-lg-4">
            <?php if (isset($vista) && is_file($vista)) {
                require $vista;
            } ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo e(asset_url('js/main.js')); ?>"></script>
<script src="<?php echo e(asset_url('js/tablas/renderizadores.js')); ?>"></script>
<?php if (($ruta_actual ?? '') === 'usuarios/index'): ?>
<script>
window.USUARIOS_FLASH = {
    tipo: '<?php echo e((string) ($flash['tipo'] ?? '')); ?>',
    texto: '<?php echo e((string) ($flash['texto'] ?? '')); ?>',
    accion: '<?php echo strpos((string) ($flash['texto'] ?? ''), 'Usuario creado') !== false ? 'crear' : ''; ?>'
};
</script>
<script src="<?php echo e(asset_url('js/usuarios.js')); ?>"></script>
<?php endif; ?>
<?php if (in_array(($ruta_actual ?? ''), ['roles/index', 'roles'], true)): ?>
<script>
window.ROLES_FLASH = {
    tipo: '<?php echo e((string) ($flash['tipo'] ?? '')); ?>',
    texto: '<?php echo e((string) ($flash['texto'] ?? '')); ?>',
    accion: '<?php echo strpos((string) ($flash['texto'] ?? ''), 'Rol creado') !== false ? 'crear' : ''; ?>'
};
</script>
<script src="<?php echo e(asset_url('js/roles.js')); ?>"></script>
<?php endif; ?>
<?php if (($ruta_actual ?? '') === 'permisos/index'): ?>
<script src="<?php echo e(asset_url('js/permisos.js')); ?>"></script>
<?php endif; ?>
<?php if (in_array(($ruta_actual ?? ''), ['config/empresa', 'empresa/empresa'], true)): ?>
<script src="<?php echo e(asset_url('js/empresa.js')); ?>"></script>
<?php endif; ?>
<?php if (in_array(($ruta_actual ?? ''), ['items'], true)): ?>
<script src="<?php echo e(asset_url('js/item.js')); ?>"></script>
<?php endif; ?>
<?php if (in_array(($ruta_actual ?? ''), ['terceros'], true)): ?>
<script src="<?php echo e(asset_url('js/terceros.js')); ?>"></script>
<?php endif; ?>

<?php if (!empty($flash['texto'])): ?>
<script>
Swal.fire({icon:'<?php echo e($flash['tipo'] === 'error' ? 'error' : 'success'); ?>',text:'<?php echo e($flash['texto']); ?>',confirmButtonText:'Aceptar'});
</script>
<?php endif; ?>
</body>
</html>
