<?php
$horarios = $horarios ?? [];
$empleados = $empleados ?? [];
$asignaciones = $asignaciones ?? [];
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Asistencia · Horarios y Asignaciones</h1>
        <p class="text-muted mb-0">Bloque 1: catálogo de turnos y asignación por empleado/día.</p>
    </div>
    <a class="btn btn-outline-primary" href="<?php echo e(route_url('asistencia/importar')); ?>">
        <i class="bi bi-file-earmark-arrow-up me-1"></i> Ir a Importación TXT
    </a>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h2 class="h6 mb-0">Crear / Editar horario</h2>
            </div>
            <div class="card-body bg-light">
                <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="row g-2" id="horarioForm">
                    <input type="hidden" name="accion" value="guardar_horario">
                    <input type="hidden" name="id" id="horarioId" value="0">

                    <div class="col-12 form-floating">
                        <input type="text" class="form-control" name="nombre" id="horarioNombre" placeholder="Nombre" maxlength="100" required>
                        <label for="horarioNombre">Nombre del horario</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input type="time" class="form-control" name="hora_entrada" id="horarioEntrada" required>
                        <label for="horarioEntrada">Hora entrada</label>
                    </div>
                    <div class="col-md-6 form-floating">
                        <input type="time" class="form-control" name="hora_salida" id="horarioSalida" required>
                        <label for="horarioSalida">Hora salida</label>
                    </div>
                    <div class="col-12 form-floating">
                        <input type="number" class="form-control" name="tolerancia_minutos" id="horarioTolerancia" min="0" step="1" value="0" required>
                        <label for="horarioTolerancia">Tolerancia (minutos)</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-light" id="btnLimpiarHorario">Limpiar</button>
                        <button class="btn btn-primary">Guardar horario</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-white"><h2 class="h6 mb-0">Horarios registrados</h2></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nombre</th><th>Entrada</th><th>Salida</th><th>Tolerancia</th><th>Estado</th><th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($horarios as $horario): ?>
                        <tr>
                            <td><?php echo e($horario['nombre']); ?></td>
                            <td><?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?></td>
                            <td><?php echo e(substr((string) $horario['hora_salida'], 0, 5)); ?></td>
                            <td><?php echo (int) $horario['tolerancia_minutos']; ?> min</td>
                            <td><?php echo ((int) $horario['estado'] === 1) ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'; ?></td>
                            <td class="text-end">
                                <button
                                    class="btn btn-sm btn-outline-primary js-editar-horario"
                                    data-id="<?php echo (int) $horario['id']; ?>"
                                    data-nombre="<?php echo e($horario['nombre']); ?>"
                                    data-entrada="<?php echo e(substr((string) $horario['hora_entrada'], 0, 5)); ?>"
                                    data-salida="<?php echo e(substr((string) $horario['hora_salida'], 0, 5)); ?>"
                                    data-tolerancia="<?php echo (int) $horario['tolerancia_minutos']; ?>"
                                >Editar</button>
                                <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="d-inline">
                                    <input type="hidden" name="accion" value="cambiar_estado_horario">
                                    <input type="hidden" name="id" value="<?php echo (int) $horario['id']; ?>">
                                    <input type="hidden" name="estado" value="<?php echo ((int) $horario['estado'] === 1) ? 0 : 1; ?>">
                                    <button class="btn btn-sm btn-outline-secondary"><?php echo ((int) $horario['estado'] === 1) ? 'Desactivar' : 'Activar'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($horarios)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-3">No hay horarios aún.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mt-3">
    <div class="card-header bg-white"><h2 class="h6 mb-0">Asignación de horarios a empleados</h2></div>
    <div class="card-body bg-light">
        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" class="row g-2 align-items-end">
            <input type="hidden" name="accion" value="guardar_asignacion">
            <div class="col-md-4">
                <label class="form-label mb-1">Empleado</label>
                <select name="id_tercero" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($empleados as $empleado): ?>
                        <option value="<?php echo (int) $empleado['id']; ?>"><?php echo e($empleado['nombre_completo']); ?><?php echo !empty($empleado['codigo_biometrico']) ? ' · ' . e($empleado['codigo_biometrico']) : ''; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Día de semana</label>
                <select name="dia_semana" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($dias as $num => $dia): ?>
                        <option value="<?php echo $num; ?>"><?php echo e($dia); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label mb-1">Horario</label>
                <select name="id_horario" class="form-select" required>
                    <option value="">Seleccionar...</option>
                    <?php foreach ($horarios as $horario): ?>
                        <?php if ((int) $horario['estado'] !== 1) continue; ?>
                        <option value="<?php echo (int) $horario['id']; ?>"><?php echo e($horario['nombre']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button class="btn btn-primary w-100">Guardar</button>
            </div>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Empleado</th><th>Código Biométrico</th><th>Día</th><th>Horario</th><th>Rango</th><th class="text-end">Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($asignaciones as $asignacion): ?>
                <tr>
                    <td><?php echo e($asignacion['empleado']); ?></td>
                    <td><?php echo e($asignacion['codigo_biometrico'] ?? ''); ?></td>
                    <td><?php echo e($dias[(int) $asignacion['dia_semana']] ?? 'N/A'); ?></td>
                    <td><?php echo e($asignacion['horario']); ?></td>
                    <td><?php echo e(substr((string) $asignacion['hora_entrada'], 0, 5)); ?> - <?php echo e(substr((string) $asignacion['hora_salida'], 0, 5)); ?></td>
                    <td class="text-end">
                        <form method="post" action="<?php echo e(route_url('horario/index')); ?>" onsubmit="return confirm('¿Eliminar asignación?')">
                            <input type="hidden" name="accion" value="eliminar_asignacion">
                            <input type="hidden" name="id" value="<?php echo (int) $asignacion['id']; ?>">
                            <button class="btn btn-sm btn-outline-danger">Eliminar</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($asignaciones)): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No hay asignaciones registradas.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
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
});
</script>
