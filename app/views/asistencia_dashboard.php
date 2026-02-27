<?php
$fecha = $fecha ?? date('Y-m-d');
$registros = $registros ?? [];
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-clock-history me-2 text-primary"></i> Dashboard Diario de RRHH
            </h1>
            <p class="text-muted small mb-0 ms-1">Control diario de puntualidad, tardanzas y faltas del personal.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('asistencia/incidencias')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-clipboard2-pulse me-2 text-info"></i>Incidencias
            </a>
            
            <?php if (tiene_permiso('asistencia.importar')): // Opcional: validación de permisos ?>
                <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-primary shadow-sm">
                    <i class="bi bi-file-earmark-arrow-up me-2"></i>Importar Biométrico
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="asistencia/dashboard">
                
                <div class="col-12 col-md-3">
                    <div class="input-group shadow-sm-sm">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-calendar3 text-muted"></i></span>
                        <input type="date" class="form-control bg-light border-start-0 ps-0 text-secondary fw-medium" name="fecha" value="<?php echo e($fecha); ?>" required>
                    </div>
                </div>
                
                <div class="col-6 col-md-2">
                    <button class="btn btn-white border shadow-sm text-secondary fw-semibold w-100" type="submit">
                        <i class="bi bi-search me-2"></i>Consultar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro table-hover">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                            <th class="text-secondary fw-semibold">Hora Esperada</th>
                            <th class="text-secondary fw-semibold">Hora Real</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Min. Tardanza</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($registros)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No hay datos de asistencia para la fecha seleccionada.
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
                                
                                // Colores de los badges adaptados a tu estilo UI
                                $badgeColor = 'bg-secondary';
                                if ($estado === 'PUNTUAL') $badgeColor = 'bg-success';
                                if ($estado === 'TARDANZA') $badgeColor = 'bg-warning text-dark';
                                if ($estado === 'FALTA') $badgeColor = 'bg-danger';
                                ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 fw-semibold text-dark align-top pt-3">
                                        <?php echo e((string) ($row['nombre_completo'] ?? '')); ?>
                                    </td>
                                    <td class="text-muted align-top pt-3">
                                        <?php echo e($esperada !== '' ? $esperada : '-'); ?>
                                    </td>
                                    <td class="fw-medium align-top pt-3">
                                        <?php echo e($real !== '' ? $real : '-'); ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo $badgeColor; ?>">
                                            <?php echo e($estado); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 align-top pt-3">
                                        <?php if((int)($row['minutos_tardanza'] ?? 0) > 0): ?>
                                            <span class="fw-bold text-danger"><?php echo (int) ($row['minutos_tardanza']); ?> min</span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
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