<div class="container-fluid p-4" id="reportesProduccionApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Reportes de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de productos terminados y consumo de insumos.</p>
        </div>
        
        <?php 
            // 1. Ruta segura por defecto (Fallback principal)
            $urlRegreso = route_url('reportes/dashboard');
            
            // 2. Revisar si viene un parámetro "origen" explícito en la URL
            $origenParam = is_array($_GET['origen'] ?? '') ? ($_GET['origen'][0] ?? '') : ($_GET['origen'] ?? '');
            $origenParam = strtoupper(trim((string)$origenParam));
            
            if ($origenParam === 'PRODUCCION') {
                $urlRegreso = route_url('produccion/dashboard'); // Ajusta a tu ruta real
            } elseif ($origenParam === 'INVENTARIO') {
                $urlRegreso = route_url('inventario/dashboard'); // Ajusta a tu ruta real
            } else {
                // 3. Filtrar el Referer con Lista Blanca
                $referer = $_SERVER['HTTP_REFERER'] ?? '';
                $hostActual = $_SERVER['HTTP_HOST'] ?? '';
                $refererHost = parse_url($referer, PHP_URL_HOST) ?? '';

                if ($referer !== '' && $refererHost === $hostActual) {
                    
                    // --- LISTA BLANCA DE RUTAS PERMITIDAS PARA REPORTES DE PRODUCCION ---
                    $rutasPermitidas = [
                        'reportes/dashboard', 
                        'produccion', 
                        'inventario',
                        'almacen',
                        'dashboard'
                    ];
                    
                    $esRefererValido = false;
                    foreach ($rutasPermitidas as $rutaPermitida) {
                        // Verificamos ruta válida y EVITAMOS bucles de esta misma página
                        if (strpos($referer, $rutaPermitida) !== false && 
                            strpos($referer, 'reportes/produccion') === false && 
                            strpos($referer, 'reportes%2Fproduccion') === false) {
                            $esRefererValido = true;
                            break;
                        }
                    }

                    // Si pasó el filtro de la lista blanca, lo usamos
                    if ($esRefererValido) {
                        $urlRegreso = $referer;
                    }
                }
            }
        ?>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a href="<?= e($urlRegreso) ?>" 
               onclick="if(typeof window.navigateWithoutReload === 'function') { event.preventDefault(); window.navigateWithoutReload(new window.URL(this.href, window.location.origin), true); }"
               class="btn btn-light bg-white border border-secondary-subtle shadow-sm text-secondary fw-medium px-3 transition-hover d-inline-flex align-items-center text-nowrap">
                <i class="bi bi-arrow-left me-2"></i>Regresar
            </a>
            
            <a href="<?php echo e(route_url('reportes/costos_produccion')); ?>" 
               onclick="if(typeof window.navigateWithoutReload === 'function') { event.preventDefault(); window.navigateWithoutReload(new window.URL(this.href, window.location.origin), true); }"
               class="btn btn-outline-primary shadow-sm fw-semibold d-inline-flex align-items-center text-nowrap transition-hover px-3">
                <i class="bi bi-graph-up-arrow me-2"></i>Costos de producción
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/produccion')); ?>">
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Producto</label>
                    <input type="number" name="id_item" class="form-control bg-light" placeholder="Todos..." value="<?php echo ($filtros['id_item'] ?? 0) > 0 ? (int)$filtros['id_item'] : ''; ?>">
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
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-box2-fill me-2 text-info"></i>Producción por producto</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepProdProductos" placeholder="Buscar producto...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepProdProductos"
                       data-erp-table="true"
                       data-search-input="#filtroRepProdProductos"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Producto</th>
                            <th class="text-end text-secondary fw-semibold">Cant. Producida</th>
                            <th class="text-end text-secondary fw-semibold">Costo Unit. Prom.</th>
                            <th class="text-center text-secondary fw-semibold">Primer Registro</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Último Registro</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($porProducto['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de producción en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porProducto['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['producto'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['producto']); ?></td>
                                    <td class="text-end fw-bold text-success"><?php echo number_format((float)($r['cantidad_producida'] ?? 0), 2); ?></td>
                                    <td class="text-end text-muted">S/ <?php echo number_format((float)($r['costo_unitario_promedio'] ?? 0), 2); ?></td>
                                    <td class="text-center text-muted small"><i class="bi bi-calendar-check me-1"></i><?php echo e((string)$r['primer_registro']); ?></td>
                                    <td class="text-center pe-4 text-muted small"><i class="bi bi-calendar-check-fill me-1"></i><?php echo e((string)$r['ultimo_registro']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepProdProductosPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepProdProductosPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-basket3-fill me-2 text-warning"></i>Consumo de insumos</h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepProdInsumos" placeholder="Buscar insumo...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepProdInsumos"
                       data-erp-table="true"
                       data-search-input="#filtroRepProdInsumos"
                       data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Insumo</th>
                            <th class="text-end text-secondary fw-semibold">Cantidad Consumida</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Costo Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($consumos['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay consumos registrados.</td></tr>
                        <?php else: ?>
                            <?php foreach (($consumos['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['insumo'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['insumo']); ?></td>
                                    <td class="text-end fw-semibold text-primary"><?php echo number_format((float)($r['cantidad_consumida'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold text-dark">S/ <?php echo number_format((float)($r['costo_total'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepProdInsumosPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepProdInsumosPaginationControls"></ul></nav>
            </div>
        </div>
    </div>

</div>
