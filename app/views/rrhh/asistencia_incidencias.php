<?php
$empleados = $empleados ?? [];
$incidencias = $incidencias ?? [];
?>

<div class="container-fluid p-4" id="moduloIncidenciasApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-clipboard2-pulse me-2 text-primary"></i> Módulo de Incidencias
            </h1>
            <p class="text-muted small mb-0 ms-1">Registro y control de vacaciones, descansos médicos y permisos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard
            </a>
            <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover">
                <i class="bi bi-fingerprint me-2 text-secondary"></i>Logs
            </a>
            <div class="vr mx-1 d-none d-sm-block"></div>
            <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearIncidencia">
                <i class="bi bi-plus-circle me-2"></i>Registrar Incidencia
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="searchIncidencias" placeholder="Buscar empleado o tipo de incidencia...">
                    </div>
                </div>
                <div class="col-12 col-md-7 text-md-end mt-2 mt-md-0">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-journal-text me-1"></i> <?php echo count($incidencias); ?> Incidencias registradas
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro table-hover" id="tablaIncidencias"
                       data-erp-table="true"
                       data-search-input="#searchIncidencias"
                       data-rows-selector="#incidenciasTableBody tr:not(.empty-msg-row)"
                       data-empty-text="No se encontraron incidencias"
                       data-info-text-template="Mostrando {start} a {end} de {total} incidencias"
                       data-pagination-controls="#incidenciasPaginationControls"
                       data-pagination-info="#incidenciasPaginationInfo"
                       data-rows-per-page="15">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                            <th class="text-secondary fw-semibold">Tipo de Incidencia</th>
                            <th class="text-secondary fw-semibold">Periodo Registrado</th>
                            <th class="text-center text-secondary fw-semibold">Goce de Sueldo</th>
                            <th class="text-center text-secondary fw-semibold">Respaldo</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="incidenciasTableBody">
                        <?php if (empty($incidencias)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No hay incidencias registradas en el sistema.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($incidencias as $row): ?>
                                <?php
                                    // Determinar color del badge según el tipo
                                    $tipo = (string) ($row['tipo_incidencia'] ?? '');
                                    $badgeTipo = 'bg-secondary-subtle text-secondary border-secondary-subtle';
                                    if ($tipo === 'VACACIONES') $badgeTipo = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                    if ($tipo === 'DESCANSO_MEDICO') $badgeTipo = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    if ($tipo === 'PERMISO_PERSONAL') $badgeTipo = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                    if ($tipo === 'SUBSIDIO') $badgeTipo = 'bg-primary-subtle text-primary border border-primary-subtle';
                                    
                                    $tieneGoce = ((int) ($row['con_goce_sueldo'] ?? 1) === 1);
                                    
                                    // Cadena para el buscador JS
                                    $searchStr = strtolower(($row['empleado'] ?? '') . ' ' . $tipo);
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 fw-bold text-dark align-top pt-3">
                                        <?php echo e((string) ($row['empleado'] ?? '')); ?>
                                    </td>
                                    <td class="align-top pt-3">
                                        <span class="badge rounded-pill <?php echo $badgeTipo; ?> px-3 py-1 shadow-sm" style="font-size: 0.75rem;">
                                            <?php echo e(str_replace('_', ' ', $tipo)); ?>
                                        </span>
                                    </td>
                                    <td class="align-top pt-3 fw-medium text-dark">
                                        <i class="bi bi-calendar3 small text-muted me-1"></i>
                                        <?php echo e((string) ($row['fecha_inicio'] ?? '')); ?> <br>
                                        <span class="text-muted small ms-3">al <?php echo e((string) ($row['fecha_fin'] ?? '')); ?></span>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <?php if($tieneGoce): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Sí</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <?php if (!empty($row['documento_respaldo'])): ?>
                                            <a href="<?php echo e(base_url() . '/' . ltrim((string) $row['documento_respaldo'], '/')); ?>" target="_blank" class="btn btn-sm btn-light text-primary border-0 rounded-circle shadow-sm transition-hover" data-bs-toggle="tooltip" title="Ver Documento">
                                                <i class="bi bi-file-earmark-text fs-5"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small opacity-50">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 align-top pt-3">
                                        <form method="post" action="<?php echo e(route_url('asistencia/incidencias')); ?>" onsubmit="return confirm('¿Estás seguro de eliminar esta incidencia? Esto recalculará la asistencia del empleado en este periodo.');" class="d-inline">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 rounded-circle shadow-sm transition-hover" data-bs-toggle="tooltip" title="Eliminar Incidencia">
                                                <i class="bi bi-trash fs-6"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($incidencias)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="incidenciasPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de incidencias">
                    <ul class="pagination mb-0 shadow-sm" id="incidenciasPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearIncidencia" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-clipboard2-pulse me-2"></i>Registrar Nueva Incidencia
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('asistencia/incidencias')); ?>" enctype="multipart/form-data" id="formIncidencia">
                    <input type="hidden" name="accion" value="guardar">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Datos del Evento</h6>
                            <div class="row g-3">
                                
                                <div class="col-md-7">
                                    <label class="form-label small text-muted fw-bold mb-1">Empleado <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-secondary-subtle" name="id_tercero" required>
                                        <option value="">Seleccione un empleado...</option>
                                        <?php foreach ($empleados as $emp): ?>
                                            <option value="<?php echo (int) ($emp['id'] ?? 0); ?>">
                                                <?php echo e((string) ($emp['nombre_completo'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-5">
                                    <label class="form-label small text-muted fw-bold mb-1">Tipo de Incidencia <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-secondary-subtle" name="tipo_incidencia" required>
                                        <option value="">Seleccione tipo...</option>
                                        <option value="VACACIONES">Vacaciones</option>
                                        <option value="DESCANSO_MEDICO">Descanso Médico</option>
                                        <option value="PERMISO_PERSONAL">Permiso Personal</option>
                                        <option value="SUBSIDIO">Subsidio / Maternidad</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold mb-1">Fecha Inicio <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control shadow-none border-secondary-subtle text-dark fw-medium" name="fecha_inicio" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold mb-1">Fecha Fin <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control shadow-none border-secondary-subtle text-dark fw-medium" name="fecha_fin" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold mb-1">Goce de Sueldo <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-secondary-subtle" name="con_goce_sueldo">
                                        <option value="1">Sí, pagado</option>
                                        <option value="0">No, sin goce</option>
                                    </select>
                                </div>

                                <div class="col-12 mt-4 pt-3 border-top">
                                    <label class="form-label small text-muted fw-bold mb-1"><i class="bi bi-paperclip me-1"></i>Documento de Respaldo <span class="text-muted fw-normal">(Opcional)</span></label>
                                    <input type="file" class="form-control shadow-none border-secondary-subtle" name="documento_respaldo" accept=".pdf,.jpg,.jpeg,.png">
                                    <div class="form-text small mt-1 text-secondary"><i class="bi bi-info-circle me-1"></i> Soporta PDF, JPG o PNG. Tamaño máximo recomendado: 2MB. Sube aquí certificados médicos o papeletas de vacaciones.</div>
                                </div>

                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary shadow-sm fw-bold px-5" type="submit" onclick="if(document.getElementById('formIncidencia').checkValidity()){ this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span> Guardando...'; this.classList.add('disabled'); }">
                            <i class="bi bi-save me-2"></i>Guardar Registro
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>