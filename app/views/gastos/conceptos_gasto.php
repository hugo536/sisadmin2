<?php
$registros = $registros ?? [];
$centrosCosto = $centrosCosto ?? [];
$codigoSugerido = $codigoSugerido ?? '';
$filtros = $filtros ?? [];
?>
<div class="container-fluid p-4" id="gastosConceptosApp">
    
    <!-- ========================================== -->
    <!-- ENCABEZADO Y BOTONES PRINCIPALES           -->
    <!-- ========================================== -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-tags-fill me-2 text-primary"></i> Conceptos de Gasto
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo maestro de gastos.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-md-end">
            <button class="btn btn-primary shadow-sm fw-bold px-3" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoConcepto">
                <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Concepto
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- BARRA DE FILTROS                           -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" id="buscarConcepto" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" placeholder="Buscar código o nombre...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select id="filtroCentroCosto" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Centros de Costo</option>
                        <?php foreach($centrosCosto as $cc): ?>
                            <option value="<?php echo e($cc['codigo']); ?>"><?php echo e($cc['codigo'].' - '.$cc['nombre']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select id="filtroRecurrente" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los tipos</option>
                        <option value="1">Solo Recurrentes</option>
                        <option value="0">No Recurrentes</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TABLA DE REGISTROS                         -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive inventario-table-wrapper">
                
                <table id="conceptosTable" class="table align-middle mb-0 table-pro table-hover" 
                       data-erp-table="true" 
                       data-rows-selector="#conceptosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#buscarConcepto" 
                       data-empty-text="No hay conceptos registrados."
                       data-info-text-template="Mostrando {start} a {end} de {total} conceptos"
                       data-erp-filters='[{"el":"#filtroCentroCosto", "attr":"data-centro"}, {"el":"#filtroRecurrente", "attr":"data-recurrente"}]'
                       data-rows-per-page="15"
                       data-pagination-controls="#conceptosPaginationControls"
                       data-pagination-info="#conceptosPaginationInfo">
                       
                    <thead class="inventario-sticky-thead bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Concepto</th>
                            <th class="text-secondary fw-semibold">Centro de costo</th>
                            <th class="text-center text-secondary fw-semibold">Recurrente</th>
                            <th class="text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="conceptosTableBody">
                    <?php foreach ($registros as $r): ?>
                        <?php 
                            $sinCuenta = (int)($r['id_cuenta_contable'] ?? 0) <= 0;
                            $textoBusqueda = strtolower($r['codigo'] . ' ' . $r['nombre']);
                            $tieneRelacion = (int)($r['total_relaciones'] ?? 0) > 0;
                            $estaActivo = (int)($r['estado'] ?? 1) === 1;
                        ?>
                        <tr class="border-bottom <?php echo $estaActivo ? '' : 'bg-light opacity-75'; ?>" 
                            data-search="<?php echo e($textoBusqueda); ?>"
                            data-centro="<?php echo e((string)($r['centro_costo_codigo'] ?? '')); ?>"
                            data-recurrente="<?php echo (int)$r['es_recurrente']; ?>">
                            
                            <!-- Código (Limpio y directo) -->
                            <td class="ps-4">
                                <div class="fw-bold text-primary"><?php echo e((string)$r['codigo']); ?></div>
                            </td>
                            
                            <td class="fw-medium text-dark">
                                <?php echo e((string)$r['nombre']); ?>
                                <?php if ($sinCuenta): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1" data-bs-toggle="tooltip" title="No vinculado a una cuenta contable. Vincúlelo en Contabilidad > Configurar Parámetros."></i>
                                <?php endif; ?>
                            </td>
                            
                            <td class="text-muted">
                                <?php echo e((string)($r['centro_costo_codigo'] . ' - ' . $r['centro_costo_nombre'])); ?>
                            </td>
                            
                            <td class="text-center">
                                <?php if ((int)$r['es_recurrente'] === 1): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 shadow-sm">Sí</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 shadow-sm">No</span>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <?php if ((int)$r['es_recurrente'] === 1): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 shadow-sm">Alerta <?php echo (int)($r['dias_anticipacion'] ?? 0); ?> días antes</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-secondary border px-2 py-1 shadow-sm">Sin recordatorio</span>
                                <?php endif; ?>
                            </td>
                            
                            <!-- Acciones (Adaptadas para AJAX sin formularios) -->
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                    
                                    <!-- Botón Toggle Estado -->
                                    <button type="button" 
                                            class="btn btn-link p-0 text-decoration-none shadow-none border-0 js-toggle-estado" 
                                            data-id="<?php echo (int)$r['id']; ?>"
                                            data-estado="<?php echo $estaActivo ? 0 : 1; ?>"
                                            title="<?php echo $estaActivo ? 'Desactivar' : 'Activar'; ?>">
                                        <?php if($estaActivo): ?>
                                            <i class="bi bi-toggle-on text-primary" style="font-size: 1.8rem; line-height: 1.5;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-toggle-off text-secondary opacity-50" style="font-size: 1.8rem; line-height: 1;"></i>
                                        <?php endif; ?>
                                    </button>

                                    <div class="vr bg-secondary opacity-25 mx-2" style="width: 2px; height: 22px;"></div>

                                    <!-- Botón Editar -->
                                    <button type="button"
                                            class="btn btn-sm btn-light text-secondary border-0 rounded-circle js-editar-concepto"
                                            title="Editar"
                                            data-id="<?php echo (int)$r['id']; ?>"
                                            data-codigo="<?php echo e((string)$r['codigo']); ?>"
                                            data-nombre="<?php echo e((string)$r['nombre']); ?>"
                                            data-id-centro="<?php echo (int)($r['id_centro_costo'] ?? 0); ?>"
                                            data-es-recurrente="<?php echo (int)($r['es_recurrente'] ?? 0); ?>"
                                            data-dia-vencimiento="<?php echo (int)($r['dia_vencimiento'] ?? 0); ?>"
                                            data-dias-anticipacion="<?php echo (int)($r['dias_anticipacion'] ?? 0); ?>"
                                            <?php echo $estaActivo ? '' : 'disabled'; ?>>
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>

                                    <!-- Botón Eliminar -->
                                    <button type="button"
                                            class="btn btn-sm btn-light border-0 rounded-circle js-eliminar-concepto <?php echo $tieneRelacion ? 'text-secondary opacity-50' : 'text-danger'; ?>"
                                            title="<?php echo $tieneRelacion ? 'No se puede eliminar: tiene datos relacionados' : 'Eliminar'; ?>"
                                            data-id="<?php echo (int)$r['id']; ?>"
                                            <?php echo $tieneRelacion ? 'disabled' : ''; ?>>
                                        <i class="bi bi-trash fs-5"></i>
                                    </button>
                                    
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if(empty($registros)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-tags fs-1 d-block mb-2 text-light"></i>
                                No hay conceptos registrados.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-0 px-4 py-3 border-top bg-white rounded-bottom">
                <div class="small text-muted fw-medium" id="conceptosPaginationInfo">Calculando resultados...</div>
                <nav aria-label="Paginación de conceptos">
                    <ul class="pagination pagination-sm mb-0 shadow-sm" id="conceptosPaginationControls"></ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: NUEVO CONCEPTO (Estilo Limpio)      -->
<!-- ========================================== -->
<div class="modal fade" id="modalNuevoConcepto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <!-- Añadido ID al formulario para el JS -->
        <form method="post" action="<?php echo e(route_url('gastos/guardar_concepto')); ?>" class="modal-content border-0 shadow-lg" id="formNuevoConcepto">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Concepto de Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <!-- Cambiado modal-pastel-card a card limpia -->
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Código</label>
                                <input readonly class="form-control bg-light border-secondary-subtle shadow-none fw-bold text-secondary" name="codigo" value="<?php echo e($codigoSugerido); ?>">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Nombre del Concepto <span class="text-danger">*</span></label>
                                <input required class="form-control shadow-none border-secondary-subtle fw-medium" name="nombre" placeholder="Ej: Útiles de Oficina">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Centro de Costo <span class="text-danger">*</span></label>
                                <select id="id_centro_costo" required class="form-select shadow-none border-secondary-subtle" name="id_centro_costo">
                                    <option value="" selected disabled hidden>Seleccionar...</option>
                                    <?php foreach($centrosCosto as $cc): ?>
                                        <option value="<?php echo (int)$cc['id']; ?>"><?php echo e($cc['codigo'].' - '.$cc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12 mt-4 border-top pt-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input border-success" type="checkbox" name="es_recurrente" id="esRecurrente" style="cursor: pointer; transform: scale(1.1);">
                                    <label class="form-check-label fw-bold text-success small ms-2" for="esRecurrente" style="cursor: pointer;">
                                        Gasto Recurrente / Recordatorio
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12 d-none fade-in" id="bloqueRecurrente">
                                <div class="row g-2 p-3 border border-success-subtle rounded-3 bg-success-subtle bg-opacity-10">
                                    <div class="col-6">
                                        <label class="form-label small text-success-emphasis fw-bold mb-1">Día vencimiento</label>
                                        <input type="number" min="1" max="31" class="form-control shadow-none border-success-subtle text-center text-success fw-bold" name="dia_vencimiento" placeholder="Ej: 15">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-success-emphasis fw-bold mb-1">Días anticipación</label>
                                        <input type="number" min="0" max="60" class="form-control shadow-none border-success-subtle text-center text-success fw-bold" name="dias_anticipacion" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 rounded-bottom">
                <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Concepto</button>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: EDITAR CONCEPTO (Estilo Limpio)     -->
<!-- ========================================== -->
<div class="modal fade" id="modalEditarConcepto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <!-- Añadido ID al formulario para el JS -->
        <form method="post" action="<?php echo e(route_url('gastos/actualizar_concepto')); ?>" class="modal-content border-0 shadow-lg" id="formEditarConcepto">
            <input type="hidden" name="id" id="editarConceptoId" value="">
            
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Concepto de Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <div class="card border-0 shadow-sm mb-0">
                    <div class="card-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Código</label>
                                <input readonly class="form-control bg-light border-secondary-subtle shadow-none fw-bold text-secondary" id="editarConceptoCodigo" value="">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Nombre del Concepto <span class="text-danger">*</span></label>
                                <input required class="form-control shadow-none border-secondary-subtle fw-medium" id="editarConceptoNombre" name="nombre">
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Centro de Costo <span class="text-danger">*</span></label>
                                <select id="editar_id_centro_costo" required class="form-select shadow-none border-secondary-subtle" name="id_centro_costo">
                                    <option value="" selected disabled hidden>Seleccionar...</option>
                                    <?php foreach($centrosCosto as $cc): ?>
                                        <option value="<?php echo (int)$cc['id']; ?>"><?php echo e($cc['codigo'].' - '.$cc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mt-4 border-top pt-3">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input border-success" type="checkbox" name="es_recurrente" id="editarEsRecurrente" style="cursor: pointer; transform: scale(1.1);">
                                    <label class="form-check-label fw-bold text-success small ms-2" for="editarEsRecurrente" style="cursor: pointer;">
                                        Gasto Recurrente / Recordatorio
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-12 d-none fade-in" id="editarBloqueRecurrente">
                                <div class="row g-2 p-3 border border-success-subtle rounded-3 bg-success-subtle bg-opacity-10">
                                    <div class="col-6">
                                        <label class="form-label small text-success-emphasis fw-bold mb-1">Día vencimiento</label>
                                        <input type="number" min="1" max="31" class="form-control shadow-none border-success-subtle text-center text-success fw-bold" name="dia_vencimiento" id="editarDiaVencimiento" placeholder="Ej: 15">
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label small text-success-emphasis fw-bold mb-1">Días anticipación</label>
                                        <input type="number" min="0" max="60" class="form-control shadow-none border-success-subtle text-center text-success fw-bold" name="dias_anticipacion" id="editarDiasAnticipacion" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 rounded-bottom">
                <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Actualizar</button>
            </div>
        </form>
    </div>
</div>