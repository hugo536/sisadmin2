<?php 
    // Capturamos la sección activa de la URL, por defecto 'tendencias'
    $seccionActiva = $_GET['seccion_activa'] ?? ($filtros['seccion_activa'] ?? 'tendencias');
    if (!in_array($seccionActiva, ['tendencias', 'clientes', 'productos', 'pendientes'])) {
        $seccionActiva = 'tendencias';
    }
?>
<div class="container-fluid p-4" id="reportesVentasApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bag-check-fill me-2 text-primary"></i> Reportes de Ventas
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de facturación, top de clientes/productos y control de despachos.</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-secondary shadow-sm fw-semibold">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>

    <ul class="nav nav-tabs border-bottom-1 mb-0 px-2" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'tendencias' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="tendencias">
                <i class="bi bi-graph-up-arrow me-2"></i>Tendencias y Periodos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'clientes' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="clientes">
                <i class="bi bi-person-lines-fill me-2"></i>Por Clientes
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'productos' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="productos">
                <i class="bi bi-star-fill me-2"></i>Top Productos
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'pendientes' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="pendientes">
                <i class="bi bi-truck me-2"></i>Pendientes de Despacho
            </button>
        </li>
    </ul>

    <div class="card border-0 shadow-sm mb-4 rounded-top-0 border-top border-primary border-3">
        <div class="card-body p-4 bg-white">
            <form class="row g-3" method="get" action="<?php echo e(route_url('reportes/ventas')); ?>" id="formFiltrosReporteVentas">
                <input type="hidden" name="ruta" value="reportes/ventas">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold mb-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold mb-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-4 col-lg-2">
                    <label class="form-label text-muted small fw-bold mb-1">Estado Doc.</label>
                    <select name="estado" class="form-select bg-light auto-submit">
                        <option value="">Todos...</option>
                        <option value="1" <?php echo ($filtros['estado'] ?? '') === '1' ? 'selected' : ''; ?>>Activas</option>
                        <option value="0" <?php echo ($filtros['estado'] ?? '') === '0' ? 'selected' : ''; ?>>Anuladas</option>
                    </select>
                </div>

                <div class="col-12 col-lg-4 d-flex flex-column justify-content-end">
                    
                    <?php if ($seccionActiva === 'tendencias'): ?>
                        <div class="d-flex gap-2">
                            <div class="w-50">
                                <label class="form-label text-muted small fw-bold mb-1">Agrupar por</label>
                                <select name="agrupacion" class="form-select bg-light border-info-subtle auto-submit">
                                    <option value="diaria" <?php echo ($filtros['agrupacion'] ?? 'diaria') === 'diaria' ? 'selected' : ''; ?>>Diario</option>
                                    <option value="semanal" <?php echo ($filtros['agrupacion'] ?? '') === 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                                </select>
                            </div>
                            <div class="w-50">
                                <label class="form-label text-muted small fw-bold mb-1">Tipo Gráfico</label>
                                <select name="tipo_grafico" class="form-select bg-light border-info-subtle auto-submit">
                                    <option value="barras" <?php echo ($filtros['tipo_grafico'] ?? 'barras') === 'barras' ? 'selected' : ''; ?>>Barras</option>
                                    <option value="linea" <?php echo ($filtros['tipo_grafico'] ?? '') === 'linea' ? 'selected' : ''; ?>>Líneas</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'clientes' || $seccionActiva === 'pendientes'): ?>
                        <div class="d-flex gap-2">
                            <div class="w-40">
                                <label class="form-label text-muted small fw-bold mb-1">Tipo Tercero</label>
                                <select name="tipo_tercero" id="filtroVentasTipoTercero" class="form-select bg-light auto-submit">
                                    <option value="">Todos...</option>
                                    <option value="cliente" <?php echo ($filtros['tipo_tercero'] ?? '') === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                    <option value="cliente_distribuidor" <?php echo ($filtros['tipo_tercero'] ?? '') === 'cliente_distribuidor' ? 'selected' : ''; ?>>Cliente-Distrib.</option>
                                    <option value="distribuidor" <?php echo ($filtros['tipo_tercero'] ?? '') === 'distribuidor' ? 'selected' : ''; ?>>Distribuidor</option>
                                </select>
                            </div>
                            <div class="w-60">
                                <label class="form-label text-muted small fw-bold mb-1">Cliente Específico</label>
                                <select name="id_cliente" id="filtroVentasCliente" class="form-select bg-light auto-submit" placeholder="Buscar...">
                                    <option value="">Todos...</option>
                                    <?php foreach (($clientesFiltro ?? []) as $cli): ?>
                                        <option value="<?php echo (int) ($cli['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_cliente'] ?? 0) === (int)($cli['id'] ?? 0)) ? 'selected' : ''; ?>>
                                            <?php echo e((string) ($cli['nombre_completo'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'productos'): ?>
                        <div>
                            <label class="form-label text-muted small fw-bold mb-1">Producto Específico</label>
                            <select name="id_item" id="filtroVentasProducto" class="form-select bg-light auto-submit" placeholder="Buscar...">
                                <option value="">Todos...</option>
                                <?php foreach (($productosFiltro ?? []) as $item): ?>
                                    <option value="<?php echo (int) ($item['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_item'] ?? 0) === (int)($item['id'] ?? 0)) ? 'selected' : ''; ?>>
                                        <?php echo e((string) ($item['nombre'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                </div>
                
                <div class="col-12 d-flex justify-content-end mt-4 pt-3 border-top">
                    <button type="submit" name="exportar_pdf" value="1" class="btn btn-danger shadow-sm fw-semibold px-4" formtarget="_blank">
                        <i class="bi bi-file-pdf-fill me-2"></i>Exportar <?php echo ucfirst($seccionActiva); ?> a PDF
                    </button>
                </div>

            </form>
        </div>
    </div>

    <?php if ($seccionActiva === 'tendencias'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-graph-up-arrow me-2 text-success"></i>Ventas <?php echo (($filtros['agrupacion'] ?? 'diaria') === 'semanal') ? 'Semanales' : 'Diarias'; ?>
            </h5>
            <span class="badge bg-light text-secondary border">Periodo seleccionado</span>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <div class="col-12 col-lg-7">
                    <div class="border rounded-3 p-3 bg-light-subtle h-100" style="position: relative; min-height: 300px;">
                        <canvas id="ventasPeriodoChart" aria-label="Gráfico de ventas por periodo" role="img"></canvas>
                    </div>
                </div>
                <div class="col-12 col-lg-5">
                    <div class="table-responsive border rounded-3 h-100">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="py-3 ps-3">Periodo</th>
                                    <th class="text-end py-3">Docs.</th>
                                    <th class="text-end py-3 pe-3">Total Vendido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($porPeriodo)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-5">Sin datos para el rango seleccionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($porPeriodo as $r): ?>
                                        <tr class="border-bottom">
                                            <td class="fw-semibold ps-3"><?php echo e((string)($r['etiqueta'] ?? '-')); ?></td>
                                            <td class="text-end text-muted"><?php echo e((string)($r['documentos'] ?? '0')); ?></td>
                                            <td class="text-end fw-bold text-dark pe-3">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'clientes'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-info"></i>Ventas por Cliente
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasCliente" placeholder="Buscar cliente en tabla...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasCliente" data-erp-table="true" data-search-input="#filtroRepVentasCliente" data-rows-per-page="10">
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
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de ventas.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porCliente['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float)($r['ticket_promedio'] ?? 0), 2); ?></td>
                                    <td class="text-center pe-4"><span class="badge bg-light text-secondary border"><?php echo e((string)$r['documentos']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepVentasClientePaginationInfo"></small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasClientePaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'productos'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-star-fill me-2 text-warning"></i>Top Productos Vendidos
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasProd" placeholder="Buscar producto en tabla...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasProd" data-erp-table="true" data-search-input="#filtroRepVentasProd" data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Producto</th>
                            <th class="text-end text-secondary fw-semibold">Cantidad Vendida</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Monto Generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($topProductos)): ?>
                            <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay productos vendidos.</td></tr>
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
                <small class="text-muted fw-semibold" id="tablaRepVentasProdPaginationInfo"></small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasProdPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'pendientes'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-truck me-2 text-danger"></i>Pendientes de Despacho
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">Incluye donaciones</span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVentasPendientes" placeholder="Buscar doc o cliente...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepVentasPendientes" data-erp-table="true" data-search-input="#filtroRepVentasPendientes" data-rows-per-page="10">
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
                                    $esDonacion = ($r['tipo_operacion'] ?? '') === 'DONACION';
                                    
                                    if ($dias >= 7) $badgeDias = 'bg-danger-subtle text-danger border-danger-subtle';
                                    elseif ($dias >= 3) $badgeDias = 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
                                    else $badgeDias = 'bg-success-subtle text-success border-success-subtle';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['documento'] . ' ' . (string)$r['cliente'] . ($esDonacion ? ' donacion' : ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary">
                                        <?php echo e((string)$r['documento']); ?>
                                        <?php if($esDonacion): ?><br><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-0 mt-1" style="font-size: 0.65rem;">DONACIÓN</span><?php endif; ?>
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
                <small class="text-muted fw-semibold" id="tablaRepVentasPendientesPaginationInfo"></small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepVentasPendientesPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
    window.datosReporteVentas = {
        graficoPeriodo: <?php echo json_encode($porPeriodo ?? [], JSON_UNESCAPED_UNICODE); ?>,
        tipoGrafico: "<?php echo ($filtros['tipo_grafico'] ?? 'barras') === 'linea' ? 'line' : 'bar'; ?>"
    };
</script>
