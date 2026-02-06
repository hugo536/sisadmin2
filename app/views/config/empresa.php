<?php
// Variables de ayuda mapeadas desde la BD
$temaActual = strtolower((string) ($config['color_sistema'] ?? 'light'));
$logoActual = (string) ($config['ruta_logo'] ?? '');
?>

<div class="container-fluid p-4">
    
    <?php if (!empty($flash['texto'])): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: '<?= $flash['tipo'] === "success" ? "success" : "error" ?>',
                title: '<?= $flash['tipo'] === "success" ? "¡Guardado!" : "Error" ?>',
                text: '<?= $flash['texto'] ?>',
                confirmButtonColor: '#0d6efd'
            });
        });
    </script>
    <?php endif; ?>

    <div class="mb-4 fade-in d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-building-gear me-2 text-primary"></i> Perfil de la Empresa
            </h1>
            <p class="text-muted small mb-0">Gestiona la identidad y configuración regional de tu organización.</p>
        </div>
    </div>

    <form method="post" enctype="multipart/form-data" id="empresaForm">
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold text-uppercase small text-primary">Información General</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8 form-floating">
                                <input name="razon_social" id="razonSocial" class="form-control" placeholder="Razón social" value="<?= htmlspecialchars($config['nombre_empresa'] ?? '') ?>" required>
                                <label for="razonSocial">Razón social</label>
                            </div>
                            <div class="col-md-4 form-floating">
                                <input name="ruc" id="ruc" class="form-control" placeholder="RUC" value="<?= htmlspecialchars($config['ruc'] ?? '') ?>" maxlength="11">
                                <label for="ruc">RUC / Tax ID</label>
                            </div>
                            <div class="col-md-12 form-floating">
                                <input name="direccion" id="direccion" class="form-control" placeholder="Dirección" value="<?= htmlspecialchars($config['direccion'] ?? '') ?>">
                                <label for="direccion">Dirección Fiscal</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <input name="telefono" id="telefono" class="form-control" placeholder="Teléfono" value="<?= htmlspecialchars($config['telefono'] ?? '') ?>">
                                <label for="telefono">Teléfono de contacto</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <input name="email" id="email" type="email" class="form-control" placeholder="Correo" value="<?= htmlspecialchars($config['email'] ?? '') ?>">
                                <label for="email">Email corporativo</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <select name="moneda" id="moneda" class="form-select text-primary fw-bold">
                                    <option value="PEN" <?= ($config['moneda'] ?? '') == 'PEN' ? 'selected' : '' ?>>S/ - Sol Peruano</option>
                                    <option value="USD" <?= ($config['moneda'] ?? '') == 'USD' ? 'selected' : '' ?>>$ - Dólar Estadounidense</option>
                                    <option value="EUR" <?= ($config['moneda'] ?? '') == 'EUR' ? 'selected' : '' ?>>€ - Euro</option>
                                </select>
                                <label for="moneda">Moneda del Sistema</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <select name="tema" id="tema" class="form-select">
                                    <option value="light" <?= $temaActual === 'light' ? 'selected' : '' ?>>☀️ Light (Claro)</option>
                                    <option value="dark" <?= $temaActual === 'dark' ? 'selected' : '' ?>>🌙 Dark (Oscuro)</option>
                                    <option value="blue" <?= $temaActual === 'blue' ? 'selected' : '' ?>>🔹 Blue (Corporativo)</option>
                                </select>
                                <label for="tema">Tema de Interfaz</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold text-uppercase small text-primary">Identidad Visual</h6>
                    </div>
                    <div class="card-body text-center p-4">
                        <div class="mb-3">
                            <div class="bg-light rounded p-3 d-inline-block border position-relative" style="width: 100%; min-height: 150px; display: flex; align-items: center; justify-content: center;">
                                <?php if (!empty($logoActual)): ?>
                                    <img id="previewLogo" src="<?= base_url() . '/' . ltrim($logoActual, '/') ?>" class="img-fluid rounded" style="max-height: 120px;">
                                <?php else: ?>
                                    <div id="noLogo" class="text-muted small">
                                        <i class="bi bi-image h1 d-block"></i>
                                        Sin logo cargado
                                    </div>
                                    <img id="previewLogo" src="#" class="img-fluid rounded d-none" style="max-height: 120px;">
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-upload me-2"></i>Seleccionar Logo
                                <input name="logo" type="file" id="inputLogo" class="d-none" accept="image/*">
                            </label>
                            <div class="form-text mt-2" style="font-size: 0.75rem;">PNG, JPG o WEBP (Máx. 2MB)</div>
                        </div>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary btn-lg shadow-sm" type="submit">
                        <i class="bi bi-check-circle me-2"></i>Guardar Cambios
                    </button>
                    <button type="reset" class="btn btn-link btn-sm text-muted">Deshacer cambios</button>
                </div>
            </div>
        </div>
    </form>
</div>