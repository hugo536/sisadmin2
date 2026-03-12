<div class="container-fluid p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Configuración de Costos</h1>
      <p class="text-muted mb-0 small">Define políticas base para evolucionar el costeo (estándar, variaciones y alertas).</p>
    </div>
    <a class="btn btn-outline-primary btn-sm" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
      <i class="bi bi-graph-up-arrow me-1"></i>Ir a análisis de costos
    </a>
  </div>

  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Método de costeo (recomendado)</div>
        <div class="card-body">
          <ul class="small mb-0">
            <li>Costo estándar por receta activa.</li>
            <li>Comparativo contra costo real de ejecución.</li>
            <li>Umbral de alerta por variación (%) configurable.</li>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Componentes pendientes (fase 2)</div>
        <div class="card-body">
          <ul class="small mb-0">
            <li>Mano de obra directa (MOD).</li>
            <li>Costos indirectos de fabricación (CIF).</li>
            <li>Reglas de prorrateo por centro de costo.</li>
          </ul>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card border-0 shadow-sm h-100">
        <div class="card-header bg-white fw-semibold">Estado actual del sistema</div>
        <div class="card-body">
          <span class="badge bg-success-subtle text-success-emphasis border border-success-subtle mb-2">Snapshot teórico activo</span>
          <span class="badge bg-primary-subtle text-primary-emphasis border border-primary-subtle mb-2">Costo real por OP activo</span>
          <p class="small text-muted mb-0">Este panel queda listo para conectar formularios de configuración en la siguiente fase.</p>
        </div>
      </div>
    </div>
  </div>
</div>
