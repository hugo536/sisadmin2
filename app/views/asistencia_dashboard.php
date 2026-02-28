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
            
            <?php if (tiene_permiso('asistencia.importar')): ?>
                <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-primary shadow-sm fw-semibold">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar Biométrico
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" class="row g-3 align-items-center">
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

                <div class="col-6 col-md-2 d-flex align-items-end" style="height: 60px;">
                    <button class="btn btn-primary shadow-sm fw-bold w-100 h-100" type="submit">
                        <i class="bi bi-search me-2"></i>Consultar
                    </button>
                </div>
            </form>
            <div class="small text-primary-emphasis mt-3 bg-primary-subtle d-inline-block px-3 py-1 rounded-pill fw-medium">
                <i class="bi bi-info-circle me-1"></i> Mostrando periodo del <strong><?php echo e($desde); ?></strong> al <strong><?php echo e($hasta); ?></strong>.
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Registros de Asistencia</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?php echo count($registros); ?> Registros</span>
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
                            <th class="text-center pe-4 text-secondary fw-semibold">Acciones</th> </tr>
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
                                $esperada = '';
                                if (!empty($row['hora_entrada']) || !empty($row['hora_salida'])) {
                                    $esperada = substr((string) ($row['hora_entrada'] ?? ''), 0, 5) . ' - ' . substr((string) ($row['hora_salida'] ?? ''), 0, 5);
                                }

                                $real = '';
                                if (!empty($row['hora_ingreso']) || !empty($row['hora_salida_real'])) {
                                    $real = substr((string) ($row['hora_ingreso'] ?? ''), 11, 5) . ' - ' . substr((string) ($row['hora_salida_real'] ?? ''), 11, 5);
                                }

                                $estado = (string) ($row['estado_asistencia'] ?? 'FALTA');
                                
                                $badgeColor = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                                if ($estado === 'PUNTUAL') $badgeColor = 'bg-success-subtle text-success border border-success-subtle';
                                if ($estado === 'TARDANZA') $badgeColor = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                if ($estado === 'FALTA') $badgeColor = 'bg-danger-subtle text-danger border border-danger-subtle';
                                if ($estado === 'INCOMPLETO') $badgeColor = 'bg-secondary border border-secondary text-white';
                                
                                // Color especial para justificados
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
                                        <i class="bi bi-clock small me-1 opacity-50"></i><?php echo e($esperada !== '' ? $esperada : '-'); ?>
                                    </td>
                                    <td class="fw-medium align-top pt-3 text-primary text-center">
                                        <i class="bi bi-clock-history small me-1 opacity-50"></i><?php echo e($real !== '' ? $real : '-'); ?>
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
                                            data-entrada="<?php echo substr((string)($row['hora_entrada'] ?? ''), 0, 5); ?>"
                                            data-salida="<?php echo substr((string)($row['hora_salida'] ?? ''), 0, 5); ?>"
                                            data-bs-toggle="tooltip" title="Gestionar Excepciones">
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
                <h5 class="modal-title fw-bold"><i class="bi bi-person-gear me-2"></i>Gestionar Asistencia</h5>
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

                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">1. Ajustar Turno del Día (Excepción)</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Hora Entrada Esperada</label>
                            <input type="time" class="form-control bg-white shadow-sm border-secondary-subtle" name="hora_entrada_esperada" id="gestHoraEntrada" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Hora Salida Esperada</label>
                            <input type="time" class="form-control bg-white shadow-sm border-secondary-subtle" name="hora_salida_esperada" id="gestHoraSalida" required>
                        </div>
                        <div class="col-12 mt-1">
                            <small class="text-muted"><i class="bi bi-exclamation-triangle me-1"></i>Modificar esto recalculará la tardanza automáticamente en base a la hora real de llegada.</small>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Lógica para llenar los datos del Modal cuando se hace clic en la tuerca
    const botonesGestion = document.querySelectorAll('.js-gestionar-asistencia');
    
    botonesGestion.forEach(btn => {
        btn.addEventListener('click', function() {
            // Llenar campos ocultos
            document.getElementById('gestIdAsistencia').value = this.dataset.id;
            document.getElementById('gestIdTercero').value = this.dataset.tercero;
            document.getElementById('gestFecha').value = this.dataset.fecha;
            
            // Llenar textos visuales
            document.getElementById('gestNombreEmpleado').innerText = this.dataset.nombre;
            document.getElementById('gestFechaDisplay').innerText = 'Día: ' + this.dataset.fecha;
            
            // Llenar inputs de hora
            document.getElementById('gestHoraEntrada').value = this.dataset.entrada !== '-' ? this.dataset.entrada : '';
            document.getElementById('gestHoraSalida').value = this.dataset.salida !== '-' ? this.dataset.salida : '';
            
            // Resetear justificación al abrir
            document.getElementById('gestCheckJustificar').checked = false;
            document.getElementById('boxJustificacion').classList.add('d-none');
            document.getElementById('gestObservacion').value = '';
        });
    });

    // Lógica para mostrar/ocultar la caja de justificación
    const checkJustificar = document.getElementById('gestCheckJustificar');
    const boxJustificacion = document.getElementById('boxJustificacion');
    const obsInput = document.getElementById('gestObservacion');
    
    if(checkJustificar) {
        checkJustificar.addEventListener('change', function() {
            if (this.checked) {
                boxJustificacion.classList.remove('d-none');
                obsInput.setAttribute('required', 'required');
            } else {
                boxJustificacion.classList.add('d-none');
                obsInput.removeAttribute('required');
            }
        });
    }
});
</script>