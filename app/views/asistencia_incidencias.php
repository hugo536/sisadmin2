<?php
$empleados = $empleados ?? [];
$incidencias = $incidencias ?? [];
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-clipboard2-pulse me-2 text-primary"></i> Módulo de Incidencias
            </h1>
            <p class="text-muted small mb-0 ms-1">Registro y control de vacaciones, descansos médicos y permisos.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard RRHH
            </a>
            <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-fingerprint me-2 text-info"></i>Importar Logs
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="bi bi-plus-circle-fill me-2 text-primary"></i>Registrar Nueva Incidencia
            </h6>
            <form method="post" action="<?php echo e(route_url('asistencia/incidencias')); ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
                <input type="hidden" name="accion" value="guardar">
                
                <div class="col-md-4">
                    <label class="form-label small text-muted fw-bold">Empleado <span class="text-danger">*</span></label>
                    <select class="form-select bg-light border-secondary-subtle" name="id_tercero" required>
                        <option value="">Seleccione un empleado...</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?php echo (int) ($emp['id'] ?? 0); ?>">
                                <?php echo e((string) ($emp['nombre_completo'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-bold">Tipo de Incidencia <span class="text-danger">*</span></label>
                    <select class="form-select bg-light border-secondary-subtle" name="tipo_incidencia" required>
                        <option value="VACACIONES">Vacaciones</option>
                        <option value="DESCANSO_MEDICO">Descanso Médico</option>
                        <option value="PERMISO_PERSONAL">Permiso Personal</option>
                        <option value="SUBSIDIO">Subsidio</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Fecha Inicio <span class="text-danger">*</span></label>
                    <input type="date" class="form-control bg-light border-secondary-subtle text-secondary fw-medium" name="fecha_inicio" required>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label small text-muted fw-bold">Fecha Fin <span class="text-danger">*</span></label>
                    <input type="date" class="form-control bg-light border-secondary-subtle text-secondary fw-medium" name="fecha_fin" required>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label small text-muted fw-bold">Goce</label>
                    <select class="form-select bg-light border-secondary-subtle" name="con_goce_sueldo">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
                
                <div class="col-md-5 mt-3">
                    <label class="form-label small text-muted fw-bold">Documento de Respaldo (Opcional)</label>
                    <input type="file" class="form-control bg-light border-secondary-subtle" name="documento_respaldo" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text mt-1" style="font-size: 0.7rem;">Soporta PDF, JPG o PNG. Tamaño máximo recomendado: 2MB.</div>
                </div>
                
                <div class="col-md-7 mt-3 text-end">
                    <button class="btn btn-primary shadow-sm fw-semibold px-4" type="submit">
                        <i class="bi bi-save me-2"></i>Guardar Incidencia
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <h2 class="h6 fw-bold text-dark mb-0">Historial de Incidencias Registradas</h2>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchIncidencias" placeholder="Buscar empleado o tipo...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaIncidencias"
                       data-erp-table="true"
                       data-search-input="#searchIncidencias"
                       data-pagination-controls="#incidenciasPaginationControls"
                       data-pagination-info="#incidenciasPaginationInfo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                            <th class="text-secondary fw-semibold">Tipo</th>
                            <th class="text-secondary fw-semibold">Periodo</th>
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
                                    $badgeTipo = 'bg-secondary';
                                    if ($tipo === 'VACACIONES') $badgeTipo = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                    if ($tipo === 'DESCANSO_MEDICO') $badgeTipo = 'bg-danger-subtle text-danger border border-danger-subtle';
                                    if ($tipo === 'PERMISO_PERSONAL') $badgeTipo = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                    if ($tipo === 'SUBSIDIO') $badgeTipo = 'bg-primary-subtle text-primary border border-primary-subtle';
                                    
                                    $tieneGoce = ((int) ($row['con_goce_sueldo'] ?? 1) === 1);
                                    
                                    // Cadena para el buscador JS
                                    $searchStr = strtolower(($row['empleado'] ?? '') . ' ' . $tipo);
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 fw-semibold text-dark align-top pt-3">
                                        <?php echo e((string) ($row['empleado'] ?? '')); ?>
                                    </td>
                                    <td class="align-top pt-3">
                                        <span class="badge rounded-pill <?php echo $badgeTipo; ?> px-2 py-1" style="font-size: 0.75rem;">
                                            <?php echo e(str_replace('_', ' ', $tipo)); ?>
                                        </span>
                                    </td>
                                    <td class="align-top pt-3 fw-medium text-secondary">
                                        <i class="bi bi-calendar3 small text-muted me-1"></i>
                                        <?php echo e((string) ($row['fecha_inicio'] ?? '')); ?> <br>
                                        <span class="text-muted ms-3">al <?php echo e((string) ($row['fecha_fin'] ?? '')); ?></span>
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
                                            <a href="<?php echo e(base_url() . '/' . ltrim((string) $row['documento_respaldo'], '/')); ?>" target="_blank" class="btn btn-sm btn-light text-primary border-0 rounded-circle" data-bs-toggle="tooltip" title="Ver Documento">
                                                <i class="bi bi-file-earmark-text fs-5"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 align-top pt-3">
                                        <form method="post" action="<?php echo e(route_url('asistencia/incidencias')); ?>" onsubmit="return confirm('¿Estás seguro de eliminar esta incidencia? Esto recalculará la asistencia.');" class="d-inline">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent rounded-circle" data-bs-toggle="tooltip" title="Eliminar Incidencia">
                                                <i class="bi bi-trash fs-5"></i>
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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="incidenciasPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="incidenciasPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>