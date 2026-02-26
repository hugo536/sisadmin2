<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISADMIN</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

    <link rel="stylesheet" href="<?php echo e(asset_url('css/app.css')); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/sidebar.css')); ?>">
    
    <?php if (($ruta_actual ?? '') === 'terceros/perfil'): ?>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/terceros_perfil.css')); ?>">
    <?php endif; ?>
    
    <style>
        .ts-dropdown, .ts-dropdown.form-control { z-index: 2000 !important; }
        .ts-control { border-radius: 0.375rem; }
    </style>

    <?php
    $baseUrl = base_url();
    $baseUrl = $baseUrl === '' ? '/' : rtrim($baseUrl, '/') . '/';
    ?>
    <script>window.BASE_URL = "<?php echo e($baseUrl); ?>";</script>
</head>

<?php
$configEmpresa = $configEmpresa ?? [];
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
            } else {
                echo '<div class="alert alert-danger">Error: Vista no definida.</div>';
            } ?>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>

<script src="<?php echo e(asset_url('js/main.js')); ?>"></script>
<script src="<?php echo e(asset_url('js/tablas/renderizadores.js')); ?>"></script>

<?php 
// --- DETECCIÓN DE RUTA PARA SCRIPTS ESPECÍFICOS ---
$currentRoute = $ruta_actual ?? $_GET['ruta'] ?? ''; 
?>

<?php if (in_array($currentRoute, ['usuarios', 'usuarios/index'], true)): ?>
    <script>
    window.USUARIOS_FLASH = {
        tipo: '<?php echo e((string) ($flash['tipo'] ?? '')); ?>',
        texto: '<?php echo e((string) ($flash['texto'] ?? '')); ?>',
        accion: '<?php echo strpos((string) ($flash['texto'] ?? ''), 'Usuario creado') !== false ? 'crear' : ''; ?>'
    };
    </script>
    <script src="<?php echo e(asset_url('js/usuarios.js')); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['roles', 'roles/index'], true)): ?>
    <script>
    window.ROLES_FLASH = {
        tipo: '<?php echo e((string) ($flash['tipo'] ?? '')); ?>',
        texto: '<?php echo e((string) ($flash['texto'] ?? '')); ?>',
        accion: '<?php echo strpos((string) ($flash['texto'] ?? ''), 'Rol creado') !== false ? 'crear' : ''; ?>'
    };
    </script>
    <script src="<?php echo e(asset_url('js/roles.js')); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['permisos', 'permisos/index'], true)): ?>
    <script src="<?php echo e(asset_url('js/permisos.js')); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['config/empresa', 'empresa/empresa'], true)): ?>
    <script src="<?php echo e(asset_url('js/empresa.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'items')): ?>
    <script src="<?php echo e(asset_url('js/items/categorias_rubros.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/items/atributos.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/items/unidades_conversion.js')); ?>"></script>
    <script src="<?php echo e(asset_url('js/items/main.js')); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'items/perfil'): ?>
    <script src="<?php echo e(asset_url('js/items_perfil.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'terceros')): ?>
    <script src="<?php echo e(asset_url('js/terceros.js')); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'terceros/perfil'): ?>
    <script src="<?php echo e(asset_url('js/terceros_perfil.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'compras')): ?>
    <script src="<?php echo e(asset_url('js/compras.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'ventas')): ?>
    <script src="<?php echo e(asset_url('js/ventas.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'produccion')): ?>
    <script src="<?php echo e(asset_url('js/produccion.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'comercial')): ?>
    <script src="<?php echo e(asset_url('js/comercial.js')); ?>?v=<?php echo time(); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'almacenes')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/almacenes.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'cajas_bancos')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/cajas_bancos.js')); ?>"></script>
<?php endif; ?>


<?php if (str_starts_with($currentRoute, 'impuestos')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/impuestos.js')); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'series')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/series.js')); ?>"></script>
<?php endif; ?>

<?php if (!empty($flash['texto']) && empty($flash['custom_js_handled'])): ?>
<script>
Swal.fire({
    icon: '<?php echo e($flash['tipo'] === 'error' ? 'error' : 'success'); ?>',
    title: '<?php echo e($flash['tipo'] === 'error' ? 'Error' : 'Éxito'); ?>',
    text: '<?php echo e($flash['texto']); ?>',
    confirmButtonText: 'Aceptar'
});
</script>
<?php endif; ?>

</body>
</html>
