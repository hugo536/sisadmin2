<?php
$config = is_array($config ?? null) ? $config : [];
$temaActual = strtolower((string) ($config['color_sistema'] ?? 'light'));
if (!in_array($temaActual, ['light', 'dark', 'blue'], true)) {
    $temaActual = 'light';
}
$logoActual = (string) ($config['ruta_logo'] ?? '');
?>
<div class="container-fluid">
    <h1 class="h3 mb-1">Configuración de Empresa</h1>
    <p class="text-muted">Administra la información principal de la empresa.</p>

    <div class="card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6"><label class="form-label">Razón social</label><input name="nombre_empresa" class="form-control" value="<?php echo e((string) ($config['nombre_empresa'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">RUC</label><input name="ruc" class="form-control" value="<?php echo e((string) ($config['ruc'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Dirección</label><input name="direccion" class="form-control" value="<?php echo e((string) ($config['direccion'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Teléfono</label><input name="telefono" class="form-control" value="<?php echo e((string) ($config['telefono'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?php echo e((string) ($config['email'] ?? '')); ?>"></div>
                <div class="col-md-3"><label class="form-label">Moneda</label><input name="moneda" class="form-control" value="<?php echo e((string) ($config['moneda'] ?? 'PEN')); ?>"></div>
                <div class="col-md-3">
                    <label class="form-label">Tema</label>
                    <select name="color_sistema" class="form-select">
                        <option value="light" <?php echo $temaActual === 'light' ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo $temaActual === 'dark' ? 'selected' : ''; ?>>Dark</option>
                        <option value="blue" <?php echo $temaActual === 'blue' ? 'selected' : ''; ?>>Blue</option>
                    </select>
                </div>
                <div class="col-md-6"><label class="form-label">Logo (png/jpg/jpeg/webp, máx. 2MB)</label><input name="logo" type="file" class="form-control" accept=".png,.jpg,.jpeg,.webp"></div>
                <div class="col-12">
                    <div class="mb-2">Logo actual:</div>
                    <?php if (!empty($logoActual)): ?>
                        <img src="<?php echo e(base_url() . '/' . ltrim($logoActual, '/')); ?>" alt="Logo empresa" style="max-height:70px">
                    <?php else: ?>
                        <span class="text-muted">Sin logo cargado.</span>
                    <?php endif; ?>
                </div>
                <div class="col-12"><button class="btn btn-primary">Guardar configuración</button></div>
            </form>
        </div>
    </div>
</div>
