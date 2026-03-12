<?php
$config = $config ?? [];
$tipoCalculo = $config['tipo_calculo_horas_extras'] ?? 'EXACTO';
?>

<div class="container-fluid p-4 fade-in" id="politicasRRHHApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-sliders me-2 text-primary"></i> Políticas de Recursos Humanos
            </h1>
            <p class="text-muted small mb-0 ms-1">Motor de Reglas para el cálculo automático de asistencias y horas extra.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-warning"></i>Reglas de Tiempo (Clamping y Redondeo)</h5>
                </div>
                <div class="card-body p-4">
                    
                    <form action="<?php echo e(route_url('rrhh/config_rrhh/guardar')); ?>" method="POST" id="formConfigRRHH">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token ?? ''); ?>">

                        <div class="border border-secondary-subtle rounded-3 p-4 mb-4 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Pagar desde que marca (Llegadas tempranas)</h6>
                                    <p class="text-muted small mb-0">Si está apagado, el sistema pondrá un tope y no pagará minutos extras por llegar antes de su turno oficial.</p>
                                </div>
                                <div class="form-check form-switch pt-1 ms-3">
                                    <input class="form-check-input shadow-none" style="cursor: pointer; width: 2.5em; height: 1.25em;" type="checkbox" role="switch" name="pagar_llegada_temprano" id="checkTemprano" value="1" <?php echo !empty($config['pagar_llegada_temprano']) ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="alert alert-info border-info-subtle py-2 small mb-0 mt-3 d-flex align-items-center">
                                <i class="bi bi-info-circle-fill me-2 fs-5 text-info"></i>
                                <div>
                                    <strong>Ejemplo (Turno 08:00 AM):</strong> Si llega a las 07:45 AM, el sistema contará su inicio a las 
                                    <span id="txtEjemploTemprano" class="badge bg-primary ms-1"><?php echo !empty($config['pagar_llegada_temprano']) ? '07:45 AM' : '08:00 AM'; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="border border-secondary-subtle rounded-3 p-4 bg-light shadow-sm">
                            <div class="d-flex justify-content-between align-items-start mb-3 border-bottom pb-3">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Habilitar cálculo automático de Horas Extras (Salidas Tarde)</h6>
                                    <p class="text-muted small mb-0">Si está apagado, el sistema cortará el tiempo a su hora de salida oficial, ignorando cualquier exceso.</p>
                                </div>
                                <div class="form-check form-switch pt-1 ms-3">
                                    <input class="form-check-input shadow-none" style="cursor: pointer; width: 2.5em; height: 1.25em;" type="checkbox" role="switch" name="pagar_salida_tarde" id="checkTarde" value="1" <?php echo !empty($config['pagar_salida_tarde']) ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div id="cajaConfigExtras" class="<?php echo empty($config['pagar_salida_tarde']) ? 'opacity-50' : ''; ?>" style="transition: all 0.3s ease;">
                                
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark small mb-1">Tiempo de Gracia para Salidas (Tolerancia)</label>
                                    <div class="input-group shadow-sm w-100" style="max-width: 300px;">
                                        <input type="number" class="form-control shadow-none border-secondary-subtle fw-bold text-center" name="minutos_gracia_salida" value="<?php echo (int)($config['minutos_gracia_salida'] ?? 5); ?>" min="0" <?php echo empty($config['pagar_salida_tarde']) ? 'readonly' : ''; ?>>
                                        <span class="input-group-text bg-white text-muted border-secondary-subtle">minutos</span>
                                    </div>
                                    <div class="form-text text-muted mt-2" style="font-size: 0.75rem;"><i class="bi bi-clock me-1"></i>Ej. Si sale 5 min tarde recogiendo sus cosas, se ignora. Si pasa este tiempo, se activa el cálculo inferior.</div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label fw-bold text-dark small mb-2">Método de Cálculo para las Extras <span class="text-danger">*</span></label>
                                    <select name="tipo_calculo_horas_extras" id="selectTipoCalculo" class="form-select shadow-none border-secondary-subtle fw-medium" <?php echo empty($config['pagar_salida_tarde']) ? 'disabled' : ''; ?>>
                                        <option value="EXACTO" <?php echo $tipoCalculo === 'EXACTO' ? 'selected' : ''; ?>>Cálculo Exacto (Se paga por minuto real trabajado)</option>
                                        <option value="BLOQUES" <?php echo $tipoCalculo === 'BLOQUES' ? 'selected' : ''; ?>>Por Bloques Escalonados (Ej. Media hora, Una hora)</option>
                                    </select>
                                </div>

                                <div id="cajaBloques" class="<?php echo $tipoCalculo === 'EXACTO' ? 'd-none' : ''; ?>">
                                    <div class="row g-4 mt-2 bg-white p-4 rounded-3 border border-warning-subtle mx-0 shadow-sm">
                                        <div class="col-12 mb-1">
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 fs-6 rounded-pill">
                                                <i class="bi bi-diagram-3 me-2"></i> Lógica de Bloques Escalonados
                                            </span>
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-dark small mb-1">Umbral para pagar <span class="text-success">Media Hora (30m)</span></label>
                                            <div class="input-group shadow-sm">
                                                <input type="number" class="form-control shadow-none border-secondary-subtle fw-bold text-center text-primary" name="minutos_umbral_media_hora" id="umbralMedia" value="<?php echo (int)($config['minutos_umbral_media_hora'] ?? 15); ?>" min="1" <?php echo empty($config['pagar_salida_tarde']) ? 'readonly' : ''; ?>>
                                                <span class="input-group-text bg-light text-muted border-secondary-subtle">minutos</span>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold text-dark small mb-1">Umbral para pagar <span class="text-success">1 Hora Completa (60m)</span></label>
                                            <div class="input-group shadow-sm">
                                                <input type="number" class="form-control shadow-none border-secondary-subtle fw-bold text-center text-primary" name="minutos_umbral_hora_completa" id="umbralHora" value="<?php echo (int)($config['minutos_umbral_hora_completa'] ?? 45); ?>" min="1" <?php echo empty($config['pagar_salida_tarde']) ? 'readonly' : ''; ?>>
                                                <span class="input-group-text bg-light text-muted border-secondary-subtle">minutos</span>
                                            </div>
                                        </div>

                                        <div class="col-12 mt-3 pt-3 border-top">
                                            <div class="alert alert-light border border-secondary-subtle py-3 small mb-0 d-flex align-items-start shadow-sm" id="textoEjemploBloques">
                                                </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow-sm transition-hover" id="btnGuardarReglas">
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
                    <h4 class="fw-bold mb-3">Auditoría en Tiempo Real</h4>
                    <p class="text-white-50 mb-4" style="font-size: 0.95rem; line-height: 1.6;">
                        Cualquier cambio en estas políticas afectará <strong>inmediatamente</strong> a todas las Planillas de Nómina que se encuentren actualmente en estado "BORRADOR".
                    </p>
                    <hr class="border-white opacity-25 w-50 mx-auto my-0 mb-4">
                    <p class="small text-white-50 mb-0">
                        <i class="bi bi-info-circle me-1"></i> Las planillas que ya se encuentren "APROBADAS" o "PAGADAS" están protegidas criptográficamente y no sufrirán modificaciones históricas.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/rrhh/config_rrhh.js')); ?>"></script>