<?php
$horarios = $horarios ?? [];
$empleados = $empleados ?? [];
$asignaciones = $asignaciones ?? [];
$empleadosAgrupados = $empleadosAgrupados ?? []; // Nuestra nueva variable agrupada del controlador
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar-week me-2 text-primary"></i> Horarios y Asignaciones
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo de turnos y asignación de horarios por empleado/día.</p>
        </div>

        <div class="d-flex gap-2">
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
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                        <i class="bi bi-clock-history me-2 text-primary"></i>Crear / Editar Turno
                    </h6>
                    <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="row g-3" id="horarioForm">
                        <input type="hidden" name="accion" value="guardar_horario">
                        <input type="hidden" name="id" id="horarioId" value="0">

                        <div class="col-12">
                            <label for="horarioNombre" class="form-label small text-muted fw-bold">Nombre del turno <span class="text-danger">*</span></label>
                            <input type="text" class="form-control bg-light border-secondary-subtle" name="nombre" id="horarioNombre" placeholder="Ej. Turno Mañana" maxlength="100" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="horarioEntrada" class="form-label small text-muted fw-bold">Hora Entrada <span class="text-danger">*</span></label>
                            <input type="time" class="form-control bg-light border-secondary-subtle text-secondary fw-medium" name="hora_entrada" id="horarioEntrada" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="horarioSalida" class="form-label small text-muted fw-bold">Hora Salida <span class="text-danger">*</span></label>
                            <input type="time" class="form-control bg-light border-secondary-subtle text-secondary fw-medium" name="hora_salida" id="horarioSalida" required>
                        </div>
                        
                        <div class="col-12">
                            <label for="horarioTolerancia" class="form-label small text-muted fw-bold">Tolerancia (minutos) <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm-sm">
                                <input type="number" class="form-control bg-light border-secondary-subtle border-end-0" name="tolerancia_minutos" id="horarioTolerancia" min="0" step="1" value="0" required>
                                <span class="input-group-text bg-light border-secondary-subtle text-muted">min</span>
                            </div>
                        </div>
                        
                        <div class="col-12 d-flex justify-content-end gap-2 mt-4">
                            <button type="button" class="btn btn-white border shadow-sm fw-semibold" id="btnLimpiarHorario">Limpiar</button>
                            <button class="btn btn-primary shadow-sm fw-semibold" type="submit">
                                <i class="bi bi-save me-1"></i> Guardar Turno
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 ps-4">
                    <h2 class="h6 fw-bold text-dark mb-0">Catálogo de Turnos</h2>
                </div>
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-pro table-hover">
                            <thead class="table-light border-bottom border-top">
                                <tr>
                                    <th class="ps-4 text-secondary fw-semibold">Nombre</th>
                                    <th class="text-secondary fw-semibold">Rango Horario</th>
                                    <th class="text-secondary fw-semibold text-center">Tolerancia</th>
                                    <th class="text-secondary fw-semibold text-center">Estado</th>
                                    <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($horarios)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                            No hay turnos registrados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($horarios as $horario): ?>
                                        <?php $activo = ((int) $horario['estado'] === 1); ?>
                                        <tr class="border-bottom">
                                            <td class="ps-4 fw-semibold text-dark align-top pt-3">
                                                <?php echo e($horario['nombre']); ?>
                                            </td>
                                            <td class="align-top pt-3 fw-medium text-secondary">
                                                <i class="bi bi-clock small text-muted me-1"></i>
                                                <?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?> - <?php echo e(substr((string) $horario['hora_salida'], 0, 5)); ?>
                                            </td>
                                            <td class="text-center align-top pt-3 text-muted">
                                                <?php echo (int) $horario['tolerancia_minutos']; ?> min
                                            </td>
                                            <td class="text-center align-top pt-3">
                                                <?php if($activo): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Activo</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Inactivo</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4 align-top pt-3">
                                                <button type="button" class="btn btn-sm btn-light text-primary border-0 rounded-circle js-editar-horario me-1" 
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
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 d-flex align-items-center">
            <h2 class="h6 fw-bold text-dark mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Asignación Masiva de Horarios</h2>
        </div>
        
        <div class="card-body p-4 bg-light border-bottom">
            <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="row g-3 align-items-start" id="asignacionMasivaForm">
                <input type="hidden" name="accion" value="guardar_asignacion">
                
                <div class="col-md-5">
                    <div class="d-flex justify-content-between align-items-end mb-1">
                        <label class="form-label small text-muted fw-bold mb-0">Selecciona Empleado(s) <span class="text-danger">*</span></label>
                        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0 fw-semibold js-select-all" style="font-size: 0.75rem;">Seleccionar Todos</button>
                    </div>
                    
                    <div class="input-group mb-2">
                        <select id="empleadoSelectorMasivo" class="form-select border-secondary-subtle">
                            <option value="">Buscar o seleccionar empleado...</option>
                            <?php foreach ($empleados as $empleado): ?>
                                <option value="<?php echo (int) $empleado['id']; ?>" data-nombre="<?php echo e($empleado['nombre_completo']); ?>" data-codigo="<?php echo e((string) ($empleado['codigo_biometrico'] ?? '')); ?>">
                                    <?php echo e($empleado['nombre_completo']); ?>
                                    <?php echo !empty($empleado['codigo_biometrico']) ? ' (Cód: ' . e($empleado['codigo_biometrico']) . ')' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-outline-primary" type="button" id="btnAgregarEmpleadoMasivo" title="Agregar a la selección">
                            <i class="bi bi-plus-lg"></i>
                        </button>
                    </div>
                    
                    <div id="empleadosMasivoSeleccionados" class="d-flex flex-wrap gap-2"></div>
                    <div id="empleadosMasivoInputs"></div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Día de la Semana <span class="text-danger">*</span></label>
                    <select name="dia_semana" class="form-select border-secondary-subtle" required>
                        <option value="">Seleccione día...</option>
                        <option value="0" class="fw-bold text-primary">Toda la semana (Lun - Dom)</option>
                        <option disabled>-----------------------</option>
                        <?php foreach ($dias as $num => $dia): ?>
                            <option value="<?php echo $num; ?>"><?php echo e($dia); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Turno a Asignar <span class="text-danger">*</span></label>
                    <select name="id_horario" class="form-select border-secondary-subtle" required>
                        <option value="">Seleccione turno...</option>
                        <?php foreach ($horarios as $horario): ?>
                            <?php if ((int) $horario['estado'] !== 1) continue; ?>
                            <option value="<?php echo (int) $horario['id']; ?>"><?php echo e($horario['nombre']); ?> (<?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-1 d-flex align-items-end" style="height: 60px;">
                    <button class="btn btn-primary shadow-sm w-100 fw-semibold h-100" type="submit" data-bs-toggle="tooltip" title="Guardar Asignaciones">
                        <i class="bi bi-floppy fs-5"></i>
                    </button>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro table-hover">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 30%;">Empleado</th>
                            <th class="text-secondary fw-semibold">Semana Asignada (Turnos)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($empleadosAgrupados)): ?>
                            <tr>
                                <td colspan="2" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    Aún no hay asignaciones configuradas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($empleadosAgrupados as $idEmp => $emp): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 fw-semibold text-dark align-top pt-4">
                                        <?php echo e($emp['nombre_completo']); ?>
                                        <?php if(!empty($emp['codigo_biometrico'])): ?>
                                            <span class="d-block badge bg-light text-secondary border mt-2 w-auto" style="font-size: 0.7rem; width: fit-content;">
                                                <i class="bi bi-upc-scan me-1"></i> Cód: <?php echo e($emp['codigo_biometrico']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="align-top pt-3 pb-3">
                                        <div class="d-flex flex-wrap gap-2">
                                            <?php 
                                            // Ordenamos para que los días salgan de Lunes a Domingo
                                            ksort($emp['dias_asignados']); 
                                            foreach ($emp['dias_asignados'] as $diaNumero => $info): 
                                            ?>
                                                <div class="border border-secondary-subtle rounded px-3 py-2 bg-white shadow-sm d-flex align-items-center" style="font-size: 0.85rem;">
                                                    <div>
                                                        <span class="fw-bold text-dark d-block mb-1"><?php echo $info['nombre_dia']; ?></span>
                                                        <span class="text-primary fw-semibold" style="font-size: 0.75rem;">
                                                            <?php echo e($info['nombre_horario']); ?> 
                                                            <small class="text-muted fw-normal ms-1">(<?php echo $info['hora_entrada']; ?> - <?php echo $info['hora_salida']; ?>)</small>
                                                        </span>
                                                    </div>
                                                    
                                                    <div class="ms-3 border-start ps-2">
                                                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" onsubmit="return confirm('¿Quitar el turno del <?php echo $info['nombre_dia']; ?> a este empleado?')" class="d-inline mb-0">
                                                            <input type="hidden" name="accion" value="eliminar_asignacion">
                                                            <input type="hidden" name="id" value="<?php echo (int) $info['id_asignacion']; ?>">
                                                            <button type="submit" class="btn btn-sm text-danger p-0 border-0 bg-transparent" data-bs-toggle="tooltip" title="Eliminar este turno">
                                                                <i class="bi bi-x-circle-fill fs-5"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    // --- LÓGICA DE CREAR/EDITAR TURNO ---
    const idInput = document.getElementById('horarioId');
    const nombreInput = document.getElementById('horarioNombre');
    const entradaInput = document.getElementById('horarioEntrada');
    const salidaInput = document.getElementById('horarioSalida');
    const toleranciaInput = document.getElementById('horarioTolerancia');

    document.querySelectorAll('.js-editar-horario').forEach((btn) => {
        btn.addEventListener('click', function () {
            idInput.value = this.dataset.id || '0';
            nombreInput.value = this.dataset.nombre || '';
            entradaInput.value = this.dataset.entrada || '';
            salidaInput.value = this.dataset.salida || '';
            toleranciaInput.value = this.dataset.tolerancia || '0';
            
            nombreInput.focus();
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    });

    const limpiar = document.getElementById('btnLimpiarHorario');
    if (limpiar) {
        limpiar.addEventListener('click', function () {
            idInput.value = '0';
            nombreInput.value = '';
            entradaInput.value = '';
            salidaInput.value = '';
            toleranciaInput.value = '0';
        });
    }

    // --- LÓGICA DE ASIGNACIÓN MASIVA (CHIPS) ---
    const asignacionForm = document.getElementById('asignacionMasivaForm');
    const empleadoSelectorMasivo = document.getElementById('empleadoSelectorMasivo');
    const btnAgregarEmpleadoMasivo = document.getElementById('btnAgregarEmpleadoMasivo');
    const empleadosMasivoSeleccionados = document.getElementById('empleadosMasivoSeleccionados');
    const empleadosMasivoInputs = document.getElementById('empleadosMasivoInputs');
    const btnSeleccionarTodos = document.querySelector('.js-select-all');
    
    const empleadosElegidos = new Map();

    const renderEmpleadosMasivos = () => {
        if (!empleadosMasivoSeleccionados || !empleadosMasivoInputs) return;

        empleadosMasivoSeleccionados.innerHTML = '';
        empleadosMasivoInputs.innerHTML = '';

        empleadosElegidos.forEach((empleado, id) => {
            const chip = document.createElement('span');
            chip.className = 'badge bg-white text-dark border d-inline-flex align-items-center gap-2 px-2 py-2 mt-1 shadow-sm';
            chip.innerHTML = `
                <span>${empleado.nombre}${empleado.codigo ? ` <small class="text-muted fw-normal ms-1">(Cód: ${empleado.codigo})</small>` : ''}</span>
                <button type="button" class="btn btn-sm p-0 border-0 bg-transparent text-danger ms-1" data-remove-id="${id}" title="Quitar">
                    <i class="bi bi-x-circle-fill fs-6"></i>
                </button>
            `;
            empleadosMasivoSeleccionados.appendChild(chip);

            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'id_terceros[]';
            hidden.value = id;
            empleadosMasivoInputs.appendChild(hidden);
        });
    };

    if (btnAgregarEmpleadoMasivo && empleadoSelectorMasivo) {
        btnAgregarEmpleadoMasivo.addEventListener('click', function () {
            const option = empleadoSelectorMasivo.options[empleadoSelectorMasivo.selectedIndex];
            const id = option?.value || '';
            if (!id) return;

            if (!empleadosElegidos.has(id)) {
                empleadosElegidos.set(id, {
                    nombre: option.dataset.nombre || option.textContent.trim(),
                    codigo: option.dataset.codigo || ''
                });
                renderEmpleadosMasivos();
            }
            empleadoSelectorMasivo.value = '';
        });

        empleadoSelectorMasivo.addEventListener('change', function (event) {
            if (event.target.value) {
                btnAgregarEmpleadoMasivo.click();
            }
        });
    }

    if (btnSeleccionarTodos && empleadoSelectorMasivo) {
        let todosSeleccionados = false;
        btnSeleccionarTodos.addEventListener('click', function() {
            todosSeleccionados = !todosSeleccionados;
            if(todosSeleccionados) {
                for (let i = 1; i < empleadoSelectorMasivo.options.length; i++) {
                    const opt = empleadoSelectorMasivo.options[i];
                    empleadosElegidos.set(opt.value, {
                        nombre: opt.dataset.nombre || opt.textContent.trim(),
                        codigo: opt.dataset.codigo || ''
                    });
                }
                this.innerText = 'Deseleccionar Todos';
                this.classList.replace('text-primary', 'text-danger');
            } else {
                empleadosElegidos.clear();
                this.innerText = 'Seleccionar Todos';
                this.classList.replace('text-danger', 'text-primary');
            }
            renderEmpleadosMasivos();
        });
    }

    if (empleadosMasivoSeleccionados) {
        empleadosMasivoSeleccionados.addEventListener('click', function (event) {
            const btn = event.target.closest('[data-remove-id]');
            if (!btn) return;
            const id = btn.dataset.removeId || '';
            if (!id) return;
            empleadosElegidos.delete(id);
            renderEmpleadosMasivos();
            
            if(btnSeleccionarTodos && empleadosElegidos.size === 0) {
                btnSeleccionarTodos.innerText = 'Seleccionar Todos';
                btnSeleccionarTodos.classList.replace('text-danger', 'text-primary');
            }
        });
    }

    if (asignacionForm) {
        asignacionForm.addEventListener('submit', function (event) {
            if (empleadosElegidos.size === 0) {
                event.preventDefault();
                alert('Debes seleccionar al menos un empleado para asignar el horario.');
            }
        });
    }

    // Inicializar Tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl)
    })
});
</script>