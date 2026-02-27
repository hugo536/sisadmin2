<?php
$fecha = $fecha ?? date('Y-m-d');
$registros = $registros ?? [];
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-0">Dashboard Diario de RRHH</h1>
            <small class="text-muted">Control diario de puntualidad, tardanzas y faltas.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-outline-primary">
                <i class="bi bi-file-earmark-arrow-up me-1"></i> Importación
            </a>
            <a href="<?php echo e(route_url('asistencia/incidencias')); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-clipboard2-pulse me-1"></i> Incidencias
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="ruta" value="asistencia/dashboard">
                <div class="col-md-3">
                    <label class="form-label">Fecha</label>
                    <input type="date" class="form-control" name="fecha" value="<?php echo e($fecha); ?>" required>
                </div>
                <div class="col-md-2 d-grid">
                    <button class="btn btn-primary" type="submit">Ver</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0">Resultado de <?php echo e($fecha); ?></h2>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Hora Esperada</th>
                        <th>Hora Real</th>
                        <th>Estado</th>
                        <th class="text-end">Min. Tardanza</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$registros): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No hay datos para la fecha seleccionada.</td></tr>
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
                            $badge = 'text-bg-secondary';
                            if ($estado === 'PUNTUAL') $badge = 'text-bg-success';
                            if ($estado === 'TARDANZA') $badge = 'text-bg-warning';
                            if ($estado === 'FALTA') $badge = 'text-bg-danger';
                            ?>
                            <tr>
                                <td><?php echo e((string) ($row['nombre_completo'] ?? '')); ?></td>
                                <td><?php echo e($esperada !== '' ? $esperada : 'Sin horario'); ?></td>
                                <td><?php echo e($real !== '' ? $real : 'Sin marca'); ?></td>
                                <td><span class="badge <?php echo $badge; ?>"><?php echo e($estado); ?></span></td>
                                <td class="text-end"><?php echo (int) ($row['minutos_tardanza'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
