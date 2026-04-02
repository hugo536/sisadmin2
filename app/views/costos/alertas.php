<div class="container-fluid p-4" id="alertasCostosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bell-fill me-2 text-warning"></i> Alertas de Variación de Costos
            </h1>
            <p class="text-muted small mb-0 ms-1">Monitoreo de órdenes con desvíos relevantes entre costo teórico y real.</p>
        </div>
        <a class="btn btn-outline-primary shadow-sm fw-semibold" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
            <i class="bi bi-search me-2"></i>Ver detalle por OP
        </a>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-primary-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-percent fs-4 text-primary"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Umbral sugerido</div>
                        <div class="h3 mb-0 fw-bold text-dark">10.00%</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-info-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-clipboard2-data fs-4 text-info"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Órdenes monitoreadas</div>
                        <div class="h5 mb-0 fw-bold text-dark">En línea con reporte</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body p-4 d-flex align-items-center">
                    <div class="bg-success-subtle p-3 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 56px; height: 56px;">
                        <i class="bi bi-shield-check fs-4 text-success"></i>
                    </div>
                    <div>
                        <div class="small text-muted text-uppercase fw-bold mb-1">Estado</div>
                        <div class="h5 mb-0 fw-bold text-success">Módulo base operativo</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info d-flex align-items-center shadow-sm border-0 rounded-3 mb-0">
        <i class="bi bi-info-circle-fill fs-3 me-3 text-info"></i>
        <div>
            <strong class="d-block mb-1">Próximos pasos en el desarrollo:</strong>
            Esta vista está preparada para conectar reglas automáticas de alerta y flujos de aprobación en la siguiente iteración.
        </div>
    </div>
</div>