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

<div class="container-fluid p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Costos de Producción</h1>
      <p class="text-muted mb-0 small">Comparativo teórico vs real por orden ejecutada.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo e(route_url('reportes/produccion')); ?>">
      <i class="bi bi-arrow-left me-1"></i>Volver a reporte de producción
    </a>
  </div>

  <form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('reportes/costos_produccion')); ?>">
    <div class="col-md-3">
      <label class="form-label small text-muted mb-1">Fecha desde</label>
      <input type="date" name="fecha_desde" class="form-control" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>" required>
    </div>
    <div class="col-md-3">
      <label class="form-label small text-muted mb-1">Fecha hasta</label>
      <input type="date" name="fecha_hasta" class="form-control" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>" required>
    </div>
    <div class="col-md-2 d-grid align-self-end">
      <button class="btn btn-primary"><i class="bi bi-funnel me-1"></i>Filtrar</button>
    </div>
  </form>

  <div class="row g-3 mb-3">
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Órdenes en periodo</div>
          <div class="h4 mb-0"><?php echo (int) $ordenes; ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Costo teórico total</div>
          <div class="h5 mb-0">S/ <?php echo number_format($teoricoTotal, 4); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Costo real total</div>
          <div class="h5 mb-0">S/ <?php echo number_format($realTotal, 4); ?></div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Variación global</div>
          <div class="h5 mb-0 <?php echo $variacionTotal > 0 ? 'text-danger' : ($variacionTotal < 0 ? 'text-success' : 'text-secondary'); ?>">
            S/ <?php echo number_format($variacionTotal, 4); ?>
            (<?php echo number_format($variacionPctGlobal, 2); ?>%)
          </div>
          <div class="small text-muted mt-1">Órdenes con desvío: <?php echo (int) $desviadas; ?></div>
        </div>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white">
      <h2 class="h6 mb-0">Detalle por orden</h2>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead class="table-light">
            <tr>
              <th>#OP</th>
              <th>Producto</th>
              <th class="text-end">Cant. planificada</th>
              <th class="text-end">Cant. producida</th>
              <th class="text-end">Teórico unit.</th>
              <th class="text-end">Real unit.</th>
              <th class="text-end">Teórico total</th>
              <th class="text-end">Real total</th>
              <th class="text-end">Variación</th>
              <th class="text-end">Variación %</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($rows === []): ?>
              <tr><td colspan="10" class="text-center text-muted py-4">No hay órdenes para el rango seleccionado.</td></tr>
            <?php else: ?>
              <?php foreach ($rows as $row): ?>
                <?php
                $varTotal = (float) ($row['variacion_total'] ?? 0);
                $varPct = (float) ($row['variacion_pct'] ?? 0);
                $badge = $varTotal > 0 ? 'bg-danger-subtle text-danger-emphasis border border-danger-subtle' : ($varTotal < 0 ? 'bg-success-subtle text-success-emphasis border border-success-subtle' : 'bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle');
                ?>
                <tr>
                  <td class="fw-semibold"><?php echo e((string) ($row['codigo'] ?? '-')); ?></td>
                  <td><?php echo e((string) ($row['producto'] ?? 'Sin snapshot')); ?></td>
                  <td class="text-end"><?php echo number_format((float) ($row['cantidad_planificada'] ?? 0), 4); ?></td>
                  <td class="text-end"><?php echo number_format((float) ($row['cantidad_producida'] ?? 0), 4); ?></td>
                  <td class="text-end">S/ <?php echo number_format((float) ($row['costo_teorico_unitario_snapshot'] ?? 0), 4); ?></td>
                  <td class="text-end">S/ <?php echo number_format((float) ($row['costo_real_unitario'] ?? 0), 4); ?></td>
                  <td class="text-end">S/ <?php echo number_format((float) ($row['costo_teorico_total_snapshot'] ?? 0), 4); ?></td>
                  <td class="text-end">S/ <?php echo number_format((float) ($row['costo_real_total'] ?? 0), 4); ?></td>
                  <td class="text-end"><span class="badge <?php echo $badge; ?>">S/ <?php echo number_format($varTotal, 4); ?></span></td>
                  <td class="text-end"><?php echo number_format($varPct, 2); ?>%</td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
