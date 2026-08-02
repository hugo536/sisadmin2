<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$detalle = is_array($detalle ?? null) ? $detalle : ['rows' => [], 'total' => 0, 'resumen' => []];
$porProducto = is_array($porProducto ?? null) ? $porProducto : [];
$proveedoresEstadoCuenta = array_values(array_filter(array_map(
    static fn($item): string => trim((string)$item),
    is_array($proveedoresEstadoCuenta ?? null) ? $proveedoresEstadoCuenta : []
), static fn(string $nombre): bool => $nombre !== ''));
$resumen = is_array($detalle['resumen'] ?? null) ? $detalle['resumen'] : [];
$vista = (string) ($filtros['vista'] ?? 'DETALLE');
$periodoResumen = (string)($filtros['fecha_desde'] ?? '') !== '' && (string)($filtros['fecha_hasta'] ?? '') !== ''
    ? 'Mostrando movimientos del ' . date('d/m/Y', strtotime((string)$filtros['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime((string)$filtros['fecha_hasta']))
    : 'Mostrando historial completo';
?>

<div class="container-fluid p-4" id="reportesEstadoCuentaProveedoresApp" data-url-index="<?php echo e(base_url() . '/'); ?>">
    
    <!-- Cabecera de la página -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Estado de Cuenta Proveedores
            </h1>
            <p class="text-muted small mb-0 ms-1"><?php echo e($periodoResumen); ?></p>
        </div>
        <a href="index.php?ruta=reportes/dashboard" 
           class="btn btn-light border shadow-sm fw-semibold text-secondary transition-hover sb-link"
           style="width: fit-content; flex: 0 0 auto;">
            <i class="bi bi-arrow-left-short fs-5 align-middle me-1"></i>Regresar
        </a>
    </div>

    <!-- Indicadores / KPIs (Ajustado a col-6 para hacer el 2x2 en celular) -->
    <div class="row g-3 mb-4">
        <!-- Total Movimientos -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Total Movimientos</div>
                    <div class="h4 fw-bold mb-0 text-secondary"><?php echo (int) ($resumen['total_documentos'] ?? 0); ?></div>
                </div>
            </div>
        </div>
        <!-- Total Cargos (Deuda) -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Total Cargos (Deuda)</div>
                    <div class="h4 fw-bold mb-0 text-danger">S/ <?php echo number_format((float)($resumen['total_facturado'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
        <!-- Total Abonos (Pagos) -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Total Abonos (Pagos)</div>
                    <div class="h4 fw-bold mb-0 text-success">S/ <?php echo number_format((float)($resumen['total_pagado'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
        <!-- Saldo Final -->
        <div class="col-6 col-md-3">
            <div class="card border-0 border-start border-primary border-4 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Saldo Pendiente Final</div>
                    <div class="h4 fw-bold mb-0 text-primary">S/ <?php echo number_format((float)($resumen['total_saldo'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros (Ajustada para verse idéntica a Clientes) -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(base_url() . '/'); ?>" id="estadoCuentaProveedoresFiltrosForm">
                <input type="hidden" name="ruta" value="reportes/estado_cuenta_proveedores">

                <!-- Proveedor (Tom Select) -->
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Proveedor</label>
                    <select name="proveedor" id="filtroProveedorEstadoCuenta" class="form-select bg-light shadow-sm">
                        <option value="">Todos</option>
                        <?php foreach ($proveedoresEstadoCuenta as $proveedorNombre): ?>
                            <option value="<?php echo e($proveedorNombre); ?>" <?php echo (string)($filtros['proveedor'] ?? '') === $proveedorNombre ? 'selected' : ''; ?>>
                                <?php echo e($proveedorNombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Tipo de Vista -->
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Tipo de Vista</label>
                    <select name="vista" class="form-select bg-light shadow-sm">
                        <option value="DETALLE" <?php echo $vista === 'DETALLE' ? 'selected' : ''; ?>>Historial General</option>
                        <option value="PRODUCTO" <?php echo $vista === 'PRODUCTO' ? 'selected' : ''; ?>>Resumen por Producto</option>
                    </select>
                </div>

                <!-- Filtro de Fechas Agrupado -->
                <div class="col-12 col-md-5">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1 d-none d-md-block">&nbsp;</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" class="form-control bg-light border-start-0 border-end-0" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" class="form-control bg-light border-start-0" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                        
                        <button class="btn btn-light border text-primary px-3 transition-hover" type="submit" title="Aplicar filtros" style="z-index: 0;">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <button type="button" id="btnLimpiarFiltrosEstadoCuenta" class="btn btn-light border text-danger px-3 transition-hover" title="Limpiar filtros" style="z-index: 0;">
                            <i class="bi bi-eraser-fill"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Vistas de Tabla -->
    <?php if ($vista === 'PRODUCTO'): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-box-seam me-2 text-primary"></i>Resumen por producto</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 260px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroEstadoCuentaProveedoresProducto" placeholder="Buscar producto...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaEstadoCuentaProveedoresProducto"
                           data-erp-table="true"
                           data-search-input="#filtroEstadoCuentaProveedoresProducto"
                           data-rows-per-page="15"
                           data-total-rows="<?php echo count($porProducto); ?>">
                        <thead class="table-light">
                            <tr>
                                <th class="ps-4">Producto</th>
                                <th class="text-end">Cantidad Comprada</th>
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
                        <?php if (!empty($porProducto)): ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="2" class="text-end ps-4 text-muted">Totales:</td>
                                <td class="text-end text-dark">S/ <?php echo number_format((float)($resumen['total_facturado'] ?? 0), 2); ?></td>
                                <td class="text-end pe-4 text-danger">S/ <?php echo number_format((float)($resumen['total_saldo'] ?? 0), 2); ?></td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaEstadoCuentaProveedoresProductoPaginationInfo">Cargando...</small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaEstadoCuentaProveedoresProductoPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark">
                    <i class="bi bi-clock-history me-2 text-primary"></i>Historial de Movimientos
                </h5>
                <div class="d-flex align-items-center gap-3">
                    
                    <div class="dropdown">
                        <button class="btn btn-secondary btn-sm shadow-sm fw-semibold dropdown-toggle" type="button" id="btnMenuExportar" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-cloud-download me-1"></i> Exportar
                        </button>
                        <ul class="dropdown-menu shadow-sm" aria-labelledby="btnMenuExportar">
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" id="btnExportarExcel">
                                    <i class="bi bi-file-earmark-excel-fill text-success me-2"></i> Formato Excel (.xlsx)
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" id="btnExportarCsv">
                                    <i class="bi bi-filetype-csv text-secondary me-2"></i> Datos Crudos (.csv)
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="dropdown-item d-flex align-items-center" id="btnExportarPdfProveedores">
                                    <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> Formato PDF (.pdf)
                                </button>
                            </li>
                        </ul>
                    </div>

                    <div class="input-group input-group-sm shadow-sm" style="max-width: 280px;">
                        <span class="input-group-text bg-white border-end-0 text-primary px-3">
                            <i class="bi bi-search"></i>
                        </span>
                        <input type="search" class="form-control border-start-0 ps-0" id="filtroEstadoCuentaProveedoresDetalle" placeholder="Buscar documento, concepto...">
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaEstadoCuentaProveedoresDetalle"
                           data-erp-table="true"
                           data-search-input="#filtroEstadoCuentaProveedoresDetalle"
                           data-rows-per-page="15">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4">Fecha</th>
                                <th>Proveedor</th>
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
                                
                                $montoFmt = number_format((float)($row['monto_transaccion'] ?? 0), 2, '.', '');
                                $search = mb_strtolower(trim(
                                    ($row['proveedor'] ?? '') . ' ' . 
                                    ($row['documento'] ?? '') . ' ' . 
                                    ($row['producto'] ?? '') . ' ' . 
                                    $fechaFmt . ' ' . 
                                    $montoFmt
                                ));
                                ?>
                                <tr data-search="<?php echo e($search); ?>">
                                    <td class="ps-4 text-muted"><?php echo e($fechaFmt); ?></td>
                                    <td class="fw-semibold text-truncate" style="max-width: 200px;"><?php echo e((string)($row['proveedor'] ?? '')); ?></td>
                                    <td><?php echo e((string)($row['documento'] ?? '')); ?></td>
                                    <td>
                                        <?php if($esCargo): ?>
                                            <span class="text-dark fw-medium"><?php echo e((string)($row['producto'] ?? '')); ?></span> <br>
                                            <small class="text-muted"><?php echo number_format((float)($row['cantidad'] ?? 0), 2); ?> x S/ <?php echo number_format((float)($row['precio_unitario'] ?? 0), 2); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">
                                                <i class="bi bi-wallet2 me-1"></i> <?php echo htmlspecialchars((string)($row['producto'] ?? '')); ?>
                                            </span>
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
                        <?php if (!empty($rows)): ?>
                        <tfoot class="table-light fw-bold">
                            <tr>
                                <td colspan="4" class="text-end pe-3 text-muted">Totales mostrados:</td>
                                <td class="text-end pe-4">
                                    <div class="text-danger small">Cargos: + S/ <?php echo number_format((float)($resumen['total_facturado'] ?? 0), 2); ?></div>
                                    <div class="text-success small">Abonos: - S/ <?php echo number_format((float)($resumen['total_pagado'] ?? 0), 2); ?></div>
                                </td>
                            </tr>
                        </tfoot>
                        <?php endif; ?>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                    <small class="text-muted fw-semibold" id="tablaEstadoCuentaProveedoresDetallePaginationInfo">Cargando...</small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaEstadoCuentaProveedoresDetallePaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>