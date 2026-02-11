<?php
$totales = is_array($totales ?? null) ? $totales : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$cumpleanosSemana = is_array($cumpleanosSemana ?? null) ? $cumpleanosSemana : [];
?>
<div class="container-fluid p-4 dashboard-page">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard
            </h1>
            <p class="text-muted small mb-0 ms-1">Resumen general del sistema y próximos cumpleaños del equipo.</p>
        </div>
        <span class="dashboard-pill"><i class="bi bi-activity me-2"></i>Panel en tiempo real</span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm dashboard-stat-card stat-items h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small text-uppercase fw-semibold mb-1">Total ítems activos</p>
                        <h2 class="mb-0 fw-bold"><?php echo (int) ($totales['items'] ?? 0); ?></h2>
                    </div>
                    <span class="dashboard-stat-icon"><i class="bi bi-box-seam"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm dashboard-stat-card stat-terceros h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small text-uppercase fw-semibold mb-1">Total terceros activos</p>
                        <h2 class="mb-0 fw-bold"><?php echo (int) ($totales['terceros'] ?? 0); ?></h2>
                    </div>
                    <span class="dashboard-stat-icon"><i class="bi bi-people-fill"></i></span>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm dashboard-stat-card stat-usuarios h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted small text-uppercase fw-semibold mb-1">Total usuarios activos</p>
                        <h2 class="mb-0 fw-bold"><?php echo (int) ($totales['usuarios'] ?? 0); ?></h2>
                    </div>
                    <span class="dashboard-stat-icon"><i class="bi bi-person-badge-fill"></i></span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header d-flex justify-content-between align-items-center py-3">
            <span class="d-flex align-items-center gap-2"><i class="bi bi-cake2-fill text-danger"></i>Cumpleaños de la semana</span>
            <span class="badge rounded-pill text-bg-primary px-3 py-2"><?php echo count($cumpleanosSemana); ?></span>
        </div>
        <div class="card-body">
            <?php if ($cumpleanosSemana === []): ?>
                <div class="alert alert-light border mb-0 d-flex align-items-center"><i class="bi bi-calendar2-heart me-2 text-primary"></i>No hay cumpleaños programados en los próximos 7 días.</div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Empleado</th>
                                <th>Cargo / Área</th>
                                <th>Edad que cumple</th>
                                <th>Faltan</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($cumpleanosSemana as $cumple): ?>
                            <tr>
                                <td data-label="Fecha">
                                    <span class="d-inline-flex align-items-center gap-2">
                                        <i class="bi bi-calendar-event text-primary"></i>
                                        <?php echo e((string) ($cumple['fecha_cumple'] ?? '')); ?>
                                    </span>
                                </td>
                                <td data-label="Empleado"><?php echo e((string) ($cumple['nombre_completo'] ?? '')); ?></td>
                                <td data-label="Cargo / Área"><?php echo e(trim((string) (($cumple['cargo'] ?? '') . ' / ' . ($cumple['area'] ?? '')), ' /')); ?></td>
                                <td data-label="Edad que cumple"><span class="badge text-bg-light border"><?php echo (int) ($cumple['edad_cumple'] ?? 0); ?> años</span></td>
                                <td data-label="Faltan">
                                    <?php if ((int) ($cumple['dias_restantes'] ?? 0) === 0): ?>
                                        <span class="badge bg-success">🎉 Hoy</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><?php echo (int) ($cumple['dias_restantes'] ?? 0); ?> día(s)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex align-items-center gap-2 py-3">
            <i class="bi bi-journal-check text-primary"></i>
            Últimos 10 registros de bitácora
        </div>
        <div class="card-body">
            <?php if ($eventos === []): ?>
                <div class="alert alert-light border mb-0 d-flex align-items-center"><i class="bi bi-inboxes me-2 text-primary"></i>No hay registros en bitácora.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro">
                    <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th></tr></thead>
                    <tbody>
                    <?php foreach ($eventos as $row): ?>
                        <tr>
                            <td data-label="Fecha"><?php echo e((string) ($row['created_at'] ?? '')); ?></td>
                            <td data-label="Evento"><?php echo e((string) ($row['evento'] ?? '')); ?></td>
                            <td data-label="Usuario"><?php echo e((string) ($row['usuario'] ?? '')); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>
