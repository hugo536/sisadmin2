<?php
$fecha = $fecha ?? date('Y-m-d');
$periodo = $periodo ?? 'dia';
$semana = $semana ?? date('o-\WW');
$mes = $mes ?? date('Y-m');
$fechaInicio = $fecha_inicio ?? $fecha;
$fechaFin = $fecha_fin ?? $fecha;
$idTercero = (int) ($id_tercero ?? 0);
$estado = $estado ?? '';
$desde = $desde ?? $fecha;
$hasta = $hasta ?? $fecha;
$registros = $registros ?? [];
$empleados = $empleados ?? [];

// Variables para los Grupos
$grupos = $grupos ?? []; 
$empleadosSinGrupo = $empleadosSinGrupo ?? []; 
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in asistencia-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-clock-history me-2 text-primary"></i> Dashboard Diario de RRHH
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de puntualidad, tardanzas y faltas del personal por día, semana, mes o rango.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">

            <a href="<?php echo e(route_url('asistencia/incidencias')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-clipboard2-pulse me-2 text-info"></i>Incidencias
            </a>
            
            <button type="button" class="btn btn-warning shadow-sm fw-semibold text-dark" data-bs-toggle="modal" data-bs-target="#modalGestionGrupos">
                <i class="bi bi-people-fill me-2"></i>Excepciones por Grupo
            </button>

            <button type="button" class="btn btn-success shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalRegistroManual">
                <i class="bi bi-pencil-square me-2"></i>Registro Manual
            </button>
            
            <?php if (tiene_permiso('asistencia.importar')): ?>
                <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-primary shadow-sm fw-semibold">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar Biométrico
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" class="row g-3 align-items-center" id="formFiltrosAsistencia">
                <input type="hidden" name="ruta" value="asistencia/dashboard">

                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Periodo</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="periodo">
                        <option value="dia" <?php echo $periodo === 'dia' ? 'selected' : ''; ?>>Día</option>
                        <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Semana</option>
                        <option value="mes" <?php echo $periodo === 'mes' ? 'selected' : ''; ?>>Mes</option>
                        <option value="rango" <?php echo $periodo === 'rango' ? 'selected' : ''; ?>>Rango</option>
                    </select>
                </div>

                <div class="col-12 col-md-3 <?php echo $periodo === 'dia' ? '' : 'd-none'; ?>" data-period-field="dia">
                    <label class="form-label small text-muted fw-bold mb-1">Fecha Específica</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-calendar3 text-muted"></i></span>
                        <input type="date" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 text-secondary fw-medium" name="fecha" value="<?php echo e($fecha); ?>" required>
                    </div>
                </div>

                <div class="col-12 col-md-3 <?php echo $periodo === 'semana' ? '' : 'd-none'; ?>" data-period-field="semana">
                    <label class="form-label small text-muted fw-bold mb-1">Semana</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-calendar-week text-muted"></i></span>
                        <input type="week" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 text-secondary fw-medium" name="semana" value="<?php echo e($semana); ?>">
                    </div>
                </div>

                <div class="col-12 col-md-3 <?php echo $periodo === 'mes' ? '' : 'd-none'; ?>" data-period-field="mes">
                    <label class="form-label small text-muted fw-bold mb-1">Mes</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-calendar2-month text-muted"></i></span>
                        <input type="month" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 text-secondary fw-medium" name="mes" value="<?php echo e($mes); ?>">
                    </div>
                </div>

                <div class="col-12 col-md-2 <?php echo $periodo === 'rango' ? '' : 'd-none'; ?>" data-period-field="rango">
                    <label class="form-label small text-muted fw-bold mb-1">Desde</label>
                    <input type="date" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="fecha_inicio" value="<?php echo e($fechaInicio); ?>">
                </div>

                <div class="col-12 col-md-2 <?php echo $periodo === 'rango' ? '' : 'd-none'; ?>" data-period-field="rango">
                    <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
                    <input type="date" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="fecha_fin" value="<?php echo e($fechaFin); ?>">
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Empleado</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="id_tercero">
                        <option value="">Todo el personal</option>
                        <?php foreach ($empleados as $empleado): ?>
                            <?php $empleadoId = (int) ($empleado['id'] ?? 0); ?>
                            <option value="<?php echo $empleadoId; ?>" <?php echo $idTercero === $empleadoId ? 'selected' : ''; ?>>
                                <?php echo e((string) ($empleado['nombre_completo'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Estado</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="estado">
                        <option value="" <?php echo $estado === '' ? 'selected' : ''; ?>>Todos los estados</option>
                        <option value="PUNTUAL" <?php echo $estado === 'PUNTUAL' ? 'selected' : ''; ?>>Puntual</option>
                        <option value="TARDANZA" <?php echo $estado === 'TARDANZA' ? 'selected' : ''; ?>>Tardanza</option>
                        <option value="FALTA" <?php echo $estado === 'FALTA' ? 'selected' : ''; ?>>Falta</option>
                        <option value="INCOMPLETO" <?php echo $estado === 'INCOMPLETO' ? 'selected' : ''; ?>>Incompleto</option>
                        <option value="JUSTIFICADA" <?php echo $estado === 'JUSTIFICADA' ? 'selected' : ''; ?>>Justificadas</option>
                    </select>
                </div>
            </form>
            
            <div class="small text-primary-emphasis mt-3 bg-primary-subtle d-inline-block px-3 py-1 rounded-pill fw-medium" id="infoPeriodoTexto">
                <i class="bi bi-info-circle me-1"></i> Mostrando periodo del <strong><?php echo e($desde); ?></strong> al <strong><?php echo e($hasta); ?></strong>.
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Registros de Asistencia</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3" id="badgeRegistros"><?php echo count($registros); ?> Registros</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchAsistencia" placeholder="Buscar empleado o estado...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive asistencia-table-wrapper">
                <table class="table align-middle mb-0 table-pro" id="asistenciaTable"
                       data-erp-table="true"
                       data-manager-global="asistenciaManager"
                       data-rows-selector="#asistenciaTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchAsistencia"
                       data-empty-text="No hay registros para mostrar"
                       data-info-text-template="Mostrando {start} a {end} de {total} registros"
                       data-pagination-controls="#asistenciaPaginationControls"
                       data-pagination-info="#asistenciaPaginationInfo">
                    <thead class="asistencia-sticky-thead bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Empleado</th>
                            <th class="text-secondary fw-semibold text-center">Hora Esperada</th>
                            <th class="text-secondary fw-semibold text-center">Hora Real</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-center text-secondary fw-semibold">Min. Tardanza</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="asistenciaTableBody">
                        <?php if (empty($registros)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No hay datos de asistencia para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $row): ?>
                                <?php
                                // Extraemos directamente las horas pre-formateadas desde el Modelo
                                $esperada = $row['esperada_formateada'] ?? '-';
                                $real = $row['real_formateada'] ?? '-';

                                $estado = (string) ($row['estado_asistencia'] ?? 'FALTA');
                               
                                $badgeColor = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                if ($estado === 'PUNTUAL') $badgeColor = 'bg-success-subtle text-success border border-success-subtle';
                                if ($estado === 'TARDANZA') $badgeColor = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                if ($estado === 'FALTA') $badgeColor = 'bg-danger-subtle text-danger border border-danger-subtle';
                                if ($estado === 'INCOMPLETO') $badgeColor = 'bg-secondary border border-secondary text-white';
                               
                                if (strpos($estado, 'JUSTIFICADA') !== false || strpos($estado, 'PERMISO') !== false || strpos($estado, 'OLVIDO') !== false) {
                                    $badgeColor = 'bg-info-subtle text-info-emphasis border border-info-subtle';
                                }
                               
                                $searchStr = strtolower(($row['nombre_completo'] ?? '') . ' ' . $estado . ' ' . ($row['fecha'] ?? ''));
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 text-muted align-top pt-3">
                                        <?php echo e((string) ($row['fecha'] ?? '')); ?>
                                    </td>
                                    <td class="fw-bold text-dark align-top pt-3">
                                        <?php echo e((string) ($row['nombre_completo'] ?? '')); ?>
                                    </td>
                                    <td class="text-muted align-top pt-3 text-center">
                                        <i class="bi bi-clock small me-1 opacity-50 d-block mb-1"></i>
                                        <?php echo nl2br(htmlspecialchars($esperada, ENT_QUOTES, 'UTF-8')); ?>
                                    </td>
                                    <td class="fw-medium align-top pt-3 text-primary text-center">
                                        <i class="bi bi-clock-history small me-1 opacity-50 d-block mb-1"></i>
                                        <?php echo nl2br(htmlspecialchars($real, ENT_QUOTES, 'UTF-8')); ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo $badgeColor; ?>">
                                            <?php echo e($estado); ?>
                                        </span>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <?php if((int)($row['minutos_tardanza'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger text-white rounded-pill px-2 py-1"><i class="bi bi-exclamation-triangle me-1"></i><?php echo (int) ($row['minutos_tardanza']); ?> min</span>
                                        <?php else: ?>
                                            <span class="text-muted opacity-50">0</span>
                                        <?php endif; ?>
                                    </td>
                                   
                                    <td class="text-center pe-4 align-top pt-3">
                                        <button type="button" class="btn btn-sm btn-light text-primary border-0 rounded-circle js-gestionar-asistencia"
                                            data-bs-toggle="modal" data-bs-target="#modalGestionAsistencia"
                                            data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                                            data-tercero="<?php echo (int)($row['id_tercero'] ?? 0); ?>"
                                            data-nombre="<?php echo htmlspecialchars($row['nombre_completo'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-fecha="<?php echo htmlspecialchars($row['fecha'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                                            data-ingresos="<?php echo htmlspecialchars((string)($row['horas_ingreso'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-salidas="<?php echo htmlspecialchars((string)($row['horas_salida'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                            data-bs-toggle="tooltip" title="Gestionar Registro">
                                            <i class="bi bi-gear fs-5"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
           
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="asistenciaPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="asistenciaPaginationControls"></ul>
                </nav>
            </div>
           
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionAsistencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2"></i>Gestionar Registro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="formGestionAsistencia" action="<?php echo e(route_url('asistencia/dashboard')); ?>">
                <input type="hidden" name="accion" value="gestionar_excepcion">
                <input type="hidden" name="id_asistencia" id="gestIdAsistencia">
                <input type="hidden" name="id_tercero" id="gestIdTercero">
                <input type="hidden" name="fecha" id="gestFecha">

                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-info py-2 d-flex align-items-center mb-4 shadow-sm border-0">
                        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                        <div>
                            <strong class="d-block" id="gestNombreEmpleado">Cargando...</strong>
                            <span class="small" id="gestFechaDisplay">----/--/--</span>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">1. Completar Registro Incompleto</h6>
                    <div class="alert alert-warning small border-0 py-2">
                        <i class="bi bi-shield-lock me-1"></i> <strong>Memoria Activa:</strong> El sistema calculará la tardanza usando automáticamente la hora de entrada esperada y la tolerancia que estaban vigentes <b>el día que se registró la marcación</b>, sin importar si el horario ha cambiado hoy.
                    </div>
                    
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">1. Editar / Completar Marcaciones</h6>
                    <div class="row g-2 mb-2 fw-semibold small text-muted">
                        <div class="col-4">Tramo</div>
                        <div class="col-4">Hora Ingreso Real</div>
                        <div class="col-4">Hora Salida Real</div>
                    </div>
                    <div id="gestTramosContainer" class="mb-4">
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="row g-2 mb-2 tramo-edicion" id="gestTramo<?php echo $i; ?>">
                            <div class="col-4 d-flex align-items-center">
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Tramo <?php echo $i; ?></span>
                            </div>
                            <div class="col-4">
                                <input type="time" class="form-control bg-white shadow-sm border-secondary-subtle" name="horas_ingreso_real[]" id="gestHoraIngreso<?php echo $i; ?>">
                            </div>
                            <div class="col-4">
                                <input type="time" class="form-control bg-white shadow-sm border-secondary-subtle" name="horas_salida_real[]" id="gestHoraSalida<?php echo $i; ?>">
                            </div>
                        </div>
                        <?php endfor; ?>
                        <div class="col-12 mt-1">
                            <small class="text-muted"><i class="bi bi-shield-lock me-1"></i><strong>Memoria Activa:</strong> La tardanza se recalculará automáticamente usando el horario y tolerancia oficiales de ese día.</small>
                        </div>
                    </div>

                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">2. Resolución de Anomalía</h6>
                   
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="gestCheckJustificar" name="aplicar_justificacion" value="1" style="cursor: pointer; width: 2.5em; height: 1.25em;">
                        <label class="form-check-label fw-semibold text-dark ms-2 pt-1" for="gestCheckJustificar" style="cursor: pointer;">Aplicar Justificación o Permiso</label>
                    </div>

                    <div id="boxJustificacion" class="d-none bg-white p-3 border border-secondary-subtle rounded shadow-sm">
                        <div class="mb-3">
                            <label class="form-label small text-muted fw-bold mb-1">Nuevo Estado <span class="text-danger">*</span></label>
                            <select class="form-select border-secondary-subtle" name="nuevo_estado" id="gestNuevoEstado">
                                <option value="TARDANZA JUSTIFICADA">Tardanza Justificada</option>
                                <option value="FALTA JUSTIFICADA">Falta Justificada</option>
                                <option value="PERMISO">Permiso / Salida Temprano</option>
                                <option value="OLVIDO MARCACION">Olvido de Marcación</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label small text-muted fw-bold mb-1">Motivo / Observación <span class="text-danger">*</span></label>
                            <textarea class="form-control border-secondary-subtle" name="observacion" id="gestObservacion" rows="2" placeholder="Ej: Autorizado por gerencia, falla transporte..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRegistroManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-person-plus me-2"></i>Asistencia Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRegistroManual" action="<?php echo e(route_url('asistencia/guardar_manual')); ?>" method="POST">
                <div class="modal-body p-4 bg-light">
                   
                    <div class="alert alert-info small border-0 shadow-sm mb-4">
                        <i class="bi bi-info-circle-fill me-2"></i> Use este formulario para registrar asistencias que no fueron capturadas por el biométrico.
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold mb-1">Empleado <span class="text-danger">*</span></label>
                        <select name="id_tercero" id="selectEmpleadoManual" class="form-select border-secondary-subtle shadow-sm" required>
                            <option value="">Seleccione el personal...</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?= (int)$emp['id'] ?>"><?= e($emp['nombre_completo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold mb-1">Fecha de Registro <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control border-secondary-subtle shadow-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Hora Ingreso</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-box-arrow-in-right text-success"></i></span>
                                <input type="time" name="hora_ingreso" class="form-control border-start-0 shadow-sm">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Hora Salida</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="bi bi-box-arrow-left text-danger"></i></span>
                                <input type="time" name="hora_salida" class="form-control border-start-0 shadow-sm">
                            </div>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small text-muted fw-bold mb-1">Motivo / Observación <span class="text-danger">*</span></label>
                        <textarea name="observaciones" class="form-control border-secondary-subtle shadow-sm" rows="2" placeholder="Describa el motivo del registro manual..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" id="btnGuardarManual" class="btn btn-primary px-4 fw-bold shadow-sm">
                        <i class="bi bi-save me-2"></i>Guardar Asistencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionGrupos" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-people-fill me-2"></i>Gestión de Grupos de Trabajo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light">
    <div class="row g-4">
        
        <div class="col-lg-5 border-end pe-lg-4">
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Crear Nuevo Grupo</h6>
            <form method="post" action="<?php echo e(route_url('asistencia/dashboard')); ?>" id="formCrearGrupo">
                <input type="hidden" name="accion" value="crear_grupo">

                <div class="mb-3">
                    <label class="form-label small text-muted fw-bold mb-1">Nombre del Grupo <span class="text-danger">*</span></label>
                    <input type="text" name="nombre" id="inputNombreGrupo" class="form-control border-secondary-subtle shadow-sm" placeholder="Ej. Cuadrilla Mañana, Equipo Mantenimiento..." required>
                </div>
                
                <div class="p-3 bg-white border border-secondary-subtle rounded shadow-sm mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small text-primary fw-bold mb-0" id="labelPeriodoExcepcion">Fecha</label>
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" role="switch" id="checkRangoDias" style="cursor: pointer;">
                            <label class="form-check-label small text-muted ms-1" for="checkRangoDias" style="cursor: pointer;">Rango de días</label>
                        </div>
                    </div>
                    <div class="row g-2">
                        <div class="col-12" id="colFechaInicio">
                            <input type="date" name="fecha_inicio" id="fechaInicioGrupo" class="form-control border-secondary-subtle" min="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6 d-none" id="colFechaFin">
                            <input type="date" name="fecha_fin" id="fechaFinGrupo" class="form-control border-secondary-subtle" min="<?php echo date('Y-m-d'); ?>" disabled>
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted fw-bold mb-1">Añadir Empleados <span class="text-danger">*</span></label>
                    <select id="selectEmpleadosGrupo" class="form-select shadow-sm" disabled>
                        <option value="">Seleccione primero la fecha...</option>
                        </select>
                    <small class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-info-circle me-1"></i>Los empleados ocupados en las fechas seleccionadas no aparecerán.</small>
                </div>

                <div class="p-2 border bg-white rounded shadow-sm mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="small fw-bold text-dark">Seleccionados (<span id="contadorMiembros">0</span>)</span>
                    </div>
                    <div id="panelMiembros" class="overflow-auto pe-2" style="max-height: 100px; min-height: 60px;">
                        <div id="listaVaciaHint" class="text-center text-muted mt-2 opacity-50 small">
                            <i class="bi bi-person-slash fs-5 d-block mb-1"></i>Vacío
                        </div>
                    </div>
                    <div id="inputMiembrosContenedor"></div>
                </div>

                <div class="p-3 bg-white border border-secondary-subtle rounded shadow-sm mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2 border-bottom pb-2">
                        <label class="form-label small text-primary fw-bold mb-0">Horario de Trabajo</label>
                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btnAgregarTramo" style="font-size: 0.75rem;">+ Tramo</button>
                    </div>
                    
                    <div class="row g-2 mb-2 tramo-horario" id="tramo1">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1" style="font-size: 0.7rem;">Entrada 1</label>
                            <input type="time" name="t1_entrada" class="form-control form-control-sm border-secondary-subtle" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1" style="font-size: 0.7rem;">Salida 1</label>
                            <input type="time" name="t1_salida" class="form-control form-control-sm border-secondary-subtle" required>
                        </div>
                    </div>

                    <div class="row g-2 mb-2 tramo-horario d-none" id="tramo2">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1" style="font-size: 0.7rem;">Entrada 2</label>
                            <input type="time" name="t2_entrada" class="form-control form-control-sm border-secondary-subtle">
                        </div>
                        <div class="col-6 position-relative">
                            <label class="form-label small text-muted mb-1" style="font-size: 0.7rem;">Salida 2</label>
                            <div class="d-flex gap-1">
                                <input type="time" name="t2_salida" class="form-control form-control-sm border-secondary-subtle w-100">
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 js-quitar-tramo" data-target="tramo2"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-0 tramo-horario d-none" id="tramo3">
                        <div class="col-6">
                            <label class="form-label small text-muted mb-1" style="font-size: 0.7rem;">Entrada 3</label>
                            <input type="time" name="t3_entrada" class="form-control form-control-sm border-secondary-subtle">
                        </div>
                        <div class="col-6 position-relative">
                            <label class="form-label small text-muted mb-1" style="font-size: 0.7rem;">Salida 3</label>
                            <div class="d-flex gap-1">
                                <input type="time" name="t3_salida" class="form-control form-control-sm border-secondary-subtle w-100">
                                <button type="button" class="btn btn-sm btn-outline-danger p-1 js-quitar-tramo" data-target="tramo3"><i class="bi bi-x"></i></button>
                            </div>
                        </div>
                    </div>
                    <input type="hidden" name="cantidad_tramos" id="inputCantidadTramos" value="1">
                </div>

                <div class="mb-4">
                    <label class="form-label small text-muted fw-bold mb-1">Tolerancia de Ingreso (minutos) <span class="text-danger">*</span></label>
                    <input type="number" name="tolerancia_minutos" class="form-control border-secondary-subtle shadow-sm" value="0" min="0" required>
                    <small class="text-muted" style="font-size: 0.7rem;"><i class="bi bi-info-circle me-1"></i>Tiempo de gracia antes de marcar como tardanza.</small>
                </div>

                <button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm text-dark" id="btnGuardarGrupoInterno">
                    <i class="bi bi-save me-2"></i>Guardar Grupo
                </button>

            </form>
        </div>

        <div class="col-lg-7 ps-lg-4">
            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Grupos Creados Recientemente</h6>
            <div class="table-responsive border rounded bg-white shadow-sm" style="max-height: 550px; overflow-y: auto;">
                <table class="table align-middle table-hover mb-0">
                    <thead class="bg-light sticky-top">
                        <tr>
                            <th class="ps-3 text-secondary fw-semibold">Grupo y Periodo</th>
                            <th class="text-center text-secondary fw-semibold">Miembros</th>
                            <th class="text-center text-secondary fw-semibold pe-3">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyGruposRecientes">
                        <?php if (empty($grupos)): ?>
                            <tr>
                                <td colspan="3" class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-2 d-block mb-2 text-light"></i>
                                    No hay grupos configurados para las próximas fechas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($grupos as $grupo): ?>
                                <tr>
                                    <td class="ps-3">
                                        <div class="d-flex align-items-center gap-2">
                                            <div style="width: 15px; height: 15px; background-color: <?php echo e($grupo['color'] ?? '#eee'); ?>; border-radius: 4px;"></div>
                                            <div class="fw-bold text-dark"><?php echo e($grupo['nombre']); ?></div>
                                        </div>
                                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-calendar-event me-1"></i> 
                                            <?php if($grupo['fecha_inicio'] === $grupo['fecha_fin']): ?>
                                                <?php echo e($grupo['fecha_inicio']); ?>
                                            <?php else: ?>
                                                <?php echo e($grupo['fecha_inicio']); ?> al <?php echo e($grupo['fecha_fin']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill px-3 py-1">
                                            <?php echo (int)($grupo['total_miembros'] ?? 0); ?>
                                        </span>
                                    </td>
                                    <td class="text-center pe-3">
                                        <button type="button" class="btn btn-sm btn-light text-success border-0 rounded-circle me-1 js-clonar-grupo" 
                                                data-id="<?php echo (int) $grupo['id']; ?>" 
                                                data-bs-toggle="tooltip" title="Usar como plantilla">
                                            <i class="bi bi-copy fs-6"></i>
                                        </button>
                                        <form method="post" action="<?php echo e(route_url('asistencia/dashboard')); ?>" class="d-inline js-form-eliminar-grupo">
                                            <input type="hidden" name="accion" value="eliminar_grupo">
                                            <input type="hidden" name="id_grupo" value="<?php echo (int) $grupo['id']; ?>">
                                            <button type="button" class="btn btn-sm btn-light text-danger border-0 rounded-circle js-btn-eliminar-grupo" data-bs-toggle="tooltip" title="Eliminar Grupo">
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
        </div>

    </div>
</div>
        </div>
    </div>
</div>

<script src="assets/js/rrhh/asistencia.js"></script>
