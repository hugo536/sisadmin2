<?php 
    // Capturamos la sección activa de la URL, por defecto 'tendencias'
    $seccionActiva = $_GET['seccion_activa'] ?? ($filtros['seccion_activa'] ?? 'tendencias');
    if (!in_array($seccionActiva, ['tendencias', 'clientes', 'productos', 'pendientes'])) {
        $seccionActiva = 'tendencias';
    }
?>
<div class="container-fluid p-4" id="reportesVentasApp">
    
    <!-- ENCABEZADO -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bag-check-fill me-2 text-primary"></i> Reportes de Ventas
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de facturación, top de clientes/productos y control de despachos.</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-white border shadow-sm fw-semibold text-secondary transition-hover">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>

    <!-- PESTAÑAS (Estilo "Carpeta" moderna) -->
    <div class="bg-light pt-2 px-3 rounded-top border border-bottom-0">
        <ul class="nav nav-tabs border-bottom-0 mb-0" role="tablist">
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/ventas')); ?>&seccion_activa=tendencias" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'tendencias' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-graph-up-arrow me-2"></i>Tendencias y Periodos
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/ventas')); ?>&seccion_activa=clientes" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'clientes' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-person-lines-fill me-2"></i>Por Clientes
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/ventas')); ?>&seccion_activa=productos" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'productos' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-star-fill me-2"></i>Top Productos
                </a>
            </li>
            <li class="nav-item" role="presentation">
                <a href="<?php echo e(route_url('reportes/ventas')); ?>&seccion_activa=pendientes" 
                   class="nav-link fs-6 fw-semibold py-3 px-4 <?php echo $seccionActiva === 'pendientes' ? 'active text-primary border-bottom-0 bg-white' : 'text-muted border-0 bg-transparent transition-hover'; ?>">
                    <i class="bi bi-truck me-2"></i>Pendientes de Despacho
                </a>
            </li>
        </ul>
    </div>

    <!-- TARJETA DE FILTROS -->
    <div class="card border-0 shadow-sm mb-4 rounded-top-0 border-top border-primary border-3">
        <div class="card-body p-3 p-md-4 bg-white">
            <form class="row g-3 align-items-end" method="get" action="<?php echo e(route_url('reportes/ventas')); ?>" id="formFiltrosReporteVentas">
                <input type="hidden" name="ruta" value="reportes/ventas">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <!-- 1. ESTADO DOC (Más ancho: pasamos a col-xl-3 y col-md-5) -->
                <div class="col-12 col-md-5 col-xl-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Estado Doc.</label>
                    <select name="estado" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                        <option value="validas" <?php echo ($filtros['estado'] ?? 'validas') === 'validas' ? 'selected' : ''; ?>>Ventas Válidas (Recomendado)</option>
                        <option value="2" <?php echo ($filtros['estado'] ?? '') === '2' ? 'selected' : ''; ?>>Aprobado (Por Despachar)</option>
                        <option value="6" <?php echo ($filtros['estado'] ?? '') === '6' ? 'selected' : ''; ?>>Despacho Parcial</option>
                        <option value="3" <?php echo ($filtros['estado'] ?? '') === '3' ? 'selected' : ''; ?>>Cerrado / Entregado</option>
                        <option value="5" <?php echo ($filtros['estado'] ?? '') === '5' ? 'selected' : ''; ?>>Devolución Parcial</option>
                        <option value="4" <?php echo ($filtros['estado'] ?? '') === '4' ? 'selected' : ''; ?>>Devuelto Total</option>
                        <option value="9" <?php echo ($filtros['estado'] ?? '') === '9' ? 'selected' : ''; ?>>Anuladas</option>
                        <option value="0" <?php echo ($filtros['estado'] ?? '') === '0' ? 'selected' : ''; ?>>Borradores</option>
                        <option value="todas" <?php echo ($filtros['estado'] ?? '') === 'todas' ? 'selected' : ''; ?>>Todos (Sin filtro)</option>
                    </select>
                </div>

                <!-- 2. SECCIÓN DEL MEDIO (Más angosto: pasamos a col-xl-4 y col-md-7) -->
                <div class="col-12 col-md-7 col-xl-4">
                    <?php if ($seccionActiva === 'tendencias'): ?>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Agrupar por</label>
                                <select name="agrupacion" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="diaria" <?php echo ($filtros['agrupacion'] ?? 'diaria') === 'diaria' ? 'selected' : ''; ?>>Diario</option>
                                    <option value="semanal" <?php echo ($filtros['agrupacion'] ?? '') === 'semanal' ? 'selected' : ''; ?>>Semanal</option>
                                    <option value="mensual" <?php echo ($filtros['agrupacion'] ?? '') === 'mensual' ? 'selected' : ''; ?>>Mensual</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Tipo Gráfico</label>
                                <select name="tipo_grafico" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <!-- AQUÍ ESTÁ EL CAMBIO: Línea ahora es el valor por defecto -->
                                    <option value="linea" <?php echo ($filtros['tipo_grafico'] ?? 'linea') === 'linea' ? 'selected' : ''; ?>>Líneas</option>
                                    <option value="barras" <?php echo ($filtros['tipo_grafico'] ?? '') === 'barras' ? 'selected' : ''; ?>>Barras</option>
                                </select>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($seccionActiva === 'clientes' || $seccionActiva === 'pendientes'): ?>
                        <div class="row g-2">
                            <div class="col-12 col-sm-5">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Tipo Tercero</label>
                                <select name="tipo_tercero" id="filtroVentasTipoTercero" class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit">
                                    <option value="">Todos...</option>
                                    <option value="cliente" <?php echo ($filtros['tipo_tercero'] ?? '') === 'cliente' ? 'selected' : ''; ?>>Cliente</option>
                                    <option value="cliente_distribuidor" <?php echo ($filtros['tipo_tercero'] ?? '') === 'cliente_distribuidor' ? 'selected' : ''; ?>>Cliente-Distrib.</option>
                                    <option value="distribuidor" <?php echo ($filtros['tipo_tercero'] ?? '') === 'distribuidor' ? 'selected' : ''; ?>>Distribuidor</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-7">
                                <label class="form-label text-muted small fw-bold mb-1 ms-1">Cliente Específico</label>
                                <select name="id_cliente" id="filtroVentasCliente" class="form-select bg-light shadow-none border-secondary-subtle auto-submit" placeholder="Buscar cliente...">
                                    <option value="" <?php echo empty($filtros['id_cliente']) ? 'selected' : ''; ?>>Todos...</option>
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
                            <label class="form-label text-muted small fw-bold mb-1 ms-1">Producto Específico</label>
                            <select name="id_item" id="filtroVentasProducto" class="form-select bg-light shadow-none border-secondary-subtle auto-submit" placeholder="Buscar producto...">
                                <option value="" <?php echo empty($filtros['id_item']) ? 'selected' : ''; ?>>Todos...</option>
                                <?php foreach (($productosFiltro ?? []) as $item): ?>
                                    <option value="<?php echo (int) ($item['id'] ?? 0); ?>" <?php echo ((int)($filtros['id_item'] ?? 0) === (int)($item['id'] ?? 0)) ? 'selected' : ''; ?>>
                                        <?php echo e((string) ($item['nombre'] ?? '')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-xl-5">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Periodo de Fechas <span class="text-danger">*</span></label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <!-- Le quitamos la clase auto-submit -->
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control bg-light border-start-0 border-end-0 border-secondary-subtle text-secondary" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>

                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <!-- Le quitamos la clase auto-submit -->
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

    <!-- SECCIÓN: TENDENCIAS -->
    <?php if ($seccionActiva === 'tendencias'): ?>
    
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-graph-up-arrow me-2 text-success"></i>Ventas 
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
                        <button type="submit" form="formFiltrosReporteVentas" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                            <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                        </button>
                    </li>
                    <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                    <li>
                        <button type="submit" form="formFiltrosReporteVentas" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                            <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                        </button>
                    </li>
                </ul>
            </div>
        </div>
        <div class="card-body p-4">
            <div class="row g-4">
                <!-- COLUMNA DEL GRÁFICO -->
                <div class="col-12 col-lg-7">
                    <!-- Quitamos el h-100 y fijamos una altura para que el gráfico se mantenga proporcionado -->
                    <div class="border border-secondary-subtle rounded-3 p-3 bg-light" style="position: relative; height: 450px;">
                        <canvas id="ventasPeriodoChart" 
                                aria-label="Gráfico de ventas por periodo" 
                                role="img"
                                data-chart-data='<?php echo htmlspecialchars(json_encode($porPeriodo ?? []), ENT_QUOTES, 'UTF-8'); ?>'
                                data-chart-type="<?php echo ($filtros['tipo_grafico'] ?? 'barras') === 'linea' ? 'line' : 'bar'; ?>">
                        </canvas>
                    </div>
                </div>
                
                <!-- COLUMNA DE LA TABLA -->
                <div class="col-12 col-lg-5 d-flex flex-column">
                    <!-- Agregamos bg-white y flex-grow-1 para estructurar bien la tarjeta con su footer -->
                    <div class="table-responsive border border-secondary-subtle rounded-3 bg-white d-flex flex-column h-100">
                        
                        <!-- Agregamos id, data-erp-table y data-rows-per-page="12" (puedes cambiarlo a 25 si prefieres) -->
                        <table class="table table-sm align-middle mb-0 table-hover" id="tablaRepVentasTendencias" data-erp-table="true" data-rows-per-page="12">
                            <thead class="table-light border-bottom border-secondary-subtle">
                                <tr>
                                    <th class="py-3 ps-3 text-secondary fw-semibold">Periodo</th>
                                    <th class="text-end py-3 text-secondary fw-semibold">Docs.</th>
                                    <th class="text-end py-3 pe-3 text-secondary fw-semibold">Total Vendido</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($porPeriodo)): ?>
                                    <tr class="empty-msg-row">
                                        <td colspan="3" class="text-center text-muted py-5 fst-italic">Sin datos para el rango seleccionado.</td>
                                    </tr>
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
                                            <td class="text-end fw-bold text-success pe-3">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                        <!-- NUEVO: PIE DE PÁGINA PARA LA PAGINACIÓN -->
                        <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom-3">
                            <small class="text-muted fw-semibold" id="tablaRepVentasTendenciasPaginationInfo"></small>
                            <nav>
                                <ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepVentasTendenciasPaginationControls"></ul>
                            </nav>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECCIÓN: CLIENTES -->
    <?php if ($seccionActiva === 'clientes'): ?>
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-person-lines-fill me-2 text-info"></i>Ventas por Cliente
                <span class="badge bg-light text-secondary border ms-3 fw-normal d-none d-md-inline" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            
            <div class="d-flex align-items-center gap-2">
                <!-- DROPDOWN DE EXPORTAR -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                        <i class="bi bi-cloud-download me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <button type="submit" form="formFiltrosReporteVentas" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                                <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                            </button>
                        </li>
                        <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                        <li>
                            <button type="submit" form="formFiltrosReporteVentas" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="input-group input-group-sm w-auto ms-2 shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-white border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" id="filtroRepVentasCliente" placeholder="Buscar cliente...">
                </div>
            </div>
        </div>
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive bg-white">
                <!-- SE QUITÓ table-pro PARA RECUPERAR EL DISEÑO LIMPIO -->
                <table class="table align-middle mb-0 table-hover" id="tablaRepVentasCliente" data-erp-table="true" data-search-input="#filtroRepVentasCliente" data-rows-per-page="12">
                    <thead class="table-light border-bottom border-secondary-subtle">
                        <tr>
                            <th class="py-3 ps-4 text-secondary fw-semibold">Cliente</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Total Vendido</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Ticket Promedio</th>
                            <th class="py-3 text-center text-secondary fw-semibold">Docs. Emitidos</th>
                            <th class="py-3 text-center pe-4 text-secondary fw-semibold">Acciones</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($porCliente['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de ventas.</td></tr>
                        <?php else: ?>
                            <?php foreach (($porCliente['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format((float)($r['total_vendido'] ?? 0), 2); ?></td>
                                    <td class="text-end text-muted fw-medium">S/ <?php echo number_format((float)($r['ticket_promedio'] ?? 0), 2); ?></td>
                                    <td class="text-center"><span class="badge bg-light text-secondary border border-secondary-subtle shadow-sm"><?php echo e((string)$r['documentos']); ?></span></td>
                                    <td class="text-center pe-4">
                                        <?php 
                                            $urlDetalle = route_url('reportes/estado_cuenta') . 
                                                          '&cliente=' . urlencode((string)$r['cliente']) . 
                                                          '&fecha_desde=' . urlencode($filtros['fecha_desde'] ?? '') . 
                                                          '&fecha_hasta=' . urlencode($filtros['fecha_hasta'] ?? '') . 
                                                          '&vista=PRODUCTO';
                                        ?>
                                        <a href="<?php echo $urlDetalle; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm transition-hover" data-bs-toggle="tooltip" title="Ver detalle de productos">
                                            <i class="bi bi-eye-fill me-1"></i> Detalle
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                <small class="text-muted fw-semibold" id="tablaRepVentasClientePaginationInfo"></small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepVentasClientePaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECCIÓN: PRODUCTOS -->
    <?php if ($seccionActiva === 'productos'): ?>
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-star-fill me-2 text-warning"></i>Top Productos Vendidos
                <span class="badge bg-light text-secondary border ms-3 fw-normal d-none d-md-inline" style="font-size: 0.75rem;">Solo ventas comerciales</span>
            </h5>
            
            <div class="d-flex align-items-center gap-2">
                <!-- DROPDOWN DE EXPORTAR -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                        <i class="bi bi-cloud-download me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <button type="submit" form="formFiltrosReporteVentas" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                                <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                            </button>
                        </li>
                        <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                        <li>
                            <button type="submit" form="formFiltrosReporteVentas" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="input-group input-group-sm w-auto ms-2 shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-white border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" id="filtroRepVentasProd" placeholder="Buscar producto...">
                </div>
            </div>
        </div>
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive bg-white">
                <!-- SE QUITÓ table-pro -->
                <table class="table align-middle mb-0 table-hover" id="tablaRepVentasProd" data-erp-table="true" data-search-input="#filtroRepVentasProd" data-rows-per-page="12">
                    <thead class="table-light border-bottom border-secondary-subtle">
                        <tr>
                            <th class="py-3 ps-4 text-secondary fw-semibold">Producto</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Cantidad Vendida</th>
                            <th class="py-3 text-end pe-4 text-secondary fw-semibold">Monto Generado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($topProductos)): ?>
                            <tr class="empty-msg-row"><td colspan="3" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay productos vendidos.</td></tr>
                        <?php else: ?>
                            <?php foreach (($topProductos ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['producto'])); ?>">
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
            </div>
            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                <small class="text-muted fw-semibold" id="tablaRepVentasProdPaginationInfo"></small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepVentasProdPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- SECCIÓN: PENDIENTES -->
    <?php if ($seccionActiva === 'pendientes'): ?>
    <div class="card border-0 shadow-sm mb-4 bg-white">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-truck me-2 text-danger"></i>Pendientes de Despacho
                <span class="badge bg-light text-secondary border ms-3 fw-normal d-none d-md-inline" style="font-size: 0.75rem;">Incluye donaciones</span>
            </h5>
            
            <div class="d-flex flex-wrap align-items-center gap-2">
                <!-- DROPDOWN DE EXPORTAR -->
                <div class="dropdown">
                    <button class="btn btn-sm btn-secondary fw-semibold shadow-sm dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="background-color: #4b5563; border-color: #4b5563;">
                        <i class="bi bi-cloud-download me-2"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
                        <li>
                            <button type="submit" form="formFiltrosReporteVentas" name="exportar_excel" value="1" class="dropdown-item py-2 d-flex align-items-center">
                                <i class="bi bi-file-earmark-excel-fill text-success fs-5 me-2"></i> Formato Excel (.xlsx)
                            </button>
                        </li>
                        <li><hr class="dropdown-divider m-0 border-secondary-subtle"></li>
                        <li>
                            <button type="submit" form="formFiltrosReporteVentas" name="exportar_pdf" value="1" class="dropdown-item py-2 d-flex align-items-center" formtarget="_blank">
                                <i class="bi bi-file-earmark-pdf-fill text-danger fs-5 me-2"></i> Formato PDF (.pdf)
                            </button>
                        </li>
                    </ul>
                </div>
                
                <div class="input-group input-group-sm w-auto ms-2 shadow-sm" style="max-width: 250px;">
                    <span class="input-group-text bg-white border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" id="filtroRepVentasPendientes" placeholder="Buscar doc o cliente...">
                </div>
            </div>
        </div>
        <div class="card-body p-0 d-flex flex-column">
            <div class="table-responsive bg-white">
                <table class="table align-middle mb-0 table-hover" id="tablaRepVentasPendientes" data-erp-table="true" data-search-input="#filtroRepVentasPendientes" data-rows-per-page="12">
                    <thead class="table-light border-bottom border-secondary-subtle">
                        <tr>
                            <!-- Eliminamos la columna del Checkbox y la de Ruta -->
                            <th class="py-3 ps-4 text-secondary fw-semibold">Documento</th>
                            <th class="py-3 text-secondary fw-semibold">Cliente</th>
                            <th class="py-3 text-end text-secondary fw-semibold">Saldo Pendiente</th>
                            <th class="py-3 text-center pe-4 text-secondary fw-semibold">SLA Espera</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($pendientes['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-check2-circle fs-1 d-block mb-2 text-success opacity-50"></i>Todo al día. No hay despachos pendientes.</td></tr>
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
                                    <!-- Eliminamos el TD del checkbox -->
                                    <td class="ps-4 fw-bold text-primary">
                                        <?php echo e((string)$r['documento']); ?>
                                        <?php if($esDonacion): ?><br><span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2 py-0 mt-1 shadow-sm" style="font-size: 0.65rem;">DONACIÓN</span><?php endif; ?>
                                    </td>
                                    <td class="fw-semibold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <!-- Eliminamos el TD de la ruta -->
                                    <td class="text-end fw-bold text-danger">
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 shadow-sm"><?php echo number_format((float)($r['saldo_despachar'] ?? 0), 2); ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge px-3 py-1 rounded-pill border shadow-sm <?php echo $badgeDias; ?>"><?php echo $dias; ?> día(s)</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="mt-auto border-top border-secondary-subtle py-2 px-3 d-flex justify-content-between align-items-center bg-light rounded-bottom">
                <small class="text-muted fw-semibold" id="tablaRepVentasPendientesPaginationInfo"></small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end shadow-none" id="tablaRepVentasPendientesPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>