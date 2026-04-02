<?php
// Asumimos que el controlador nos pasará la lista de plantas
$plantas = $plantas ?? []; 
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>
<div class="container-fluid p-4" id="configCostosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-fill me-2 text-secondary"></i> Configuración de Costos de Planta
            </h1>
            <p class="text-muted small mb-0 ms-1">Define las tarifas estándar de Mano de Obra (MOD) y Gastos (CIF) por hora de máquina.</p>
        </div>
        <a class="btn btn-outline-primary shadow-sm fw-semibold" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
            <i class="bi bi-graph-up-arrow me-2"></i>Ir a análisis de costos
        </a>
    </div>

    <?php if ($flash['texto'] !== ''): ?>
        <div class="alert alert-<?php echo $flash['tipo'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-<?php echo $flash['tipo'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>-fill me-2"></i>
            <?php echo e($flash['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        
        <div class="col-12 col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-building-gear me-2 text-primary"></i>Tarifas Estándar por Centro de Trabajo</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Planta / Centro de Costo</th>
                                    <th class="text-end">Tarifa MOD (S/ x Hora)</th>
                                    <th class="text-end">Tarifa CIF (S/ x Hora)</th>
                                    <th class="text-center pe-4">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($plantas)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="bi bi-inbox fs-2 d-block mb-2"></i> No hay Almacenes de tipo "Planta" configurados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach($plantas as $p): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-dark">
                                            <i class="bi bi-building me-2 text-secondary"></i><?php echo e((string)$p['nombre']); ?>
                                        </td>
                                        <td class="text-end fw-bold text-primary">
                                            S/ <?php echo number_format((float)($p['tarifa_mod_hora'] ?? 0), 2); ?>
                                        </td>
                                        <td class="text-end fw-bold text-warning">
                                            S/ <?php echo number_format((float)($p['tarifa_cif_hora'] ?? 0), 2); ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <button class="btn btn-sm btn-outline-dark shadow-sm" onclick="abrirModalTarifa(<?php echo (int)$p['id']; ?>, '<?php echo e((string)$p['nombre']); ?>', <?php echo (float)($p['tarifa_mod_hora'] ?? 0); ?>, <?php echo (float)($p['tarifa_cif_hora'] ?? 0); ?>)">
                                                <i class="bi bi-pencil-square"></i> Editar
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-4">
            <div class="card border border-info-subtle shadow-sm h-100 bg-info-subtle bg-opacity-10">
                <div class="card-header bg-transparent border-bottom-0 px-4 py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calculator-fill me-2 text-info"></i>Calculadora de Tasas</h6>
                </div>
                <div class="card-body px-4 pt-0">
                    <p class="small text-muted mb-3">Si no conoces tu tarifa por hora, ingresa tus gastos mensuales para obtener un estimado.</p>
                    
                    <div class="bg-white p-3 rounded border shadow-sm mb-4">
                        <label class="small fw-bold text-secondary mb-1">1. Gasto Total del Mes (Planilla o Luz)</label>
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text bg-light">S/</span>
                            <input type="number" id="calcGasto" class="form-control shadow-none" placeholder="Ej: 10000">
                        </div>
                        
                        <label class="small fw-bold text-secondary mb-1">2. Horas Productivas del Mes</label>
                        <div class="input-group input-group-sm mb-3">
                            <input type="number" id="calcHoras" class="form-control shadow-none" placeholder="Ej: 800 (5 operarios x 160h)">
                            <span class="input-group-text bg-light">Horas</span>
                        </div>
                        
                        <button class="btn btn-sm btn-dark w-100 fw-bold" onclick="calcularSugerencia()">Calcular Tarifa</button>
                    </div>

                    <div class="text-center bg-white border rounded p-3 shadow-sm">
                        <span class="d-block small text-muted text-uppercase fw-bold mb-1">Tarifa Sugerida</span>
                        <h2 class="fw-bold text-success mb-0" id="resCalc">S/ 0.00</h2>
                        <small class="text-muted">Por cada hora de máquina</small>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>

<div class="modal fade" id="modalEditarTarifa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Actualizar Tarifas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="formTarifas">
                <input type="hidden" name="accion" value="actualizar_tarifas_planta">
                <input type="hidden" name="id_planta" id="modalIdPlanta">
                
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-light border shadow-sm mb-4">
                        <span class="small text-muted d-block fw-bold text-uppercase">Centro de Trabajo</span>
                        <h6 class="fw-bold text-primary mb-0 mt-1" id="modalNombrePlanta">Nombre Planta</h6>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Tarifa MOD (Mano de Obra) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">S/</span>
                            <input type="number" step="0.01" min="0" name="tarifa_mod" id="modalTarifaMod" class="form-control shadow-none fw-bold text-primary" required>
                            <span class="input-group-text bg-light text-muted small">x Hora</span>
                        </div>
                        <div class="form-text small">Costo laboral por cada hora que la máquina esté encendida.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark small">Tarifa CIF (Costos Indirectos) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-muted">S/</span>
                            <input type="number" step="0.01" min="0" name="tarifa_cif" id="modalTarifaCif" class="form-control shadow-none fw-bold text-warning" required>
                            <span class="input-group-text bg-light text-muted small">x Hora</span>
                        </div>
                        <div class="form-text small">Gastos de luz, agua y depreciación por hora de uso.</div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top shadow-sm">
                    <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-save me-2"></i>Guardar Tarifas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function calcularSugerencia() {
        const gasto = parseFloat(document.getElementById('calcGasto').value) || 0;
        const horas = parseFloat(document.getElementById('calcHoras').value) || 0;
        const res = document.getElementById('resCalc');
        
        if (horas > 0 && gasto > 0) {
            res.textContent = 'S/ ' + (gasto / horas).toFixed(2);
            res.classList.replace('text-muted', 'text-success');
        } else {
            res.textContent = 'S/ 0.00';
        }
    }

    function abrirModalTarifa(id, nombre, mod, cif) {
        document.getElementById('modalIdPlanta').value = id;
        document.getElementById('modalNombrePlanta').textContent = nombre;
        document.getElementById('modalTarifaMod').value = mod.toFixed(2);
        document.getElementById('modalTarifaCif').value = cif.toFixed(2);
        
        new bootstrap.Modal(document.getElementById('modalEditarTarifa')).show();
    }
</script>