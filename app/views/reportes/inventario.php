<div class="container-fluid p-4" id="reportesInventarioApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Reportes de Inventario
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de stock, movimientos valorizados y control de lotes.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/inventario')); ?>">
                <input type="hidden" name="ruta" value="reportes/inventario">
                
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Almacén</label>
                    <input type="number" name="id_almacen" class="form-control bg-light" placeholder="Todos..." value="<?php echo ($filtros['id_almacen'] ?? 0) > 0 ? (int)$filtros['id_almacen'] : ''; ?>">
                </div>
                <div class="col-6 col-md-2 d-flex align-items-center pb-2">
                    <div class="form-check form-switch ms-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="filtroBajoMinimo" name="solo_bajo_minimo" value="1" <?php echo !empty($filtros['solo_bajo_minimo']) ? 'checked' : ''; ?>>
                        <label class="form-check-label text-muted small fw-bold user-select-none" style="cursor: pointer;" for="filtroBajoMinimo">Bajo mínimo</label>
                    </div>
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
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-layers-half me-2 text-info"></i>Stock actual</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepStock" placeholder="Buscar ítem o almacén...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepStock"
                       data-erp-table="true"
                       data-search-input="#filtroRepStock"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Ítem</th>
                            <th class="text-secondary fw-semibold">Almacén</th>
                            <th class="text-end text-secondary fw-semibold">Stock</th>
                            <th class="text-end text-secondary fw-semibold">C/U</th>
                            <th class="text-end text-secondary fw-semibold">Valor</th>
                            <th class="text-end text-secondary fw-semibold">Mínimo</th>
                            <th class="text-center text-secondary fw-semibold">Unidad</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Alerta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($stock['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de stock.</td></tr>
                        <?php else: ?>
                            <?php foreach (($stock['rows'] ?? []) as $r): ?>
                                <?php 
                                    // Lógica visual básica para alertas
                                    $alertaTexto = (string)$r['alerta'];
                                    $esCritico = stripos($alertaTexto, 'bajo') !== false
                                        || stripos($alertaTexto, 'crítico') !== false
                                        || stripos($alertaTexto, 'critico') !== false;
                                    $alertaClase = $esCritico ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-light text-secondary border-secondary-subtle';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['almacen'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                    <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                    <td class="text-end fw-bold <?php echo $esCritico ? 'text-danger' : 'text-success'; ?>"><?php echo e((string)$r['stock_actual']); ?></td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 4); ?></td>
                                    <td class="text-end fw-semibold text-dark">S/ <?php echo number_format((float)($r['valor_total'] ?? 0), 2); ?></td>
                                    <td class="text-end text-muted"><?php echo e((string)$r['stock_minimo']); ?></td>
                                    <td class="text-center"><span class="badge bg-light text-secondary border"><?php echo e((string)$r['unidad']); ?></span></td>
                                    <td class="text-center pe-4">
                                        <span class="badge px-2 py-1 rounded border <?php echo $alertaClase; ?>"><?php echo e($alertaTexto !== '' ? $alertaTexto : 'OK'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepStockPaginationInfo">
                    Cargando...
                    <?php if (!empty($stock['valor_total'])): ?>
                        <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle">
                            Valor total filtrado: S/ <?php echo number_format((float)($stock['valor_total'] ?? 0), 2); ?>
                        </span>
                    <?php endif; ?>
                </small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepStockPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-check me-2 text-primary"></i>Kardex valorizado</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepKardex" placeholder="Buscar ref o tipo...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepKardex"
                       data-erp-table="true"
                       data-search-input="#filtroRepKardex"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-center text-secondary fw-semibold">Tipo</th>
                            <th class="text-end text-secondary fw-semibold">Cantidad</th>
                            <th class="text-end text-secondary fw-semibold">C/U</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-secondary fw-semibold">Referencia</th>
                            <th class="pe-4 text-secondary fw-semibold">Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($kardex['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay movimientos en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($kardex['rows'] ?? []) as $r): ?>
                                <?php 
                                    $tipo = mb_strtolower((string)$r['tipo']);
                                    $esIngreso = stripos($tipo, 'ingreso') !== false || stripos($tipo, 'entrada') !== false;
                                    $badgeTipo = $esIngreso ? 'bg-success-subtle text-success border-success-subtle' : 'bg-danger-subtle text-danger border-danger-subtle';
                                    $iconoTipo = $esIngreso ? 'bi-arrow-down-left' : 'bi-arrow-up-right';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['referencia'] . ' ' . (string)$r['tipo'])); ?>">
                                    <td class="ps-4 text-muted small"><?php echo e((string)$r['fecha']); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-2 py-1 rounded-pill border <?php echo $badgeTipo; ?>">
                                            <i class="bi <?php echo $iconoTipo; ?> me-1"></i><?php echo e((string)$r['tipo']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo e((string)$r['cantidad']); ?></td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float)($r['costo_unitario'] ?? 0), 2); ?></td>
                                    <td class="text-end fw-semibold text-dark">S/ <?php echo number_format((float)($r['costo_total'] ?? 0), 2); ?></td>
                                    <td class="text-muted small"><?php echo e((string)$r['referencia']); ?></td>
                                    <td class="pe-4 text-muted small"><i class="bi bi-person me-1"></i><?php echo e((string)$r['usuario']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepKardexPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepKardexPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar2-x me-2 text-warning"></i>Vencimientos y lotes</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVencimientos" placeholder="Buscar ítem o lote...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVencimientos"
                       data-erp-table="true"
                       data-search-input="#filtroRepVencimientos"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Ítem</th>
                            <th class="text-secondary fw-semibold">Almacén</th>
                            <th class="text-secondary fw-semibold">Lote</th>
                            <th class="text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-end text-secondary fw-semibold">Stock</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Alerta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($vencimientos['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de lotes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($vencimientos['rows'] ?? []) as $r): ?>
                                <?php 
                                    $alertaVenc = (string)$r['alerta'];
                                    $esVencido = stripos($alertaVenc, 'vencido') !== false;
                                    $badgeVenc = $esVencido ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['item'] . ' ' . (string)$r['lote'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['item']); ?></td>
                                    <td class="text-muted"><?php echo e((string)$r['almacen']); ?></td>
                                    <td><span class="badge bg-light text-dark border"><i class="bi bi-upc-scan me-1"></i><?php echo e((string)$r['lote']); ?></span></td>
                                    <td class="text-muted"><i class="bi bi-calendar-event me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                    <td class="text-end fw-semibold text-dark"><?php echo e((string)$r['stock_lote']); ?></td>
                                    <td class="text-center pe-4">
                                        <span class="badge px-2 py-1 rounded border <?php echo $badgeVenc; ?>"><?php echo e($alertaVenc !== '' ? $alertaVenc : 'Normal'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVencimientosPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVencimientosPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>
