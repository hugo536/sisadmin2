<?php 
    // Por defecto iniciamos en 'stock' si no hay sección seleccionada
    $seccionActiva = $filtros['seccion_activa'] ?? 'stock'; 
?>
<div class="container-fluid p-4" id="reportesInventarioApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Reportes de Inventario
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de stock, movimientos valorizados y control de lotes.</p>
        </div>
    </div>

    <ul class="nav nav-tabs border-bottom-1 mb-0 px-2" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'stock' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" onclick="cambiarSeccion('stock')">
                <i class="bi bi-layers-half me-2"></i>Stock Actual
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'kardex' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" onclick="cambiarSeccion('kardex')">
                <i class="bi bi-journal-check me-2"></i>Kardex Valorizado
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'vencimientos' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" onclick="cambiarSeccion('vencimientos')">
                <i class="bi bi-calendar2-x me-2"></i>Lotes y Vencimientos
            </button>
        </li>
    </ul>

    <div class="card border-0 shadow-sm mb-4 rounded-top-0 border-top border-primary border-3">
        <div class="card-body p-4 bg-white">
            <form id="formFiltrosInventario" class="row g-3" method="get" action="<?php echo e(route_url('reportes/inventario')); ?>">
                <input type="hidden" name="ruta" value="reportes/inventario">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold mb-1">Categoría</label>
                    <select name="id_categoria" class="form-select bg-light auto-submit">
                        <option value="">Todas las categorías</option>
                        <?php foreach (($categorias ?? []) as $categoria): ?>
                            <option value="<?php echo (int) ($categoria['id'] ?? 0); ?>" <?php echo (int) ($filtros['id_categoria'] ?? 0) === (int) ($categoria['id'] ?? 0) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($categoria['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold mb-1">Tipo de Ítem</label>
                    <select name="tipo_item" class="form-select bg-light auto-submit">
                        <option value="">Todos los tipos</option>
                        <option value="producto_terminado" <?php echo (($filtros['tipo_item'] ?? '') === 'producto_terminado') ? 'selected' : ''; ?>>Producto terminado</option>
                        <option value="materia_prima" <?php echo (($filtros['tipo_item'] ?? '') === 'materia_prima') ? 'selected' : ''; ?>>Materia prima</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-3">
                    <label class="form-label text-muted small fw-bold mb-1">Almacén</label>
                    <select name="id_almacen" class="form-select bg-light auto-submit">
                        <option value="">Todos los almacenes</option>
                        <?php foreach (($almacenes ?? []) as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>" <?php echo (int) ($filtros['id_almacen'] ?? 0) === (int) ($almacen['id'] ?? 0) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($almacen['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-3 d-flex flex-column justify-content-end">
                    <?php if ($seccionActiva === 'stock'): ?>
                        <div class="form-check form-switch p-2 bg-light rounded border d-flex align-items-center" style="height: 38px;">
                            <input class="form-check-input mt-0 me-2 auto-submit" type="checkbox" role="switch" id="filtroBajoMinimo" name="solo_bajo_minimo" value="1" <?php echo !empty($filtros['solo_bajo_minimo']) ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-bold text-danger cursor-pointer mb-0" for="filtroBajoMinimo">Solo bajo mínimo</label>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'kardex'): ?>
                        <div class="d-flex gap-2">
                            <div class="w-50">
                                <label class="form-label text-muted small fw-bold mb-1">Desde <span class="text-danger">*</span></label>
                                <input type="date" id="fecha_desde" name="fecha_desde" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                            </div>
                            <div class="w-50">
                                <label class="form-label text-muted small fw-bold mb-1">Hasta <span class="text-danger">*</span></label>
                                <input type="date" id="fecha_hasta" name="fecha_hasta" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'vencimientos'): ?>
                        <div>
                            <label class="form-label text-muted small fw-bold mb-1">Filtro de Alerta</label>
                            <select name="situacion_alerta" class="form-select bg-light border-warning-subtle auto-submit">
                                <option value="">Todas las alertas</option>
                                <option value="disponible" <?php echo (($filtros['situacion_alerta'] ?? '') === 'disponible') ? 'selected' : ''; ?>>Disponible</option>
                                <option value="proximo_a_vencer" <?php echo (($filtros['situacion_alerta'] ?? '') === 'proximo_a_vencer') ? 'selected' : ''; ?>>Próximo a vencer</option>
                                <option value="vencido" <?php echo (($filtros['situacion_alerta'] ?? '') === 'vencido') ? 'selected' : ''; ?>>Vencido</option>
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

    <?php if ($seccionActiva === 'stock'): ?>
        
        <div class="row mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold mb-3">Distribución del Valor</h6>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartStockDona"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h6 class="text-muted fw-bold mb-3">Top 5 Artículos de Mayor Valor</h6>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartStockBarras"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-layers-half me-2 text-info"></i>Detalle de Inventario</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepStock" placeholder="Buscar ítem o almacén...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepStock" data-erp-table="true" data-search-input="#filtroRepStock" data-rows-per-page="10">
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
                                        $alertaTexto = (string)$r['alerta'];
                                        $esCritico = stripos($alertaTexto, 'bajo') !== false || stripos($alertaTexto, 'crítico') !== false || stripos($alertaTexto, 'critico') !== false;
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
                            <span class="ms-2 badge bg-success-subtle text-success border border-success-subtle px-2">
                                Valor total: S/ <?php echo number_format((float)($stock['valor_total'] ?? 0), 2); ?>
                            </span>
                        <?php endif; ?>
                    </small>
                    <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepStockPaginationControls"></ul></nav>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'kardex'): ?>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h6 class="text-muted fw-bold mb-3">Entradas vs Salidas (Periodo Seleccionado)</h6>
                <div style="position: relative; height: 300px; width: 100%;">
                    <canvas id="chartKardexLineas"></canvas>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-journal-check me-2 text-primary"></i>Movimientos Detallados</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepKardex" placeholder="Buscar ref o tipo...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepKardex" data-erp-table="true" data-search-input="#filtroRepKardex" data-rows-per-page="10">
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
    <?php endif; ?>

    <?php if ($seccionActiva === 'vencimientos'): ?>
        
        <div class="row mb-4 justify-content-center">
            <div class="col-12 col-md-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body text-center">
                        <h6 class="text-muted fw-bold mb-3">Estado de Salud de Lotes</h6>
                        <div style="position: relative; height: 250px; width: 100%;">
                            <canvas id="chartLotesPie"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-calendar2-x me-2 text-warning"></i>Detalle de Lotes</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVencimientos" placeholder="Buscar ítem o lote...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaRepVencimientos" data-erp-table="true" data-search-input="#filtroRepVencimientos" data-rows-per-page="10">
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
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="/assets/js/reportes/inventario.js"></script>