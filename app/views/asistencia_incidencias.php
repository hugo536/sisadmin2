<?php
$empleados = $empleados ?? [];
$incidencias = $incidencias ?? [];
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-0">Módulo de Incidencias</h1>
            <small class="text-muted">Registrar vacaciones, descansos médicos y permisos.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Importación
            </a>
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-bar-chart-line me-1"></i> Dashboard RRHH
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Nueva Incidencia</h2></div>
        <div class="card-body bg-light">
            <form method="post" action="<?php echo e(route_url('asistencia/incidencias')); ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                <input type="hidden" name="accion" value="guardar">
                <div class="col-md-4">
                    <label class="form-label">Empleado</label>
                    <select class="form-select" name="id_tercero" required>
                        <option value="">Seleccionar...</option>
                        <?php foreach ($empleados as $emp): ?>
                            <option value="<?php echo (int) ($emp['id'] ?? 0); ?>"><?php echo e((string) ($emp['nombre_completo'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Tipo incidencia</label>
                    <select class="form-select" name="tipo_incidencia" required>
                        <option value="VACACIONES">VACACIONES</option>
                        <option value="DESCANSO_MEDICO">DESCANSO_MEDICO</option>
                        <option value="PERMISO_PERSONAL">PERMISO_PERSONAL</option>
                        <option value="SUBSIDIO">SUBSIDIO</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">F. Inicio</label>
                    <input type="date" class="form-control" name="fecha_inicio" required>
                </div>
                <div class="col-md-2">
                    <label class="form-label">F. Fin</label>
                    <input type="date" class="form-control" name="fecha_fin" required>
                </div>
                <div class="col-md-1">
                    <label class="form-label">Goce</label>
                    <select class="form-select" name="con_goce_sueldo">
                        <option value="1">Sí</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label">Documento respaldo (PDF/JPG/PNG)</label>
                    <input type="file" class="form-control" name="documento_respaldo" accept=".pdf,.jpg,.jpeg,.png">
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit">Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white"><h2 class="h6 mb-0">Incidencias Registradas</h2></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Goce</th>
                        <th>Documento</th>
                        <th class="text-end">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$incidencias): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Sin incidencias registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($incidencias as $row): ?>
                            <tr>
                                <td><?php echo e((string) ($row['empleado'] ?? '')); ?></td>
                                <td><?php echo e((string) ($row['tipo_incidencia'] ?? '')); ?></td>
                                <td><?php echo e((string) ($row['fecha_inicio'] ?? '')); ?></td>
                                <td><?php echo e((string) ($row['fecha_fin'] ?? '')); ?></td>
                                <td><?php echo ((int) ($row['con_goce_sueldo'] ?? 1) === 1) ? 'Sí' : 'No'; ?></td>
                                <td>
                                    <?php if (!empty($row['documento_respaldo'])): ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(base_url() . '/' . ltrim((string) $row['documento_respaldo'], '/')); ?>" target="_blank">Ver</a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <form method="post" action="<?php echo e(route_url('asistencia/incidencias')); ?>" onsubmit="return confirm('¿Eliminar incidencia?');" class="d-inline">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int) ($row['id'] ?? 0); ?>">
                                        <button class="btn btn-sm btn-outline-danger">Eliminar</button>
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
