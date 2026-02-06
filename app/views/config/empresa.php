<?php
$config = is_array($config ?? null) ? $config : [];
$temaActual = strtolower((string) ($config['tema'] ?? 'light'));
if (!in_array($temaActual, ['light', 'dark', 'blue'], true)) {
    $temaActual = 'light';
}
$logoActual = (string) ($config['logo_path'] ?? '');
?>
<div class="container-fluid p-4">
    <div class="mb-4 fade-in">
        <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
            <i class="bi bi-building me-2 text-primary"></i> Configuración de Empresa
        </h1>
        <p class="text-muted small mb-0 ms-1">Datos corporativos, identidad visual y tema.</p>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 bg-light">
            <form method="post" enctype="multipart/form-data" class="row g-3" id="empresaForm">
                <div class="col-md-6 form-floating">
                    <input name="razon_social" id="razonSocial" class="form-control" placeholder="Razón social" value="<?php echo e((string) ($config['razon_social'] ?? '')); ?>">
                    <label for="razonSocial">Razón social</label>
                </div>
                <div class="col-md-6 form-floating">
                    <input name="ruc" id="ruc" class="form-control" placeholder="RUC" value="<?php echo e((string) ($config['ruc'] ?? '')); ?>">
                    <label for="ruc">RUC</label>
                </div>
                <div class="col-md-6 form-floating">
                    <input name="direccion" id="direccion" class="form-control" placeholder="Dirección" value="<?php echo e((string) ($config['direccion'] ?? '')); ?>">
                    <label for="direccion">Dirección</label>
                </div>
                <div class="col-md-3 form-floating">
                    <input name="telefono" id="telefono" class="form-control" placeholder="Teléfono" value="<?php echo e((string) ($config['telefono'] ?? '')); ?>">
                    <label for="telefono">Teléfono</label>
                </div>
                <div class="col-md-3 form-floating">
                    <input name="email" id="email" type="email" class="form-control" placeholder="Correo" value="<?php echo e((string) ($config['email'] ?? '')); ?>">
                    <label for="email">Email</label>
                </div>
                <div class="col-md-3 form-floating">
                    <input name="moneda" id="moneda" class="form-control" placeholder="Moneda" value="<?php echo e((string) ($config['moneda'] ?? 'PEN')); ?>">
                    <label for="moneda">Moneda</label>
                </div>
                <div class="col-md-3 form-floating">
                    <select name="tema" id="tema" class="form-select">
                        <option value="light" <?php echo $temaActual === 'light' ? 'selected' : ''; ?>>light</option>
                        <option value="dark" <?php echo $temaActual === 'dark' ? 'selected' : ''; ?>>dark</option>
                        <option value="blue" <?php echo $temaActual === 'blue' ? 'selected' : ''; ?>>blue</option>
                    </select>
                    <label for="tema">Tema</label>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Logo (png/jpg/jpeg/webp, máx. 2MB)</label>
                    <input name="logo" type="file" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                </div>
                <div class="col-md-6">
                    <div class="small text-muted mb-2">Logo actual:</div>
                    <?php if (!empty($logoActual)): ?>
                        <img src="<?php echo e(base_url() . '/' . ltrim($logoActual, '/')); ?>" alt="Logo empresa" style="max-height:70px">
                    <?php else: ?>
                        <span class="text-muted">Sin logo cargado.</span>
                    <?php endif; ?>
                </div>
                <div class="col-12 mt-2">
                    <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save me-2"></i>Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
