<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$costosPorOrden = is_array($costosPorOrden ?? null) ? $costosPorOrden : ['rows' => [], 'total' => 0];
$resumenCostos = is_array($resumenCostos ?? null) ? $resumenCostos : [];
$costosMensuales = is_array($costosMensuales ?? null) ? $costosMensuales : [];
$insightMensual = is_array($insightMensual ?? null) ? $insightMensual : [];
$rows = is_array($costosPorOrden['rows'] ?? null) ? $costosPorOrden['rows'] : [];

$teoricoTotal = (float) ($resumenCostos['teorico_total'] ?? 0);
$realTotal = (float) ($resumenCostos['real_total'] ?? 0);
$variacionTotal = (float) ($resumenCostos['variacion_total'] ?? 0);
$ordenes = (int) ($resumenCostos['ordenes'] ?? 0);
$desviadas = (int) ($resumenCostos['desviadas'] ?? 0);

$variacionPctGlobal = $teoricoTotal > 0 ? (($variacionTotal / $teoricoTotal) * 100) : 0;
$resumenGerencial = is_array($resumenGerencial ?? null) ? $resumenGerencial : [];
$rc = is_array($resumenGerencial['costo_produccion'] ?? null) ? $resumenGerencial['costo_produccion'] : [];
$re = is_array($resumenGerencial['estado_resultados'] ?? null) ? $resumenGerencial['estado_resultados'] : [];

$chartLabels = [];
$chartMd = [];
$chartMod = [];
$chartCif = [];
$chartVariacion = [];

foreach ($costosMensuales as $m) {
    $chartLabels[] = (string) ($m['periodo'] ?? '');
    $chartMd[] = (float) ($m['md_real'] ?? 0);
    $chartMod[] = (float) ($m['mod_real'] ?? 0);
    $chartCif[] = (float) ($m['cif_real'] ?? 0);
    $chartVariacion[] = (float) ($m['variacion_total'] ?? 0);
}

$insightVariacion = (float) ($insightMensual['variacion_total'] ?? 0);
$insightColor = $insightVariacion > 0 ? 'danger' : ($insightVariacion < 0 ? 'success' : 'secondary');
$haySerieMensual = $chartLabels !== [];
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
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-activity me-2 text-primary"></i>Tendencia mensual MD / MOD / CIF (real)</h5>
                    <span class="badge text-bg-light border">
                        <?php echo count($chartLabels); ?> meses en rango
                    </span>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-12 col-xl-8">
                            <?php if (!$haySerieMensual): ?>
                                <div class="alert alert-light border text-muted mb-0">
                                    No hay datos mensuales para graficar en el rango seleccionado.
                                </div>
                            <?php else: ?>
                                <div style="height: 300px;">
                                    <canvas id="costosMensualesChart" aria-label="Gráfica mensual de costos MD MOD CIF" role="img"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-xl-4">
                            <div class="border rounded p-3 bg-light h-100 d-flex flex-column justify-content-between">
                                <div>
                                    <div class="text-muted small text-uppercase fw-bold mb-2">Insight sugerido</div>
                                    <div class="fw-semibold mb-1">Mes con mayor desvío total</div>
                                    <div class="d-flex align-items-center gap-2 mb-2">
                                        <span class="badge bg-<?php echo $insightColor; ?>-subtle text-<?php echo $insightColor; ?>-emphasis border border-<?php echo $insightColor; ?>-subtle">
                                            <?php echo e((string) ($insightMensual['periodo'] ?? '-')); ?>
                                        </span>
                                        <span class="small text-muted"><?php echo (int) ($insightMensual['ordenes'] ?? 0); ?> OP</span>
                                    </div>
                                    <div class="h5 mb-1 fw-bold text-<?php echo $insightColor; ?>">S/ <?php echo number_format($insightVariacion, 2); ?></div>
                                    <div class="small text-muted">Variación sobre teórico: <?php echo number_format((float) ($insightMensual['variacion_pct'] ?? 0), 2); ?>%</div>
                                </div>
                                <hr>
                                <div class="small text-muted mb-0">
                                    Tip: si sube CIF de forma sostenida, revisa servicios de planta y depreciación para detectar el componente que más presiona el costo.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
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

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-factory me-2 text-primary"></i>Reporte 1: Estado del Costo de Producción</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>Materia prima consumida</span><strong>S/ <?php echo number_format((float)($rc['materia_prima_consumida'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Mano de Obra Directa (MOD)</span><strong>S/ <?php echo number_format((float)($rc['mano_obra_directa'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>CIF inventario (planta)</span><strong>S/ <?php echo number_format((float)(($rc['cif_desglose']['inventario_consumido_planta'] ?? 0)), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>CIF servicios (planta)</span><strong>S/ <?php echo number_format((float)(($rc['cif_desglose']['servicios_planta'] ?? 0)), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>CIF depreciación (planta)</span><strong>S/ <?php echo number_format((float)(($rc['cif_desglose']['depreciacion_planta'] ?? 0)), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between bg-light"><span class="fw-bold">CIF total</span><strong>S/ <?php echo number_format((float)($rc['cif_total'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between bg-primary-subtle"><span class="fw-bold">Costo total de fabricar</span><strong>S/ <?php echo number_format((float)($rc['costo_total_fabricacion'] ?? 0), 2); ?></strong></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-bar-chart-line me-2 text-success"></i>Reporte 2: Estado de Resultados</h5>
                </div>
                <div class="card-body">
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>Ventas Totales</span><strong>S/ <?php echo number_format((float)($re['ventas_totales'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Costo de productos vendidos</span><strong>S/ <?php echo number_format((float)($re['costo_productos_vendidos'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between bg-light"><span class="fw-bold">Ganancia Bruta</span><strong>S/ <?php echo number_format((float)($re['ganancia_bruta'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Gastos de Administración</span><strong>S/ <?php echo number_format((float)($re['gastos_administracion'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Gastos de Ventas</span><strong>S/ <?php echo number_format((float)($re['gastos_ventas'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between"><span>Gastos Financieros (intereses)</span><strong>S/ <?php echo number_format((float)($re['gastos_financieros'] ?? 0), 2); ?></strong></li>
                        <li class="list-group-item d-flex justify-content-between bg-success-subtle"><span class="fw-bold">Ganancia Neta</span><strong>S/ <?php echo number_format((float)($re['ganancia_neta'] ?? 0), 2); ?></strong></li>
                    </ul>
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
                            <th class="text-center text-secondary fw-semibold">Plan. / Prod.</th>
                            <th class="text-end text-secondary fw-semibold">MD (T vs R)</th>
                            <th class="text-end text-secondary fw-semibold">MOD (T vs R)</th>
                            <th class="text-end text-secondary fw-semibold">CIF (T vs R)</th>
                            <th class="text-end text-secondary fw-semibold">Teórico Total</th>
                            <th class="text-end text-secondary fw-semibold">Real Total</th>
                            <th class="text-end text-secondary fw-semibold" title="Costo Unitario Real">C. Unit. Real</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Variación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($rows === []): ?>
                            <tr class="empty-msg-row">
                                <td colspan="10" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay órdenes para el rango seleccionado.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                    // Cálculos de variación y colores
                                    $varTotal = (float) ($row['variacion_total'] ?? 0);
                                    $varPct = (float) ($row['variacion_pct'] ?? 0);
                                    $badgeColor = $varTotal > 0 ? 'danger' : ($varTotal < 0 ? 'success' : 'secondary');
                                    
                                    // Colores específicos para MD, MOD, CIF (Si Real > Teorico = Peligro)
                                    $mdT = (float) ($row['md_teorico_total'] ?? 0);
                                    $mdR = (float) ($row['md_real_total'] ?? 0);
                                    $colorMd = $mdR > $mdT ? 'text-danger fw-bold' : 'text-success fw-bold';

                                    $modT = (float) ($row['mod_teorico_total'] ?? 0);
                                    $modR = (float) ($row['mod_real_total'] ?? 0);
                                    $colorMod = $modR > $modT ? 'text-danger fw-bold' : 'text-success fw-bold';

                                    $cifT = (float) ($row['cif_teorico_total'] ?? 0);
                                    $cifR = (float) ($row['cif_real_total'] ?? 0);
                                    $colorCif = $cifR > $cifT ? 'text-danger fw-bold' : 'text-success fw-bold';

                                    // Costo Unitario Real
                                    $cantProducida = (float) ($row['cantidad_producida'] ?? 0);
                                    $costoRealTotal = (float) ($row['costo_real_total'] ?? 0);
                                    $costoUnitarioReal = $cantProducida > 0 ? ($costoRealTotal / $cantProducida) : 0;
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string) ($row['codigo'] ?? '') . ' ' . (string) ($row['producto'] ?? ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary"><?php echo e((string) ($row['codigo'] ?? '-')); ?></td>
                                    <td class="fw-semibold text-dark text-truncate" style="max-width: 250px;" title="<?php echo e((string) ($row['producto'] ?? 'Sin snapshot')); ?>">
                                        <?php echo e((string) ($row['producto'] ?? 'Sin snapshot')); ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="small text-muted border-bottom mb-1" title="Planificada"><?php echo number_format((float) ($row['cantidad_planificada'] ?? 0), 2); ?></div>
                                        <div class="fw-bold text-dark" title="Producida"><?php echo number_format($cantProducida, 2); ?></div>
                                    </td>
                                    <td class="text-end small">
                                        <div class="text-muted" title="Teórico">T: S/ <?php echo number_format($mdT, 2); ?></div>
                                        <div class="<?php echo $colorMd; ?>" title="Real">R: S/ <?php echo number_format($mdR, 2); ?></div>
                                    </td>
                                    <td class="text-end small">
                                        <div class="text-muted" title="Teórico">T: S/ <?php echo number_format($modT, 2); ?></div>
                                        <div class="<?php echo $colorMod; ?>" title="Real">R: S/ <?php echo number_format($modR, 2); ?></div>
                                    </td>
                                    <td class="text-end small">
                                        <div class="text-muted" title="Teórico">T: S/ <?php echo number_format($cifT, 2); ?></div>
                                        <div class="<?php echo $colorCif; ?>" title="Real">R: S/ <?php echo number_format($cifR, 2); ?></div>
                                    </td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float) ($row['costo_teorico_total_snapshot'] ?? 0), 2); ?></td>
                                    <td class="text-end fw-bold text-dark">S/ <?php echo number_format($costoRealTotal, 2); ?></td>
                                    
                                    <!-- NUEVA COLUMNA: Costo Unitario Real -->
                                    <td class="text-end fw-bold text-primary bg-light">
                                        S/ <?php echo number_format($costoUnitarioReal, 4); ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <span class="badge px-2 py-1 rounded bg-<?php echo $badgeColor; ?>-subtle text-<?php echo $badgeColor; ?>-emphasis border border-<?php echo $badgeColor; ?>-subtle d-block mb-1">
                                            S/ <?php echo number_format($varTotal, 2); ?>
                                        </span>
                                        <div class="fw-semibold text-<?php echo $badgeColor; ?> small">
                                            <?php echo number_format($varPct, 2); ?>%
                                        </div>
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

<?php if ($haySerieMensual): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(() => {
    if (typeof window.Chart === 'undefined') {
        return;
    }

    const labels = <?php echo json_encode($chartLabels, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const mdData = <?php echo json_encode($chartMd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const modData = <?php echo json_encode($chartMod, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const cifData = <?php echo json_encode($chartCif, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const variacion = <?php echo json_encode($chartVariacion, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    const el = document.getElementById('costosMensualesChart');

    if (!el) {
        return;
    }

    new Chart(el, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                {
                    type: 'bar',
                    label: 'MD real',
                    data: mdData,
                    backgroundColor: 'rgba(13, 110, 253, 0.65)',
                    borderRadius: 4,
                    stack: 'costos'
                },
                {
                    type: 'bar',
                    label: 'MOD real',
                    data: modData,
                    backgroundColor: 'rgba(25, 135, 84, 0.65)',
                    borderRadius: 4,
                    stack: 'costos'
                },
                {
                    type: 'bar',
                    label: 'CIF real',
                    data: cifData,
                    backgroundColor: 'rgba(255, 193, 7, 0.70)',
                    borderRadius: 4,
                    stack: 'costos'
                },
                {
                    type: 'line',
                    label: 'Variación total',
                    data: variacion,
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.15)',
                    yAxisID: 'y1',
                    tension: 0.25,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }
            ]
        },
        options: {
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { position: 'bottom' },
                tooltip: {
                    callbacks: {
                        label: (ctx) => `${ctx.dataset.label}: S/ ${Number(ctx.parsed.y || 0).toLocaleString('es-PE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`
                    }
                }
            },
            scales: {
                x: {
                    ticks: {
                        callback: (_, i) => {
                            const val = labels[i] || '';
                            if (!/^\d{4}-\d{2}$/.test(val)) return val;
                            const [y, m] = val.split('-');
                            return `${m}/${String(y).slice(-2)}`;
                        }
                    }
                },
                y: {
                    stacked: true,
                    beginAtZero: true,
                    title: { display: true, text: 'Costos reales (S/)' }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { drawOnChartArea: false },
                    title: { display: true, text: 'Variación (S/)' }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>
