<div class="container-fluid p-4">
  <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
      <h1 class="h4 mb-1">Cierres de Costos</h1>
      <p class="text-muted mb-0 small">Control de periodos para congelar análisis y evitar recálculos no deseados.</p>
    </div>
    <a class="btn btn-outline-secondary btn-sm" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
      <i class="bi bi-arrow-left me-1"></i>Volver a costos
    </a>
  </div>

  <div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Plantilla de periodos de cierre</div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Periodo</th>
              <th>Estado</th>
              <th>Fecha cierre</th>
              <th>Usuario</th>
              <th>Observación</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><?php echo e(date('Y-m')); ?></td>
              <td><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Abierto</span></td>
              <td class="text-muted">Pendiente</td>
              <td class="text-muted">-</td>
              <td class="text-muted">Estructura lista para activar cierres mensuales.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
