<?php
$horarios = $horarios ?? [];
$empleados = $empleados ?? [];
$asignaciones = $asignaciones ?? [];
$empleadosAgrupados = $empleadosAgrupados ?? [];
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
?>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in horario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar-week-fill me-2 text-primary"></i> Horarios y Asignaciones
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo de turnos y asignación de horarios por empleado/día.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard
            </a>
            <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-file-earmark-arrow-up me-2 text-info"></i>Importar TXT
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <h6 class="fw-bold text-dark mb-4 border-bottom pb-2">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Crear / Editar Turno
                    </h6>
                    <form method="post" action="<?php echo e(route_url('horario/index')); ?>" id="horarioForm">
                        <input type="hidden" name="accion" value="guardar_horario">
                        <input type="hidden" name="id" id="horarioId" value="0">

                        <div class="mb-3">
                            <label for="horarioNombre" class="form-label small text-muted fw-bold">Nombre del turno <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light border-secondary-subtle" name="nombre" id="horarioNombre" placeholder="Ej. Turno Mañana" maxlength="100" required>
                        </div>
                        
                        <div class="row g-3 mb-3">
                            <div class="col-6">
                                <label for="horarioEntrada" class="form-label small text-muted fw-bold">Entrada <span class="text-danger">*</span></label>
                                <input type="time" class="form-control bg-light border-secondary-subtle text-secondary fw-medium" name="hora_entrada" id="horarioEntrada" required>
                            </div>
                            <div class="col-6">
                                <label for="horarioSalida" class="form-label small text-muted fw-bold">Salida <span class="text-danger">*</span></label>
                                <input type="time" class="form-control bg-light border-secondary-subtle text-secondary fw-medium" name="hora_salida" id="horarioSalida" required>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <label for="horarioTolerancia" class="form-label small text-muted fw-bold">Tolerancia <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <input type="number" class="form-control bg-light border-secondary-subtle border-end-0" name="tolerancia_minutos" id="horarioTolerancia" min="0" step="1" value="0" required>
                                <span class="input-group-text bg-light border-secondary-subtle text-muted">min</span>
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-end gap-2 mt-2">
                            <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold" id="btnLimpiarHorario">Limpiar</button>
                            <button class="btn btn-primary shadow-sm fw-semibold" type="submit">
                                <i class="bi bi-save me-1"></i> Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Catálogo de Turnos Registrados</h6>
                    <div class="input-group shadow-sm" style="max-width: 250px;">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 form-control-sm" id="searchTurnos" placeholder="Buscar turno...">
                    </div>
                </div>
                <div class="card-body p-0 d-flex flex-column justify-content-between">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-pro" id="tablaTurnos"
                               data-erp-table="true"
                               data-rows-selector="#turnosTableBody tr:not(.empty-msg-row)"
                               data-search-input="#searchTurnos"
                               data-rows-per-page="10"
                               data-empty-text="No se encontraron turnos"
                               data-info-text-template="{start}-{end} de {total}"
                               data-pagination-controls="#turnosPaginationControls"
                               data-pagination-info="#turnosPaginationInfo">
                            <thead>
                                <tr>
                                    <th class="ps-4 text-secondary fw-semibold">Nombre del Turno</th>
                                    <th class="text-center text-secondary fw-semibold">Horario</th>
                                    <th class="text-center text-secondary fw-semibold">Tolerancia</th>
                                    <th class="text-center text-secondary fw-semibold">Estado</th>
                                    <th class="text-center text-secondary fw-semibold pe-4 col-w-100">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="turnosTableBody">
                                <?php if (empty($horarios)): ?>
                                    <tr class="empty-msg-row">
                                        <td colspan="5" class="text-center text-muted py-5 border-bottom-0">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                            No hay turnos registrados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($horarios as $horario): ?>
                                        <?php 
                                            $activo = ((int) $horario['estado'] === 1); 
                                            $searchStrTurno = strtolower($horario['nombre'] . ' ' . ($activo ? 'activo' : 'inactivo'));
                                        ?>
                                        <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStrTurno, ENT_QUOTES, 'UTF-8'); ?>">
                                            <td class="ps-4 fw-semibold text-dark">
                                                <?php echo e($horario['nombre']); ?>
                                            </td>
                                            <td class="text-center text-secondary fw-medium">
                                                <i class="bi bi-clock small text-muted me-1"></i>
                                                <?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?> - <?php echo e(substr((string) $horario['hora_salida'], 0, 5)); ?>
                                            </td>
                                            <td class="text-center text-muted">
                                                <?php echo (int) $horario['tolerancia_minutos']; ?> min
                                            </td>
                                            <td class="text-center">
                                                <?php if($activo): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center pe-4">
                                                <button type="button" class="btn btn-sm btn-light text-primary border-0 rounded-circle js-editar-horario" 
                                                    data-bs-toggle="tooltip" title="Editar Turno"
                                                    data-id="<?php echo (int) $horario['id']; ?>"
                                                    data-nombre="<?php echo e($horario['nombre']); ?>"
                                                    data-entrada="<?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?>"
                                                    data-salida="<?php echo e(substr((string) $horario['hora_salida'], 0, 5)); ?>"
                                                    data-tolerancia="<?php echo (int) $horario['tolerancia_minutos']; ?>">
                                                    <i class="bi bi-pencil fs-6"></i>
                                                </button>
                                                
                                                <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="d-inline">
                                                    <input type="hidden" name="accion" value="cambiar_estado_horario">
                                                    <input type="hidden" name="id" value="<?php echo (int) $horario['id']; ?>">
                                                    <input type="hidden" name="estado" value="<?php echo $activo ? 0 : 1; ?>">
                                                    <button type="submit" class="btn btn-sm btn-light <?php echo $activo ? 'text-warning' : 'text-success'; ?> border-0 rounded-circle" data-bs-toggle="tooltip" title="<?php echo $activo ? 'Desactivar' : 'Activar'; ?>">
                                                        <i class="bi <?php echo $activo ? 'bi-toggle-on' : 'bi-toggle-off'; ?> fs-5"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if (!empty($horarios)): ?>
                    <div class="d-flex justify-content-between align-items-center p-3 border-top bg-white mt-auto">
                        <div class="small text-muted fw-medium" id="turnosPaginationInfo">Procesando...</div>
                        <nav aria-label="Paginación de turnos">
                            <ul class="pagination mb-0 shadow-sm" id="turnosPaginationControls"></ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                <i class="bi bi-person-lines-fill me-2 text-primary"></i>Asignación Masiva de Horarios
            </h6>
            <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="row g-3 align-items-start" id="asignacionMasivaForm">
                <input type="hidden" name="accion" value="guardar_asignacion">
                <div class="col-md-12">
                    <div class="d-flex justify-content-between align-items-end mb-1">
                        <label class="form-label small text-muted fw-bold mb-0">1. Selecciona Empleado(s) <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold text-primary" id="btnSeleccionarTodosEmpleados" style="font-size: 0.75rem;"><i class="bi bi-check-all"></i> Seleccionar a todos</button>
                    </div>
                    <select id="empleadoSelect2" name="id_terceros[]" class="form-select bg-light border-secondary-subtle" multiple="multiple" required>
                        <?php foreach ($empleados as $empleado): ?>
                            <option value="<?php echo (int) $empleado['id']; ?>">
                                <?php echo e($empleado['nombre_completo']); ?> <?php echo !empty($empleado['codigo_biometrico']) ? ' (Cód: ' . e($empleado['codigo_biometrico']) . ')' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6 mt-3">
                    <div class="d-flex justify-content-between align-items-end mb-1">
                        <label class="form-label small text-muted fw-bold mb-0">2. Días de la Semana <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold" id="btnSeleccionarTodosDias" style="font-size: 0.75rem;">Marcar Lun-Dom</button>
                    </div>
                    <div class="btn-group w-100 shadow-sm" role="group">
                        <?php foreach ($diasCortos as $num => $dia): ?>
                            <input type="checkbox" class="btn-check dia-checkbox" name="dias[]" id="dia_<?php echo $num; ?>" value="<?php echo $num; ?>" autocomplete="off">
                            <label class="btn btn-outline-primary fw-semibold" for="dia_<?php echo $num; ?>"><?php echo e($dia); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-md-4 mt-3">
                    <label class="form-label small text-muted fw-bold mb-1">3. Turno a Asignar <span class="text-danger">*</span></label>
                    <select name="id_horario" class="form-select bg-light border-secondary-subtle shadow-sm" required>
                        <option value="">Seleccione turno...</option>
                        <?php foreach ($horarios as $horario): ?>
                            <?php if ((int) $horario['estado'] !== 1) continue; ?>
                            <option value="<?php echo (int) $horario['id']; ?>"><?php echo e($horario['nombre']); ?> (<?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?> - <?php echo e(substr((string) $horario['hora_salida'], 0, 5)); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 mt-3 d-flex align-items-end" style="height: 58px;">
                    <button class="btn btn-primary shadow-sm w-100 fw-semibold h-100" type="submit">
                        <i class="bi bi-calendar-check fs-5 me-1"></i> Aplicar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Resumen Semanal de Empleados</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?php echo count($empleadosAgrupados); ?> Empleados con turno</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchEmpleadoHorario" placeholder="Buscar empleado o código...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive horario-table-wrapper">
                <table class="table align-middle mb-0 table-pro" id="horariosTable" 
                       data-erp-table="true"
                       data-rows-selector="#horariosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchEmpleadoHorario"
                       data-empty-text="No hay horarios asignados"
                       data-info-text-template="Mostrando {start} a {end} de {total} empleados"
                       data-pagination-info="#horariosPaginationInfo"
                       data-pagination-controls="#horariosPaginationControls">
                    <thead class="horario-sticky-thead bg-light">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold col-w-25p">Empleado</th>
                            <?php foreach ($diasCortos as $num => $dia): ?>
                                <th class="text-center text-secondary fw-semibold col-w-9p"><?php echo e($dia); ?></th>
                            <?php endforeach; ?>
                            <th class="text-center text-secondary fw-semibold pe-4 col-w-12p">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="horariosTableBody">
                        <?php if (empty($empleadosAgrupados)): ?>
                            <tr class="empty-msg-row">
                                <td colspan="9" class="text-center text-muted py-5 border-bottom-0">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-light"></i>
                                    No hay horarios asignados a los empleados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empleadosAgrupados as $idEmp => $emp): ?>
                                <?php $searchStr = strtolower($emp['nombre_completo'] . ' ' . ($emp['codigo_biometrico'] ?? '')); ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 py-3">
                                        <div class="fw-bold text-dark"><?php echo e($emp['nombre_completo']); ?></div>
                                        <?php if(!empty($emp['codigo_biometrico'])): ?>
                                            <small class="text-muted fw-medium"><i class="bi bi-upc-scan me-1"></i>Cód: <?php echo e($emp['codigo_biometrico']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <?php for($i = 1; $i <= 7; $i++): ?>
                                        <td class="text-center">
                                            <?php if(isset($emp['dias_asignados'][$i])): ?>
                                                <?php $info = $emp['dias_asignados'][$i]; ?>
                                                <div class="badge bg-primary-subtle text-primary border border-primary-subtle text-wrap p-2 lh-sm shadow-sm" data-bs-toggle="tooltip" title="<?php echo $info['hora_entrada']; ?> a <?php echo $info['hora_salida']; ?>" style="font-size: 0.75rem; width: 100%;">
                                                    <?php echo e($info['nombre_horario']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="opacity: 0.3; font-size: 0.8rem;">- Libre -</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                    <td class="text-center pe-4">
                                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" onsubmit="return confirm('¿Estás seguro de eliminar TODOS los turnos de la semana para <?php echo e($emp['nombre_completo']); ?>?')" class="d-inline">
                                            <input type="hidden" name="accion" value="limpiar_semana_empleado">
                                            <input type="hidden" name="id_tercero" value="<?php echo (int) $idEmp; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent rounded-circle" data-bs-toggle="tooltip" title="Limpiar toda la semana">
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
            
            <?php if (!empty($empleadosAgrupados)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="horariosPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación de horarios">
                    <ul class="pagination mb-0 shadow-sm" id="horariosPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

