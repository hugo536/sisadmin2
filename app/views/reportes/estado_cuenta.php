<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$detalle = is_array($detalle ?? null) ? $detalle : ['rows' => [], 'total' => 0, 'resumen' => []];
$porProducto = is_array($porProducto ?? null) ? $porProducto : [];
$clientesEstadoCuenta = array_values(array_filter(array_map(
    static fn($item): string => trim((string)$item),
    is_array($clientesEstadoCuenta ?? null) ? $clientesEstadoCuenta : []
), static fn(string $nombre): bool => $nombre !== ''));
$resumen = is_array($detalle['resumen'] ?? null) ? $detalle['resumen'] : [];
$vista = (string) ($filtros['vista'] ?? 'DETALLE');
$periodoResumen = (string)($filtros['fecha_desde'] ?? '') !== '' && (string)($filtros['fecha_hasta'] ?? '') !== ''
    ? 'Periodo: ' . date('d-m-Y', strtotime((string)$filtros['fecha_desde'])) . ' al ' . date('d-m-Y', strtotime((string)$filtros['fecha_hasta']))
    : 'Periodo filtrado';
?>

<div class="container-fluid p-4" id="reportesEstadoCuentaApp" data-url-index="<?php echo e(base_url() . '/'); ?>">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Estado de Cuenta
            </h1>
            <p class="text-muted small mb-0 ms-1">Historial cronológico de cargos y abonos por cliente.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(base_url() . '/'); ?>" id="estadoCuentaFiltrosForm">
                <input type="hidden" name="ruta" value="reportes/estado_cuenta">
                
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Cliente / Distribuidor</label>
                    <select name="cliente" id="filtroClienteEstadoCuenta" class="form-select bg-light">
                        <option value="">Todos</option>
                        <?php foreach ($clientesEstadoCuenta as $clienteNombre): ?>
                            <option value="<?php echo e($clienteNombre); ?>" <?php echo (string)($filtros['cliente'] ?? '') === $clienteNombre ? 'selected' : ''; ?>>
                                <?php echo e($clienteNombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Tipo de Vista</label>
                    <select name="vista" class="form-select bg-light">
                        <option value="DETALLE" <?php echo $vista === 'DETALLE' ? 'selected' : ''; ?>>Historial General</option>
                        <option value="PRODUCTO" <?php echo $vista === 'PRODUCTO' ? 'selected' : ''; ?>>Resumen por Producto</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-2">
                    <button type="button" class="btn btn-danger w-100 shadow-sm fw-semibold" id="btnExportarPdf">
                        <i class="bi bi-file-earmark-pdf-fill me-2"></i>Exportar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Movimientos</div>
                    <div class="h4 fw-bold mb-0"><?php echo (int) ($resumen['total_documentos'] ?? 0); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Cargos (Deuda)</div>
                    <div class="h4 fw-bold mb-0 text-danger">S/ <?php echo number_format((float)($resumen['total_facturado'] ?? 0), 2); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Abonos (Pagos)</div>
                    <div class="h4 fw-bold mb-0 text-success">S/ <?php echo number_format((float)($resumen['total_pagado'] ?? 0), 2); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Saldo Pendiente Final</div>
                    <div class="h4 fw-bold mb-0 text-primary">S/ <?php echo number_format((float)($resumen['total_saldo'] ?? 0), 2); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($vista === 'PRODUCTO'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Resumen por producto</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 260px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroEstadoCuentaProducto" placeholder="Buscar producto...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaEstadoCuentaProducto"
                           data-erp-table="true"
                           data-search-input="#filtroEstadoCuentaProducto"
                           data-rows-per-page="15">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Producto</th>
                                <th class="text-end">Cantidad Vendida</th>
                                <th class="text-end">Total Facturado</th>
                                <th class="text-end pe-4">Deuda Pendiente</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($porProducto)): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5">Sin resultados para los filtros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($porProducto as $row): ?>
                                <tr data-search="<?php echo e(mb_strtolower((string)($row['producto'] ?? ''))); ?>">
                                    <td class="ps-4 fw-semibold"><?php echo e((string)($row['producto'] ?? '')); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($row['total_cantidad'] ?? 0), 2); ?></td>
                                    <td class="text-end">S/ <?php echo number_format((float)($row['total_facturado'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold text-danger">S/ <?php echo number_format((float)($row['total_saldo'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaEstadoCuentaProductoPaginationInfo">Cargando...</small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaEstadoCuentaProductoPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-clock-history me-2 text-primary"></i>Historial de Movimientos</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 260px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroEstadoCuentaDetalle" placeholder="Filtrar en tabla...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaEstadoCuentaDetalle"
                           data-erp-table="true"
                           data-search-input="#filtroEstadoCuentaDetalle"
                           data-rows-per-page="15">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Cliente / Distribuidor</th>
                                <th>Documento</th>
                                <th>Concepto</th>
                                <th class="text-end pe-4">Monto (+ / -)</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $rows = $detalle['rows'] ?? []; ?>
                        <?php if (empty($rows)): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>Sin movimientos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $esCargo = ($row['tipo_transaccion'] ?? 'CARGO') === 'CARGO';
                                $fechaFmt = !empty($row['fecha_atencion']) ? date('d-m-Y', strtotime($row['fecha_atencion'])) : '';
                                $search = mb_strtolower(trim(($row['cliente'] ?? '') . ' ' . ($row['documento'] ?? '') . ' ' . ($row['producto'] ?? '')));
                                ?>
                                <tr data-search="<?php echo e($search); ?>" class="<?php echo !$esCargo ? 'table-success bg-opacity-10' : ''; ?>">
                                    <td class="ps-4 text-muted"><?php echo e($fechaFmt); ?></td>
                                    <td class="fw-semibold text-truncate" style="max-width: 200px;"><?php echo e((string)($row['cliente'] ?? '')); ?></td>
                                    <td><?php echo e((string)($row['documento'] ?? '')); ?></td>
                                    
                                    <td>
                                        <?php if($esCargo): ?>
                                            <span class="text-dark fw-medium"><?php echo e((string)($row['producto'] ?? '')); ?></span> <br>
                                            <small class="text-muted"><?php echo number_format((float)($row['cantidad'] ?? 0), 2); ?> x S/ <?php echo number_format((float)($row['precio_unitario'] ?? 0), 2); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-success"><i class="bi bi-cash me-1"></i> Depósito / Pago</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end fw-bold pe-4">
                                        <?php if($esCargo): ?>
                                            <span class="text-danger">+ S/ <?php echo number_format((float)($row['monto_transaccion'] ?? 0), 2); ?></span>
                                        <?php else: ?>
                                            <span class="text-success">- S/ <?php echo number_format((float)($row['monto_transaccion'] ?? 0), 2); ?></span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaEstadoCuentaDetallePaginationInfo">Cargando...</small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaEstadoCuentaDetallePaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
