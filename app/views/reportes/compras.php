<?php
// Inicialización segura de variables
$filtros = $filtros ?? [];
$porPeriodo = $porPeriodo ?? [];
$topInsumos = $topInsumos ?? [];
$porProveedor = $porProveedor ?? ['rows' => []];
$ocCumplimiento = $ocCumplimiento ?? ['rows' => []];
$variacionCostos = $variacionCostos ?? ['rows' => []];

// Capturamos la sección activa de la URL, por defecto 'tendencias' para igualar a Ventas
$seccionActiva = $_GET['seccion_activa'] ?? ($filtros['seccion_activa'] ?? 'tendencias');
if (!in_array($seccionActiva, ['tendencias', 'insumos', 'proveedores', 'cumplimiento', 'variacion'])) {
    $seccionActiva = 'tendencias';
}
?>
<div class="container-fluid p-4" id="reportesComprasApp">
    
    <!-- ENCABEZADO -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart-check-fill me-2 text-primary"></i> Reportes de Compras
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de inversión, top de insumos, proveedores y cumplimiento de órdenes.</p>
        </div>
        
        <?php 
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            $rutaDashboard = route_url('reportes/dashboard');
            
            if ($referer === '' || strpos($referer, 'reportes/compras') !== false || strpos($referer, 'reportes%2Fcompras') !== false) {
                $urlRegreso = $rutaDashboard;
            } else {
                $urlRegreso = $referer;
            }
        ?>
        <a href="<?php echo $urlRegreso; ?>" 
           onclick="if(typeof window.navigateWithoutReload === 'function') { event.preventDefault(); window.navigateWithoutReload(new window.URL(this.href, window.location.origin), true); }"
           class="btn btn-light bg-white border border-secondary-subtle shadow-sm text-secondary fw-medium px-3 transition-hover d-flex align-items-center">
            <i class="bi bi-arrow-left me-2"></i>Regresar
        </a>
    </div>

    <!-- PESTAÑAS (Estilo "Carpeta" moderna) -->
    <div class="bg-light pt-2 px-3 rounded-top border border-bottom-0 overflow-auto scrollbar-hide">
        <ul class="nav nav-tabs border-bottom-0 mb-0 flex-nowrap text-nowrap" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/compras')); ?>&seccion_activa=tendencias" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'tendencias' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-graph-up-arrow me-2"></i>Tendencias y Periodos
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/compras')); ?>&seccion_activa=insumos" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'insumos' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-star-fill me-2"></i>Top Insumos
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/compras')); ?>&seccion_activa=proveedores" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'proveedores' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-building me-2"></i>Por Proveedores
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/compras')); ?>&seccion_activa=cumplimiento" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'cumplimiento' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-check2-circle me-2"></i>Cumplimiento OC
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/compras')); ?>&seccion_activa=variacion" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'variacion' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-tags-fill me-2"></i>Variación de Costos
                </a>
            </li>
        </ul>
    </div>

    <!-- TARJETA DE FILTROS -->
    <div class="card border-0 shadow-sm mb-4 rounded-top-0 border-top border-primary border-3">
        <div class="card-body p-3 p-md-4 bg-white">
            <form class="row g-3 align-items-end" method="get" action="<?php echo e(route_url('reportes/compras')); ?>" id="formFiltrosReporteCompras">
                <input type="hidden" name="ruta" value="reportes/compras">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <!-- 1. BLOQUE IZQUIERDO (Filtros específicos según pestaña) -->
                <div class="col-12 col-xl-7">
                    
                    <?php if ($seccionActiva === 'tendencias'): ?>
                        <div class="row g-2">
                            <div class="col-12 col-md-4">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Agrupar por</label>
                                <select name="agrupacion" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="diaria" <?php echo ($filtros['agrupacion'] ?? 'diaria') === 'diaria' ? 'selected' : ''; ?>>Diario</option>
                                    <option value="semanal" <?php echo ($filtros['agrupacion'] ?? '') === 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                                    <option value="mensual" <?php echo ($filtros['agrupacion'] ?? '') === 'mensual' ? 'selected' : ''; ?>>Mensual</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Tipo Gráfico</label>
                                <select name="tipo_grafico" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="linea" <?php echo ($filtros['tipo_grafico'] ?? 'linea') === 'linea' ? 'selected' : ''; ?>>Líneas</option>
                                    <option value="barras" <?php echo ($filtros['tipo_grafico'] ?? '') === 'barras' ? 'selected' : ''; ?>>Barras</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Almacén</label>
                                <select name="id_almacen" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="">Todos los almacenes...</option>
                                    <?php foreach (($almacenesFiltro ?? []) as $alm): ?>
                                        <option value="<?php echo (int) ($alm['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_almacen'] ?? 0) === (int)($alm['id'] ?? 0)) ? 'selected' : ''; ?>>
                                            <?php echo e((string) ($alm['nombre'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'insumos' || $seccionActiva === 'variacion'): ?>
                        <div class="row g-2">
                            <div class="col-12 col-md-5">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Categoría</label>
                                <select name="id_categoria" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="">Todas...</option>
                                    <?php foreach (($categoriasFiltro ?? []) as $cat): ?>
                                        <option value="<?php echo (int) ($cat['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_categoria'] ?? 0) === (int)($cat['id'] ?? 0)) ? 'selected' : ''; ?>>
                                            <?php echo e((string) ($cat['nombre'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-7">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Insumo Específico</label>
                                <select name="id_item" class="form-select bg-light shadow-none border-secondary-subtle auto-submit">
                                    <option value="">Buscar insumo o producto...</option>
                                    <!-- Aquí se cargan los items por JS si usas Select2 -->
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'proveedores' || $seccionActiva === 'cumplimiento'): ?>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Proveedor</label>
                                <input type="number" name="id_proveedor" class="form-control bg-light border-secondary-subtle shadow-none text-secondary" placeholder="Todos los proveedores..." value="<?php echo ($filtros['id_proveedor'] ?? 0) > 0 ? (int)$filtros['id_proveedor'] : ''; ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Almacén</label>
                                <select name="id_almacen" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="">Todos los almacenes...</option>
                                    <?php foreach (($almacenesFiltro ?? []) as $alm): ?>
                                        <option value="<?php echo (int) ($alm['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_almacen'] ?? 0) === (int)($alm['id'] ?? 0)) ? 'selected' : ''; ?>>
                                            <?php echo e((string) ($alm['nombre'] ?? '')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>

                <!-- 2. BLOQUE DERECHO (Fechas y Botones Universales) -->
                <div class="col-12 col-xl-5">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Periodo de Fechas <span class="text-danger">*</span></label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control bg-light border-start-0 border-end-0 border-secondary-subtle text-secondary" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>

                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control bg-light border-start-0 border-secondary-subtle text-secondary" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                                                                                                                        
                        <button class="btn btn-light border text-primary px-3 transition-hover" type="submit" title="Aplicar filtros">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <button type="button" id="btnLimpiarFiltros" class="btn btn-light border text-danger px-3 transition-hover" title="Limpiar filtros">
                            <i class="bi bi-eraser-fill"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ==========================================
         SECCIÓN 1: TENDENCIAS Y PERIODOS
    =========================================== -->
    <?php if ($seccionActiva === 'tendencias'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-graph-up-arrow me-2 text-primary"></i>Inversión en Compras
                <?php 
                    $tipoAgr = $filtros['agrupacion'] ?? 'diaria';
                    if ($tipoAgr === 'semanal') echo 'Semanales';
                    elseif ($tipoAgr === 'mensual') echo 'Mensuales';
                    else echo 'Diarias';
                ?>
            </h5>
            <div class="dropdown">
                <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                    <i class="bi bi-cloud-download me-2"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <button type="submit" form="formFiltrosReporteCompras" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                            <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                        </button>
                    </li>
                    <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                    <li>
                        <button type="submit" form="formFiltrosReporteCompras" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Gráfico -->
                <div class="col-12 col-lg-7">
                    <div class="border border-secondary-subtle rounded-3 p-3 bg-light" style="position: relative; height: 450px;">
                        <canvas id="comprasPeriodoChart" 
                                data-chart-data='<?php echo htmlspecialchars(json_encode($porPeriodo ?? []), ENT_QUOTES, 'UTF-8'); ?>'
                                data-chart-type="<?php echo ($filtros['tipo_grafico'] ?? 'barras') === 'linea' ? 'line' : 'bar'; ?>">
                        </canvas>
                    </div>
                </div>
                <!-- Tabla -->
                <div class="col-12 col-lg-5 d-flex flex-column">
                    <div class="table-responsive border border-secondary-subtle rounded-3 bg-white d-flex flex-column h-100">
                        <table class="table table-sm align-middle mb-0 table-hover" id="tablaRepComprasTendencias" data-erp-table="true" data-rows-per-page="12">
                            <thead class="table-light border-bottom border-secondary-subtle">
                                <tr>
                                    <th class="py-3 ps-3 text-secondary fw-semibold">Periodo</th>
                                    <th class="text-end py-3 text-secondary fw-semibold">Docs.</th>
                                    <th class="text-end py-3 pe-3 text-secondary fw-semibold">Total Comprado</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($porPeriodo)): ?>
                                    <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5 fst-italic">Sin datos para el rango seleccionado.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($porPeriodo as $r): ?>
                                        <tr class="border-bottom">
                                            <td class="fw-medium text-dark ps-3">
                                                <?php 
                                                    $etiqueta = (string)($r['etiqueta'] ?? '');
                                                    $agrupacion = $filtros['agrupacion'] ?? 'diaria';
                                                    if ($agrupacion === 'diaria' && strtotime($etiqueta)) {
                                                        echo date('d/m/Y', strtotime($etiqueta));
                                                    } else {
                                                        echo htmlspecialchars($etiqueta);
                                                    }
                                                ?>
                                            </td>
                                            <td class="text-end text-muted"><?php echo e((string)($r['documentos'] ?? '0')); ?></td>
                                            <td class="text-end fw-bold text-primary pe-3">S/ <?php echo number_format((float)($r['total_comprado'] ?? 0), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom-3">
                            <small class="text-muted fw-semibold" id="tablaRepComprasTendenciasPaginationInfo"></small>
                            <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepComprasTendenciasPaginationControls"></ul></nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==========================================
         SECCIÓN 2: TOP INSUMOS
    =========================================== -->
    <?php if ($seccionActiva === 'insumos'): ?>
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-star-fill me-2 text-warning"></i>Top Insumos Comprados
            </h5>
            <div class="dropdown">
                <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                    <i class="bi bi-cloud-download me-2"></i> Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                    <li>
                        <button type="submit" form="formFiltrosReporteCompras" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                            <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                        </button>
                    </li>
                    <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                    <li>
                        <button type="submit" form="formFiltrosReporteCompras" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- Gráfico -->
                <div class="col-12 col-lg-5">
                    <div class="border border-secondary-subtle rounded-3 p-3 bg-light d-flex flex-column h-100">
                        <h6 class="text-center fw-bold text-secondary mb-3"><i class="bi bi-pie-chart-fill me-2"></i>Distribución del Gasto</h6>
                        <div style="flex: 1; position: relative; min-height: 350px;">
                            <canvas id="comprasInsumosChart" data-chart-data='<?php echo htmlspecialchars(json_encode($topInsumos ?? []), ENT_QUOTES, 'UTF-8'); ?>'></canvas>
                        </div>
                    </div>
                </div>

                <!-- Tabla -->
                <div class="col-12 col-lg-7 d-flex flex-column">
                    <div class="table-responsive border border-secondary-subtle rounded-3 bg-white d-flex flex-column h-100">
                        <table class="table align-middle mb-0 table-hover" id="tablaRepComprasInsumos" data-erp-table="true" data-rows-per-page="10">
                            <thead class="table-light border-bottom border-secondary-subtle">
                                <tr>
                                    <th class="py-3 ps-4 text-secondary fw-semibold">Insumo / Producto</th>
                                    <th class="py-3 text-end text-secondary fw-semibold">Cant. Comprada</th>
                                    <th class="py-3 text-end pe-4 text-secondary fw-semibold">Inversión Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(empty($topInsumos)): ?>
                                    <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de compras.</td></tr>
                                <?php else: ?>
                                    <?php foreach (($topInsumos ?? []) as $r): ?>
                                        <tr class="border-bottom">
                                            <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['producto']); ?></td>
                                            <td class="text-end fw-semibold text-primary">
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 shadow-sm"><?php echo number_format((float)($r['total_cantidad'] ?? 0), 2); ?></span>
                                            </td>
                                            <td class="text-end pe-4 fw-bold text-dark">S/ <?php echo number_format((float)($r['total_monto'] ?? 0), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                            <small class="text-muted fw-semibold" id="tablaRepComprasInsumosPaginationInfo"></small>
                            <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepComprasInsumosPaginationControls"></ul></nav>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==========================================
         SECCIÓN 3: POR PROVEEDORES
    =========================================== -->
    <?php if ($seccionActiva === 'proveedores'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-building me-2 text-primary"></i>Compras por proveedor
            </h5>
            <div class="d-flex align-items-center gap-3">
                <div class="input-group input-group-sm shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepProveedores" placeholder="Buscar proveedor...">
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                        <i class="bi bi-cloud-download me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><button type="submit" form="formFiltrosReporteCompras" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center"><i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Excel (.xlsx)</button></li>
                        <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                        <li><button type="submit" form="formFiltrosReporteCompras" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank"><i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> PDF (.pdf)</button></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive bg-white">
                <table class="table align-middle mb-0 table-hover" id="tablaRepProveedores"
                       data-erp-table="true"
                       data-search-input="#filtroRepProveedores"
                       data-rows-per-page="12">
                    <thead class="table-light border-bottom border-secondary-subtle">
                        <tr>
                            <th class="py-3 ps-4 text-secondary fw-semibold">Proveedor</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Total Comprado</th>
                            <th class="py-3 text-center text-secondary fw-semibold"># Recepciones</th>
                            <th class="py-3 text-end pe-4 text-secondary fw-semibold">Ticket Promedio</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($porProveedor['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay datos para este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porProveedor['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['proveedor'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['proveedor']); ?></td>
                                    <td class="text-end fw-semibold text-primary">S/ <?php echo number_format((float)($r['total_recibido'] ?? 0), 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-secondary border shadow-sm px-2 py-1"><?php echo e((string)$r['recepciones']); ?></span>
                                    </td>
                                    <td class="text-end pe-4 fw-semibold text-muted">S/ <?php echo number_format((float)($r['costo_promedio_item'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                <small class="text-muted fw-semibold" id="tablaRepProveedoresPaginationInfo">Cargando...</small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepProveedoresPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==========================================
         SECCIÓN 4: CUMPLIMIENTO OC
    =========================================== -->
    <?php if ($seccionActiva === 'cumplimiento'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-check2-circle me-2 text-primary"></i>Estado y Cumplimiento OC
            </h5>
            <div class="d-flex align-items-center gap-3">
                <div class="input-group input-group-sm shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepCumplimiento" placeholder="Buscar OC o proveedor...">
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                        <i class="bi bi-cloud-download me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li><button type="submit" form="formFiltrosReporteCompras" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center"><i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Excel (.xlsx)</button></li>
                        <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                        <li><button type="submit" form="formFiltrosReporteCompras" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank"><i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> PDF (.pdf)</button></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive bg-white">
                <table class="table align-middle mb-0 table-hover" id="tablaRepCumplimiento"
                       data-erp-table="true"
                       data-search-input="#filtroRepCumplimiento"
                       data-rows-per-page="12">
                    <thead class="table-light border-bottom border-secondary-subtle">
                        <tr>
                            <th class="py-3 ps-4 text-secondary fw-semibold">Orden de Compra</th>
                            <th class="py-3 text-secondary fw-semibold">Proveedor</th>
                            <th class="py-3 text-center text-secondary fw-semibold">Solicitado</th>
                            <th class="py-3 text-center text-secondary fw-semibold">Recibido</th>
                            <th class="py-3 text-center text-secondary fw-semibold">% Cumplimiento</th>
                            <th class="py-3 text-center pe-4 text-secondary fw-semibold">Retraso</th>
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
                                            <span class="badge px-3 py-1 rounded-pill bg-danger-subtle text-danger border border-danger-subtle shadow-sm">Sí</span>
                                        <?php else: ?>
                                            <span class="badge px-3 py-1 rounded-pill bg-success-subtle text-success border border-success-subtle shadow-sm">No</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                <small class="text-muted fw-semibold" id="tablaRepCumplimientoPaginationInfo">Cargando...</small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepCumplimientoPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ==========================================
         SECCIÓN 5: VARIACIÓN DE COSTOS
    =========================================== -->
    <?php if ($seccionActiva === 'variacion'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-tags-fill me-2 text-primary"></i>Análisis de Variación de Costos
            </h5>
            <div class="d-flex align-items-center gap-3">
                <div class="input-group input-group-sm shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepVariacion" placeholder="Buscar insumo...">
                </div>
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                        <i class="bi bi-cloud-download me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <button type="submit" form="formFiltrosReporteCompras" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                                <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                            </button>
                        </li>
                        <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                        <li>
                            <button type="submit" form="formFiltrosReporteCompras" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive bg-white">
                <table class="table align-middle mb-0 table-hover" id="tablaRepVariacion"
                       data-erp-table="true"
                       data-search-input="#filtroRepVariacion"
                       data-rows-per-page="12">
                    <thead class="table-light border-bottom border-secondary-subtle">
                        <tr>
                            <th class="py-3 ps-4 text-secondary fw-semibold">Insumo / Producto</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Costo Anterior</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Costo Actual</th>
                            <th class="py-3 text-center text-secondary fw-semibold">Variación</th>
                            <th class="py-3 text-center pe-4 text-secondary fw-semibold">Tendencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($variacionCostos['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay suficientes datos comparativos.</td></tr>
                        <?php else: ?>
                            <?php foreach (($variacionCostos['rows'] ?? []) as $r): ?>
                                <?php 
                                    $costoAnterior = (float)($r['costo_anterior'] ?? 0);
                                    $costoActual = (float)($r['costo_actual'] ?? 0);
                                    $variacion = $costoAnterior > 0 ? (($costoActual - $costoAnterior) / $costoAnterior) * 100 : 0;
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['producto'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['producto']); ?></td>
                                    <td class="text-end text-muted fw-semibold">S/ <?php echo number_format($costoAnterior, 2); ?></td>
                                    <td class="text-end fw-bold text-dark">S/ <?php echo number_format($costoActual, 2); ?></td>
                                    <td class="text-center">
                                        <?php if($variacion > 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle shadow-sm px-2 py-1">+<?php echo number_format($variacion, 2); ?>%</span>
                                        <?php elseif($variacion < 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle shadow-sm px-2 py-1"><?php echo number_format($variacion, 2); ?>%</span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border shadow-sm px-2 py-1">0.00%</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4">
                                        <?php if($variacion > 0): ?>
                                            <i class="bi bi-arrow-up-circle-fill text-danger fs-5"></i>
                                        <?php elseif($variacion < 0): ?>
                                            <i class="bi bi-arrow-down-circle-fill text-success fs-5"></i>
                                        <?php else: ?>
                                            <i class="bi bi-dash-circle-fill text-secondary fs-5 opacity-50"></i>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                <small class="text-muted fw-semibold" id="tablaRepVariacionPaginationInfo">Cargando...</small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepVariacionPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>