<?php
$rows = $rows ?? [];
$filtros = $filtros ?? [];
?>
<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-shield-lock-fill me-2 text-primary"></i> Auditoría del Sistema
            </h1>
            <p class="text-muted small mb-0 ms-1">Consulta de bitácora y rastreo de eventos (Modo Solo Lectura).</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('auditoria/exportar_csv?evento=' . urlencode((string)($filtros['evento'] ?? '')))); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-filetype-csv me-2 text-success"></i>Exportar CSV
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="auditoria/index">
                
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" name="evento" placeholder="Buscar por tipo de evento..." value="<?php echo e($filtros['evento'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Desde</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_desde" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-6 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Hasta</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_hasta" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha y Hora</th>
                            <th class="text-secondary fw-semibold">Evento</th>
                            <th class="text-secondary fw-semibold" style="min-width: 300px;">Descripción</th>
                            <th class="text-secondary fw-semibold">IP Origen</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($rows)): ?>
                            <?php foreach ($rows as $r): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 text-muted small pt-3">
                                        <i class="bi bi-clock me-1 opacity-50"></i> <?php echo e((string)$r['created_at']); ?>
                                    </td>
                                    <td class="pt-3">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill">
                                            <?php echo e((string)$r['evento']); ?>
                                        </span>
                                    </td>
                                    <td class="text-dark pt-3 text-wrap" style="max-width: 400px;">
                                        <?php echo e((string)$r['descripcion']); ?>
                                    </td>
                                    <td class="pt-3">
                                        <span class="font-monospace text-muted small bg-light px-2 py-1 rounded border">
                                            <?php echo e((string)$r['ip_address']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <div class="d-flex align-items-center justify-content-end">
                                            <i class="bi bi-person-circle fs-5 text-primary opacity-75 me-2"></i>
                                            <span class="fw-semibold text-dark"><?php echo e((string)($r['usuario'] ?? 'Sistema')); ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-shield-x fs-1 d-block mb-2 text-light"></i>
                                    No se encontraron registros de auditoría para estos filtros.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>