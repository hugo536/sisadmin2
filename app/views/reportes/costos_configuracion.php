<div class="container-fluid p-4" id="configCostosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-fill me-2 text-secondary"></i> Configuración de Costos
            </h1>
            <p class="text-muted small mb-0 ms-1">Define políticas base para evolucionar el costeo (estándar, variaciones y alertas).</p>
        </div>
        <a class="btn btn-outline-primary shadow-sm fw-semibold" href="<?php echo e(route_url('reportes/costos_produccion')); ?>">
            <i class="bi bi-graph-up-arrow me-2"></i>Ir a análisis de costos
        </a>
    </div>

    <div class="row g-4">
        
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-calculator me-2 text-primary"></i>Método de costeo (recomendado)</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled small mb-0 text-muted">
                        <li class="d-flex mb-3">
                            <i class="bi bi-check2-circle text-success fs-5 me-2 mt-n1"></i> 
                            <span>Costo estándar por receta activa.</span>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="bi bi-check2-circle text-success fs-5 me-2 mt-n1"></i> 
                            <span>Comparativo contra costo real de ejecución.</span>
                        </li>
                        <li class="d-flex">
                            <i class="bi bi-check2-circle text-success fs-5 me-2 mt-n1"></i> 
                            <span>Umbral de alerta por variación (%) configurable.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-hourglass-split me-2 text-warning"></i>Componentes pendientes (fase 2)</h6>
                </div>
                <div class="card-body p-4">
                    <ul class="list-unstyled small mb-0 text-muted">
                        <li class="d-flex mb-3">
                            <i class="bi bi-clock-history text-warning fs-5 me-2 mt-n1"></i> 
                            <span>Mano de obra directa (MOD).</span>
                        </li>
                        <li class="d-flex mb-3">
                            <i class="bi bi-clock-history text-warning fs-5 me-2 mt-n1"></i> 
                            <span>Costos indirectos de fabricación (CIF).</span>
                        </li>
                        <li class="d-flex">
                            <i class="bi bi-clock-history text-warning fs-5 me-2 mt-n1"></i> 
                            <span>Reglas de prorrateo por centro de costo.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="col-12 col-lg-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom px-4 py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-server me-2 text-info"></i>Estado actual del sistema</h6>
                </div>
                <div class="card-body p-4 d-flex flex-column">
                    
                    <div class="d-flex flex-column gap-2 mb-auto">
                        <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle w-100 text-start text-wrap text-break fw-semibold">
                            <i class="bi bi-record-circle-fill me-2"></i>Snapshot teórico activo
                        </span>
                        <span class="badge px-3 py-2 rounded-pill bg-primary-subtle text-primary border border-primary-subtle w-100 text-start text-wrap text-break fw-semibold">
                            <i class="bi bi-record-circle-fill me-2"></i>Costo real por OP activo
                        </span>
                    </div>
                    
                    <div class="alert alert-light border text-muted small mb-0 mt-4 d-flex align-items-start">
                        <i class="bi bi-info-circle-fill text-secondary me-2 mt-1"></i>
                        <span>Este panel queda listo para conectar formularios de configuración en la siguiente fase.</span>
                    </div>
                </div>
            </div>
        </div>
        
    </div>
</div>