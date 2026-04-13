<?php
$totales = is_array($totales ?? null) ? $totales : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$cumpleanosSemana = is_array($cumpleanosSemana ?? null) ? $cumpleanosSemana : [];
$reportesWidgets = is_array($reportes_widgets ?? null) ? $reportes_widgets : [];
$inventarioValorizado = is_array($inventario_valorizado ?? null) ? $inventario_valorizado : [];
$totalInventarioValorizado = (float) ($inventarioValorizado['total_inventario'] ?? 0);
?>

<div class="container-fluid p-4 dashboard-page" id="dashboardApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-speedometer2 me-2 text-primary"></i> Dashboard
            </h1>
            <p class="text-muted small mb-0 ms-1">Resumen general del sistema y próximos cumpleaños del equipo.</p>
            <a href="<?php echo e(route_url('reportes/ventas')); ?>" class="btn btn-sm btn-outline-primary mt-2">
                <i class="bi bi-bar-chart-line-fill me-1"></i>Ir a gráfico de ventas
            </a>
        </div>
        <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle px-3 py-2 rounded-pill shadow-sm">
            <i class="bi bi-activity me-2"></i>Panel en tiempo real
        </span>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 dashboard-stat-card">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-primary-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-box-seam fs-3 text-primary"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Total ítems activos</div>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo (int) ($totales['items'] ?? 0); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 dashboard-stat-card">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-info-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-people-fill fs-3 text-info"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Total terceros activos</div>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo (int) ($totales['terceros'] ?? 0); ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 dashboard-stat-card">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-secondary-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 64px; height: 64px;">
                        <i class="bi bi-person-badge-fill fs-3 text-secondary"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Total usuarios activos</div>
                        <h2 class="mb-0 fw-bold text-dark"><?php echo (int) ($totales['usuarios'] ?? 0); ?></h2>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($reportesWidgets !== []): ?>
    <div class="mb-4">
        <h2 class="h5 fw-bold text-dark mb-3"><i class="bi bi-grid-1x2 me-2 text-primary"></i>Control operativo</h2>
        <div class="row g-3">
            <?php 
            $links = [
                'stock_critico' => 'reportes/inventario',
                'compras_pendientes' => 'reportes/compras',
                'ventas_por_despachar' => 'reportes/ventas',
                'produccion_proceso' => 'reportes/produccion',
                'cxc_vencida' => 'reportes/tesoreria',
                'cxp_vencida' => 'reportes/tesoreria'
            ]; 
            ?>
            <?php foreach ($reportesWidgets as $k => $v): ?>
                <?php if ($k === 'stock_critico'): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <div class="col-12 col-sm-6 col-lg-4">
                    <a class="card border-0 shadow-sm h-100 text-decoration-none widget-link-card transition-hover" href="<?php echo e(route_url((string) ($links[$k] ?? 'reportes/dashboard'))); ?>">
                        <div class="card-body p-4 d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted small text-uppercase fw-semibold mb-1"><?php echo e(str_replace('_', ' ', (string) $k)); ?></div>
                                <div class="h3 mb-0 fw-bold text-dark"><?php echo (int) $v; ?></div>
                            </div>
                            <div class="text-light">
                                <i class="bi bi-arrow-right-circle-fill fs-3 text-primary opacity-50"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>

            <div class="col-12 col-sm-6 col-lg-4">
                <a class="card border-0 shadow-sm h-100 text-decoration-none widget-link-card transition-hover" href="<?php echo e(route_url('reportes/inventario')); ?>">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">valor inventario total</div>
                            <div class="h4 mb-0 fw-bold text-success">S/ <?php echo number_format($totalInventarioValorizado, 2); ?></div>
                        </div>
                        <div class="text-light">
                            <i class="bi bi-arrow-right-circle-fill fs-3 text-primary opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>

            <?php if (tiene_permiso('reportes.tesoreria.ver')): ?>
            <div class="col-12 col-sm-6 col-lg-4">
                <a class="card border-0 shadow-sm h-100 text-decoration-none widget-link-card transition-hover" href="<?php echo e(route_url('reportes/estado_cuenta')); ?>">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">estado de cuenta cte/distrib.</div>
                            <div class="h6 mb-0 fw-bold text-dark">Ver reporte detallado</div>
                        </div>
                        <div class="text-light">
                            <i class="bi bi-arrow-right-circle-fill fs-3 text-primary opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-12 col-sm-6 col-lg-4">
                <a class="card border-0 shadow-sm h-100 text-decoration-none widget-link-card transition-hover" href="<?php echo e(route_url('reportes/estado_cuenta_proveedores')); ?>">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small text-uppercase fw-semibold mb-1">estado de cuenta proveedores</div>
                            <div class="h6 mb-0 fw-bold text-dark">Ver reporte detallado</div>
                        </div>
                        <div class="text-light">
                            <i class="bi bi-arrow-right-circle-fill fs-3 text-primary opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="row g-4 mb-4">
        
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-cake2-fill me-2 text-danger"></i>Cumpleaños de la semana
                    </h5>
                    <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 shadow-sm">
                        <?php echo count($cumpleanosSemana); ?> programados
                    </span>
                </div>
                <div class="card-body p-0">
                    <?php if ($cumpleanosSemana === []): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-calendar2-heart fs-1 d-block mb-3 text-light"></i>
                            No hay cumpleaños programados en los próximos 7 días.
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover table-pro">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                                        <th class="text-secondary fw-semibold">Empleado</th>
                                        <th class="text-secondary fw-semibold">Cargo / Área</th>
                                        <th class="text-center text-secondary fw-semibold">Edad</th>
                                        <th class="pe-4 text-center text-secondary fw-semibold">Faltan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($cumpleanosSemana as $cumple): ?>
                                    <tr class="border-bottom">
                                        <td class="ps-4" data-label="Fecha">
                                            <span class="d-inline-flex align-items-center gap-2 fw-semibold text-dark">
                                                <i class="bi bi-calendar-event text-primary"></i>
                                                <?php echo e((string) ($cumple['fecha_cumple'] ?? '')); ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold text-dark" data-label="Empleado"><?php echo e((string) ($cumple['nombre_completo'] ?? '')); ?></td>
                                        <td class="text-muted small" data-label="Cargo / Área"><?php echo e(trim((string) (($cumple['cargo'] ?? '') . ' / ' . ($cumple['area'] ?? '')), ' /')); ?></td>
                                        <td class="text-center" data-label="Edad">
                                            <span class="badge bg-light text-secondary border"><?php echo (int) ($cumple['edad_cumple'] ?? 0); ?> años</span>
                                        </td>
                                        <td class="pe-4 text-center" data-label="Faltan">
                                            <?php if ((int) ($cumple['dias_restantes'] ?? 0) === 0): ?>
                                                <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle">🎉 Hoy</span>
                                            <?php else: ?>
                                                <span class="badge px-3 py-2 rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle"><?php echo (int) ($cumple['dias_restantes'] ?? 0); ?> día(s)</span>
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
        </div>

        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h5 class="mb-0 fw-bold text-dark">
                        <i class="bi bi-journal-text me-2 text-primary"></i>Últimos registros
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if ($eventos === []): ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-inboxes fs-1 d-block mb-3 text-light"></i>
                            No hay registros en la bitácora.
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover table-pro">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                                    <th class="text-secondary fw-semibold">Evento</th>
                                    <th class="pe-4 text-secondary fw-semibold">Usuario</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($eventos as $row): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 text-muted small" data-label="Fecha"><?php echo e((string) ($row['created_at'] ?? '')); ?></td>
                                    <td class="fw-semibold text-dark" data-label="Evento"><?php echo e((string) ($row['evento'] ?? '')); ?></td>
                                    <td class="pe-4 text-muted small" data-label="Usuario">
                                        <i class="bi bi-person-fill me-1"></i><?php echo e((string) ($row['usuario'] ?? '')); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
    </div>
</div>

<style>
/* Pequeño ajuste para darle vida a los widgets clickeables */
.transition-hover {
    transition: all 0.2s ease-in-out;
}
.transition-hover:hover {
    transform: translateY(-3px);
    box-shadow: 0 .5rem 1rem rgba(0,0,0,.08)!important;
}
</style>
