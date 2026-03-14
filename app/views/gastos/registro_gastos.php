<?php
$registros = $registros ?? [];
$proveedores = $proveedores ?? [];
$conceptos = $conceptos ?? [];
?>
<div class="container-fluid p-4" id="gastosRegistroApp">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Registro de Gastos</h4>
            <small class="text-muted">Registra gastos y genera CxP + asiento automático.</small>
        </div>
        <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
            <i class="bi bi-plus-circle me-1"></i>Nuevo Registro
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            
            <div class="row g-2 p-3 pb-0 mb-2">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input id="buscarRegistro" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" placeholder="Buscar por fecha, proveedor o concepto...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="filtroProveedor" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Proveedores</option>
                        <?php foreach($proveedores as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e((string)$p['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select id="filtroEstado" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Estados</option>
                        <option value="Pendiente">Pendiente</option>
                        <option value="Pagado">Pagado</option>
                        <option value="Anulado">Anulado</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive px-3">
                <table id="registrosTable" class="table table-hover align-middle table-pro mb-0" 
                       data-erp-table="true" 
                       data-search-input="#buscarRegistro" 
                       data-erp-filters='[{"el":"#filtroProveedor", "attr":"data-proveedor"}, {"el":"#filtroEstado", "attr":"data-estado"}]'
                       data-rows-per-page="15">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Proveedor</th>
                            <th class="text-secondary fw-semibold">Concepto</th>
                            <th class="text-secondary fw-semibold">Monto</th>
                            <th class="text-secondary fw-semibold">Impuesto</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($registros as $r): ?>
                        <?php $textoBusqueda = strtolower($r['fecha'] . ' ' . $r['proveedor'] . ' ' . $r['concepto']); ?>
                        
                        <tr class="border-bottom" 
                            data-search="<?php echo e($textoBusqueda); ?>"
                            data-proveedor="<?php echo (int)($r['id_proveedor'] ?? 0); ?>"
                            data-estado="<?php echo e((string)$r['estado']); ?>">
                            
                            <td class="ps-4 text-muted small"><i class="bi bi-calendar me-1 opacity-50"></i><?php echo e((string)$r['fecha']); ?></td>
                            <td class="fw-medium text-dark"><?php echo e((string)$r['proveedor']); ?></td>
                            <td class="text-muted"><?php echo e((string)$r['concepto']); ?></td>
                            <td class="text-muted">S/ <?php echo number_format((float)$r['monto'], 2); ?></td>
                            <td><span class="badge bg-light text-secondary border"><?php echo e((string)$r['impuesto_tipo']); ?></span></td>
                            <td class="text-end fw-bold text-primary">S/ <?php echo number_format((float)$r['total'], 2); ?></td>
                            <td class="text-center pe-4">
                                <?php 
                                    // Pequeña mejora visual para los estados
                                    $estado = (string)$r['estado'];
                                    $badgeClass = 'bg-info-subtle text-info-emphasis border-info-subtle';
                                    if(strtolower($estado) === 'pagado') $badgeClass = 'bg-success-subtle text-success border-success-subtle';
                                    if(strtolower($estado) === 'anulado') $badgeClass = 'bg-danger-subtle text-danger border-danger-subtle';
                                    if(strtolower($estado) === 'pendiente') $badgeClass = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?> border px-2 py-1 rounded-pill"><?php echo e($estado); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($registros)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                No hay gastos registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white rounded-bottom">
                <span id="registrosPaginationInfo" class="text-muted small">Calculando resultados...</span>
                <nav>
                    <ul id="registrosPaginationControls" class="pagination pagination-sm mb-0 shadow-sm">
                        </ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?php echo e(route_url('gastos/guardar_registro')); ?>" class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Registro de Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                <div class="card modal-pastel-card mb-0">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-semibold mb-1">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control shadow-none border-secondary-subtle" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-semibold mb-1">Impuestos</label>
                                <select class="form-select shadow-none border-secondary-subtle" name="impuesto_tipo">
                                    <option value="NINGUNO">Sin impuesto</option>
                                    <option value="IGV">IGV / IVA</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Proveedor <span class="text-danger">*</span></label>
                                <select id="id_proveedor" class="form-select shadow-none border-secondary-subtle" name="id_proveedor" required>
                                    <option value="" selected disabled hidden>Seleccione proveedor...</option>
                                    <?php foreach($proveedores as $p): ?>
                                        <option value="<?php echo (int)$p['id']; ?>"><?php echo e((string)$p['nombre_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Concepto <span class="text-danger">*</span></label>
                                <select id="idConceptoGasto" class="form-select shadow-none border-secondary-subtle" name="id_concepto" required placeholder="Buscar concepto...">
                                    <option value="">Buscar concepto...</option>
                                    <?php foreach($conceptos as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo e((string)$c['codigo'].' - '.$c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mt-4 pt-3 border-top">
                                <label class="form-label small text-muted fw-semibold mb-1">Monto Total <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted fw-bold border-secondary-subtle">S/</span>
                                    <input type="number" step="0.01" min="0.01" class="form-control shadow-none border-secondary-subtle text-primary fw-bold fs-5" name="monto" placeholder="0.00" required>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Gasto</button>
            </div>
        </form>
    </div>
</div>