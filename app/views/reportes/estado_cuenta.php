<?php
$filtros = is_array($filtros ?? null) ? $filtros : [];
$detalle = is_array($detalle ?? null) ? $detalle : ['rows' => [], 'total' => 0, 'resumen' => []];
$porProducto = is_array($porProducto ?? null) ? $porProducto : [];
$resumen = is_array($detalle['resumen'] ?? null) ? $detalle['resumen'] : [];
$vista = (string) ($filtros['vista'] ?? 'DETALLE');
$periodoResumen = (string)($filtros['fecha_desde'] ?? '') !== '' && (string)($filtros['fecha_hasta'] ?? '') !== ''
    ? 'Periodo: ' . date('d-m-Y', strtotime((string)$filtros['fecha_desde'])) . ' al ' . date('d-m-Y', strtotime((string)$filtros['fecha_hasta']))
    : 'Periodo filtrado';
?>

<div class="container-fluid p-4" id="reportesEstadoCuentaApp">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Estado de Cuenta Clientes / Distribuidores
            </h1>
            <p class="text-muted small mb-0 ms-1">Consulta fechas de atención, productos, cantidades, precios, depósitos y saldo pendiente.</p>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form class="row g-2 align-items-end" method="get" action="<?php echo e(route_url('reportes/estado_cuenta')); ?>" id="estadoCuentaFiltrosForm">
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control bg-light" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Cliente/Distribuidor</label>
                    <input type="search" name="cliente" class="form-control bg-light" placeholder="Nombre del cliente" value="<?php echo e((string)($filtros['cliente'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Producto</label>
                    <input type="search" name="producto" class="form-control bg-light" placeholder="Nombre del producto" value="<?php echo e((string)($filtros['producto'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Estado deuda</label>
                    <select name="estado" class="form-select bg-light">
                        <?php $estadoSel = (string)($filtros['estado'] ?? ''); ?>
                        <option value="" <?php echo $estadoSel === '' ? 'selected' : ''; ?>>Todos</option>
                        <option value="PENDIENTE" <?php echo $estadoSel === 'PENDIENTE' ? 'selected' : ''; ?>>PENDIENTE</option>
                        <option value="PARCIAL" <?php echo $estadoSel === 'PARCIAL' ? 'selected' : ''; ?>>PARCIAL</option>
                        <option value="PAGADA" <?php echo $estadoSel === 'PAGADA' ? 'selected' : ''; ?>>PAGADA</option>
                        <option value="VENCIDA" <?php echo $estadoSel === 'VENCIDA' ? 'selected' : ''; ?>>VENCIDA</option>
                        <option value="ANULADA" <?php echo $estadoSel === 'ANULADA' ? 'selected' : ''; ?>>ANULADA</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Vista</label>
                    <select name="vista" class="form-select bg-light">
                        <option value="DETALLE" <?php echo $vista === 'DETALLE' ? 'selected' : ''; ?>>Detalle</option>
                        <option value="PRODUCTO" <?php echo $vista === 'PRODUCTO' ? 'selected' : ''; ?>>Resumen por Producto</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-1"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Documentos</div>
                    <div class="h4 fw-bold mb-0"><?php echo (int) ($resumen['total_documentos'] ?? 0); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Facturado</div>
                    <div class="h4 fw-bold mb-0 text-primary">S/ <?php echo number_format((float)($resumen['total_facturado'] ?? 0), 2); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Total Depósitos</div>
                    <div class="h4 fw-bold mb-0 text-success">S/ <?php echo number_format((float)($resumen['total_pagado'] ?? 0), 2); ?></div>
                    <div class="small text-muted mt-1"><?php echo e($periodoResumen); ?></div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="small text-muted text-uppercase">Saldo Pendiente</div>
                    <div class="h4 fw-bold mb-0 text-danger">S/ <?php echo number_format((float)($resumen['total_saldo'] ?? 0), 2); ?></div>
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
                        <thead>
                            <tr>
                                <th class="ps-4">Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Total facturado</th>
                                <th class="text-end pe-4">Saldo deuda</th>
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
                <h5 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Detalle de atención y deuda</h5>
                <div class="input-group input-group-sm w-auto" style="max-width: 260px;">
                    <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroEstadoCuentaDetalle" placeholder="Buscar cliente/producto...">
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle mb-0 table-pro" id="tablaEstadoCuentaDetalle"
                           data-erp-table="true"
                           data-search-input="#filtroEstadoCuentaDetalle"
                           data-rows-per-page="15">
                        <thead>
                            <tr>
                                <th class="ps-4">Fecha atención</th>
                                <th>Cliente/Distribuidor</th>
                                <th>Documento</th>
                                <th>Producto</th>
                                <th class="text-end">Cantidad</th>
                                <th class="text-end">Precio</th>
                                <th class="text-end">Depósitos</th>
                                <th class="text-end">Saldo deuda</th>
                                <th class="text-center pe-4">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php $rows = $detalle['rows'] ?? []; ?>
                        <?php if (empty($rows)): ?>
                            <tr class="empty-msg-row"><td colspan="9" class="text-center text-muted py-5">Sin resultados para los filtros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($rows as $row): ?>
                                <?php
                                $estado = strtoupper((string)($row['estado'] ?? ''));
                                $badge = match ($estado) {
                                    'PAGADA' => 'bg-success-subtle text-success border-success-subtle',
                                    'PARCIAL' => 'bg-warning-subtle text-warning-emphasis border-warning-subtle',
                                    'VENCIDA' => 'bg-danger-subtle text-danger border-danger-subtle',
                                    'ANULADA' => 'bg-secondary-subtle text-secondary border-secondary-subtle',
                                    default => 'bg-info-subtle text-info border-info-subtle',
                                };
                                $search = mb_strtolower(trim((string)($row['cliente'] ?? '') . ' ' . (string)($row['documento'] ?? '') . ' ' . (string)($row['producto'] ?? '')));
                                ?>
                                <tr data-search="<?php echo e($search); ?>">
                                    <?php
                                    $fechaAtencion = trim((string)($row['fecha_atencion'] ?? ''));
                                    $fechaAtencionFmt = $fechaAtencion !== '' ? date('d-m-Y', strtotime($fechaAtencion)) : '';
                                    ?>
                                    <td class="ps-4"><?php echo e($fechaAtencionFmt); ?></td>
                                    <td class="fw-semibold"><?php echo e((string)($row['cliente'] ?? '')); ?></td>
                                    <td><?php echo e((string)($row['documento'] ?? '')); ?></td>
                                    <td><?php echo e((string)($row['producto'] ?? 'Sin producto asociado')); ?></td>
                                    <td class="text-end"><?php echo number_format((float)($row['cantidad'] ?? 0), 2); ?></td>
                                    <td class="text-end">S/ <?php echo number_format((float)($row['precio_unitario'] ?? 0), 2); ?></td>
                                    <td class="text-end text-success fw-semibold">S/ <?php echo number_format((float)($row['depositos_documento'] ?? 0), 2); ?></td>
                                    <td class="text-end text-danger fw-bold">S/ <?php echo number_format((float)($row['saldo_documento'] ?? 0), 2); ?></td>
                                    <td class="text-center pe-4"><span class="badge border <?php echo e($badge); ?>"><?php echo e($estado === '' ? 'PENDIENTE' : $estado); ?></span></td>
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

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('estadoCuentaFiltrosForm');
    if (!form) return;

    const fields = form.querySelectorAll('input[name], select[name]');
    let timer = null;
    const autoSubmit = function () {
        if (timer) window.clearTimeout(timer);
        timer = window.setTimeout(function () {
            form.submit();
        }, 450);
    };

    fields.forEach(function (field) {
        const isTextLike = field.matches('input[type="text"], input[type="search"]');
        if (isTextLike) {
            field.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    form.submit();
                }
            });
            return;
        }

        field.addEventListener('change', autoSubmit);
    });
});
</script>
