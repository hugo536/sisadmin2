<div class="container-fluid p-4" id="reportesTesoreriaApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bank2 me-2 text-primary"></i> Reportes de Tesorería
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de cuentas por cobrar, pagar y flujo de caja operativo.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/tesoreria')); ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Cuenta</label>
                    <input type="number" name="id_cuenta" class="form-control bg-light" placeholder="Todas..." value="<?php echo ($filtros['id_cuenta'] ?? 0) > 0 ? (int)$filtros['id_cuenta'] : ''; ?>">
                </div>
                <div class="col-12 col-md-3">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm fw-semibold">
                        <i class="bi bi-funnel-fill me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-down-left-circle me-2 text-success"></i>Aging Cuentas por Cobrar (CxC)</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepCxC" placeholder="Buscar cliente...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepCxC"
                       data-erp-table="true"
                       data-search-input="#filtroRepCxC"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cliente</th>
                            <th class="text-end text-secondary fw-semibold">Saldo</th>
                            <th class="text-center text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-center text-secondary fw-semibold">Días Atraso</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($agingCxc['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay cuentas por cobrar pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($agingCxc['rows'] ?? []) as $r): ?>
                                <?php 
                                    $diasAtraso = (int)($r['dias_atraso'] ?? 0);
                                    $badgeAtraso = $diasAtraso > 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle';
                                    $textoAtraso = $diasAtraso > 0 ? $diasAtraso . ' días' : 'Al día';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format((float)($r['saldo'] ?? 0), 2); ?></td>
                                    <td class="text-center text-muted small"><i class="bi bi-calendar-x me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-2 py-1 rounded border <?php echo $badgeAtraso; ?>"><?php echo $textoAtraso; ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['bucket']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepCxCPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepCxCPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-arrow-up-right-circle me-2 text-danger"></i>Aging Cuentas por Pagar (CxP)</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepCxP" placeholder="Buscar proveedor...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepCxP"
                       data-erp-table="true"
                       data-search-input="#filtroRepCxP"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Proveedor</th>
                            <th class="text-end text-secondary fw-semibold">Saldo</th>
                            <th class="text-center text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-center text-secondary fw-semibold">Días Atraso</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($agingCxp['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay cuentas por pagar pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($agingCxp['rows'] ?? []) as $r): ?>
                                <?php 
                                    $diasAtraso = (int)($r['dias_atraso'] ?? 0);
                                    $badgeAtraso = $diasAtraso > 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle';
                                    $textoAtraso = $diasAtraso > 0 ? $diasAtraso . ' días' : 'Al día';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['proveedor'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['proveedor']); ?></td>
                                    <td class="text-end fw-bold text-danger">S/ <?php echo number_format((float)($r['saldo'] ?? 0), 2); ?></td>
                                    <td class="text-center text-muted small"><i class="bi bi-calendar-x me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-2 py-1 rounded border <?php echo $badgeAtraso; ?>"><?php echo $textoAtraso; ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['bucket']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepCxPPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepCxPPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-wallet2 me-2 text-info"></i>Flujo por cuenta</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepFlujo" placeholder="Buscar cuenta...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepFlujo"
                       data-erp-table="true"
                       data-search-input="#filtroRepFlujo"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cuenta</th>
                            <th class="text-end text-secondary fw-semibold">Ingresos</th>
                            <th class="text-end text-secondary fw-semibold">Egresos</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($flujo['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay movimientos en las cuentas en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($flujo['rows'] ?? []) as $r): ?>
                                <?php 
                                    $neto = (float)($r['saldo_neto'] ?? 0);
                                    $colorNeto = $neto > 0 ? 'text-success' : ($neto < 0 ? 'text-danger' : 'text-secondary');
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cuenta'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><i class="bi bi-journal-album me-2 text-muted"></i><?php echo e((string)$r['cuenta']); ?></td>
                                    <td class="text-end fw-semibold text-success">S/ <?php echo number_format((float)($r['total_ingresos'] ?? 0), 2); ?></td>
                                    <td class="text-end fw-semibold text-danger">S/ <?php echo number_format((float)($r['total_egresos'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold <?php echo $colorNeto; ?>">S/ <?php echo number_format($neto, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepFlujoPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepFlujoPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

</div>