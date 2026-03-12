<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$costosPorOrden = is_array($costosPorOrden ?? null) ? $costosPorOrden : ['rows' => [], 'total' => 0];
$resumenCostos = is_array($resumenCostos ?? null) ? $resumenCostos : [];
$rows = is_array($costosPorOrden['rows'] ?? null) ? $costosPorOrden['rows'] : [];

$teoricoTotal = (float) ($resumenCostos['teorico_total'] ?? 0);
$realTotal = (float) ($resumenCostos['real_total'] ?? 0);
$variacionTotal = (float) ($resumenCostos['variacion_total'] ?? 0);
$ordenes = (int) ($resumenCostos['ordenes'] ?? 0);
$desviadas = (int) ($resumenCostos['desviadas'] ?? 0);

$variacionPctGlobal = $teoricoTotal > 0 ? (($variacionTotal / $teoricoTotal) * 100) : 0;
?>

<div class="container-fluid p-4" id="costosProduccionApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-graph-up-arrow me-2 text-primary"></i> Costos de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Comparativo teórico vs real por orden ejecutada.</p>
        </div>
        <a class="btn btn-outline-secondary shadow-sm fw-semibold" href="<?php echo e(route_url('reportes/produccion')); ?>">
            <i class="bi bi-arrow-left me-2"></i>Volver a reporte
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/costos_produccion')); ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>" required>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm fw-semibold">
                        <i class="bi bi-funnel-fill me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-secondary-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-hash fs-5 text-secondary"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Órdenes en periodo</div>
                        <div class="h4 mb-0 fw-bold text-dark"><?php echo (int) $ordenes; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-primary-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-cash fs-5 text-primary"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Costo teórico total</div>
                        <div class="h5 mb-0 fw-bold text-dark">S/ <?php echo number_format($teoricoTotal, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <div class="bg-info-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-cash-coin fs-5 text-info"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Costo real total</div>
                        <div class="h5 mb-0 fw-bold text-dark">S/ <?php echo number_format($realTotal, 2); ?></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-3 d-flex align-items-center">
                    <?php
                        $varColorClass = $variacionTotal > 0 ? 'danger' : ($variacionTotal < 0 ? 'success' : 'secondary');
                    ?>
                    <div class="bg-<?php echo $varColorClass; ?>-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-arrow-down-up fs-5 text-<?php echo $varColorClass; ?>"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1" style="font-size: 0.75rem;">Variación global</div>
                        <div class="h6 mb-1 fw-bold text-<?php echo $varColorClass; ?>">
                            S/ <?php echo number_format($variacionTotal, 2); ?> (<?php echo number_format($variacionPctGlobal, 2); ?>%)
                        </div>
                        <div class="small text-muted" style="font-size: 0.7rem;"><i class="bi bi-exclamation-triangle-fill me-1"></i>Con desvío: <?php echo (int) $desviadas; ?></div>
                    </div>
                </div>
            </div>
        </div>
        
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-columns-reverse me-2 text-primary"></i>Detalle por orden</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroCostosOP" placeholder="Buscar OP o producto...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaCostosOP"
                       data-erp-table="true"
                       data-search-input="#filtroCostosOP"
                       data-rows-per-page="15">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">#OP</th>
                            <th class="text-secondary fw-semibold" style="min-width: 200px;">Producto</th>
                            <th class="text-end text-secondary fw-semibold">Planificada</th>
                            <th class="text-end text-secondary fw-semibold">Producida</th>
                            <th class="text-end text-secondary fw-semibold">MD (T vs R)</th>
                            <th class="text-end text-secondary fw-semibold">MOD (T vs R)</th>
                            <th class="text-end text-secondary fw-semibold">CIF (T vs R)</th>
                            <th class="text-end text-secondary fw-semibold">Teórico Total</th>
                            <th class="text-end text-secondary fw-semibold">Real Total</th>
                            <th class="text-end text-secondary fw-semibold">Variación</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Var. %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr class="empty-msg-row"><td colspan="11" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay órdenes para el rango seleccionado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    $varTotal = (float) ($row['variacion_total'] ?? 0);
                                    $varPct = (float) ($row['variacion_pct'] ?? 0);
                                    $badgeColor = $varTotal > 0 ? 'danger' : ($varTotal < 0 ? 'success' : 'secondary');
                                    $badgeClass = "bg-{$badgeColor}-subtle text-{$badgeColor}-emphasis border border-{$badgeColor}-subtle";
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string) ($row['codigo'] ?? '') . ' ' . (string) ($row['producto'] ?? ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary"><?php echo e((string) ($row['codigo'] ?? '-')); ?></td>
                                    <td class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="<?php echo e((string) ($row['producto'] ?? 'Sin snapshot')); ?>">
                                        <?php echo e((string) ($row['producto'] ?? 'Sin snapshot')); ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format((float) ($row['cantidad_planificada'] ?? 0), 2); ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format((float) ($row['cantidad_producida'] ?? 0), 2); ?></td>
                                    <td class="text-end small">
                                        <div class="text-muted">T: S/ <?php echo number_format((float) ($row['md_teorico_total'] ?? 0), 2); ?></div>
                                        <div class="fw-semibold">R: S/ <?php echo number_format((float) ($row['md_real_total'] ?? 0), 2); ?></div>
                                    </td>
                                    <td class="text-end small">
                                        <div class="text-muted">T: S/ <?php echo number_format((float) ($row['mod_teorico_total'] ?? 0), 2); ?></div>
                                        <div class="fw-semibold">R: S/ <?php echo number_format((float) ($row['mod_real_total'] ?? 0), 2); ?></div>
                                    </td>
                                    <td class="text-end small">
                                        <div class="text-muted">T: S/ <?php echo number_format((float) ($row['cif_teorico_total'] ?? 0), 2); ?></div>
                                        <div class="fw-semibold">R: S/ <?php echo number_format((float) ($row['cif_real_total'] ?? 0), 2); ?></div>
                                    </td>
                                    <td class="text-end">S/ <?php echo number_format((float) ($row['costo_teorico_total_snapshot'] ?? 0), 2); ?></td>
                                    <td class="text-end fw-bold text-dark">S/ <?php echo number_format((float) ($row['costo_real_total'] ?? 0), 2); ?></td>
                                    <td class="text-end">
                                        <span class="badge px-2 py-1 rounded <?php echo $badgeClass; ?>">
                                            S/ <?php echo number_format($varTotal, 2); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 fw-semibold text-<?php echo $badgeColor; ?>">
                                        <?php echo number_format($varPct, 2); ?>%
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaCostosOPPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación costos OP">
                    <ul class="pagination mb-0 justify-content-end" id="tablaCostosOPPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>