<?php
$cierres = $cierres ?? [];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];

function colorVariacion($monto) {
    if ($monto > 0) return 'text-success fw-bold'; // Eficiencia (Ganancia)
    if ($monto < 0) return 'text-danger fw-bold';  // Ineficiencia (Pérdida)
    return 'text-muted fw-bold';
}
?>
<div class="container-fluid p-4" id="cierresCostosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-lock-fill me-2 text-secondary"></i> Cierres de Costos
            </h1>
            <p class="text-muted small mb-0 ms-1">Congela el periodo y compara el costo absorbido vs el real pagado.</p>
        </div>
        <div class="d-flex gap-2">
            <a class="btn btn-outline-secondary shadow-sm fw-semibold" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
                <i class="bi bi-arrow-left me-2"></i>Volver a costos
            </a>
            <button class="btn btn-primary shadow-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#modalNuevoCierre">
                <i class="bi bi-plus-circle me-2"></i>Ejecutar Cierre Mensual
            </button>
        </div>
    </div>

    <?php if ($flash['texto'] !== ''): ?>
        <div class="alert alert-<?php echo $flash['tipo'] === 'error' ? 'danger' : 'success'; ?> alert-dismissible fade show shadow-sm" role="alert">
            <i class="bi bi-<?php echo $flash['tipo'] === 'error' ? 'exclamation-triangle' : 'check-circle'; ?>-fill me-2"></i>
            <?php echo e($flash['texto']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-range me-2 text-primary"></i>Historial de Cierres</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0 shadow-none" id="filtroCierres" placeholder="Buscar periodo...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 table-pro" id="tablaCierres"
                       data-erp-table="true"
                       data-search-input="#filtroCierres"
                       data-rows-per-page="12">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Periodo</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end text-secondary fw-semibold">Variación MOD</th>
                            <th class="text-end text-secondary fw-semibold">Variación CIF</th>
                            <th class="text-secondary fw-semibold ps-4">Usuario</th>
                            <th class="pe-4 text-secondary fw-semibold">Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($cierres)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay periodos cerrados aún.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach($cierres as $c): ?>
                                <tr class="border-bottom" data-search="<?php echo e($c['periodo']); ?>">
                                    <td class="ps-4 fw-bold text-dark">
                                        <i class="bi bi-calendar-event me-2 text-primary"></i><?php echo e($c['periodo']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle">
                                            <i class="bi bi-lock-fill me-1"></i> Cerrado
                                        </span>
                                    </td>
                                    <td class="text-end <?php echo colorVariacion($c['mod_variacion']); ?>">
                                        S/ <?php echo number_format((float)$c['mod_variacion'], 2); ?>
                                    </td>
                                    <td class="text-end <?php echo colorVariacion($c['cif_variacion']); ?>">
                                        S/ <?php echo number_format((float)$c['cif_variacion'], 2); ?>
                                    </td>
                                    <td class="ps-4 text-muted small">
                                        <?php echo e((string)($c['usuario_nombre'] ?? 'Sistema')); ?><br>
                                        <span class="opacity-50"><?php echo date('d/m/Y H:i', strtotime($c['created_at'])); ?></span>
                                    </td>
                                    <td class="pe-4 text-muted small text-truncate" style="max-width: 200px;" title="<?php echo e((string)$c['observaciones']); ?>">
                                        <?php echo e((string)$c['observaciones'] ?: '-'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
            <small class="text-muted fw-semibold" id="tablaCierresPaginationInfo">Cargando...</small>
            <nav aria-label="Navegación cierres">
                <ul class="pagination mb-0 justify-content-end" id="tablaCierresPaginationControls"></ul>
            </nav>
        </div>
    </div>
</div>

<!-- MODAL DE NUEVO CIERRE -->
<div class="modal fade" id="modalNuevoCierre" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-calculator me-2"></i>Ejecutar Cierre de Periodo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <form method="post" id="formCierre">
                    <input type="hidden" name="accion" value="guardar_cierre">
                    
                    <div class="row g-3 align-items-center mb-4 bg-white p-3 rounded shadow-sm border">
                        <div class="col-md-5">
                            <label class="form-label small fw-bold text-secondary mb-1">Seleccionar Periodo (Mes)</label>
                            <input type="month" name="periodo" id="cierrePeriodo" class="form-control form-control-lg fw-bold shadow-none border-primary" required value="<?php echo date('Y-m', strtotime('-1 month')); ?>">
                        </div>
                        <div class="col-md-7 text-end mt-4">
                            <button type="button" class="btn btn-outline-primary fw-bold px-4" id="btnAnalizarPeriodo">
                                <i class="bi bi-search me-2"></i>Analizar Datos del Mes
                            </button>
                        </div>
                    </div>

                    <!-- CAJA DE ANÁLISIS (Oculta por defecto) -->
                    <div id="cajaAnalisis" class="d-none">
                        <div class="alert alert-info border-info-subtle d-flex align-items-center p-3 shadow-sm">
                            <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Análisis completado</h6>
                                <p class="small text-muted mb-0">Se procesaron <strong id="lblTotalOrdenes" class="text-dark">0</strong> órdenes de producción terminadas en este mes. Ingresa lo que realmente pagaste de planilla y recibos para calcular tu eficiencia.</p>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <!-- MOD -->
                            <div class="col-md-6">
                                <div class="card border border-primary-subtle shadow-sm h-100">
                                    <div class="card-header bg-primary-subtle border-bottom-0 py-2">
                                        <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-people-fill me-2"></i>Mano de Obra (MOD)</h6>
                                    </div>
                                    <div class="card-body bg-white p-3">
                                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                                            <span class="small text-muted fw-bold">Absorbido (Cobrado al Producto)</span>
                                            <span class="fw-bold text-dark">S/ <span id="lblModAbs">0.00</span></span>
                                            <input type="hidden" name="mod_absorbida" id="inputModAbs" value="0">
                                        </div>
                                        <label class="small fw-bold text-secondary mb-1">Gasto Real en Planilla (S/)</label>
                                        <input type="number" step="0.01" min="0" name="mod_real" id="inputModReal" class="form-control shadow-none" placeholder="Ej: 15000" required>
                                        
                                        <div class="mt-3 text-center p-2 rounded bg-light border">
                                            <span class="small d-block text-muted fw-bold mb-1">Variación de Eficiencia</span>
                                            <h5 class="mb-0 fw-bold" id="lblModVar">S/ 0.00</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- CIF -->
                            <div class="col-md-6">
                                <div class="card border border-warning-subtle shadow-sm h-100">
                                    <div class="card-header bg-warning-subtle border-bottom-0 py-2">
                                        <h6 class="mb-0 fw-bold text-warning-emphasis"><i class="bi bi-lightning-charge-fill me-2"></i>Costos Indirectos (CIF)</h6>
                                    </div>
                                    <div class="card-body bg-white p-3">
                                        <div class="d-flex justify-content-between mb-3 border-bottom pb-2">
                                            <span class="small text-muted fw-bold">Absorbido (Cobrado al Producto)</span>
                                            <span class="fw-bold text-dark">S/ <span id="lblCifAbs">0.00</span></span>
                                            <input type="hidden" name="cif_absorbido" id="inputCifAbs" value="0">
                                        </div>
                                        <label class="small fw-bold text-secondary mb-1">Gasto Real Luz/Agua/Etc. (S/)</label>
                                        <input type="number" step="0.01" min="0" name="cif_real" id="inputCifReal" class="form-control shadow-none" placeholder="Ej: 5000" required>
                                        
                                        <div class="mt-3 text-center p-2 rounded bg-light border">
                                            <span class="small d-block text-muted fw-bold mb-1">Variación de Eficiencia</span>
                                            <h5 class="mb-0 fw-bold" id="lblCifVar">S/ 0.00</h5>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-secondary mb-1">Observaciones / Notas del Cierre</label>
                            <input type="text" name="observaciones" class="form-control shadow-none border-secondary-subtle" placeholder="Ej: Variación desfavorable por pago de horas extras...">
                        </div>

                        <div class="d-flex justify-content-end border-top pt-3">
                            <button type="button" class="btn btn-light border fw-semibold me-2" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-dark fw-bold px-4"><i class="bi bi-lock-fill me-2"></i>Congelar y Cerrar Periodo</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>