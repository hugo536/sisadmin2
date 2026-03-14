<?php
$registros = $registros ?? [];
$centrosCosto = $centrosCosto ?? [];
$codigoSugerido = $codigoSugerido ?? '';
$filtros = $filtros ?? [];
?>
<div class="container-fluid p-4" id="gastosConceptosApp">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-tags-fill text-primary me-2"></i>Conceptos de Gasto</h4>
            <small class="text-muted">Catálogo maestro de gastos.</small>
        </div>
        <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" data-bs-toggle="modal" data-bs-target="#modalNuevoConcepto">
            <i class="bi bi-plus-circle me-1"></i>Nuevo Concepto
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            
            <div class="row g-2 p-3 pb-0 mb-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input id="buscarConcepto" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" placeholder="Buscar código o nombre...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filtroCentroCosto" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Centros de Costo</option>
                        <?php foreach($centrosCosto as $cc): ?>
                            <option value="<?php echo e($cc['codigo']); ?>"><?php echo e($cc['codigo'].' - '.$cc['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filtroRecurrente" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los tipos</option>
                        <option value="1">Solo Recurrentes</option>
                        <option value="0">No Recurrentes</option>
                    </select>
                </div>
            </div>
            
            <div class="table-responsive px-3">
                <table id="conceptosTable" class="table table-hover align-middle table-pro mb-0" 
                       data-erp-table="true" 
                       data-search-input="#buscarConcepto" 
                       data-erp-filters='[{"el":"#filtroCentroCosto", "attr":"data-centro"}, {"el":"#filtroRecurrente", "attr":"data-recurrente"}]'
                       data-rows-per-page="15">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Concepto</th>
                            <th class="text-secondary fw-semibold">Centro de costo</th>
                            <th class="text-center text-secondary fw-semibold">Recurrente</th>
                            <th class="text-secondary fw-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $r): ?>
                        <?php 
                            $sinCuenta = (int)($r['id_cuenta_contable'] ?? 0) <= 0; 
                            // El texto de búsqueda ahora solo necesita el código y el nombre
                            $textoBusqueda = strtolower($r['codigo'] . ' ' . $r['nombre']);
                        ?>
                        <tr class="border-bottom" 
                            data-search="<?php echo e($textoBusqueda); ?>"
                            data-centro="<?php echo e((string)($r['centro_costo_codigo'] ?? '')); ?>"
                            data-recurrente="<?php echo (int)$r['es_recurrente']; ?>">
                            
                            <td class="ps-3 fw-semibold text-primary"><?php echo e((string)$r['codigo']); ?></td>
                            <td class="fw-medium text-dark">
                                <?php echo e((string)$r['nombre']); ?>
                                <?php if ($sinCuenta): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1" data-bs-toggle="tooltip" title="No vinculado a una cuenta contable. Vincúlelo en Contabilidad > Configurar Parámetros."></i>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo e((string)($r['centro_costo_codigo'] . ' - ' . $r['centro_costo_nombre'])); ?></td>
                            <td class="text-center">
                                <?php if ((int)$r['es_recurrente'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">No</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ((int)$r['es_recurrente'] === 1): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Alerta <?php echo (int)($r['dias_anticipacion'] ?? 0); ?> días antes</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border">Sin recordatorio</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($registros)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-tags fs-1 d-block mb-2 text-light"></i>
                                No hay conceptos registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                <span id="conceptosPaginationInfo" class="text-muted small">Calculando resultados...</span>
                <nav>
                    <ul id="conceptosPaginationControls" class="pagination pagination-sm mb-0 shadow-sm">
                        </ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoConcepto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?php echo e(route_url('gastos/guardar_concepto')); ?>" class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Concepto de Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                <div class="card modal-pastel-card mb-0">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Código</label>
                                <input readonly class="form-control bg-light border-secondary-subtle shadow-none fw-bold text-secondary" name="codigo" value="<?php echo e($codigoSugerido); ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Nombre del Concepto <span class="text-danger">*</span></label>
                                <input required class="form-control shadow-none border-secondary-subtle fw-medium" name="nombre" placeholder="Ej: Útiles de Oficina">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Centro de Costo <span class="text-danger">*</span></label>
                                <select id="id_centro_costo" required class="form-select shadow-none border-secondary-subtle" name="id_centro_costo">
                                    <option value="" selected disabled hidden>Seleccionar...</option>
                                    <?php foreach($centrosCosto as $cc): ?>
                                        <option value="<?php echo (int)$cc['id']; ?>"><?php echo e($cc['codigo'].' - '.$cc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 mt-4 border-top pt-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input mt-1" type="checkbox" name="es_recurrente" id="esRecurrente">
                                    <label class="form-check-label fw-medium text-dark small" for="esRecurrente">Gasto Recurrente / Recordatorio</label>
                                </div>
                            </div>
                            
                            <div class="col-12 d-none" id="bloqueRecurrente">
                                <div class="row g-2 p-3 border border-warning-subtle rounded-3 bg-warning-subtle bg-opacity-10">
                                    <div class="col-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Día de vencimiento</label>
                                        <input type="number" min="1" max="31" class="form-control form-control-sm shadow-none border-secondary-subtle text-center" name="dia_vencimiento" placeholder="Ej: 15">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-muted fw-semibold mb-1">Días anticipación</label>
                                        <input type="number" min="0" max="60" class="form-control form-control-sm shadow-none border-secondary-subtle text-center" name="dias_anticipacion" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Concepto</button>
            </div>
        </form>
    </div>
</div>