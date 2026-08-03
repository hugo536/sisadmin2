<?php
/**
 * @var array|null $filtros
 * @var array|null $registros
 * @var array|null $resumen
 * @var array|null $clientesEstadoCuenta
 */

$filtros = is_array($filtros ?? null) ? $filtros : [];
$registros = is_array($registros ?? null) ? $registros : [];
$resumen = is_array($resumen ?? null) ? $resumen : [];
$clientesEstadoCuenta = array_values(array_filter(array_map(
    static fn($item): string => trim((string)$item),
    is_array($clientesEstadoCuenta ?? null) ? $clientesEstadoCuenta : []
), static fn(string $nombre): bool => $nombre !== ''));

// Subtítulo dinámico para la cabecera
$periodoResumen = (string)($filtros['fecha_desde'] ?? '') !== '' && (string)($filtros['fecha_hasta'] ?? '') !== ''
    ? 'Mostrando cartera del ' . date('d/m/Y', strtotime((string)$filtros['fecha_desde'])) . ' al ' . date('d/m/Y', strtotime((string)$filtros['fecha_hasta']))
    : 'Mostrando análisis global de cartera';

$badge = static function (string $estado): string {
    if ($estado === 'PAGADA') return 'bg-success-subtle text-success border border-success-subtle';
    if ($estado === 'PARCIAL') return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    if ($estado === 'VENCIDA') return 'bg-danger-subtle text-danger border border-danger-subtle';
    if ($estado === 'ANULADA') return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    return 'bg-primary-subtle text-primary border border-primary-subtle';
};
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
            <div class="card border-0 shadow-sm h-100 border-start border-warning border-4">
                <div class="card-body d-flex flex-column justify-content-center">
                    <div class="small text-muted text-uppercase fw-semibold mb-1">Por Vencer</div>
                    <div class="h4 fw-bold mb-0 text-dark">S/ <?php echo number_format((float)($resumen['total_por_vencer'] ?? 0), 2); ?></div>
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

                <!-- Cliente / Distribuidor (Tom Select id sincronizado) -->
                <div class="col-12 col-md-4">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Cliente / Distribuidor</label>
                    <select name="cliente" id="filtroClienteEstadoCuenta" class="form-select bg-light shadow-sm">
                        <option value="">Todos</option>
                        <?php foreach ($clientesEstadoCuenta as $clienteNombre): ?>
                            <option value="<?php echo e($clienteNombre); ?>" <?php echo (string)($filtros['cliente'] ?? '') === $clienteNombre ? 'selected' : ''; ?>>
                                <?php echo e($clienteNombre); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Estado de Factura -->
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Estado de Obligación</label>
                    <select name="estado_factura" class="form-select bg-light shadow-sm">
                        <option value="todos" <?php echo (($filtros['estado_factura'] ?? 'todos') === 'todos') ? 'selected' : ''; ?>>Todas (Pendientes y Vencidas)</option>
                        <option value="vencida" <?php echo (($filtros['estado_factura'] ?? '') === 'vencida') ? 'selected' : ''; ?>>Solo Vencidas</option>
                        <option value="corriente" <?php echo (($filtros['estado_factura'] ?? '') === 'corriente') ? 'selected' : ''; ?>>Al Corriente (Por Vencer)</option>
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

    <!-- Vista de Tabla de Cartera -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark">
                <i class="bi bi-journal-text me-2 text-primary"></i> Detalle de Cartera por Cobrar
            </h5>
            <!-- Derecha: Acciones agrupadas (Botón Exportar y Buscador) -->
            <div class="d-flex align-items-center gap-3">
                
                <!-- Botón Exportar con opciones -->
                <div class="dropdown">
                    <button class="btn btn-secondary btn-sm shadow-sm fw-semibold dropdown-toggle" type="button" id="btnMenuExportarCxc" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-cloud-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu shadow-sm" aria-labelledby="btnMenuExportarCxc">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center" onclick="alert('Generador Excel en desarrollo');">
                                <i class="bi bi-file-earmark-excel-fill text-success me-2"></i> Formato Excel (.xlsx)
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center" onclick="alert('Generador CSV en desarrollo');">
                                <i class="bi bi-filetype-csv text-secondary me-2"></i> Datos Crudos (.csv)
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center" onclick="alert('Generador PDF en desarrollo');">
                                <i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i> Formato PDF (.pdf)
                            </button>
                        </li>
                    </ul>
                </div>

                <!-- Buscador -->
                <div class="input-group input-group-sm shadow-sm" style="max-width: 280px;">
                    <span class="input-group-text bg-white border-end-0 text-primary px-3">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="search" class="form-control border-start-0 ps-0" id="filtroCxcReporteDetalle" placeholder="Buscar cliente, documento...">
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
                            <th>Documento Ref.</th>
                            <th class="text-center">Emisión</th>
                            <th class="text-center">Vencimiento</th>
                            <th class="text-center">Días Mora</th>
                            <th class="text-end">Total Emitido</th>
                            <th class="text-end">Saldo Pendiente</th>
                            <th class="text-center pe-4">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($registros)): ?>
                        <tr class="empty-msg-row"><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de cartera con los filtros aplicados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($registros as $row): ?>
                            <?php
                            $estadoStr = strtoupper(trim((string)($row['estado'] ?? 'PENDIENTE')));
                            $fechaEmisionFmt = !empty($row['fecha_emision']) ? date('d-m-Y', strtotime($row['fecha_emision'])) : '';
                            $fechaVencFmt = !empty($row['fecha_vencimiento']) ? date('d-m-Y', strtotime($row['fecha_vencimiento'])) : '';
                            
                            $fechaVencTime = strtotime((string)($row['fecha_vencimiento'] ?? ''));
                            $hoy = time();
                            $diasMora = 0;
                            if ($fechaVencTime && $fechaVencTime < $hoy && in_array($estadoStr, ['VENCIDA', 'PARCIAL', 'PENDIENTE', 'ABIERTA'], true)) {
                                $diasMora = floor(($hoy - $fechaVencTime) / (60 * 60 * 24));
                            }

                            $montoTotalFmt = number_format((float)($row['monto_total'] ?? 0), 2, '.', '');
                            $saldoFmt = number_format((float)($row['saldo'] ?? 0), 2, '.', '');
                            
                            $search = mb_strtolower(trim(
                                ($row['cliente'] ?? '') . ' ' . 
                                ($row['documento_referencia'] ?? '') . ' ' . 
                                $estadoStr . ' ' . 
                                $fechaEmisionFmt . ' ' . 
                                $fechaVencFmt . ' ' . 
                                $montoTotalFmt . ' ' . 
                                $saldoFmt
                            ));
                            ?>
                            <tr data-search="<?php echo e($search); ?>">
                                <td class="ps-4 fw-semibold text-truncate" style="max-width: 220px;"><?php echo e((string)($row['cliente'] ?? '')); ?></td>
                                <td class="text-muted"><?php echo e((string)($row['documento_referencia'] ?? ('#' . str_pad((string)($row['id_documento_venta'] ?? 0), 6, '0', STR_PAD_LEFT)))); ?></td>
                                <td class="text-center text-muted"><?php echo e($fechaEmisionFmt); ?></td>
                                <td class="text-center text-muted"><?php echo e($fechaVencFmt); ?></td>
                                <td class="text-center">
                                    <?php if ($diasMora > 0): ?>
                                        <span class="badge bg-danger rounded-pill px-2"><?php echo $diasMora; ?> días</span>
                                    <?php else: ?>
                                        <span class="text-muted small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-secondary">S/ <?php echo number_format((float)($row['monto_total'] ?? 0), 2); ?></td>
                                <td class="text-end fw-bold text-primary">S/ <?php echo number_format((float)($row['saldo'] ?? 0), 2); ?></td>
                                <td class="text-center pe-4">
                                    <span class="badge px-2 py-1 rounded-pill shadow-sm <?php echo e($badge($estadoStr)); ?>" style="font-size: 0.75rem;">
                                        <?php echo e($estadoStr); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                    
                    <!-- Fila de Totales -->
                    <?php if (!empty($registros)): ?>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="5" class="text-end ps-4 text-muted">Totales Cartera Mostrada:</td>
                            <td class="text-end text-dark">S/ <?php echo number_format((float)($resumen['total_cartera'] ?? 0), 2); ?></td>
                            <td class="text-end text-primary pe-4" colspan="2">S/ <?php echo number_format((float)($resumen['total_cartera'] ?? 0), 2); ?></td>
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