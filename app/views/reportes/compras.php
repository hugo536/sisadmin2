<div class="container-fluid p-4" id="reportesComprasApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-file-earmark-bar-graph-fill me-2 text-primary"></i> Reportes de Compras
            </h1>
            <p class="text-muted small mb-0 ms-1">Métricas y análisis de compras, proveedores y órdenes.</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-secondary shadow-sm fw-semibold">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/compras')); ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Proveedor</label>
                    <input type="number" name="id_proveedor" class="form-control bg-light" placeholder="Todos..." value="<?php echo ($filtros['id_proveedor'] ?? 0) > 0 ? (int)$filtros['id_proveedor'] : ''; ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Almacén</label>
                    <input type="number" name="id_almacen" class="form-control bg-light" placeholder="Todos..." value="<?php echo ($filtros['id_almacen'] ?? 0) > 0 ? (int)$filtros['id_almacen'] : ''; ?>">
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm fw-semibold">
                        <i class="bi bi-funnel-fill me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-building me-2 text-primary"></i>Compras por proveedor</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepProveedores" placeholder="Buscar proveedor...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepProveedores"
                       data-erp-table="true"
                       data-search-input="#filtroRepProveedores"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Proveedor</th>
                            <th class="text-end text-secondary fw-semibold">Total Recibido</th>
                            <th class="text-center text-secondary fw-semibold"># Recepciones</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Costo Prom.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($porProveedor['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay datos para este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porProveedor['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['proveedor'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['proveedor']); ?></td>
                                    <td class="text-end fw-semibold text-success">S/ <?php echo number_format((float)($r['total_recibido'] ?? 0), 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['recepciones']); ?></span>
                                    </td>
                                    <td class="text-end pe-4 fw-semibold text-dark">S/ <?php echo number_format((float)($r['costo_promedio_item'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepProveedoresPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación proveedores">
                    <ul class="pagination mb-0 justify-content-end" id="tablaRepProveedoresPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-check2-circle me-2 text-success"></i>Estado y cumplimiento OC</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepCumplimiento" placeholder="Buscar OC o proveedor...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepCumplimiento"
                       data-erp-table="true"
                       data-search-input="#filtroRepCumplimiento"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">OC</th>
                            <th class="text-secondary fw-semibold">Proveedor</th>
                            <th class="text-center text-secondary fw-semibold">Solicitado</th>
                            <th class="text-center text-secondary fw-semibold">Recibido</th>
                            <th class="text-center text-secondary fw-semibold">% Cumplimiento</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Retraso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($ocCumplimiento['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay datos para este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($ocCumplimiento['rows'] ?? []) as $r): ?>
                                <?php $retraso = (int)($r['retrasada'] ?? 0); ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['codigo'] . ' ' . (string)$r['proveedor'])); ?>">
                                    <td class="ps-4 fw-bold text-primary"><?php echo e((string)$r['codigo']); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e((string)$r['proveedor']); ?></td>
                                    <td class="text-center"><?php echo e((string)$r['solicitado']); ?></td>
                                    <td class="text-center fw-semibold text-success"><?php echo e((string)$r['recibido']); ?></td>
                                    
                                    <td class="text-center">
                                        <?php $pct = (float)($r['pct_cumplimiento'] ?? 0); ?>
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <div class="progress" style="width: 60px; height: 6px;">
                                                <div class="progress-bar <?php echo $pct >= 100 ? 'bg-success' : ($pct >= 50 ? 'bg-warning' : 'bg-danger'); ?>" role="progressbar" style="width: <?php echo $pct; ?>%;"></div>
                                            </div>
                                            <span class="small fw-bold <?php echo $pct >= 100 ? 'text-success' : ''; ?>"><?php echo e((string)$r['pct_cumplimiento']); ?>%</span>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center pe-4">
                                        <?php if($retraso === 1): ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-danger-subtle text-danger border border-danger-subtle">Sí</span>
                                        <?php else: ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepCumplimientoPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación cumplimiento">
                    <ul class="pagination mb-0 justify-content-end" id="tablaRepCumplimientoPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>
