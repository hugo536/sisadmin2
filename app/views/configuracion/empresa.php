<?php
// Variables de ayuda mapeadas desde la BD
$logoActual = (string) ($config['ruta_logo'] ?? '');
// Ya no forzamos que sea un Hexadecimal, porque ahora guardaremos el nombre del tema (ej. 'theme-ocean')
$temaActual = (string) ($config['color_sistema'] ?? 'theme-midnight');
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
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold text-uppercase small text-primary">Identidad Corporativa</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8 form-floating">
                                <input name="razon_social" id="razonSocial" class="form-control" placeholder="Razón social" value="<?= htmlspecialchars($config['nombre_empresa'] ?? '') ?>" required>
                                <label for="razonSocial">Razón social</label>
                            </div>
                            <div class="col-md-4 form-floating">
                                <input name="ruc" id="ruc" class="form-control" placeholder="RUC" value="<?= htmlspecialchars($config['ruc'] ?? '') ?>" maxlength="11" required>
                                <label for="ruc">RUC / ID Fiscal</label>
                            </div>
                            <div class="col-md-12 form-floating">
                                <input name="slogan" id="slogan" class="form-control" placeholder="Slogan" value="<?= htmlspecialchars($config['slogan'] ?? '') ?>">
                                <label for="slogan">Slogan</label>
                            </div>
                            <div class="col-md-12 form-floating">
                                <input name="direccion" id="direccion" class="form-control" placeholder="Dirección" value="<?= htmlspecialchars($config['direccion'] ?? '') ?>">
                                <label for="direccion">Dirección Fiscal</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-4">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold text-uppercase small text-primary">Parámetros Operativos</h6>
                    </div>
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6 form-floating">
                                <input name="telefono" id="telefono" class="form-control" placeholder="Teléfono" value="<?= htmlspecialchars($config['telefono'] ?? '') ?>">
                                <label for="telefono">Teléfono</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <input name="email" id="email" type="email" class="form-control" placeholder="Correo" value="<?= htmlspecialchars($config['email'] ?? '') ?>">
                                <label for="email">Email de contacto</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <select name="moneda" id="moneda" class="form-select text-primary fw-bold">
                                    <option value="PEN" <?= ($config['moneda'] ?? '') == 'PEN' ? 'selected' : '' ?>>S/ - Sol Peruano</option>
                                    <option value="USD" <?= ($config['moneda'] ?? '') == 'USD' ? 'selected' : '' ?>>$ - Dólar Estadounidense</option>
                                    <option value="EUR" <?= ($config['moneda'] ?? '') == 'EUR' ? 'selected' : '' ?>>€ - Euro</option>
                                </select>
                                <label for="moneda">Moneda</label>
                            </div>
                            <div class="col-md-6 form-floating">
                                <input name="impuesto" id="impuesto" type="number" step="0.01" class="form-control" placeholder="Impuesto" value="<?= htmlspecialchars((string) ($config['impuesto'] ?? '18')) ?>">
                                <label for="impuesto">Impuesto (%)</label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white py-3 border-bottom-0">
                        <h6 class="mb-0 fw-bold text-uppercase small text-primary">Branding</h6>
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
                        
                        <!-- NUEVO SELECTOR DE TEMAS -->
                        <div class="text-start mt-4 border-top pt-3">
                            <label class="form-label fw-bold text-dark mb-3">Tema Visual del Sistema</label>
                            
                            <?php 
                                // Definimos los 5 temas disponibles
                                $temas = [
                                    'theme-midnight' => ['nombre' => 'Midnight', 'bg' => '#1e293b', 'btn' => '#0d6efd'],
                                    'theme-ocean'    => ['nombre' => 'Ocean',    'bg' => '#0f172a', 'btn' => '#0891b2'],
                                    'theme-forest'   => ['nombre' => 'Forest',   'bg' => '#064e3b', 'btn' => '#10b981'],
                                    'theme-sunset'   => ['nombre' => 'Sunset',   'bg' => '#1c1917', 'btn' => '#f97316'],
                                    'theme-royal'    => ['nombre' => 'Royal',    'bg' => '#312e81', 'btn' => '#8b5cf6'],
                                ];
                                
                                // Si el valor en BD no existe en nuestra lista, forzamos midnight
                                if (!array_key_exists($temaActual, $temas)) {
                                    $temaActual = 'theme-midnight';
                                }
                            ?>
                            
                            <div class="d-flex flex-wrap gap-3 justify-content-center">
                                <?php foreach($temas as $id => $colores): ?>
                                    <div class="theme-option">
                                        <input type="radio" name="color_sistema" id="<?= $id ?>" value="<?= $id ?>" class="d-none theme-radio" <?= $temaActual === $id ? 'checked' : '' ?>>
                                        <label for="<?= $id ?>" class="theme-swatch rounded-circle shadow-sm" style="background: linear-gradient(135deg, <?= $colores['bg'] ?> 50%, <?= $colores['btn'] ?> 50%);" data-bs-toggle="tooltip" title="<?= $colores['nombre'] ?>"></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-text mt-3 text-center">Selecciona la combinación de colores para la barra lateral, tablas y botones.</div>
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

<!-- ESTILOS PARA EL SELECTOR DE TEMAS -->
<style>
    .theme-swatch {
        width: 45px;
        height: 45px;
        display: block;
        cursor: pointer;
        border: 3px solid transparent;
        transition: transform 0.2s, border-color 0.2s;
    }
    .theme-swatch:hover {
        transform: scale(1.1);
    }
    .theme-radio:checked + .theme-swatch {
        border-color: #000;
        transform: scale(1.15);
        box-shadow: 0 0 0 3px rgba(0,0,0,0.1) !important;
    }
</style>

<!-- SCRIPT PARA PREVISUALIZACIÓN DE TEMAS EN TIEMPO REAL -->
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Seleccionamos todos los círculos de colores (radios)
        const themeRadios = document.querySelectorAll('.theme-radio');
        
        // Lista de todos los temas posibles para poder borrarlos antes de aplicar uno nuevo
        const themeClasses = [
            'theme-midnight', 
            'theme-ocean', 
            'theme-forest', 
            'theme-sunset', 
            'theme-royal'
        ];

        themeRadios.forEach(radio => {
            radio.addEventListener('change', (e) => {
                // 1. Borramos cualquier tema que el body tenga actualmente
                document.body.classList.remove(...themeClasses);
                
                // 2. Le agregamos al body la clase del tema que acabas de clickear
                document.body.classList.add(e.target.value);
            });
        });
    });
</script>