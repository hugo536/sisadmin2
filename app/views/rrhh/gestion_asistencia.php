<?php
$periodo = $periodo ?? 'semana';
$semana = $semana ?? date('o-\WW');
$mes = $mes ?? date('Y-m');
$fechaInicio = $fecha_inicio ?? date('Y-m-d');
$fechaFin = $fecha_fin ?? date('Y-m-d');

$empleados = $empleados ?? [];
?>

<style>
    /* Estilos para la "Cuadrícula de Edición" */
    .grid-asistencia th {
        background-color: #f8f9fa;
        color: #495057;
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
    }
    .grid-asistencia td {
        vertical-align: middle;
        padding: 0;
        border: 1px solid #e9ecef;
        position: relative;
    }
    .grid-asistencia td:hover {
        background-color: #f1f8ff;
    }
    
    /* Input invisible hasta que se hace focus */
    .cell-input {
        width: 100%;
        min-width: 65px;
        height: 42px;
        border: 2px solid transparent;
        background: transparent;
        text-align: center;
        font-weight: 600;
        color: #0b5ed7;
        outline: none;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    .cell-input:focus {
        background-color: #fff;
        border-color: #0b5ed7;
        box-shadow: inset 0 0 5px rgba(11, 94, 215, 0.2);
        z-index: 10;
        position: relative;
    }
    .cell-input::placeholder { color: #adb5bd; font-weight: normal; }

    /* Barra lateral de empleados */
    .sidebar-empleados {
        max-height: calc(100vh - 180px);
        overflow-y: auto;
        background-color: #fff;
    }
    .empleado-item {
        cursor: pointer;
        border-left: 4px solid transparent;
        transition: all 0.2s;
    }
    .empleado-item:hover { background-color: #f8f9fa; }
    .empleado-item.active {
        background-color: #e9ecef;
        border-left-color: #0b5ed7;
    }
</style>

<div class="container-fluid p-4" id="gestionAsistenciaApp">

    <!-- CABECERA -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar3-range me-2 text-primary"></i> Gestión de Asistencia
            </h1>
            <p class="text-muted small mb-0 ms-1">Edición directa con autoguardado y gestión de justificaciones.</p>
        </div>

        <div class="d-flex gap-3 flex-wrap justify-content-end align-items-center">
            
            <!-- Controles de Periodo -->
            <div class="d-flex bg-white border border-secondary-subtle rounded-2 p-1 shadow-sm align-items-center">
                <select class="form-select form-select-sm border-0 shadow-none fw-bold text-secondary bg-transparent" id="tipoPeriodo" style="width: 110px;">
                    <option value="semana" <?php echo $periodo === 'semana' ? 'selected' : ''; ?>>Semana</option>
                    <option value="mes" <?php echo $periodo === 'mes' ? 'selected' : ''; ?>>Mes</option>
                    <option value="rango" <?php echo $periodo === 'rango' ? 'selected' : ''; ?>>Rango</option>
                </select>
                
                <div class="vr mx-1 bg-secondary-subtle"></div>

                <!-- Input Semana -->
                <input type="week" class="form-control form-control-sm border-0 shadow-none fw-bold text-primary filter-input <?php echo $periodo !== 'semana' ? 'd-none' : ''; ?>" id="filtroSemana" value="<?php echo htmlspecialchars($semana); ?>">
                
                <!-- Input Mes -->
                <input type="month" class="form-control form-control-sm border-0 shadow-none fw-bold text-primary filter-input <?php echo $periodo !== 'mes' ? 'd-none' : ''; ?>" id="filtroMes" value="<?php echo htmlspecialchars($mes); ?>">
                
                <!-- Inputs Rango -->
                <div class="d-flex align-items-center filter-input <?php echo $periodo !== 'rango' ? 'd-none' : ''; ?>" id="filtroRango">
                    <input type="date" class="form-control form-control-sm border-0 shadow-none fw-bold text-primary px-1" id="filtroDesde" value="<?php echo htmlspecialchars($fechaInicio); ?>" title="Desde">
                    <span class="text-muted small mx-1">al</span>
                    <input type="date" class="form-control form-control-sm border-0 shadow-none fw-bold text-primary px-1" id="filtroHasta" value="<?php echo htmlspecialchars($fechaFin); ?>" title="Hasta">
                </div>
            </div>

        </div>
    </div>

    <!-- ÁREA DE TRABAJO -->
    <div class="card border-0 shadow-sm overflow-hidden fade-in">
        <div class="row g-0">
            
            <!-- Columna Izquierda: Buscador y Lista de Empleados -->
            <div class="col-md-3 border-end bg-white d-flex flex-column" style="height: calc(100vh - 180px);">
                <div class="p-3 border-bottom bg-light">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="text" class="form-control border-start-0 shadow-none" id="buscarEmpleado" placeholder="Buscar empleado...">
                    </div>
                </div>
                
                <div class="list-group list-group-flush rounded-0 sidebar-empleados flex-grow-1" id="listaEmpleados">
                    <?php foreach ($empleados as $emp): ?>
                        <div class="list-group-item empleado-item border-bottom py-3" data-id="<?php echo (int)$emp['id']; ?>">
                            <div class="d-flex align-items-center gap-1 mb-1">
                                <div class="fw-bold text-dark flex-grow-1" style="font-size: 0.85rem; line-height: 1.2;"><?php echo htmlspecialchars($emp['nombre_completo']); ?></div>
                                <!-- Se eliminó la alerta de 'sin_horario' ya que el cálculo es dinámico -->
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge bg-light text-secondary border fw-normal" style="font-size: 0.7rem;">Cód: <?php echo htmlspecialchars($emp['codigo_biometrico'] ?? 'N/A'); ?></span>
                                <i class="bi bi-exclamation-circle text-warning d-none" title="Inconsistencias"></i>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Columna Derecha: Cuadrícula de Edición -->
            <div class="col-md-9 bg-white d-flex flex-column" style="height: calc(100vh - 180px);">
                
                <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold text-dark d-flex align-items-center" id="nombreEmpleadoActivo">
                            <i class="bi bi-person-fill text-muted me-2"></i>Esperando selección...
                        </h5>
                        <small class="text-muted fw-semibold" id="rangoActivoLabel">--</small>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div id="syncStatus" class="small fw-bold me-2 text-muted" style="width: 110px; text-align: right;"></div>
                        <!-- Resumen global de horas del empleado seleccionado -->
                        <span class="badge bg-light text-dark border px-3 py-2 rounded-pill shadow-sm d-flex gap-3 align-items-center">
                            <span>Regulares: <strong class="text-primary" id="totalRegulares">0h</strong></span>
                            <div class="vr"></div>
                            <span>Extras: <strong class="text-danger" id="totalExtras">0h</strong></span>
                        </span>
                    </div>
                </div>

                <div class="p-0 overflow-auto flex-grow-1 bg-white position-relative" id="gridContainer">
                    <table class="table table-hover grid-asistencia mb-0 text-center">
                        <thead class="sticky-top z-2 shadow-sm">
                            <tr>
                                <th style="width: 12%; background: #f8f9fa;" class="align-middle" rowspan="2">Fecha</th>
                                <th colspan="2" style="background: #e9ecef;" class="border-start">Tramo 1</th>
                                <th colspan="2" style="background: #f8f9fa;" class="border-start">Tramo 2</th>
                                <th colspan="2" style="background: #e9ecef;" class="border-start">Tramo 3</th>
                                <!-- Columna recomendada: suma de horas de los 3 tramos (NO dinero) -->
                                <th style="width: 8%; background: #fff3cd;" class="align-middle border-start text-dark" rowspan="2">Horas Día</th>
                                <th style="width: 20%; background: #f8f9fa;" class="align-middle border-start" rowspan="2">Estado / Observación</th>
                            </tr>
                            <tr>
                                <th class="py-2 border-start" style="background: #e9ecef; font-size: 0.7rem;">Ingreso</th>
                                <th class="py-2" style="background: #e9ecef; font-size: 0.7rem;">Salida</th>
                                <th class="py-2 border-start" style="background: #f8f9fa; font-size: 0.7rem;">Ingreso</th>
                                <th class="py-2" style="background: #f8f9fa; font-size: 0.7rem;">Salida</th>
                                <th class="py-2 border-start" style="background: #e9ecef; font-size: 0.7rem;">Ingreso</th>
                                <th class="py-2" style="background: #e9ecef; font-size: 0.7rem;">Salida</th>
                            </tr>
                        </thead>
                        <tbody id="gridAsistenciaCuerpo">
                            <!-- Estado Vacío Inicial -->
                            <tr>
                                <td colspan="9" class="text-center py-5 bg-light border-bottom-0">
                                    <i class="bi bi-person-lines-fill d-block text-muted opacity-25 mb-3" style="font-size: 4rem;"></i>
                                    <h5 class="fw-bold text-dark">Selecciona un Empleado</h5>
                                    <p class="text-muted small mb-0">Haz clic en un empleado del panel lateral izquierdo para cargar su cuadrícula de asistencia.</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL DE JUSTIFICACIONES / PERMISOS        -->
<!-- ========================================== -->
<div class="modal fade" id="modalJustificar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-shield-check me-2"></i>Estado y Justificación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary-subtle">
                    <strong class="text-dark small text-uppercase">Fecha seleccionada:</strong>
                    <span class="badge bg-white text-primary border fs-6 px-3" id="modalJustificarFecha">--/--/----</span>
                </div>

                <div class="mb-3">
                    <label class="form-label small text-muted fw-bold mb-1">Estado Forzado <span class="text-danger">*</span></label>
                    <select class="form-select bg-white border-secondary-subtle shadow-none fw-medium" id="selectEstadoJustificacion">
                        <option value="ASISTENCIA">Automático (Según marcación)</option>
                        <option value="FALTA_JUSTIFICADA">Falta Justificada</option>
                        <option value="PERMISO">Permiso (Salida Temprano)</option>
                        <option value="OLVIDO">Olvido de Marcación</option>
                        <option value="DESCANSO_MEDICO">Descanso Médico</option>
                        <option value="FERIADO">Día Feriado / Libre</option>
                    </select>
                </div>

                <div class="mb-0">
                    <label class="form-label small text-muted fw-bold mb-1">Observación (Opcional)</label>
                    <textarea class="form-control bg-white border-secondary-subtle shadow-none" id="txtObservacionJustificacion" rows="3" placeholder="Ej: Autorizado por gerencia para apoyar en planta..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top shadow-sm">
                <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning fw-bold shadow-sm px-4" id="btnAplicarJustificacion">
                    Guardar
                </button>
            </div>
        </div>
    </div>
</div>