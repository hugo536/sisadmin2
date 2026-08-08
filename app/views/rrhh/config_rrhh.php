<?php
$config = $config ?? [];
?>

<div class="container-fluid p-4 fade-in" id="politicasRRHHApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-sliders me-2 text-primary"></i> Políticas de Recursos Humanos
            </h1>
            <p class="text-muted small mb-0 ms-1">Configuración del redondeo dinámico y bolsa de horas.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-warning"></i>Reglas de Tiempo Efectivo</h5>
                </div>
                <div class="card-body p-4">
                    
                    <form action="<?php echo e(route_url('rrhh/config_rrhh/guardar')); ?>" method="POST" id="formConfigRRHH">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token ?? ''); ?>">

                        <!-- SECCIÓN 1: META DIARIA -->
                        <div class="border border-secondary-subtle rounded-3 p-4 mb-4 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Meta de Horas Diarias</h6>
                                    <p class="text-muted small mb-0">Define cuántas horas al día debe cumplir el trabajador antes de empezar a generar horas extras (sobretiempo).</p>
                                </div>
                            </div>
                            <div class="mt-3 border-top pt-3">
                                <div class="input-group shadow-sm w-100 mb-2" style="max-width: 300px;">
                                    <input type="number" step="0.5" class="form-control shadow-none border-secondary-subtle fw-bold text-center text-primary" name="meta_horas_diarias" value="<?php echo (float)($config['meta_horas_diarias'] ?? 8); ?>" min="1">
                                    <span class="input-group-text bg-white text-muted border-secondary-subtle">horas por día</span>
                                </div>
                            </div>
                        </div>

                        <!-- SECCIÓN 2: REDONDEO DINÁMICO -->
                        <div class="border border-secondary-subtle rounded-3 p-4 bg-light shadow-sm">
                            <div class="mb-3 border-bottom pb-3">
                                <h6 class="fw-bold mb-1 text-dark">Reglas de Redondeo en Marcaciones</h6>
                                <p class="text-muted small mb-0">El sistema ajustará la hora de entrada y salida real a bloques fijos de tiempo.</p>
                            </div>

                            <div class="row g-4 mt-2">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small mb-1">Tamaño del Bloque</label>
                                    <select name="bloque_minutos" class="form-select shadow-sm border-secondary-subtle fw-medium text-primary">
                                        <option value="15" <?php echo (($config['bloque_minutos'] ?? 30) == 15) ? 'selected' : ''; ?>>Cada 15 minutos</option>
                                        <option value="30" <?php echo (($config['bloque_minutos'] ?? 30) == 30) ? 'selected' : ''; ?>>Cada 30 minutos</option>
                                        <option value="60" <?php echo (($config['bloque_minutos'] ?? 30) == 60) ? 'selected' : ''; ?>>Cada hora completa</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark small mb-1">Umbral de Corte (Tolerancia)</label>
                                    <div class="input-group shadow-sm">
                                        <input type="number" class="form-control shadow-none border-secondary-subtle fw-bold text-center text-primary" name="minutos_tolerancia" value="<?php echo (int)($config['minutos_tolerancia'] ?? 14); ?>" min="0">
                                        <span class="input-group-text bg-light text-muted border-secondary-subtle">minutos</span>
                                    </div>
                                </div>

                                <div class="col-12 mt-4 pt-3 border-top">
                                    <div class="alert alert-info border-info-subtle py-3 small mb-0 d-flex align-items-start shadow-sm">
                                        <i class="bi bi-info-circle-fill me-2 fs-5 text-info mt-1"></i>
                                        <div>
                                            <strong>¿Cómo funcionará en la práctica?</strong><br>
                                            Con un bloque de <strong>30 min</strong> y un umbral de <strong>14 min</strong>:<br><br>
                                            <span class="text-success fw-bold"><i class="bi bi-box-arrow-in-right"></i> Entradas:</span><br>
                                            • Marca <span class="badge bg-secondary">08:14</span> ➔ Se redondea a su favor: <strong>08:00</strong>.<br>
                                            • Marca <span class="badge bg-secondary">08:16</span> ➔ Superó el umbral, el tiempo corre desde las: <strong>08:30</strong>.<br><br>
                                            <span class="text-danger fw-bold"><i class="bi bi-box-arrow-right"></i> Salidas:</span><br>
                                            • Marca <span class="badge bg-secondary">17:14</span> ➔ No completó el bloque, se corta en: <strong>17:00</strong>.<br>
                                            • Marca <span class="badge bg-secondary">17:45</span> ➔ Completó el primer bloque y pasó el umbral del segundo: <strong>17:30</strong>.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow-sm transition-hover">
                                <i class="bi bi-save me-2"></i>Guardar Políticas
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        
        <div class="col-12 col-xl-4 mt-4 mt-xl-0">
            <div class="card border-0 shadow-sm bg-primary text-white h-100 overflow-hidden position-relative">
                <div class="position-absolute top-0 end-0 bg-white opacity-10 rounded-circle" style="width: 150px; height: 150px; transform: translate(30%, -30%);"></div>
                <div class="position-absolute bottom-0 start-0 bg-white opacity-10 rounded-circle" style="width: 200px; height: 200px; transform: translate(-40%, 40%);"></div>
                
                <div class="card-body p-4 text-center d-flex flex-column justify-content-center position-relative z-1">
                    <i class="bi bi-shield-lock opacity-50 mb-3" style="font-size: 5rem;"></i>
                    <h4 class="fw-bold mb-3">Auditoría Dinámica</h4>
                    <p class="text-white-50 mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                        El cálculo de tiempo efectivo ignora catálogos de turnos predeterminados y evalúa únicamente la presencia real en el centro comercial.
                    </p>
                    <hr class="border-white opacity-25 w-50 mx-auto my-0 mb-4">
                    <p class="small text-white-50 mb-0">
                        <i class="bi bi-info-circle me-1"></i> Los cambios en estos parámetros recalcularán los valores en las planillas activas en modo "BORRADOR".
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Validaciones básicas en el frontend para evitar que el umbral sea mayor al bloque
document.getElementById('formConfigRRHH').addEventListener('submit', function(e) {
    const bloque = parseInt(document.querySelector('[name="bloque_minutos"]').value);
    const umbral = parseInt(document.querySelector('[name="minutos_tolerancia"]').value);
    
    if (umbral >= bloque) {
        e.preventDefault();
        alert('El umbral de corte debe ser estrictamente menor al tamaño del bloque.');
    }
});
</script>