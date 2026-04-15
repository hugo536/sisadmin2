<?php 
    // Capturamos la sección activa de la URL, por defecto 'cxc'
    $seccionActiva = $_GET['seccion_activa'] ?? ($filtros['seccion_activa'] ?? 'cxc');
    if (!in_array($seccionActiva, ['cxc', 'cxp', 'flujo', 'depositos'])) {
        $seccionActiva = 'cxc';
    }
?>
<div class="container-fluid p-4" id="reportesTesoreriaApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bank2 me-2 text-primary"></i> Reportes de Tesorería
            </h1>
            <p class="text-muted small mb-0 ms-1">Análisis de cuentas por cobrar, pagar, flujo y depósitos.</p>
        </div>
        <a href="javascript:history.back()" class="btn btn-outline-secondary shadow-sm fw-semibold">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>

    <ul class="nav nav-tabs border-bottom-1 mb-0 px-2" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'cxc' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="cxc">
                <i class="bi bi-arrow-down-left-circle me-2"></i>Cuentas por Cobrar
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'cxp' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="cxp">
                <i class="bi bi-arrow-up-right-circle me-2"></i>Cuentas por Pagar
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'flujo' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="flujo">
                <i class="bi bi-wallet2 me-2"></i>Flujo por Cuenta
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link btn-tab-seccion fs-6 fw-semibold py-3 <?php echo $seccionActiva === 'depositos' ? 'active text-primary border-primary border-bottom-0' : 'text-secondary bg-light border-0'; ?>" data-seccion="depositos">
                <i class="bi bi-cash-coin me-2"></i>Ingresos / Depósitos
            </button>
        </li>
    </ul>

    <div class="card border-0 shadow-sm mb-4 rounded-top-0 border-top border-primary border-3">
        <div class="card-body p-4 bg-white">
            <form class="row g-3" method="get" action="<?php echo e(route_url('reportes/tesoreria')); ?>" id="formFiltrosReporteTesoreria">
                <input type="hidden" name="ruta" value="reportes/tesoreria">
                <input type="hidden" name="seccion_activa" id="input_seccion_activa" value="<?php echo e($seccionActiva); ?>">
                
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Desde <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_desde" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">Fecha Hasta <span class="text-danger">*</span></label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light auto-submit" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>" required>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label text-muted small fw-bold mb-1 ms-1">ID Cuenta</label>
                    <input type="number" name="id_cuenta" class="form-control bg-light auto-submit" placeholder="Todas..." value="<?php echo ($filtros['id_cuenta'] ?? 0) > 0 ? (int)$filtros['id_cuenta'] : ''; ?>">
                </div>

                <div class="col-12 col-md-3 d-flex flex-column justify-content-end">
                    <button type="submit" name="exportar_pdf" value="1" class="btn btn-danger w-100 shadow-sm fw-semibold" formtarget="_blank">
                        <i class="bi bi-file-pdf-fill me-2"></i>Exportar PDF
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($seccionActiva === 'cxc'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-arrow-down-left-circle me-2 text-success"></i>Aging Cuentas por Cobrar (CxC)
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepCxC" placeholder="Buscar cliente en tabla...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepCxC" data-erp-table="true" data-search-input="#filtroRepCxC" data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cliente</th>
                            <th class="text-end text-secondary fw-semibold">Saldo</th>
                            <th class="text-center text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-center text-secondary fw-semibold">Días Atraso</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($agingCxc['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay cuentas por cobrar pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($agingCxc['rows'] ?? []) as $r): ?>
                                <?php 
                                    $diasAtraso = (int)($r['dias_atraso'] ?? 0);
                                    $badgeAtraso = $diasAtraso > 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle';
                                    $textoAtraso = $diasAtraso > 0 ? $diasAtraso . ' días' : 'Al día';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['cliente']); ?></td>
                                    <td class="text-end fw-bold text-success">S/ <?php echo number_format((float)($r['saldo'] ?? 0), 2); ?></td>
                                    <td class="text-center text-muted small"><i class="bi bi-calendar-x me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-2 py-1 rounded border <?php echo $badgeAtraso; ?>"><?php echo $textoAtraso; ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['bucket']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepCxCPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepCxCPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'cxp'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-arrow-up-right-circle me-2 text-danger"></i>Aging Cuentas por Pagar (CxP)
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepCxP" placeholder="Buscar proveedor en tabla...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepCxP" data-erp-table="true" data-search-input="#filtroRepCxP" data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Proveedor</th>
                            <th class="text-end text-secondary fw-semibold">Saldo</th>
                            <th class="text-center text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-center text-secondary fw-semibold">Días Atraso</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Bucket</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($agingCxp['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay cuentas por pagar pendientes.</td></tr>
                        <?php else: ?>
                            <?php foreach (($agingCxp['rows'] ?? []) as $r): ?>
                                <?php 
                                    $diasAtraso = (int)($r['dias_atraso'] ?? 0);
                                    $badgeAtraso = $diasAtraso > 0 ? 'bg-danger-subtle text-danger border-danger-subtle' : 'bg-success-subtle text-success border-success-subtle';
                                    $textoAtraso = $diasAtraso > 0 ? $diasAtraso . ' días' : 'Al día';
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['proveedor'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><?php echo e((string)$r['proveedor']); ?></td>
                                    <td class="text-end fw-bold text-danger">S/ <?php echo number_format((float)($r['saldo'] ?? 0), 2); ?></td>
                                    <td class="text-center text-muted small"><i class="bi bi-calendar-x me-1"></i><?php echo e((string)$r['fecha_vencimiento']); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-2 py-1 rounded border <?php echo $badgeAtraso; ?>"><?php echo $textoAtraso; ?></span>
                                    </td>
                                    <td class="text-center pe-4">
                                        <span class="badge bg-light text-secondary border"><?php echo e((string)$r['bucket']); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepCxPPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepCxPPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'flujo'): ?>
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-wallet2 me-2 text-info"></i>Flujo por cuenta
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepFlujo" placeholder="Buscar cuenta en tabla...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepFlujo" data-erp-table="true" data-search-input="#filtroRepFlujo" data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cuenta</th>
                            <th class="text-end text-secondary fw-semibold">Ingresos</th>
                            <th class="text-end text-secondary fw-semibold">Egresos</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Neto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($flujo['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="4" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay movimientos en las cuentas en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($flujo['rows'] ?? []) as $r): ?>
                                <?php 
                                    $neto = (float)($r['saldo_neto'] ?? 0);
                                    $colorNeto = $neto > 0 ? 'text-success' : ($neto < 0 ? 'text-danger' : 'text-secondary');
                                ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cuenta'])); ?>">
                                    <td class="ps-4 fw-bold text-dark"><i class="bi bi-journal-album me-2 text-muted"></i><?php echo e((string)$r['cuenta']); ?></td>
                                    <td class="text-end fw-semibold text-success">S/ <?php echo number_format((float)($r['total_ingresos'] ?? 0), 2); ?></td>
                                    <td class="text-end fw-semibold text-danger">S/ <?php echo number_format((float)($r['total_egresos'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold <?php echo $colorNeto; ?>">S/ <?php echo number_format($neto, 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepFlujoPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepFlujoPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($seccionActiva === 'depositos'): ?>
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom px-4 py-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-primary"></i>Ingresos y Depósitos Confirmados
                <span class="badge bg-light text-secondary border ms-3 fw-normal" style="font-size: 0.75rem;">
                    Total: S/ <?php echo number_format((float)($depositos['suma_total'] ?? 0), 2); ?>
                </span>
            </h5>
            <div class="input-group input-group-sm w-auto" style="max-width: 250px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroRepDepositos" placeholder="Buscar cliente o ref...">
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaRepDepositos" data-erp-table="true" data-search-input="#filtroRepDepositos" data-rows-per-page="10">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Cliente / Origen</th>
                            <th class="text-secondary fw-semibold">Referencia</th>
                            <th class="text-secondary fw-semibold">Cuenta Destino</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Monto Depositado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($depositos['rows'])): ?>
                            <tr class="empty-msg-row"><td colspan="5" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay depósitos registrados en este periodo.</td></tr>
                        <?php else: ?>
                            <?php foreach (($depositos['rows'] ?? []) as $r): ?>
                                <tr class="border-bottom" data-search="<?php echo e(mb_strtolower((string)$r['cliente_origen'] . ' ' . (string)$r['referencia'])); ?>">
                                    <td class="ps-4 text-muted small"><i class="bi bi-calendar me-1"></i><?php echo date('d/m/Y', strtotime((string)$r['fecha'])); ?></td>
                                    <td class="fw-bold text-dark"><?php echo e((string)$r['cliente_origen']); ?></td>
                                    <td class="text-muted"><?php echo e((string)$r['referencia'] ?: '-'); ?></td>
                                    <td class="text-dark fw-medium"><span class="badge bg-secondary-subtle text-secondary border"><?php echo e((string)$r['cuenta']); ?></span></td>
                                    <td class="text-end pe-4 fw-bold text-success">S/ <?php echo number_format((float)($r['monto'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="tablaRepDepositosPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="tablaRepDepositosPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnTabs = document.querySelectorAll('.btn-tab-seccion');
    const inputSeccion = document.getElementById('input_seccion_activa');
    const autoSubmits = document.querySelectorAll('.auto-submit');
    const formFiltros = document.getElementById('formFiltrosReporteTesoreria');

    btnTabs.forEach(btn => {
        btn.addEventListener('click', function() {
            if (this.classList.contains('active')) return;
            const seccion = this.getAttribute('data-seccion');
            if (inputSeccion) inputSeccion.value = seccion;
            if (formFiltros) formFiltros.submit();
        });
    });

    autoSubmits.forEach(input => {
        input.addEventListener('change', function() {
            if (formFiltros) formFiltros.submit();
        });
    });
});
</script>