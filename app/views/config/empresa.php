<?php $config = is_array($config ?? null) ? $config : []; ?>
<div class="container-fluid">
    <h1 class="h3 mb-1">Configuración de Empresa</h1>
    <p class="text-muted">Administra la información principal de la empresa.</p>

    <div class="card">
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <div class="col-md-6"><label class="form-label">Nombre empresa</label><input name="nombre_empresa" class="form-control" value="<?php echo e((string) ($config['nombre_empresa'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">RUC</label><input name="ruc" class="form-control" value="<?php echo e((string) ($config['ruc'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Dirección</label><input name="direccion" class="form-control" value="<?php echo e((string) ($config['direccion'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Teléfono</label><input name="telefono" class="form-control" value="<?php echo e((string) ($config['telefono'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Email</label><input name="email" type="email" class="form-control" value="<?php echo e((string) ($config['email'] ?? '')); ?>"></div>
                <div class="col-md-3"><label class="form-label">Moneda</label><input name="moneda" class="form-control" value="<?php echo e((string) ($config['moneda'] ?? 'PEN')); ?>"></div>
                <div class="col-md-3"><label class="form-label">Impuesto (%)</label><input name="impuesto" type="number" step="0.01" class="form-control" value="<?php echo e((string) ($config['impuesto'] ?? '0')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Slogan</label><input name="slogan" class="form-control" value="<?php echo e((string) ($config['slogan'] ?? '')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Color sistema</label><input name="color_sistema" type="color" class="form-control form-control-color" value="<?php echo e((string) ($config['color_sistema'] ?? '#0d6efd')); ?>"></div>
                <div class="col-md-6"><label class="form-label">Logo (png/jpg/jpeg/webp, máx. 2MB)</label><input name="logo" type="file" class="form-control" accept=".png,.jpg,.jpeg,.webp"></div>
                <div class="col-12">
                    <div class="mb-2">Logo actual:</div>
                    <?php if (!empty($config['ruta_logo'])): ?>
                        <img src="<?php echo e(base_url() . '/' . $config['ruta_logo']); ?>" alt="Logo empresa" style="max-height:70px">
                    <?php else: ?>
                        <span class="text-muted">Sin logo cargado.</span>
                    <?php endif; ?>
                </div>
                <div class="col-12"><button class="btn btn-primary">Guardar configuración</button></div>
            </form>
        </div>
    </div>
</div>
