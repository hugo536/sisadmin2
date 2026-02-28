<?php
$movimientos = $movimientos ?? [];
$items = $items ?? [];
$filtros = $filtros ?? [];

$tiposEntrada = ['INI', 'AJ+', 'COM', 'PROD'];
$tiposSalida = ['AJ-', 'CON', 'VEN'];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3 fade-in kardex-sticky-header">
        <h1 class="h4 fw-bold mb-0 text-dark d-flex align-items-center">
            <i class="bi bi-journal-text me-2 text-primary"></i>Kardex de inventario
        </h1>
        <a class="btn btn-white border shadow-sm text-secondary fw-semibold" href="<?php echo e(route_url('inventario')); ?>">
            <i class="bi bi-arrow-left me-1 text-info"></i>Volver al Inventario
        </a>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="">
                <input type="hidden" name="ruta" value="inventario/kardex">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Ítem</label>
                        <select class="form-select bg-light border-secondary-subtle shadow-sm" name="id_item">
                            <option value="0">Todos</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?php echo (int) ($item['id'] ?? 0); ?>" <?php echo ((int) ($filtros['id_item'] ?? 0) === (int) ($item['id'] ?? 0)) ? 'selected' : ''; ?>>
                                    <?php echo e((string) ($item['sku'] ?? '') . ' - ' . (string) ($item['nombre'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted fw-bold mb-1">Lote</label>
                        <input class="form-control bg-light border-secondary-subtle shadow-sm" type="text" name="lote" value="<?php echo e((string) ($filtros['lote'] ?? '')); ?>" placeholder="Ej: LOTE-001">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted fw-bold mb-1">Desde</label>
                        <input class="form-control bg-light border-secondary-subtle shadow-sm" type="date" name="fecha_desde" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
                        <input class="form-control bg-light border-secondary-subtle shadow-sm" type="date" name="fecha_hasta" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>">
                    </div>
                    <div class="col-md-2 d-flex align-items-end" style="height: 60px;">
                        <button class="btn btn-primary w-100 shadow-sm fw-bold h-100" type="submit">
                            <i class="bi bi-funnel me-2"></i>Filtrar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Movimientos Registrados</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?php echo count($movimientos); ?> Resultados</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchKardex" placeholder="Buscar en resultados...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-pro align-middle mb-0" id="tablaKardex"
                       data-erp-table="true"
                       data-rows-selector="#kardexTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchKardex"
                       data-empty-text="No se encontraron movimientos"
                       data-info-text-template="Mostrando {start} a {end} de {total} movimientos"
                       data-pagination-controls="#kardexPaginationControls"
                       data-pagination-info="#kardexPaginationInfo">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Tipo</th>
                            <th class="text-secondary fw-semibold">Ítem</th>
                            <th class="text-secondary fw-semibold">Origen</th>
                            <th class="text-secondary fw-semibold">Destino</th>
                            <th class="text-end text-secondary fw-semibold">Cantidad</th>
                            <th class="text-secondary fw-semibold ps-3">Usuario</th>
                            <th class="pe-4 text-secondary fw-semibold">Referencia</th>
                        </tr>
                    </thead>
                    <tbody id="kardexTableBody">
                    <?php if (empty($movimientos)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="bi bi-journal-x fs-1 d-block mb-2 text-light"></i>
                                Sin movimientos para los filtros seleccionados.
                            </td>
                        </tr>
                    <?php else: foreach ($movimientos as $mov): ?>
                        <?php 
                            $tipoMov = strtoupper(trim((string) ($mov['tipo_movimiento'] ?? ''))); 
                            
                            // Creamos la cadena para que el buscador funcione
                            $searchStr = strtolower(($mov['created_at'] ?? '') . ' ' . $tipoMov . ' ' . ($mov['sku'] ?? '') . ' ' . ($mov['item_nombre'] ?? '') . ' ' . ($mov['almacen_origen'] ?? '') . ' ' . ($mov['almacen_destino'] ?? '') . ' ' . ($mov['usuario'] ?? '') . ' ' . ($mov['referencia'] ?? ''));
                        ?>
                        <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                            <td class="ps-4 text-muted align-top pt-3">
                                <i class="bi bi-clock small me-1 opacity-50"></i><?php echo e((string) ($mov['created_at'] ?? '')); ?>
                            </td>
                            <td class="align-top pt-3 text-center">
                                <?php if (in_array($tipoMov, $tiposEntrada, true)): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">ENT - <?php echo e($tipoMov); ?></span>
                                <?php elseif (in_array($tipoMov, $tiposSalida, true)): ?>
                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">SAL - <?php echo e($tipoMov); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><?php echo e($tipoMov); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-dark align-top pt-3">
                                <?php echo e((string) ($mov['sku'] ?? '') . ' - ' . (string) ($mov['item_nombre'] ?? '')); ?>
                            </td>
                            <td class="align-top pt-3">
                                <?php if(!empty($mov['almacen_origen'])): ?>
                                    <i class="bi bi-building small text-muted me-1"></i><?php echo e((string) ($mov['almacen_origen'] ?? '-')); ?>
                                <?php else: ?>
                                    <span class="text-muted opacity-50">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="align-top pt-3">
                                <?php if(!empty($mov['almacen_destino'])): ?>
                                    <i class="bi bi-building small text-muted me-1"></i><?php echo e((string) ($mov['almacen_destino'] ?? '-')); ?>
                                <?php else: ?>
                                    <span class="text-muted opacity-50">-</span>
                                <?php endif; ?>
                            </td>
                            <?php
                                $cantidadBase = (float) ($mov['cantidad'] ?? 0);
                                $cantidadPrincipal = number_format($cantidadBase, 4, '.', '');
                                $referencia = (string) ($mov['referencia'] ?? '');
                                $detalleConversion = '';
                                if (preg_match('/Conv:\s*([^|]+)/i', $referencia, $matchConv)) {
                                    $detalleConversion = trim((string) ($matchConv[1] ?? ''));
                                }
                            ?>
                            <td class="text-end fw-semibold text-primary align-top pt-3">
                                <?php if ($detalleConversion !== ''): ?>
                                    <div class="text-dark"><?php echo e($detalleConversion); ?></div>
                                    <div class="small text-muted fw-normal"><?php echo e($cantidadPrincipal); ?></div>
                                <?php else: ?>
                                    <?php echo e($cantidadPrincipal); ?>
                                <?php endif; ?>
                            </td>
                            <td class="align-top pt-3 ps-3 text-secondary small">
                                <i class="bi bi-person-circle me-1 opacity-50"></i><?php echo e((string) ($mov['usuario'] ?? '-')); ?>
                            </td>
                            <td class="pe-4 align-top pt-3 small text-muted">
                                <?php echo e((string) ($mov['referencia'] ?? '-')); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($movimientos)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="kardexPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación de kardex">
                    <ul class="pagination mb-0 shadow-sm" id="kardexPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

