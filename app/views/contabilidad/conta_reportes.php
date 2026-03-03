<?php
$filtros = $filtros ?? [];
$periodos = $periodos ?? [];
$libroDiario = $libroDiario ?? [];
$balance = $balance ?? [];
?>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bar-chart-fill me-2 text-primary"></i> Reportes Financieros
            </h1>
            <p class="text-muted small mb-0 ms-1">Consulta del Libro Diario y Balance de Comprobación.</p>
        </div>
        
        <div class="d-flex gap-2">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" onclick="window.print()">
                <i class="bi bi-printer-fill me-2 text-info"></i>Imprimir Reporte
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="contabilidad/reportes">
                
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted"><i class="bi bi-calendar-check me-1"></i> Periodo</span>
                        <select class="form-select bg-light border-start-0 shadow-none fw-semibold" name="id_periodo">
                            <option value="0">Todos los periodos</option>
                            <?php foreach ($periodos as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)($filtros['id_periodo'] ?? 0) === (int)$p['id'] ? 'selected' : ''; ?>>
                                    <?php echo e($p['anio'].'-'.str_pad((string)$p['mes'], 2, '0', STR_PAD_LEFT)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Desde</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_desde" value="<?php echo e((string)($filtros['fecha_desde'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Hasta</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_hasta" value="<?php echo e((string)($filtros['fecha_hasta'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-funnel-fill me-2"></i> Generar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs nav-tabs-pro mb-4" id="reportesTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold px-4 py-3" id="diario-tab" data-bs-toggle="tab" data-bs-target="#tab-diario" type="button" role="tab" aria-controls="tab-diario" aria-selected="true">
                <i class="bi bi-journal-text me-2"></i>Libro Diario
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold text-secondary px-4 py-3" id="balance-tab" data-bs-toggle="tab" data-bs-target="#tab-balance" type="button" role="tab" aria-controls="tab-balance" aria-selected="false">
                <i class="bi bi-scales me-2"></i>Balance de Comprobación
            </button>
        </li>
    </ul>

    <div class="tab-content" id="reportesTabsContent">
        
        <div class="tab-pane fade show active" id="tab-diario" role="tabpanel" aria-labelledby="diario-tab">
            <?php if (!empty($libroDiario)): ?>
                <div class="row g-3">
                    <?php foreach ($libroDiario as $a): ?>
                        <div class="col-12">
                            <div class="card border-0 shadow-sm border-start border-primary border-4">
                                <div class="card-header bg-white border-bottom py-2 d-flex align-items-center">
                                    <span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1 me-3 font-monospace">
                                        <?php echo e($a['codigo']); ?>
                                    </span>
                                    <span class="text-muted small me-3 fw-medium">
                                        <i class="bi bi-calendar3 me-1"></i><?php echo e($a['fecha']); ?>
                                    </span>
                                    <span class="fw-bold text-dark text-uppercase" style="font-size: 0.9rem;">
                                        <?php echo e($a['glosa']); ?>
                                    </span>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0 table-borderless table-striped">
                                            <thead class="bg-light text-muted border-bottom" style="font-size: 0.75rem;">
                                                <tr>
                                                    <th class="ps-4" style="width: 20%;">Código Cuenta</th>
                                                    <th style="width: 50%;">Nombre de la Cuenta</th>
                                                    <th class="text-end" style="width: 15%;">Debe</th>
                                                    <th class="text-end pe-4" style="width: 15%;">Haber</th>
                                                </tr>
                                            </thead>
                                            <tbody style="font-size: 0.85rem;">
                                                <?php 
                                                    $sumDebe = 0; $sumHaber = 0;
                                                    foreach ($detalleFn((int)$a['id']) as $d): 
                                                        $sumDebe += (float)$d['debe'];
                                                        $sumHaber += (float)$d['haber'];
                                                ?>
                                                    <tr>
                                                        <td class="ps-4 font-monospace text-primary fw-semibold"><?php echo e($d['cuenta_codigo']); ?></td>
                                                        <td><?php echo e($d['cuenta_nombre']); ?></td>
                                                        <td class="text-end fw-medium"><?php echo number_format((float)$d['debe'], 4); ?></td>
                                                        <td class="text-end fw-medium pe-4"><?php echo number_format((float)$d['haber'], 4); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card border-0 shadow-sm py-5 text-center text-muted bg-light">
                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No se encontraron registros en el Libro Diario para este periodo/rango.
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="tab-balance" role="tabpanel" aria-labelledby="balance-tab">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover table-pro">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-4 text-secondary fw-semibold">Cuenta Contable</th>
                                    <th class="text-end text-secondary fw-semibold">Débitos (Debe)</th>
                                    <th class="text-end text-secondary fw-semibold">Créditos (Haber)</th>
                                    <th class="text-end pe-4 text-secondary fw-bold text-dark">Saldo Final</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($balance)): ?>
                                    <?php 
                                        $totDebe = 0; $totHaber = 0; $totSaldo = 0;
                                        foreach ($balance as $b): 
                                            $totDebe += (float)$b['debe'];
                                            $totHaber += (float)$b['haber'];
                                            $totSaldo += (float)$b['saldo'];
                                    ?>
                                        <tr class="border-bottom">
                                            <td class="ps-4 pt-3">
                                                <span class="font-monospace text-primary fw-bold me-2"><?php echo e($b['codigo']); ?></span>
                                                <span class="text-dark fw-medium"><?php echo e($b['nombre']); ?></span>
                                            </td>
                                            <td class="text-end pt-3 text-muted"><?php echo number_format((float)$b['debe'], 4); ?></td>
                                            <td class="text-end pt-3 text-muted"><?php echo number_format((float)$b['haber'], 4); ?></td>
                                            <td class="text-end pe-4 pt-3 fw-bold <?php echo (float)$b['saldo'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                                <?php echo number_format((float)$b['saldo'], 4); ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="bg-light">
                                        <td class="ps-4 py-3 text-end fw-bold text-secondary text-uppercase">Sumas Iguales:</td>
                                        <td class="text-end py-3 fw-bold text-dark"><?php echo number_format($totDebe, 4); ?></td>
                                        <td class="text-end py-3 fw-bold text-dark"><?php echo number_format($totHaber, 4); ?></td>
                                        <td class="text-end pe-4 py-3 fw-bold text-dark border-start border-2"><?php echo number_format($totSaldo, 4); ?></td>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted py-5">
                                            <i class="bi bi-scales fs-1 d-block mb-2 text-light"></i>
                                            El balance de comprobación está vacío para este periodo.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* Pequeño ajuste visual para las pestañas activas */
.nav-tabs-pro .nav-link {
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
}
.nav-tabs-pro .nav-link:hover {
    border-color: #e9ecef;
}
.nav-tabs-pro .nav-link.active {
    color: #0d6efd !important;
    border-bottom: 3px solid #0d6efd;
    background-color: transparent;
}
</style>