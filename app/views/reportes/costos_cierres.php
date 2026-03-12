<div class="container-fluid p-4" id="cierresCostosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-lock-fill me-2 text-secondary"></i> Cierres de Costos
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de periodos para congelar análisis y evitar recálculos no deseados.</p>
        </div>
        <a class="btn btn-outline-secondary shadow-sm fw-semibold" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
            <i class="bi bi-arrow-left me-2"></i>Volver a costos
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar-range me-2 text-primary"></i>Plantilla de periodos de cierre</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroCierres" placeholder="Buscar periodo...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaCierres"
                       data-erp-table="true"
                       data-search-input="#filtroCierres"
                       data-rows-per-page="12">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Periodo</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-secondary fw-semibold">Fecha cierre</th>
                            <th class="text-secondary fw-semibold">Usuario</th>
                            <th class="pe-4 text-secondary fw-semibold">Observación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-bottom" data-search="<?php echo e(date('Y-m')); ?> abierto">
                            <td class="ps-4 fw-bold text-dark">
                                <i class="bi bi-calendar-event me-2 text-muted"></i><?php echo e(date('Y-m')); ?>

                            </td>
                            <td class="text-center">
                                <span class="badge px-3 py-2 rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">
                                    Abierto
                                </span>
                            </td>
                            <td class="text-muted fst-italic">Pendiente</td>
                            <td class="text-muted">-</td>
                            <td class="pe-4 text-muted small">Estructura lista para activar cierres mensuales.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaCierresPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación cierres">
                    <ul class="pagination mb-0 justify-content-end" id="tablaCierresPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>