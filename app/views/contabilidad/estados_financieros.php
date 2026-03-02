<div class="container-fluid py-3">
  <h3>Estados Financieros</h3>
  <form class="row g-2 mb-3" method="get">
    <input type="hidden" name="ruta" value="cierre_contable/estados_financieros">
    <div class="col-md-3"><input type="date" class="form-control" name="fecha_desde" value="<?php echo e($filtros['fecha_desde']); ?>"></div>
    <div class="col-md-3"><input type="date" class="form-control" name="fecha_hasta" value="<?php echo e($filtros['fecha_hasta']); ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Recalcular</button></div>
    <div class="col-md-2"><a class="btn btn-outline-primary w-100" href="<?php echo e(route_url('cierre_contable/estados_financieros?fecha_desde=' . urlencode((string)$filtros['fecha_desde']) . '&fecha_hasta=' . urlencode((string)$filtros['fecha_hasta']) . '&formato=csv')); ?>">Exportar CSV</a></div>
  </form>
  <div class="row g-3">
    <div class="col-lg-6"><div class="card"><div class="card-body">
      <h5>Estado de Resultados</h5>
      <div class="d-flex justify-content-between"><span>Ingresos</span><b><?php echo number_format((float)$estadoResultados['ingresos'], 4); ?></b></div>
      <div class="d-flex justify-content-between"><span>Costos y gastos</span><b><?php echo number_format((float)$estadoResultados['costos_gastos'], 4); ?></b></div>
      <hr><div class="d-flex justify-content-between"><span>Resultado neto</span><b><?php echo number_format((float)$estadoResultados['resultado_neto'], 4); ?></b></div>
    </div></div></div>
    <div class="col-lg-6"><div class="card"><div class="card-body">
      <h5>Balance General</h5>
      <div class="d-flex justify-content-between"><span>Activos</span><b><?php echo number_format((float)$balanceGeneral['activos'], 4); ?></b></div>
      <div class="d-flex justify-content-between"><span>Pasivos</span><b><?php echo number_format((float)$balanceGeneral['pasivos'], 4); ?></b></div>
      <div class="d-flex justify-content-between"><span>Patrimonio</span><b><?php echo number_format((float)$balanceGeneral['patrimonio'], 4); ?></b></div>
    </div></div></div>
  </div>
</div>
