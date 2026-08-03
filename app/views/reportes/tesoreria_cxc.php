<?php
/**
 * @var array|null $filtros
 * @var array|null $registros
 * @var array|null $resumen
 */

$filtros = is_array($filtros ?? null) ? $filtros : [];
$registros = is_array($registros ?? null) ? $registros : [];
$resumen = is_array($resumen ?? null) ? $resumen : [];

// Subtítulo dinámico para la cabecera
$periodoResumen = (string)($filtros['fecha_desde'] ?? '') !== '' && (string)($filtros['fecha_hasta'] ?? '') !== ''
    ? 'Análisis de cartera acumulada del ' . date('d/m/Y', strtotime((string)$filtros['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime((string)$filtros['fecha_hasta']))
    : 'Mostrando análisis global de cartera';
?>

<div class="container-fluid p-4" id="reportesCxcApp" data-url-index="<?php echo e(base_url() . '/'); ?>">
    
    <!-- Cabecera de la página -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Reporte Global de Cuentas por Cobrar
            </h1>
            <p class="text-muted small mb-0 ms-1"><?php echo e($periodoResumen); ?></p>
        </div>
        <a href="<?php echo e(route_url('reportes/dashboard')); ?>" 
        class="btn btn-light border shadow-sm fw-semibold text-secondary transition-hover sb-link"
        style="width: fit-content; flex: 0 0 auto;">
            <i class="bi bi-arrow-left-short fs-5 align-middle me-1"></i>Regresar
        </a>
    </div>

    <!-- Indicadores / KPIs -->
    <div class="row g-3 mb-4">
        <!-- Total Cartera -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Total Cartera (Por Cobrar)</div>
                    <div class="h4 fw-bold mb-0 text-primary">S/ <?php echo number_format((float)($resumen['total_cartera'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
        <!-- Total Vencido -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-danger border-4">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Total Vencido (Mora)</div>
                    <div class="h4 fw-bold mb-0 text-danger">S/ <?php echo number_format((float)($resumen['total_vencido'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
        <!-- Por Vencer -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100 border-start border-success border-4">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Por Vencer (Al Corriente)</div>
                    <div class="h4 fw-bold mb-0 text-success">S/ <?php echo number_format((float)($resumen['total_por_vencer'] ?? 0), 2); ?></div>
                </div>
            </div>
        </div>
        <!-- Clientes con Deuda -->
        <div class="col-6 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Clientes con Deuda</div>
                    <div class="h4 fw-bold mb-0 text-secondary"><?php echo (int)($resumen['clientes_con_deuda'] ?? 0); ?> <span class="fs-6 text-muted fw-normal">terceros</span></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Barra de Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(base_url() . '/'); ?>" id="cxcReporteFiltrosForm">
                <input type="hidden" name="ruta" value="reportes/cxc">

                <!-- Segmentación de Cartera -->
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Segmento / Tipo</label>
                    <select name="tipo_tercero" class="form-select bg-light shadow-sm">
                        <option value="todos" <?php echo (($filtros['tipo_tercero'] ?? 'todos') === 'todos') ? 'selected' : ''; ?>>Toda la Cartera</option>
                        <option value="cliente" <?php echo (($filtros['tipo_tercero'] ?? '') === 'cliente') ? 'selected' : ''; ?>>Solo Clientes (Retail)</option>
                        <option value="distribuidor" <?php echo (($filtros['tipo_tercero'] ?? '') === 'distribuidor') ? 'selected' : ''; ?>>Solo Distribuidores (Mayorista)</option>
                    </select>
                </div>
                
                <!-- Estado de Deuda -->
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Estado de Deuda</label>
                    <select name="estado_factura" class="form-select bg-light shadow-sm">
                        <option value="todos" <?php echo (($filtros['estado_factura'] ?? 'todos') === 'todos') ? 'selected' : ''; ?>>Con y Sin Mora</option>
                        <option value="vencida" <?php echo (($filtros['estado_factura'] ?? '') === 'vencida') ? 'selected' : ''; ?>>Solo Morosos (Vencida)</option>
                        <option value="corriente" <?php echo (($filtros['estado_factura'] ?? '') === 'corriente') ? 'selected' : ''; ?>>Al Corriente (Por Vencer)</option>
                    </select>
                </div>

                <!-- Filtro de Fechas Agrupado -->
                <div class="col-12 col-md-6">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1 d-none d-md-block">&nbsp;</label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" class="form-control bg-light border-start-0 border-end-0" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" class="form-control bg-light border-start-0" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                        
                        <!-- Botón Filtrar Azul -->
                        <button class="btn btn-light border text-primary px-3 transition-hover" type="submit" title="Aplicar filtros" style="z-index: 0;">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <!-- Botón Limpiar Rojo -->
                        <button type="button" id="btnLimpiarFiltrosCxcReporte" class="btn btn-light border text-danger px-3 transition-hover" title="Limpiar filtros" style="z-index: 0;" onclick="window.location.href='<?php echo e(route_url('reportes/cxc')); ?>';">
                            <i class="bi bi-eraser-fill"></i>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Vista de Tabla de Cartera Agrupada (Aging) -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-people-fill me-2 text-primary"></i> Antigüedad de Saldos por Cliente (Aging)
            </h5>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Botón Exportar -->
                <div class="dropdown">
                    <button class="btn btn-secondary btn-sm shadow-sm fw-semibold dropdown-toggle" type="button" id="btnMenuExportarCxc" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-cloud-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu shadow-sm" aria-labelledby="btnMenuExportarCxc">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center" id="btnExportarExcelCxc">
                                <i class="bi bi-file-earmark-excel-fill text-success me-2"></i> Formato Excel (.xlsx)
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center" id="btnExportarCsvCxc">
                                <i class="bi bi-filetype-csv text-secondary me-2"></i> Datos Crudos (.csv)
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Buscador rápido (Filtra la tabla en memoria) -->
                <div class="input-group input-group-sm shadow-sm" style="max-width: 280px;">
                    <span class="input-group-text bg-white border-end-0 text-primary px-3">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="search" class="form-control border-start-0 ps-0" id="filtroCxcReporteDetalle" placeholder="Buscar cliente...">
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaCxcReporteDetalle"
                       data-erp-table="true"
                       data-search-input="#filtroCxcReporteDetalle"
                       data-rows-per-page="15">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Cliente / Distribuidor</th>
                            <th class="text-end">Total Deuda</th>
                            <th class="text-end text-success">Al Corriente</th>
                            <th class="text-end text-warning">Mora 1-30 días</th>
                            <th class="text-end text-orange" style="color: #fd7e14 !important;">Mora 31-60 días</th>
                            <th class="text-end text-danger">Mora 61+ días</th>
                            <th class="text-center pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($registros)): ?>
                        <tr class="empty-msg-row"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay deuda acumulada en los filtros seleccionados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registros as $row): ?>
                            <?php
                            $search = mb_strtolower(trim(($row['cliente'] ?? '') . ' ' . ($row['tipo_tercero'] ?? '')));
                            ?>
                            <tr data-search="<?php echo e($search); ?>">
                                <td class="ps-4">
                                    <div class="fw-bold text-dark text-truncate" style="max-width: 250px; font-size: 0.95rem;">
                                        <?php echo e((string)($row['cliente'] ?? '')); ?>
                                    </div>
                                    <div class="small text-muted"><?php echo e(ucfirst((string)($row['tipo_tercero'] ?? 'Cliente'))); ?></div>
                                </td>
                                
                                <td class="text-end fw-bold text-dark fs-6">S/ <?php echo number_format((float)($row['total_deuda'] ?? 0), 2); ?></td>
                                
                                <!-- Al Corriente -->
                                <td class="text-end fw-medium <?php echo ((float)($row['por_vencer'] ?? 0) > 0) ? 'text-success' : 'text-muted opacity-50'; ?>">
                                    <?php echo ((float)($row['por_vencer'] ?? 0) > 0) ? 'S/ ' . number_format((float)$row['por_vencer'], 2) : '-'; ?>
                                </td>
                                
                                <!-- Mora 1 a 30 -->
                                <td class="text-end fw-medium <?php echo ((float)($row['mora_30'] ?? 0) > 0) ? 'text-warning' : 'text-muted opacity-50'; ?>">
                                    <?php echo ((float)($row['mora_30'] ?? 0) > 0) ? 'S/ ' . number_format((float)$row['mora_30'], 2) : '-'; ?>
                                </td>
                                
                                <!-- Mora 31 a 60 -->
                                <td class="text-end fw-medium" style="<?php echo ((float)($row['mora_60'] ?? 0) > 0) ? 'color: #fd7e14;' : 'color: #6c757d; opacity: 0.5;'; ?>">
                                    <?php echo ((float)($row['mora_60'] ?? 0) > 0) ? 'S/ ' . number_format((float)$row['mora_60'], 2) : '-'; ?>
                                </td>
                                
                                <!-- Mora Crítica 61+ -->
                                <td class="text-end fw-bold <?php echo ((float)($row['mora_mas_60'] ?? 0) > 0) ? 'text-danger' : 'text-muted opacity-50 fw-normal'; ?>">
                                    <?php echo ((float)($row['mora_mas_60'] ?? 0) > 0) ? 'S/ ' . number_format((float)$row['mora_mas_60'], 2) : '-'; ?>
                                </td>
                                
                                <td class="text-center pe-4">
                                    <a href="<?php echo e(route_url('reportes/estado_cuenta')); ?>&cliente=<?php echo urlencode((string)($row['cliente'] ?? '')); ?>" 
                                       class="btn btn-sm btn-light border text-primary shadow-sm rounded-pill px-3"
                                       data-bs-toggle="tooltip" title="Ver historial detallado">
                                        <i class="bi bi-file-earmark-text me-1"></i> Ver Detalle
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    
                    <!-- Fila de Totales -->
                    <?php if (!empty($registros)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td class="text-end ps-4 text-muted text-uppercase">Totales Globales:</td>
                            <td class="text-end text-dark fs-6">S/ <?php echo number_format((float)($resumen['total_cartera'] ?? 0), 2); ?></td>
                            <td class="text-end text-success">S/ <?php echo number_format((float)($resumen['total_por_vencer'] ?? 0), 2); ?></td>
                            <td class="text-end text-warning">S/ <?php echo number_format((float)($resumen['total_mora_30'] ?? 0), 2); ?></td>
                            <td class="text-end" style="color: #fd7e14;">S/ <?php echo number_format((float)($resumen['total_mora_60'] ?? 0), 2); ?></td>
                            <td class="text-end text-danger">S/ <?php echo number_format((float)($resumen['total_mora_mas_60'] ?? 0), 2); ?></td>
                            <td class="pe-4"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaCxcReporteDetallePaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaCxcReporteDetallePaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>