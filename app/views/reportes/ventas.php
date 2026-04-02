<div class="container-fluid p-4" id="reportesVentasApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bag-check-fill me-2 text-primary"></i> Reportes de Ventas
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de facturación por cliente, top de productos y control de despachos.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/ventas')); ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Cliente</label>
                    <input type="number" name="id_cliente" class="form-control bg-light" placeholder="Todos..." value="<?php echo ($filtros['id_cliente'] ?? 0) > 0 ? (int)$filtros['id_cliente'] : ''; ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Estado</label>
                    <select name="estado" class="form-select bg-light">
                        <option value="">Todos...</option>
                        <option value="1" <?php echo ($filtros['estado'] ?? '') === '1' ? 'selected' : ''; ?>>Activas</option>
                        <option value="0" <?php echo ($filtros['estado'] ?? '') === '0' ? 'selected' : ''; ?>>Anuladas</option>
                    </select>
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
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-info"></i>Ventas por cliente
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasCliente" placeholder="Buscar cliente...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasCliente"
                       data-erp-table="true"
                       data-search-input="#filtroRepVentasCliente"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cliente</th>
                            <th class="text-end text-secondary fw-semibold">Total Vendido</th>
                            <th class="text-end text-secondary fw-semibold">Ticket Promedio</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Docs. Emitidos</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($porCliente['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de ventas para este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porCliente['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float)($r['ticket_promedio'] ?? 0), 2); ?></td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['documentos']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasClientePaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasClientePaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-star-fill me-2 text-warning"></i>Top productos vendidos
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasProd" placeholder="Buscar producto...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasProd"
                       data-erp-table="true"
                       data-search-input="#filtroRepVentasProd"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Producto</th>
                            <th class="text-end text-secondary fw-semibold">Cantidad</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Monto Generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($topProductos)): ?>
                            <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay productos vendidos en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($topProductos ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['producto'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['producto']); ?></td>
                                    <td class="text-end fw-semibold text-primary"><?php echo number_format((float)($r['total_cantidad'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold text-dark">S/ <?php echo number_format((float)($r['total_monto'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasProdPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasProdPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-truck me-2 text-danger"></i>Pendientes de despacho
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Incluye donaciones</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasPendientes" placeholder="Buscar doc o cliente...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasPendientes"
                       data-erp-table="true"
                       data-search-input="#filtroRepVentasPendientes"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Documento</th>
                            <th class="text-secondary fw-semibold">Cliente</th>
                            <th class="text-end text-secondary fw-semibold">Saldo Pendiente</th>
                            <th class="text-secondary fw-semibold">Almacén Origen</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Tiempo en espera</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($pendientes['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-check2-circle fs-1 d-block mb-2 text-success opacity-50"></i>Todo al día. No hay despachos pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($pendientes['rows'] ?? []) as $r): ?>
                                <?php 
                                    $dias = (int)($r['dias_desde_emision'] ?? 0);
                                    $esDonacion = ($r['tipo_operacion'] ?? '') === 'DONACION'; // <-- NUEVO: Verificamos si es donación
                                    
                                    if ($dias >= 7) {
                                        $badgeDias = 'bg-danger-subtle text-danger border-danger-subtle';
                                    } elseif ($dias >= 3) {
                                        $badgeDias = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                    } else {
                                        $badgeDias = 'bg-success-subtle text-success border-success-subtle';
                                    }
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['documento'] . ' ' . (string)$r['cliente'] . ($esDonacion ? ' donacion' : ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary">
                                        <?php echo e((string)$r['documento']); ?>
                                        <?php if($esDonacion): ?>
                                            <br><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-0 mt-1" style="font-size: 0.65rem;">DONACIÓN</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-danger"><?php echo number_format((float)($r['saldo_despachar'] ?? 0), 2); ?></td>
                                    <td class="text-muted small"><i class="bi bi-building me-1"></i><?php echo e((string)$r['almacen']); ?></td>
                                    <td class="text-center pe-4">
                                        <span class="badge px-3 py-1 rounded-pill border <?php echo $badgeDias; ?>"><?php echo $dias; ?> día(s)</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasPendientesPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasPendientesPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

</div>