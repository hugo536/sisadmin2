<?php
$filtros = $filtros ?? ['fecha_desde' => '', 'fecha_hasta' => ''];
$estadoResultados = $estadoResultados ?? ['ingresos' => 0, 'costos_gastos' => 0, 'resultado_neto' => 0];
$balanceGeneral = $balanceGeneral ?? ['activos' => 0, 'pasivos' => 0, 'patrimonio' => 0];

// Cálculos numéricos para condicionales visuales
$ingresos = (float)$estadoResultados['ingresos'];
$costos = (float)$estadoResultados['costos_gastos'];
$utilidad = (float)$estadoResultados['resultado_neto'];

$activos = (float)$balanceGeneral['activos'];
$pasivos = (float)$balanceGeneral['pasivos'];
$patrimonio = (float)$balanceGeneral['patrimonio'];

$esGanancia = $utilidad >= 0;
// Pequeña validación de la ecuación contable (Activo - Pasivo - Patrimonio)
$descuadre = round($activos - ($pasivos + $patrimonio), 4);
$estaCuadrado = ($descuadre == 0);
?>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Estados Financieros
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de rentabilidad y situación patrimonial de la empresa.</p>
        </div>

        <div class="d-flex gap-2">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" onclick="window.print()">
                <i class="bi bi-printer-fill me-2 text-info"></i>Imprimir
            </button>
            <a href="<?php echo e(route_url('cierre_contable/estados_financieros?fecha_desde=' . urlencode((string)$filtros['fecha_desde']) . '&fecha_hasta=' . urlencode((string)$filtros['fecha_hasta']) . '&formato=csv')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-filetype-csv me-2 text-success"></i>Exportar CSV
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 d-print-none">
        <div class="card-body p-3">
            <form class="row g-2 align-items-center" method="get">
                <input type="hidden" name="ruta" value="cierre_contable/estados_financieros">
                
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Desde</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_desde" value="<?php echo e($filtros['fecha_desde']); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Hasta</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_hasta" value="<?php echo e($filtros['fecha_hasta']); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-calculator-fill me-2"></i> Recalcular Estados
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-4">
        
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 border-top border-info border-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 text-center">
                    <h5 class="fw-bold text-dark mb-0">Estado de Resultados</h5>
                    <p class="text-muted small mt-1">Acumulado en el periodo seleccionado</p>
                </div>
                <div class="card-body px-4">
                    
                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-success bg-opacity-10 text-success rounded p-2 me-3">
                                <i class="bi bi-graph-up-arrow fs-5"></i>
                            </div>
                            <span class="text-muted fw-bold text-uppercase small">Ingresos Operativos</span>
                        </div>
                        <span class="fs-5 fw-bold text-dark"><?php echo number_format($ingresos, 4); ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-danger bg-opacity-10 text-danger rounded p-2 me-3">
                                <i class="bi bi-graph-down-arrow fs-5"></i>
                            </div>
                            <span class="text-muted fw-bold text-uppercase small">Costos y Gastos (-)</span>
                        </div>
                        <span class="fs-5 fw-bold text-dark"><?php echo number_format($costos, 4); ?></span>
                    </div>

                    <div class="mt-4 p-4 rounded-3 <?php echo $esGanancia ? 'bg-success bg-opacity-10 border border-success border-opacity-25' : 'bg-danger bg-opacity-10 border border-danger border-opacity-25'; ?>">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold mb-0 <?php echo $esGanancia ? 'text-success' : 'text-danger'; ?>">
                                    <?php echo $esGanancia ? 'Utilidad Neta del Ejercicio' : 'Pérdida del Ejercicio'; ?>
                                </h6>
                                <small class="<?php echo $esGanancia ? 'text-success' : 'text-danger'; ?> opacity-75">Resultado Final</small>
                            </div>
                            <h3 class="fw-bold mb-0 <?php echo $esGanancia ? 'text-success' : 'text-danger'; ?>">
                                <?php echo number_format($utilidad, 4); ?>
                            </h3>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100 border-top border-primary border-4">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 text-center">
                    <h5 class="fw-bold text-dark mb-0">Balance General</h5>
                    <p class="text-muted small mt-1">Situación financiera al cierre del periodo</p>
                </div>
                <div class="card-body px-4">
                    
                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary rounded p-2 me-3">
                                <i class="bi bi-building fs-5"></i>
                            </div>
                            <span class="text-muted fw-bold text-uppercase small">Total Activos</span>
                        </div>
                        <span class="fs-5 fw-bold text-primary"><?php echo number_format($activos, 4); ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-warning bg-opacity-10 text-warning-emphasis rounded p-2 me-3">
                                <i class="bi bi-briefcase fs-5"></i>
                            </div>
                            <span class="text-muted fw-bold text-uppercase small">Total Pasivos</span>
                        </div>
                        <span class="fs-5 fw-bold text-dark"><?php echo number_format($pasivos, 4); ?></span>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-bottom py-3">
                        <div class="d-flex align-items-center">
                            <div class="bg-info bg-opacity-10 text-info-emphasis rounded p-2 me-3">
                                <i class="bi bi-safe fs-5"></i>
                            </div>
                            <span class="text-muted fw-bold text-uppercase small">Total Patrimonio</span>
                        </div>
                        <span class="fs-5 fw-bold text-dark"><?php echo number_format($patrimonio, 4); ?></span>
                    </div>

                    <div class="mt-4 text-center">
                        <div class="badge rounded-pill px-3 py-2 <?php echo $estaCuadrado ? 'bg-light text-secondary border' : 'bg-danger text-white shadow-sm'; ?>">
                            <?php if ($estaCuadrado): ?>
                                <i class="bi bi-check2-circle text-success me-1"></i> Ecuación contable cuadrada (Activo = Pasivo + Patrimonio)
                            <?php else: ?>
                                <i class="bi bi-exclamation-octagon me-1"></i> Descuadre detectado: <?php echo number_format($descuadre, 4); ?>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>
</div>

<style>
/* Ocultar elementos innecesarios al imprimir (Control+P) */
@media print {
    .d-print-none { display: none !important; }
    .card { border: 1px solid #dee2e6 !important; box-shadow: none !important; }
    body { background-color: white !important; }
}
</style>