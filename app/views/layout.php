<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?php echo e(asset_url('uploads/config/logo_69856b611f662.png')); ?>">
    <title>SISADMIN</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">

    <?php 
    // Variable única para la ruta actual
    $currentRoute = $ruta_actual ?? $_GET['ruta'] ?? ''; 
    
    // Control de caché dinámico (filemtime asegura que solo se descargue si el archivo cambió)
    $getAssetVersion = function($path) {
        $fullPath = BASE_PATH . '/public/' . $path; 
        return file_exists($fullPath) ? filemtime($fullPath) : '1.0';
    };
    ?>

    <link rel="stylesheet" href="<?php echo e(asset_url('css/app.css')); ?>?v=<?php echo $getAssetVersion('css/app.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/tablas-custom.css')); ?>?v=<?php echo $getAssetVersion('css/tablas-custom.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/sidebar.css')); ?>?v=<?php echo $getAssetVersion('css/sidebar.css'); ?>">
    <link rel="stylesheet" href="<?php echo e(asset_url('css/modales.css')); ?>?v=<?php echo $getAssetVersion('css/modales.css'); ?>">

    <?php if (str_starts_with($currentRoute, 'ventas')): ?>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/ventas.css')); ?>?v=<?php echo $getAssetVersion('css/ventas.css'); ?>"> 
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'compras')): ?>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/compras.css')); ?>?v=<?php echo $getAssetVersion('css/compras.css'); ?>"> 
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'inventario')): ?>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/inventario.css')); ?>?v=<?php echo $getAssetVersion('css/inventario.css'); ?>"> 
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'produccion')): ?>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/produccion.css')); ?>?v=<?php echo $getAssetVersion('css/produccion.css'); ?>"> 
    <?php endif; ?>
    
    <?php if ($currentRoute === 'terceros/perfil'): ?>
        <link rel="stylesheet" href="<?php echo e(asset_url('css/terceros_perfil.css')); ?>?v=<?php echo $getAssetVersion('css/terceros_perfil.css'); ?>">
    <?php endif; ?>
    
    <style>
        .ts-dropdown, .ts-dropdown.form-control { z-index: 2000 !important; }
        .ts-control { border-radius: 0.375rem; }
        .main-content {
            opacity: 1;
            transition: opacity .18s ease;
        }
        body.page-is-loading .main-content {
            opacity: .68;
            pointer-events: none;
        }
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
<script>
(function () {
    try {
        if (localStorage.getItem('erp.sidebar.collapsed') === '1') {
            document.body.classList.add('sidebar-collapsed');
        }
        sessionStorage.removeItem('erp.nav.loading');
        document.body.classList.remove('page-is-loading');
    } catch (_err) {
        // noop
    }
})();
</script>

<button class="btn btn-dark sidebar-mobile-trigger d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSidebarOffcanvas" aria-controls="appSidebarOffcanvas" aria-label="Abrir menú principal">
    <i class="bi bi-list"></i>
</button>

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
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"
        onerror="(function(){var s=document.createElement('script');s.src='https://unpkg.com/tom-select@2.2.2/dist/js/tom-select.complete.min.js';document.head.appendChild(s);}())"></script>

<script src="<?php echo e(asset_url('js/main.js')); ?>?v=<?php echo $getAssetVersion('js/main.js'); ?>"></script>
<script src="<?php echo e(asset_url('js/tablas/renderizadores.js')); ?>?v=<?php echo $getAssetVersion('js/tablas/renderizadores.js'); ?>"></script>
<script src="<?php echo e(asset_url('js/tablas/cards_acordeon.js')); ?>?v=<?php echo $getAssetVersion('js/tablas/cards_acordeon.js'); ?>"></script>
<script src="<?php echo e(asset_url('js/tablas/iconos_accion.js')); ?>?v=<?php echo $getAssetVersion('js/tablas/iconos_accion.js'); ?>"></script>

<?php if (in_array($currentRoute, ['usuarios', 'usuarios/index'], true)): ?>
    <script>
    window.USUARIOS_FLASH = {
        tipo: '<?php echo e((string) ($flash['tipo'] ?? '')); ?>',
        texto: '<?php echo e((string) ($flash['texto'] ?? '')); ?>',
        accion: '<?php echo strpos((string) ($flash['texto'] ?? ''), 'Usuario creado') !== false ? 'crear' : ''; ?>'
    };
    </script>
    <script src="<?php echo e(asset_url('js/usuarios.js')); ?>?v=<?php echo $getAssetVersion('js/usuarios.js'); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['roles', 'roles/index'], true)): ?>
    <script>
    window.ROLES_FLASH = {
        tipo: '<?php echo e((string) ($flash['tipo'] ?? '')); ?>',
        texto: '<?php echo e((string) ($flash['texto'] ?? '')); ?>',
        accion: '<?php echo strpos((string) ($flash['texto'] ?? ''), 'Rol creado') !== false ? 'crear' : ''; ?>'
    };
    </script>
    <script src="<?php echo e(asset_url('js/roles.js')); ?>?v=<?php echo $getAssetVersion('js/roles.js'); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['permisos', 'permisos/index'], true)): ?>
    <script src="<?php echo e(asset_url('js/permisos.js')); ?>?v=<?php echo $getAssetVersion('js/permisos.js'); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['config/empresa', 'empresa/empresa'], true)): ?>
    <script src="<?php echo e(asset_url('js/configuracion/empresa.js')); ?>?v=<?php echo $getAssetVersion('js/configuracion/empresa.js'); ?>"></script>
<?php endif; ?>

<?php if (in_array($currentRoute, ['items', 'items/index'], true)): ?>
    <script src="<?php echo e(asset_url('js/items/categorias_rubros.js')); ?>?v=<?php echo $getAssetVersion('js/items/categorias_rubros.js'); ?>"></script>
    <script src="<?php echo e(asset_url('js/items/atributos.js')); ?>?v=<?php echo $getAssetVersion('js/items/atributos.js'); ?>"></script>
    <script src="<?php echo e(asset_url('js/items/unidades_conversion.js')); ?>?v=<?php echo $getAssetVersion('js/items/unidades_conversion.js'); ?>"></script>
    <script src="<?php echo e(asset_url('js/items/main.js')); ?>?v=<?php echo $getAssetVersion('js/items/main.js'); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'items/perfil'): ?>
    <script src="<?php echo e(asset_url('js/items/perfil.js')); ?>?v=<?php echo $getAssetVersion('js/items/perfil.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'terceros')): ?>
    <script src="<?php echo e(asset_url('js/terceros/terceros.js')); ?>?v=<?php echo $getAssetVersion('js/terceros/terceros.js'); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'terceros/perfil'): ?>
    <script src="<?php echo e(asset_url('js/terceros/terceros_perfil.js')); ?>?v=<?php echo $getAssetVersion('js/terceros/terceros_perfil.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'compras')): ?>
    <script src="<?php echo e(asset_url('js/compras.js')); ?>?v=<?php echo $getAssetVersion('js/compras.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'ventas')): ?>
    <script src="<?php echo e(asset_url('js/ventas.js')); ?>?v=<?php echo $getAssetVersion('js/ventas.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'produccion')): ?>
    <script src="<?php echo e(asset_url('js/produccion/produccion_ordenes.js')); ?>?v=<?php echo $getAssetVersion('js/produccion/produccion_ordenes.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'costos')): ?>
    <script src="<?php echo e(asset_url('js/costos/cierres.js')); ?>?v=<?php echo $getAssetVersion('js/costos/cierres.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'reportes')): ?>
    <script src="<?php echo e(asset_url('js/reportes.js')); ?>?v=<?php echo $getAssetVersion('js/reportes.js'); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'reportes/inventario'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="<?php echo e(asset_url('js/reportes/inventario.js')); ?>?v=<?php echo $getAssetVersion('js/reportes/inventario.js'); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'reportes/ventas'): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
    <script src="<?php echo e(asset_url('js/reportes/ventas.js')); ?>?v=<?php echo $getAssetVersion('js/reportes/ventas.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'comercial')): ?>
    <script src="<?php echo e(asset_url('js/comercial.js')); ?>?v=<?php echo $getAssetVersion('js/comercial.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'tesoreria')): ?>
    <script src="<?php echo e(asset_url('js/tesoreria/tesoreria_core.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php if ($currentRoute === 'tesoreria/cuentas'): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/cuentas.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php elseif ($currentRoute === 'tesoreria/movimientos'): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/movimientos.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php elseif ($currentRoute === 'tesoreria/cxc'): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/cxc.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php elseif ($currentRoute === 'tesoreria/cxp'): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/cxp.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php elseif ($currentRoute === 'tesoreria/saldos_iniciales'): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/saldos_iniciales.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php elseif ($currentRoute === 'tesoreria/prestamos'): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/prestamos.js')); ?>?v=<?php echo time(); ?>"></script>
    <?php endif; ?>
<?php endif; ?>

<?php if ($currentRoute === 'gastos/conceptos'): ?>
    <script src="<?php echo e(asset_url('js/gastos/conceptos_gasto.js')); ?>?v=<?php echo $getAssetVersion('js/gastos/conceptos_gasto.js'); ?>"></script>
<?php endif; ?>

<?php if ($currentRoute === 'gastos/registros'): ?>
    <script src="<?php echo e(asset_url('js/gastos/registro_gastos.js')); ?>?v=<?php echo $getAssetVersion('js/gastos/registro_gastos.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'almacenes')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/almacenes.js')); ?>?v=<?php echo $getAssetVersion('js/configuracion/almacenes.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'cajas_bancos')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/cajas_bancos.js')); ?>?v=<?php echo $getAssetVersion('js/configuracion/cajas_bancos.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'impuestos')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/impuestos.js')); ?>?v=<?php echo $getAssetVersion('js/configuracion/impuestos.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'series')): ?>
    <script src="<?php echo e(asset_url('js/configuracion/series.js')); ?>?v=<?php echo $getAssetVersion('js/configuracion/series.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'asistencia')): ?>
    <script src="<?php echo e(asset_url('js/rrhh/asistencia.js')); ?>?v=<?php echo $getAssetVersion('js/rrhh/asistencia.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'horario')): ?>
    <script src="<?php echo e(asset_url('js/rrhh/horario.js')); ?>?v=<?php echo $getAssetVersion('js/rrhh/horario.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'activos')): ?>
    <script src="<?php echo e(asset_url('js/contabilidad/activos.js')); ?>?v=<?php echo $getAssetVersion('js/contabilidad/activos.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'conciliacion')): ?>
    <script src="<?php echo e(asset_url('js/contabilidad/conciliaciones.js')); ?>?v=<?php echo $getAssetVersion('js/contabilidad/conciliaciones.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'cierre_contable')): ?>
    <script src="<?php echo e(asset_url('js/contabilidad/cierres.js')); ?>?v=<?php echo $getAssetVersion('js/contabilidad/cierres.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'contabilidad/centros_costo')): ?>
    <script src="<?php echo e(asset_url('js/contabilidad/centros_costo.js')); ?>?v=<?php echo $getAssetVersion('js/contabilidad/centros_costo.js'); ?>"></script>
<?php endif; ?>

<?php if (!empty($flash['texto']) && empty($flash['custom_js_handled'])): ?>
<script>
Swal.fire({
    icon: <?php echo json_encode($flash['tipo'] === 'error' ? 'error' : 'success'); ?>,
    title: <?php echo json_encode($flash['tipo'] === 'error' ? 'Error' : 'Éxito'); ?>,
    text: <?php echo json_encode((string) $flash['texto']); ?>,
    confirmButtonText: 'Aceptar'
});
</script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'planillas')): ?>
    <script src="<?php echo e(asset_url('js/rrhh/planillas.js')); ?>?v=<?php echo $getAssetVersion('js/rrhh/planillas.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'rrhh/config_rrhh')): ?>
    <script src="<?php echo e(asset_url('js/rrhh/config_rrhh.js')); ?>?v=<?php echo $getAssetVersion('js/rrhh/config_rrhh.js'); ?>"></script>
<?php endif; ?>

<?php if (str_starts_with($currentRoute, 'tesoreria')): ?>
    <script src="<?php echo e(asset_url('js/tesoreria/tesoreria_core.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/tesoreria_core.js'); ?>"></script>

    <?php if ($currentRoute === 'tesoreria/cuentas' || str_starts_with($currentRoute, 'tesoreria/cuentas')): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/cuentas.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/cuentas.js'); ?>"></script>
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'tesoreria/cxc')): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/cxc.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/cxc.js'); ?>"></script>
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'tesoreria/cxp')): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/cxp.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/cxp.js'); ?>"></script>
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'tesoreria/movimientos')): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/movimientos.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/movimientos.js'); ?>"></script>
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'tesoreria/prestamos')): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/prestamos.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/prestamos.js'); ?>"></script>
    <?php endif; ?>

    <?php if (str_starts_with($currentRoute, 'tesoreria/saldos_iniciales')): ?>
        <script src="<?php echo e(asset_url('js/tesoreria/saldos_iniciales.js')); ?>?v=<?php echo $getAssetVersion('js/tesoreria/saldos_iniciales.js'); ?>"></script>
    <?php endif; ?>
<?php endif; ?>

</body>
</html>