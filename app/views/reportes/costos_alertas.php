<div class="container-fluid p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Alertas de Variación de Costos</h1>
      <p class="text-muted mb-0 small">Monitoreo de órdenes con desvíos relevantes entre costo teórico y real.</p>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
      <i class="bi bi-search me-1"></i>Ver detalle por OP
    </a>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Umbral sugerido</div>
          <div class="h4 mb-0">10.00%</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Órdenes monitoreadas</div>
          <div class="h4 mb-0">En línea con reporte</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-body">
          <div class="small text-muted text-uppercase">Estado</div>
          <div class="h4 mb-0 text-success">Módulo base operativo</div>
        </div>
      </div>
    </div>
  </div>

  <div class="alert alert-info border mb-0">
    <i class="bi bi-info-circle me-2"></i>
    Esta vista está preparada para conectar reglas automáticas de alerta y flujos de aprobación en la siguiente iteración.
  </div>
</div>
