<?php
$horarios = $horarios ?? [];
$empleados = $empleados ?? [];
$asignaciones = $asignaciones ?? [];
$empleadosAgrupados = $empleadosAgrupados ?? [];
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$diasCortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

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
    
    <!-- CABECERA -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar-week-fill me-2 text-primary"></i> Asignación de Horarios
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de plantillas predeterminadas de turnos por empleado.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-success btn-sm shadow-sm fw-bold px-3 py-2 transition-hover d-flex align-items-center" type="button" data-bs-toggle="modal" data-bs-target="#modalAsignacionMasiva">
                <i class="bi bi-person-lines-fill fs-6 me-2"></i>Asignación Masiva
            </button>
            <button class="btn btn-primary btn-sm shadow-sm fw-bold px-3 py-2 transition-hover d-flex align-items-center" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearTurno" id="btnNuevoTurno">
                <i class="bi bi-clock-history fs-6 me-2"></i>Catálogo de Turnos
            </button>
        </div>
    </div>

    <!-- BARRA DE HERRAMIENTAS Y BUSCADOR -->
    <div class="card border-0 shadow-sm mb-4 fade-in">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center justify-content-between">
                
                <div class="col-12 col-md-5">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-white border-start-0 ps-0 border-secondary-subtle shadow-none" id="searchEmpleadoHorario" placeholder="Buscar empleado o código...">
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <select class="form-select bg-white border-secondary-subtle shadow-sm text-secondary fw-medium" id="filtroEstadoPlantilla">
                        <option value="todos">Todos los empleados</option>
                        <option value="con_horario">Con horario asignado</option>
                        <option value="sin_horario">Sin horario (Vacíos)</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-4 text-md-end">
                    <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle px-3 py-2 rounded-pill shadow-sm fs-6">
                        <i class="bi bi-people-fill me-1"></i> <?php echo count($empleadosAgrupados); ?> Empleados con plantilla
                    </span>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLA PRINCIPAL DE PLANTILLAS -->
    <div class="card border-0 shadow-sm fade-in">
        <div class="card-body p-0">
            <div class="table-responsive" style="min-height: 400px;">
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
                            <th class="ps-4 text-secondary fw-semibold" style="width: 20%;">Empleado</th>
                            <?php foreach ($diasCortos as $num => $dia): ?>
                                <th class="text-center text-secondary fw-semibold" style="width: 10%;"><?php echo e($dia); ?></th>
                            <?php endforeach; ?>
                            <th class="text-center pe-4 text-secondary fw-semibold" style="width: 10%;">Opciones</th>
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
                                        <div class="fw-bold text-dark fs-6"><?php echo e($emp['nombre_completo']); ?></div>
                                        <?php if(!empty($emp['codigo_biometrico'])): ?>
                                            <div class="small text-muted fw-medium mt-1">
                                                <i class="bi bi-upc-scan me-1"></i>Cód: <span class="fw-semibold text-primary"><?php echo e($emp['codigo_biometrico']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- CELDAS DE DÍAS (ESTÁTICAS) -->
                                    <?php for($i = 1; $i <= 7; $i++): ?>
                                        <td class="text-center align-middle py-2 px-1">
                                            <?php 
                                            if(isset($emp['dias_asignados'][$i])): 
                                                $info = $emp['dias_asignados'][$i];
                                                $nombreLower = strtolower($info['nombre_horario']);
                                                
                                                if (strpos($nombreLower, 'día') !== false || strpos($nombreLower, 'dia') !== false) {
                                                    $claseBoton = 'bg-info-subtle text-info-emphasis border-info-subtle';
                                                } elseif (strpos($nombreLower, 'noche') !== false) {
                                                    $claseBoton = 'bg-dark text-white border-dark';
                                                } elseif (strpos($nombreLower, 'tarde') !== false) {
                                                    $claseBoton = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                                } else {
                                                    $claseBoton = 'bg-primary-subtle text-primary-emphasis border-primary-subtle';
                                                }
                                            ?>
                                                <div class="w-100 p-2 lh-sm border shadow-sm rounded <?php echo $claseBoton; ?>" title="<?php echo e(substr((string)($info['hora_entrada']??''),0,5)) . ' - ' . e(substr((string)($info['hora_salida']??''),0,5)); ?>" style="font-size: 0.75rem; cursor: default;">
                                                    <div class="fw-bold text-truncate w-100"><?php echo e($info['nombre_horario']); ?></div>
                                                </div>
                                            <?php else: ?>
                                                <div class="w-100 p-2 lh-sm border border-secondary-subtle bg-light text-secondary rounded" title="Día Libre" style="font-size: 0.75rem; border-style: dashed !important; cursor: default;">
                                                    <div class="fw-bold text-truncate w-100 opacity-75">Descanso</div>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                    
                                    <!-- OPCIONES (3 PUNTITOS) -->
                                    <td class="text-center pe-4 align-middle">
                                        <div class="dropdown">
                                            <button class="btn btn-light btn-sm rounded-circle shadow-sm border border-secondary-subtle transition-hover p-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Opciones de plantilla">
                                                <i class="bi bi-three-dots-vertical text-secondary"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 p-2" style="font-size: 0.85rem; min-width: 220px;">
                                                <li>
                                                    <a class="dropdown-item text-primary rounded py-2 transition-hover fw-medium" href="#" onclick="alert('Función Copiar en desarrollo'); return false;">
                                                        <i class="bi bi-clipboard me-2"></i>Copiar plantilla
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item text-success rounded py-2 transition-hover fw-medium" href="#" onclick="alert('Función Pegar en desarrollo'); return false;">
                                                        <i class="bi bi-clipboard-check me-2"></i>Pegar plantilla
                                                    </a>
                                                </li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li>
                                                    <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="m-0" onsubmit="return confirm('¿Estás seguro de vaciar la plantilla semanal de <?php echo e($emp['nombre_completo']); ?>?');">
                                                        <input type="hidden" name="accion" value="limpiar_semana_empleado">
                                                        <input type="hidden" name="id_tercero" value="<?php echo (int) $idEmp; ?>">
                                                        <button type="submit" class="dropdown-item text-danger fw-bold bg-danger-subtle rounded py-2 transition-hover mt-1">
                                                            <i class="bi bi-eraser-fill me-2"></i>Limpiar toda la semana
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- PAGINACIÓN -->
            <?php if (!empty($empleadosAgrupados)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="horariosPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de horarios">
                    <ul class="pagination mb-0 shadow-sm" id="horariosPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: CATÁLOGO DE TURNOS                  -->
<!-- ========================================== -->
<div class="modal fade" id="modalCrearTurno" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2 text-info"></i>Catálogo de Turnos Base</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <!-- Formulario Crear/Editar Turno (SIMPLIFICADO) -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" id="horarioForm">
                            <input type="hidden" name="accion" value="guardar_horario">
                            <input type="hidden" name="id" id="horarioId" value="0">

                            <!-- CABECERA DEL TURNO -->
                            <div class="row g-3 mb-4">
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold mb-1">Nombre del Turno <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control bg-white shadow-none border-secondary-subtle fw-semibold fs-5" name="nombre" id="horarioNombre" placeholder="Ej. Turno Mañana (08:00 - 17:00)" maxlength="100" required>
                                </div>
                            </div>

                            <!-- TRAMOS -->
                            <h6 class="small fw-bold text-primary border-bottom pb-2 mb-3"><i class="bi bi-clock me-2"></i>Tramos de Horario</h6>
                            <div class="row g-3 mb-4">
                                <!-- T1 -->
                                <div class="col-12 col-md-4">
                                    <div class="p-3 border border-primary-subtle bg-primary-subtle bg-opacity-10 rounded shadow-sm h-100">
                                        <label class="form-label small text-primary fw-bold mb-2">Tramo 1 (Principal) <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm shadow-sm">
                                            <span class="input-group-text bg-white border-primary-subtle"><i class="bi bi-box-arrow-in-right text-success"></i></span>
                                            <input type="time" class="form-control bg-white shadow-none border-primary-subtle text-center" name="t1_entrada" id="t1Entrada" required>
                                            <span class="input-group-text bg-white border-primary-subtle text-muted px-2">a</span>
                                            <input type="time" class="form-control bg-white shadow-none border-primary-subtle text-center" name="t1_salida" id="t1Salida" required>
                                        </div>
                                    </div>
                                </div>
                                <!-- T2 -->
                                <div class="col-12 col-md-4">
                                    <div class="p-3 border border-secondary-subtle bg-white rounded shadow-sm h-100">
                                        <label class="form-label small text-muted fw-bold mb-2">Tramo 2 (Opcional)</label>
                                        <div class="input-group input-group-sm shadow-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-box-arrow-in-right text-muted"></i></span>
                                            <input type="time" class="form-control bg-white shadow-none border-secondary-subtle text-center" name="t2_entrada" id="t2Entrada">
                                            <span class="input-group-text bg-light border-secondary-subtle text-muted px-2">a</span>
                                            <input type="time" class="form-control bg-white shadow-none border-secondary-subtle text-center" name="t2_salida" id="t2Salida">
                                        </div>
                                    </div>
                                </div>
                                <!-- T3 -->
                                <div class="col-12 col-md-4">
                                    <div class="p-3 border border-secondary-subtle bg-white rounded shadow-sm h-100">
                                        <label class="form-label small text-muted fw-bold mb-2">Tramo 3 (Opcional)</label>
                                        <div class="input-group input-group-sm shadow-sm">
                                            <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-box-arrow-in-right text-muted"></i></span>
                                            <input type="time" class="form-control bg-white shadow-none border-secondary-subtle text-center" name="t3_entrada" id="t3Entrada">
                                            <span class="input-group-text bg-light border-secondary-subtle text-muted px-2">a</span>
                                            <input type="time" class="form-control bg-white shadow-none border-secondary-subtle text-center" name="t3_salida" id="t3Salida">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 border-top pt-4 mt-2">
                                <button type="button" class="btn btn-light border border-secondary-subtle shadow-sm transition-hover fw-bold px-4" id="btnLimpiarHorario" title="Limpiar formulario">
                                    <i class="bi bi-eraser me-2 text-secondary"></i>Limpiar
                                </button>
                                <button type="submit" class="btn btn-primary shadow-sm transition-hover fw-bold px-5" title="Guardar Turno">
                                    <i class="bi bi-save me-2"></i>Guardar Turno
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tabla de Turnos Existentes -->
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                        <h6 class="small fw-bold text-dark text-uppercase mb-0"><i class="bi bi-list-task me-2 text-primary"></i>Turnos Registrados</h6>
                        <div class="input-group input-group-sm shadow-sm" style="max-width: 250px;">
                            <span class="input-group-text bg-white text-muted border-end-0 border-secondary-subtle"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control bg-white border-start-0 ps-0 shadow-none border-secondary-subtle" id="searchTurnos" placeholder="Buscar turno...">
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
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="ps-4 text-secondary fw-semibold py-2">Nombre del Turno</th>
                                        <th class="text-secondary fw-semibold py-2">Tramos de Horario</th>
                                        <th class="text-center text-secondary fw-semibold py-2">Estado</th>
                                        <th class="text-end pe-4 text-secondary fw-semibold py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="turnosTableBody">
                                    <?php if (empty($horarios)): ?>
                                        <tr class="empty-msg-row">
                                            <td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 text-light mb-2"></i>No hay turnos registrados.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($horarios as $horario): ?>
                                            <?php 
                                                $activo = ((int) $horario['estado'] === 1); 
                                                $searchStrTurno = strtolower($horario['nombre'] . ' ' . ($activo ? 'activo' : 'inactivo'));
                                                $textoTramos = formatearTramos($horario);
                                            ?>
                                            <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStrTurno, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td class="ps-4 fw-bold text-dark py-2">
                                                    <?php echo e($horario['nombre']); ?>
                                                </td>
                                                <td class="small text-muted fw-medium py-2">
                                                    <i class="bi bi-clock me-1 text-secondary"></i><?php echo e($textoTramos); ?>
                                                </td>
                                                <td class="text-center py-2">
                                                    <?php if($activo): ?>
                                                        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-1 rounded-pill">Activo</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle px-3 py-1 rounded-pill">Inactivo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end pe-4 py-2">
                                                    <div class="d-inline-flex gap-1 align-items-center">
                                                        
                                                        <!-- BOTÓN EDITAR -->
                                                        <button type="button" class="btn-icon btn-icon-primary js-editar-horario" 
                                                                data-bs-toggle="tooltip" title="Editar turno"
                                                                data-id="<?php echo (int) $horario['id']; ?>"
                                                                data-nombre="<?php echo e($horario['nombre']); ?>"
                                                                data-t1-entrada="<?php echo e(substr((string) ($horario['t1_entrada'] ?? ''), 0, 5)); ?>"
                                                                data-t1-salida="<?php echo e(substr((string) ($horario['t1_salida'] ?? ''), 0, 5)); ?>"
                                                                data-t2-entrada="<?php echo e(substr((string) ($horario['t2_entrada'] ?? ''), 0, 5)); ?>"
                                                                data-t2-salida="<?php echo e(substr((string) ($horario['t2_salida'] ?? ''), 0, 5)); ?>"
                                                                data-t3-entrada="<?php echo e(substr((string) ($horario['t3_entrada'] ?? ''), 0, 5)); ?>"
                                                                data-t3-salida="<?php echo e(substr((string) ($horario['t3_salida'] ?? ''), 0, 5)); ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        
                                                        <!-- BOTÓN ACTIVAR/DESACTIVAR -->
                                                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="m-0 p-0" onsubmit="return confirm('¿Cambiar estado de este turno?');">
                                                            <input type="hidden" name="accion" value="cambiar_estado_horario">
                                                            <input type="hidden" name="id" value="<?php echo (int) $horario['id']; ?>">
                                                            <input type="hidden" name="estado" value="<?php echo $activo ? 0 : 1; ?>">
                                                            <button type="submit" class="btn-icon <?php echo $activo ? 'btn-icon-warning' : 'btn-icon-success'; ?>" data-bs-toggle="tooltip" title="<?php echo $activo ? 'Desactivar turno' : 'Activar turno'; ?>">
                                                                <i class="bi <?php echo $activo ? 'bi-toggle-on' : 'bi-toggle-off'; ?>"></i>
                                                            </button>
                                                        </form>

                                                        <!-- BOTÓN ELIMINAR INTELIGENTE -->
                                                        <?php $enUso = (int) ($horario['usos'] ?? 0) > 0; ?>
                                                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="m-0 p-0 js-form-eliminar-horario">
                                                            <input type="hidden" name="accion" value="eliminar_horario">
                                                            <input type="hidden" name="id" value="<?php echo (int) $horario['id']; ?>">
                                                            <?php if ($enUso): ?>
                                                                <button type="button" class="btn-icon btn-icon-secondary" data-bs-toggle="tooltip" title="Turno en uso. Solo se puede desactivar." disabled>
                                                                    <i class="bi bi-trash3"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="submit" class="btn-icon btn-icon-danger" data-bs-toggle="tooltip" title="Eliminar turno definitivamente">
                                                                    <i class="bi bi-trash3"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </form>

                                                    </div>
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

<!-- ========================================== -->
<!-- MODAL: ASIGNACIÓN MASIVA                   -->
<!-- ========================================== -->
<div class="modal fade" id="modalAsignacionMasiva" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
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
                            
                            <!-- Paso 1 -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary-subtle pb-2">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-1-circle me-2 text-success"></i>Selección de Personal</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 py-1 shadow-sm transition-hover" id="btnSeleccionarTodosEmp">
                                            <i class="bi bi-people-fill me-1"></i>Añadir Todos
                                        </button>
                                    </div>
                                    <select id="empleadoTomSelect" class="form-select bg-white shadow-none border-secondary-subtle" placeholder="Escribe nombre o código...">
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

                            <!-- Paso 2 -->
                            <div class="card border-0 shadow-sm mb-4">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary-subtle pb-2">
                                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-2-circle me-2 text-success"></i>Días de la Semana</h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3 py-1 shadow-sm transition-hover" id="btnMarcarTodosDias">
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

                            <!-- Paso 3 -->
                            <div class="card border-0 shadow-sm">
                                <div class="card-body p-4">
                                    <h6 class="fw-bold text-dark mb-3 border-bottom border-secondary-subtle pb-2"><i class="bi bi-3-circle me-2 text-success"></i>Turno a Asignar</h6>
                                    <select name="id_horario" class="form-select bg-white fw-bold text-primary border-success-subtle shadow-sm p-3" required>
                                        <option value="">Seleccione el turno del catálogo...</option>
                                        <?php foreach ($horarios as $horario): ?>
                                            <?php if ((int) $horario['estado'] !== 1) continue; ?>
                                            <option value="<?php echo (int) $horario['id']; ?>"><?php echo e($horario['nombre']); ?> (<?php echo e(formatearTramos($horario)); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen Lateral -->
                        <div class="col-lg-5 d-flex flex-column">
                            <div class="card border-0 shadow-sm flex-grow-1 border-top border-4 border-success">
                                <div class="card-body d-flex flex-column p-4">
                                    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary-subtle pb-3">
                                        <h6 class="fw-bold text-dark mb-0">Empleados Afectados (<span id="contadorSeleccionados" class="text-success">0</span>)</h6>
                                        <button type="button" class="btn btn-sm btn-light text-danger fw-bold shadow-sm border border-secondary-subtle transition-hover" id="btnLimpiarLista">
                                            <i class="bi bi-trash me-1"></i>Vaciar
                                        </button>
                                    </div>
                                    
                                    <div id="panelSeleccionados" class="overflow-auto pe-2 flex-grow-1 bg-white" style="max-height: 400px; min-height: 200px;">
                                        <div id="listaVaciaHint" class="text-center text-muted mt-5 opacity-50">
                                            <i class="bi bi-person-lines-fill fs-1 d-block mb-3"></i>
                                            <span class="fw-semibold">No hay empleados seleccionados.</span>
                                            <p class="small mt-1">Usa el buscador (Paso 1) para añadirlos a esta lista.</p>
                                        </div>
                                    </div>
                                    <div id="inputIdsContenedor"></div>
                                </div>
                            </div>
                            
                            <button class="btn btn-success shadow-sm w-100 fw-bold p-3 mt-4 fs-5 transition-hover" type="submit">
                                <i class="bi bi-check-circle-fill me-2"></i> Aplicar Asignación
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>