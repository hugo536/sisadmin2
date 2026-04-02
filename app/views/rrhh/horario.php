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

<div class="container-fluid p-4" id="horariosAsignacionesApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar-week-fill me-2 text-primary"></i> Horarios y Asignaciones
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo de turnos y asignación de horarios por tramos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-success shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalAsignacionMasiva">
                <i class="bi bi-person-lines-fill me-2"></i>Asignación Masiva
            </button>
            <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTurno" id="btnNuevoTurno">
                <i class="bi bi-clock-history me-2"></i>Gestión de Turnos
            </button>
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold ms-2 transition-hover">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="searchEmpleadoHorario" placeholder="Buscar empleado o código...">
                    </div>
                </div>
                <div class="col-12 col-md-7 text-md-end mt-2 mt-md-0">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-people-fill me-1"></i> <?php echo count($empleadosAgrupados); ?> Empleados con turno asignado
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro table-hover" id="horariosTable" 
                       data-erp-table="true"
                       data-rows-selector="#horariosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchEmpleadoHorario"
                       data-empty-text="No hay horarios asignados en este momento"
                       data-pagination-controls="#horariosPaginationControls"
                       data-pagination-info="#horariosPaginationInfo"
                       data-rows-per-page="12">
                    <thead class="table-light border-bottom">
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
                                    
                                    <td class="ps-4 align-middle py-3">
                                        <div class="fw-bold text-dark"><?php echo e($emp['nombre_completo']); ?></div>
                                        <?php if(!empty($emp['codigo_biometrico'])): ?>
                                            <div class="small text-muted fw-medium mt-1"><i class="bi bi-upc-scan me-1"></i>Cód: <?php echo e($emp['codigo_biometrico']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php for($i = 1; $i <= 7; $i++): ?>
                                        <td class="text-center align-middle py-2 px-1">
                                            <?php if(isset($emp['dias_asignados'][$i])): ?>
                                                <?php $info = $emp['dias_asignados'][$i]; ?>
                                                <div class="badge bg-light text-dark border border-secondary-subtle shadow-sm text-wrap p-2 lh-sm w-100" data-bs-toggle="tooltip" title="Turno Asignado" style="font-size: 0.70rem;">
                                                    <?php echo e($info['nombre_horario']); ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted opacity-25" style="font-size: 0.8rem;">-</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                    
                                    <td class="text-end pe-4 align-middle">
                                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" onsubmit="return confirm('¿Estás seguro de eliminar TODOS los turnos de la semana para <?php echo e($emp['nombre_completo']); ?>?')" class="d-inline">
                                            <input type="hidden" name="accion" value="limpiar_semana_empleado">
                                            <input type="hidden" name="id_tercero" value="<?php echo (int) $idEmp; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 rounded-circle js-btn-eliminar-grupo shadow-sm" data-bs-toggle="tooltip" title="Limpiar semana">
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
            
            <?php if (!empty($empleadosAgrupados)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="horariosPaginationInfo">Cargando...</small>
                <nav aria-label="Paginación de horarios"><ul class="pagination mb-0 justify-content-end shadow-sm" id="horariosPaginationControls"></ul></nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearTurno" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Catálogo de Turnos Base</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" id="horarioForm" class="row g-3 align-items-end">
                            <input type="hidden" name="accion" value="guardar_horario">
                            <input type="hidden" name="id" id="horarioId" value="0">

                            <div class="col-12 col-md-3">
                                <label class="form-label small text-muted fw-bold mb-1">Nombre del Turno <span class="text-danger">*</span></label>
                                <input type="text" class="form-control shadow-none border-secondary-subtle fw-semibold" name="nombre" id="horarioNombre" placeholder="Ej. Día Completo" maxlength="100" required>
                            </div>
                            
                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted fw-bold mb-1">Tolerancia (min) <span class="text-danger">*</span></label>
                                <input type="number" class="form-control shadow-none border-secondary-subtle" name="tolerancia_minutos" id="horarioTolerancia" min="0" step="1" value="0" required>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label small text-primary fw-bold mb-1">T1 (Ent/Sal) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="time" class="form-control shadow-none border-primary px-1 text-center" name="t1_entrada" id="t1Entrada" required>
                                    <input type="time" class="form-control shadow-none border-primary px-1 text-center" name="t1_salida" id="t1Salida" required>
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted fw-bold mb-1 text-truncate w-100">T2 (Opcional)</label>
                                <div class="input-group">
                                    <input type="time" class="form-control shadow-none border-secondary-subtle px-1 text-center" name="t2_entrada" id="t2Entrada">
                                    <input type="time" class="form-control shadow-none border-secondary-subtle px-1 text-center" name="t2_salida" id="t2Salida">
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label small text-muted fw-bold mb-1 text-truncate w-100">T3 (Opcional)</label>
                                <div class="input-group">
                                    <input type="time" class="form-control shadow-none border-secondary-subtle px-1 text-center" name="t3_entrada" id="t3Entrada">
                                    <input type="time" class="form-control shadow-none border-secondary-subtle px-1 text-center" name="t3_salida" id="t3Salida">
                                </div>
                            </div>
                            
                            <div class="col-12 col-md-1 d-flex justify-content-end gap-2 mt-3 mt-md-0">
                                <button type="button" class="btn btn-light border w-50 shadow-sm" id="btnLimpiarHorario" title="Limpiar formulario"><i class="bi bi-eraser text-secondary"></i></button>
                                <button type="submit" class="btn btn-primary w-50 shadow-sm" title="Guardar Turno"><i class="bi bi-save"></i></button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                        <h6 class="small fw-bold text-dark text-uppercase mb-0"><i class="bi bi-list-task me-2 text-primary"></i>Turnos Registrados</h6>
                        <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                            <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control bg-light border-start-0 ps-0 shadow-none" id="searchTurnos" placeholder="Buscar turno...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm mb-0 table-hover table-pro" id="tablaTurnos"
                                   data-erp-table="true"
                                   data-rows-selector="#turnosTableBody tr:not(.empty-msg-row)"
                                   data-search-input="#searchTurnos"
                                   data-rows-per-page="10"
                                   data-empty-text="No se encontraron turnos">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 text-secondary fw-semibold">Nombre del Turno</th>
                                        <th class="text-secondary fw-semibold">Tramos de Horario</th>
                                        <th class="text-center text-secondary fw-semibold">Tolerancia</th>
                                        <th class="text-center text-secondary fw-semibold">Estado</th>
                                        <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="turnosTableBody">
                                    <?php if (empty($horarios)): ?>
                                        <tr class="empty-msg-row">
                                            <td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 text-light mb-2"></i>No hay turnos registrados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($horarios as $horario): ?>
                                            <?php 
                                                $activo = ((int) $horario['estado'] === 1); 
                                                $searchStrTurno = strtolower($horario['nombre'] . ' ' . ($activo ? 'activo' : 'inactivo'));
                                                $textoTramos = formatearTramos($horario);
                                            ?>
                                            <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStrTurno, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td class="ps-4 fw-bold text-dark">
                                                    <?php echo e($horario['nombre']); ?>
                                                </td>
                                                <td class="small text-muted fw-medium">
                                                    <i class="bi bi-clock me-1 text-secondary"></i><?php echo e($textoTramos); ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2"><?php echo (int) $horario['tolerancia_minutos']; ?> min</span>
                                                </td>
                                                <td class="text-center">
                                                    <?php if($activo): ?>
                                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button type="button" class="btn btn-sm btn-light text-primary rounded-circle border-0 js-editar-horario shadow-sm me-1" 
                                                            data-bs-toggle="tooltip" title="Editar turno"
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
                                                        <button type="submit" class="btn btn-sm btn-light <?php echo $activo ? 'text-warning' : 'text-success'; ?> rounded-circle border-0 shadow-sm" data-bs-toggle="tooltip" title="<?php echo $activo ? 'Desactivar turno' : 'Activar turno'; ?>">
                                                            <i class="bi <?php echo $activo ? 'bi-toggle-on' : 'bi-toggle-off'; ?> fs-6"></i>
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
    </div>
</div>

<div class="modal fade" id="modalAsignacionMasiva" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-lines-fill me-2"></i>Asignación Masiva de Turnos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('horario/index')); ?>" id="asignacionMasivaForm">
                    <input type="hidden" name="accion" value="guardar_asignacion">
                    
                    <div class="row g-4">
                        <div class="col-lg-7">
                            
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-1-circle me-2 text-success"></i>Selección de Personal</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1 shadow-sm" id="btnSeleccionarTodosEmp">
                                            <i class="bi bi-people-fill me-1"></i>Añadir Todos
                                        </button>
                                    </div>
                                    <select id="empleadoTomSelect" class="form-select shadow-none border-secondary-subtle" placeholder="Escribe nombre o código...">
                                        <option value="">Buscar en el directorio de empleados...</option>
                                        <?php foreach ($empleados as $empleado): ?>
                                            <option value="<?php echo (int) $empleado['id']; ?>">
                                                <?php echo e($empleado['nombre_completo']); ?> <?php echo !empty($empleado['codigo_biometrico']) ? ' (Cód: ' . e($empleado['codigo_biometrico']) . ')' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text text-muted mt-2"><i class="bi bi-info-circle me-1"></i>Los empleados seleccionados aparecerán en la lista de la derecha.</div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-2-circle me-2 text-success"></i>Días de la Semana</h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3 py-1 shadow-sm" id="btnMarcarTodosDias">
                                            <i class="bi bi-check-all me-1"></i>Marcar L-D
                                        </button>
                                    </div>
                                    <div class="btn-group w-100 shadow-sm d-flex flex-wrap" role="group">
                                        <?php foreach ($diasCortos as $num => $dia): ?>
                                            <input type="checkbox" class="btn-check dia-checkbox" name="dias[]" id="mas_dia_<?php echo $num; ?>" value="<?php echo $num; ?>" autocomplete="off">
                                            <label class="btn btn-outline-success fw-bold flex-grow-1" for="mas_dia_<?php echo $num; ?>"><?php echo e($dia); ?></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2"><i class="bi bi-3-circle me-2 text-success"></i>Turno a Asignar</h6>
                                    <select name="id_horario" class="form-select bg-light fw-bold text-primary border-success-subtle shadow-sm p-3" required>
                                        <option value="">Seleccione el turno del catálogo...</option>
                                        <?php foreach ($horarios as $horario): ?>
                                            <?php if ((int) $horario['estado'] !== 1) continue; ?>
                                            <option value="<?php echo (int) $horario['id']; ?>"><?php echo e($horario['nombre']); ?> (<?php echo e(formatearTramos($horario)); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-5 d-flex flex-column">
                            <div class="card border-0 shadow-sm flex-grow-1 border-top border-4 border-success">
                                <div class="card-body d-flex flex-column p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
                                        <h6 class="fw-bold text-dark mb-0">Empleados Afectados (<span id="contadorSeleccionados" class="text-success">0</span>)</h6>
                                        <button type="button" class="btn btn-sm btn-light text-danger fw-bold shadow-sm" id="btnLimpiarLista"><i class="bi bi-trash me-1"></i>Vaciar</button>
                                    </div>
                                    
                                    <div id="panelSeleccionados" class="overflow-auto pe-2 flex-grow-1" style="max-height: 400px; min-height: 200px;">
                                        <div id="listaVaciaHint" class="text-center text-muted mt-5 opacity-50">
                                            <i class="bi bi-person-lines-fill fs-1 d-block mb-3"></i>
                                            <span class="fw-semibold">No hay empleados seleccionados.</span>
                                            <p class="small mt-1">Usa el buscador (Paso 1) para añadirlos a esta lista.</p>
                                        </div>
                                    </div>
                                    <div id="inputIdsContenedor"></div>
                                </div>
                            </div>
                            
                            <button class="btn btn-success shadow-sm w-100 fw-bold p-3 mt-4 fs-5" type="submit">
                                <i class="bi bi-check-circle-fill me-2"></i> Aplicar Asignación
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>