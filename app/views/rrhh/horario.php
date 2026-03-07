<?php
$horarios = $horarios ?? [];
$empleados = $empleados ?? [];
$asignaciones = $asignaciones ?? [];
$empleadosAgrupados = $empleadosAgrupados ?? [];
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

// Función auxiliar para formatear los tramos en texto
function formatearTramos($horario) {
    $texto = substr((string) ($horario['t1_entrada'] ?? '00:00'), 0, 5) . ' - ' . substr((string) ($horario['t1_salida'] ?? '00:00'), 0, 5);
    if (!empty($horario['t2_entrada']) && !empty($horario['t2_salida'])) {
        $texto .= ' | ' . substr((string) $horario['t2_entrada'], 0, 5) . ' - ' . substr((string) $horario['t2_salida'], 0, 5);
    }
    if (!empty($horario['t3_entrada']) && !empty($horario['t3_salida'])) {
        $texto .= ' | ' . substr((string) $horario['t3_entrada'], 0, 5) . ' - ' . substr((string) $horario['t3_salida'], 0, 5);
    }
    return $texto;
}
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar-week-fill me-2 text-primary"></i> Horarios y Asignaciones
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo de turnos y asignación de horarios por tramos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-success shadow-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalAsignacionMasiva">
                <i class="bi bi-person-lines-fill me-2"></i>Asignación Masiva
            </button>
            <button class="btn btn-primary shadow-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTurno" id="btnNuevoTurno">
                <i class="bi bi-clock-history me-2"></i>Gestión de Turnos
            </button>
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold ms-2">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchEmpleadoHorario" placeholder="Buscar empleado o código...">
                    </div>
                </div>
                <div class="col-6 col-md-7 text-md-end">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill shadow-sm">
                        <?php echo count($empleadosAgrupados); ?> Empleados con turno
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="horariosTable" 
                       data-erp-table="true"
                       data-rows-selector="#horariosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchEmpleadoHorario"
                       data-empty-text="No hay horarios asignados">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold col-w-25p">Empleado</th>
                            <?php foreach ($diasCortos as $num => $dia): ?>
                                <th class="text-center text-secondary fw-semibold col-w-9p"><?php echo e($dia); ?></th>
                            <?php endforeach; ?>
                            <th class="text-end pe-4 text-secondary fw-semibold col-w-12p">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="horariosTableBody">
                        <?php if (empty($empleadosAgrupados)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="9" class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-light"></i>
                                    No hay horarios asignados a los empleados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empleadosAgrupados as $idEmp => $emp): ?>
                                <?php $searchStr = strtolower($emp['nombre_completo'] . ' ' . ($emp['codigo_biometrico'] ?? '')); ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 fw-semibold text-primary align-top pt-3">
                                        <div class="text-dark"><?php echo e($emp['nombre_completo']); ?></div>
                                        <?php if(!empty($emp['codigo_biometrico'])): ?>
                                            <div class="small text-muted fw-medium"><i class="bi bi-upc-scan me-1"></i>Cód: <?php echo e($emp['codigo_biometrico']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php for($i = 1; $i <= 7; $i++): ?>
                                        <td class="text-center align-top pt-3">
                                            <?php if(isset($emp['dias_asignados'][$i])): ?>
                                                <?php $info = $emp['dias_asignados'][$i]; ?>
                                                <div class="badge bg-light text-dark border shadow-sm text-wrap p-2 lh-sm" data-bs-toggle="tooltip" title="Turno Asignado" style="font-size: 0.75rem; width: 100%;">
                                                    <?php echo e($info['nombre_horario']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted" style="opacity: 0.3; font-size: 0.8rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                    <td class="text-end pe-4 align-top pt-3">
                                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" onsubmit="return confirm('¿Estás seguro de eliminar TODOS los turnos de la semana para <?php echo e($emp['nombre_completo']); ?>?')" class="d-inline">
                                            <input type="hidden" name="accion" value="limpiar_semana_empleado">
                                            <input type="hidden" name="id_tercero" value="<?php echo (int) $idEmp; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 rounded-circle js-btn-eliminar-grupo" data-bs-toggle="tooltip" title="Limpiar semana">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
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

<div class="modal fade" id="modalCrearTurno" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history me-2 text-info"></i>Gestión de Turnos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body">
                <form method="post" action="<?php echo e(route_url('horario/index')); ?>" id="horarioForm" class="row g-2 mb-3 border rounded-3 p-3 bg-light align-items-end">
                    <input type="hidden" name="accion" value="guardar_horario">
                    <input type="hidden" name="id" id="horarioId" value="0">

                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted fw-bold mb-1">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="nombre" id="horarioNombre" placeholder="Ej. Día Completo" maxlength="100" required>
                    </div>
                    
                    <div class="col-6 col-md-2">
                        <label class="form-label small text-muted fw-bold mb-1">Tolerancia (m) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control form-control-sm" name="tolerancia_minutos" id="horarioTolerancia" min="0" step="1" value="0" required>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small text-primary fw-bold mb-1">T1 (Ent/Sal) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="time" class="form-control" name="t1_entrada" id="t1Entrada" required>
                            <input type="time" class="form-control" name="t1_salida" id="t1Salida" required>
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small text-secondary fw-bold mb-1 text-truncate w-100">T2 (Opcional)</label>
                        <div class="input-group input-group-sm">
                            <input type="time" class="form-control" name="t2_entrada" id="t2Entrada">
                            <input type="time" class="form-control" name="t2_salida" id="t2Salida">
                        </div>
                    </div>

                    <div class="col-6 col-md-2">
                        <label class="form-label small text-secondary fw-bold mb-1 text-truncate w-100">T3 (Opcional)</label>
                        <div class="input-group input-group-sm">
                            <input type="time" class="form-control" name="t3_entrada" id="t3Entrada">
                            <input type="time" class="form-control" name="t3_salida" id="t3Salida">
                        </div>
                    </div>
                    
                    <div class="col-12 col-md-1 d-flex justify-content-end gap-1 mt-3 mt-md-0">
                        <button type="button" class="btn btn-sm btn-light w-50" id="btnLimpiarHorario" title="Limpiar"><i class="bi bi-eraser"></i></button>
                        <button type="submit" class="btn btn-sm btn-primary w-50" title="Guardar"><i class="bi bi-save"></i></button>
                    </div>
                </form>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="small fw-bold text-muted mb-0">Catálogo de turnos creados</h6>
                    <div class="input-group input-group-sm w-auto">
                        <span class="input-group-text bg-white text-muted border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control bg-white border-start-0 ps-0" id="searchTurnos" placeholder="Buscar turno...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table align-middle table-sm mb-0 table-pro" id="tablaTurnos"
                           data-erp-table="true"
                           data-rows-selector="#turnosTableBody tr:not(.empty-msg-row)"
                           data-search-input="#searchTurnos"
                           data-rows-per-page="10"
                           data-empty-text="No se encontraron turnos">
                        <thead>
                            <tr>
                                <th>Nombre del Turno</th>
                                <th>Tramos de Horario</th>
                                <th class="text-center">Tolerancia</th>
                                <th class="text-center">Estado</th>
                                <th class="text-end pe-3">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="turnosTableBody">
                            <?php if (empty($horarios)): ?>
                                <tr class="empty-msg-row">
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No hay turnos registrados.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($horarios as $horario): ?>
                                    <?php 
                                        $activo = ((int) $horario['estado'] === 1); 
                                        $searchStrTurno = strtolower($horario['nombre'] . ' ' . ($activo ? 'activo' : 'inactivo'));
                                        $textoTramos = formatearTramos($horario);
                                    ?>
                                    <tr data-search="<?php echo htmlspecialchars($searchStrTurno, ENT_QUOTES, 'UTF-8'); ?>">
                                        <td class="fw-semibold text-dark">
                                            <?php echo e($horario['nombre']); ?>
                                        </td>
                                        <td class="small text-muted">
                                            <i class="bi bi-clock me-1"></i><?php echo e($textoTramos); ?>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge bg-secondary-subtle text-secondary border"><?php echo (int) $horario['tolerancia_minutos']; ?> min</span>
                                        </td>
                                        <td class="text-center">
                                            <?php if($activo): ?>
                                                <span class="badge bg-success-subtle text-success border px-2 py-1 rounded-pill">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary-subtle text-secondary border px-2 py-1 rounded-pill">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-3">
                                            <button type="button" class="btn btn-sm btn-light text-primary rounded-circle border-0 js-editar-horario" 
                                                data-bs-toggle="tooltip" title="Editar"
                                                data-id="<?php echo (int) $horario['id']; ?>"
                                                data-nombre="<?php echo e($horario['nombre']); ?>"
                                                data-t1-entrada="<?php echo e(substr((string) ($horario['t1_entrada'] ?? ''), 0, 5)); ?>"
                                                data-t1-salida="<?php echo e(substr((string) ($horario['t1_salida'] ?? ''), 0, 5)); ?>"
                                                data-t2-entrada="<?php echo e(substr((string) ($horario['t2_entrada'] ?? ''), 0, 5)); ?>"
                                                data-t2-salida="<?php echo e(substr((string) ($horario['t2_salida'] ?? ''), 0, 5)); ?>"
                                                data-t3-entrada="<?php echo e(substr((string) ($horario['t3_entrada'] ?? ''), 0, 5)); ?>"
                                                data-t3-salida="<?php echo e(substr((string) ($horario['t3_salida'] ?? ''), 0, 5)); ?>"
                                                data-tolerancia="<?php echo (int) $horario['tolerancia_minutos']; ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            
                                            <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="d-inline" onsubmit="return confirm('¿Cambiar estado de este turno?');">
                                                <input type="hidden" name="accion" value="cambiar_estado_horario">
                                                <input type="hidden" name="id" value="<?php echo (int) $horario['id']; ?>">
                                                <input type="hidden" name="estado" value="<?php echo $activo ? 0 : 1; ?>">
                                                <button type="submit" class="btn btn-sm btn-light <?php echo $activo ? 'text-warning' : 'text-success'; ?> rounded-circle border-0" data-bs-toggle="tooltip" title="<?php echo $activo ? 'Desactivar' : 'Activar'; ?>">
                                                    <i class="bi <?php echo $activo ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                                                </button>
                                            </form>
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
</div>

<div class="modal fade" id="modalAsignacionMasiva" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-lines-fill me-2 text-success"></i>Asignación Masiva</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body bg-light">
                <form method="post" action="<?php echo e(route_url('horario/index')); ?>" id="asignacionMasivaForm">
                    <input type="hidden" name="accion" value="guardar_asignacion">
                    
                    <div class="row g-4">
                        <div class="col-lg-7 pe-lg-4">
                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-end mb-2">
                                        <label class="form-label small text-muted fw-bold mb-0">1. Buscar Empleado <span class="text-danger">*</span></label>
                                        <button type="button" class="btn btn-sm text-primary p-0 fw-semibold" id="btnSeleccionarTodosEmp" style="font-size: 0.8rem; text-decoration: none;">
                                            <i class="bi bi-plus-circle me-1"></i>Añadir a todos
                                        </button>
                                    </div>
                                    <select id="empleadoTomSelect" class="form-select shadow-sm" placeholder="Escribe nombre o código...">
                                        <option value="">Escribe nombre o código...</option>
                                        <?php foreach ($empleados as $empleado): ?>
                                            <option value="<?php echo (int) $empleado['id']; ?>">
                                                <?php echo e($empleado['nombre_completo']); ?> <?php echo !empty($empleado['codigo_biometrico']) ? ' (Cód: ' . e($empleado['codigo_biometrico']) . ')' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-end mb-2">
                                        <label class="form-label small text-muted fw-bold mb-0">2. Días de la Semana <span class="text-danger">*</span></label>
                                        <button type="button" class="btn btn-sm text-primary p-0 fw-semibold" id="btnMarcarTodosDias" style="font-size: 0.8rem; text-decoration: none;">
                                            Marcar Lun a Dom
                                        </button>
                                    </div>
                                    <div class="btn-group w-100 shadow-sm" role="group">
                                        <?php foreach ($diasCortos as $num => $dia): ?>
                                            <input type="checkbox" class="btn-check dia-checkbox" name="dias[]" id="mas_dia_<?php echo $num; ?>" value="<?php echo $num; ?>" autocomplete="off">
                                            <label class="btn btn-outline-secondary fw-semibold" for="mas_dia_<?php echo $num; ?>"><?php echo e($dia); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <label class="form-label small text-muted fw-bold mb-2">3. Turno a Asignar <span class="text-danger">*</span></label>
                                    <select name="id_horario" class="form-select bg-light fw-medium border-secondary-subtle" required>
                                        <option value="">Seleccione turno...</option>
                                        <?php foreach ($horarios as $horario): ?>
                                            <?php if ((int) $horario['estado'] !== 1) continue; ?>
                                            <option value="<?php echo (int) $horario['id']; ?>"><?php echo e($horario['nombre']); ?> (<?php echo e(formatearTramos($horario)); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 d-flex flex-column">
                            <div class="card border-0 shadow-sm flex-grow-1">
                                <div class="card-body d-flex flex-column p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <h6 class="small fw-bold text-dark text-uppercase mb-0">Seleccionados (<span id="contadorSeleccionados">0</span>)</h6>
                                        <button type="button" class="btn btn-sm btn-outline-danger border-0 py-0" id="btnLimpiarLista" style="font-size: 0.75rem;">Limpiar</button>
                                    </div>
                                    
                                    <div id="panelSeleccionados" class="overflow-auto pe-2 flex-grow-1" style="max-height: 250px; min-height: 150px;">
                                        <div id="listaVaciaHint" class="text-center text-muted mt-4 opacity-50">
                                            <i class="bi bi-people fs-1 d-block mb-2"></i>
                                            <small>Busca empleados a la izquierda para añadirlos.</small>
                                        </div>
                                    </div>
                                    <div id="inputIdsContenedor"></div>
                                </div>
                            </div>
                            
                            <button class="btn btn-success shadow-sm w-100 fw-bold py-2 mt-3" type="submit">
                                <i class="bi bi-check-circle me-1"></i> Aplicar Asignación
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="assets/js/rrhh/horario.js"></script>