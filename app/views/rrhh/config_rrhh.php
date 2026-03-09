<?php
$config = $config ?? [];
?>

<div class="container-fluid p-4 fade-in">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-sliders me-2 text-primary"></i> Políticas de Recursos Humanos
            </h1>
            <p class="text-muted small mb-0 ms-1">Motor de Reglas para el cálculo automático de asistencias y horas extra.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom pt-4 pb-3 px-4">
                    <h5 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history me-2 text-warning"></i>Topes de Tiempo (Clamping)</h5>
                </div>
                <div class="card-body p-4">
                    
                    <form action="<?php echo e(route_url('rrhh/config_rrhh/guardar')); ?>" method="POST">
                        
                        <div class="border rounded-3 p-4 mb-4 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Pagar desde que marca (Llegadas tempranas)</h6>
                                    <p class="text-muted small mb-0">Si está apagado, el sistema pondrá un tope y no pagará minutos extras por llegar antes de su turno oficial.</p>
                                </div>
                                <div class="form-check form-switch fs-4">
                                    <input class="form-check-input" type="checkbox" role="switch" name="pagar_llegada_temprano" id="checkTemprano" value="1" <?php echo !empty($config['pagar_llegada_temprano']) ? 'checked' : ''; ?>>
                                </div>
                            </div>
                            <div class="alert alert-info border-0 py-2 small mb-0 mt-3 d-flex align-items-center">
                                <i class="bi bi-info-circle-fill me-2 fs-5"></i>
                                <div>
                                    <strong>Ejemplo (Turno 08:00 AM):</strong> Si llega a las 07:45 AM, el sistema contará su inicio a las 
                                    <span id="txtEjemploTemprano" class="badge bg-primary ms-1"><?php echo !empty($config['pagar_llegada_temprano']) ? '07:45 AM' : '08:00 AM'; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="border rounded-3 p-4 mb-4 bg-light">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Habilitar cálculo automático de Horas Extras (Salidas Tarde)</h6>
                                    <p class="text-muted small mb-0">Si está apagado, el sistema cortará el tiempo del empleado a su hora de salida oficial, ignorando cualquier exceso.</p>
                                </div>
                                <div class="form-check form-switch fs-4">
                                    <input class="form-check-input" type="checkbox" role="switch" name="pagar_salida_tarde" id="checkTarde" value="1" <?php echo !empty($config['pagar_salida_tarde']) ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div id="cajaConfigExtras" class="<?php echo empty($config['pagar_salida_tarde']) ? 'opacity-50' : ''; ?>" style="transition: all 0.3s ease;">
                                <div class="row g-4">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark small mb-1">Mínimo para considerar Hora Extra</label>
                                        <div class="input-group input-group-sm shadow-sm">
                                            <input type="number" class="form-control" name="minutos_minimos_extra" id="minExtras" value="<?php echo (int)($config['minutos_minimos_extra'] ?? 30); ?>" min="1" <?php echo empty($config['pagar_salida_tarde']) ? 'readonly' : ''; ?>>
                                            <span class="input-group-text bg-white text-muted">minutos</span>
                                        </div>
                                        <div class="form-text" style="font-size: 0.70rem;">Si se queda menos de este tiempo, el sistema lo descartará como "demora normal".</div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold text-dark small mb-1">Tiempo de Gracia (Tolerancia Salida)</label>
                                        <div class="input-group input-group-sm shadow-sm">
                                            <input type="number" class="form-control" name="minutos_gracia_salida" value="<?php echo (int)($config['minutos_gracia_salida'] ?? 15); ?>" min="0" <?php echo empty($config['pagar_salida_tarde']) ? 'readonly' : ''; ?>>
                                            <span class="input-group-text bg-white text-muted">minutos</span>
                                        </div>
                                        <div class="form-text" style="font-size: 0.70rem;">Minutos que se le permite quedarse ordenando sus cosas sin generar costo.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" id="btnGuardarReglas">
                                <i class="bi bi-save me-2"></i>Guardar Políticas
                            </button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
        
        <div class="col-12 col-xl-4 mt-4 mt-xl-0">
            <div class="card border-0 shadow-sm bg-primary text-white h-100">
                <div class="card-body p-4 text-center d-flex flex-column justify-content-center">
                    <i class="bi bi-shield-check opacity-50 mb-3" style="font-size: 4rem;"></i>
                    <h5 class="fw-bold mb-3">Auditoría en Tiempo Real</h5>
                    <p class="small text-white-50 mb-4">Cualquier cambio en estas políticas afectará inmediatamente a todas las Planillas de Nómina que se encuentren en estado "BORRADOR".</p>
                    <p class="small text-white-50 mb-0">Las planillas ya "APROBADAS" o "PAGADAS" están protegidas y no sufrirán modificaciones históricas.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/rrhh/config_rrhh.js"></script>