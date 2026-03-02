<?php
$movimientos = $movimientos ?? [];
$resumenCuentas = $resumenCuentas ?? [];
$filtros = $filtros ?? [];
?>

<div class="container-fluid p-4" id="tesoreriaMovimientosApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Tesorería - Movimientos
            </h1>
            <p class="text-muted small mb-0 ms-1">Ledger operativo de cobros y pagos.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('tesoreria/cxc')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-cash-stack me-2 text-success"></i>Ir a CxC
            </a>
            <a href="<?php echo e(route_url('tesoreria/cxp')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-shop me-2 text-warning"></i>Ir a CxP
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success d-flex align-items-center py-2 shadow-sm border-0 fade-in mb-4">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i> Movimiento anulado correctamente.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger d-flex align-items-center py-2 shadow-sm border-0 fade-in mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> <?php echo e((string) $_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="row g-3 align-items-end" id="formFiltrosMovimientos">
                <input type="hidden" name="ruta" value="tesoreria/movimientos">

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Módulo Origen</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="origen">
                        <option value="">Todos los orígenes</option>
                        <option value="CXC" <?php echo (($filtros['origen'] ?? '') === 'CXC') ? 'selected' : ''; ?>>Cuentas por Cobrar (CXC)</option>
                        <option value="CXP" <?php echo (($filtros['origen'] ?? '') === 'CXP') ? 'selected' : ''; ?>>Cuentas por Pagar (CXP)</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">ID Origen (N° Doc)</label>
                    <input type="number" min="0" name="id_origen" class="form-control bg-light border-secondary-subtle shadow-sm" placeholder="Ej. 1024" value="<?php echo (int) ($filtros['id_origen'] ?? 0) > 0 ? (int) ($filtros['id_origen']) : ''; ?>">
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">ID Tercero (Cliente/Prov)</label>
                    <input type="number" min="0" name="id_tercero" class="form-control bg-light border-secondary-subtle shadow-sm" placeholder="Ej. 50" value="<?php echo (int) ($filtros['id_tercero'] ?? 0) > 0 ? (int) ($filtros['id_tercero']) : ''; ?>">
                </div>
                
                <div class="col-12 col-md-3 d-flex align-items-end" style="height: 58px;">
                    <button type="submit" class="btn btn-primary shadow-sm w-100 fw-bold h-100">
                        <i class="bi bi-search me-1"></i> Filtrar Historial
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4">
            <h6 class="fw-bold text-dark mb-0">
                <i class="bi bi-bank me-2 text-primary"></i>Resumen rápido Caja/Banco 
                <span class="text-muted fw-normal small ms-2">(Saldo teórico del día)</span>
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cuenta</th>
                            <th class="text-center text-secondary fw-semibold">Moneda</th>
                            <th class="text-end text-success fw-semibold">Ingresos</th>
                            <th class="text-end text-danger fw-semibold">Egresos</th>
                            <th class="text-end pe-4 text-primary fw-bold">Saldo Teórico</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($resumenCuentas)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No hay saldos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($resumenCuentas as $r): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 fw-medium text-dark"><?php echo e((string) ($r['codigo'] ?? '') . ' - ' . (string) ($r['nombre'] ?? '')); ?></td>
                                    <td class="text-center text-muted"><?php echo e((string) ($r['moneda'] ?? '')); ?></td>
                                    <td class="text-end text-success">+ <?php echo number_format((float) ($r['ingresos'] ?? 0), 2); ?></td>
                                    <td class="text-end text-danger">- <?php echo number_format((float) ($r['egresos'] ?? 0), 2); ?></td>
                                    <td class="text-end pe-4 fw-bold fs-6"><?php echo number_format((float) ($r['saldo_teorico'] ?? 0), 2); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Historial Detallado</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?php echo count($movimientos); ?> Transacciones</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchMovimientos" placeholder="Buscar transacción...">
            </div>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="movimientosTable"
                       data-erp-table="true"
                       data-manager-global="movimientosManager"
                       data-rows-selector="#movimientosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchMovimientos"
                       data-empty-text="No hay movimientos para mostrar"
                       data-info-text-template="Mostrando {start} a {end} de {total} transacciones"
                       data-pagination-controls="#movimientosPaginationControls"
                       data-pagination-info="#movimientosPaginationInfo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">ID</th>
                            <th class="text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Tipo</th>
                            <th class="text-secondary fw-semibold">Tercero</th>
                            <th class="text-center text-secondary fw-semibold">Origen</th>
                            <th class="text-secondary fw-semibold">Cuenta</th>
                            <th class="text-end text-dark fw-bold">Monto</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="movimientosTableBody">
                    <?php if (empty($movimientos)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="9" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                No se encontraron transacciones con los filtros actuales.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($movimientos as $m): ?>
                            <?php 
                                $estado = (string) ($m['estado'] ?? '');
                                $tipo = (string) ($m['tipo'] ?? '');
                                $origen = (string) ($m['origen'] ?? '');
                                $tercero = (string) ($m['tercero_nombre'] ?? ('#' . (int) ($m['id_tercero'] ?? 0)));
                                $cuenta = (string) ($m['cuenta_codigo'] ?? '') . ' - ' . (string) ($m['cuenta_nombre'] ?? '');
                                
                                // String de búsqueda
                                $searchStr = strtolower($tipo . ' ' . $tercero . ' ' . $origen . ' ' . $cuenta . ' ' . $estado);
                                
                                // Color del monto (Verde para cobros, Rojo para pagos)
                                $montoColor = $tipo === 'COBRO' ? 'text-success' : 'text-danger';
                                $montoSigno = $tipo === 'COBRO' ? '+' : '-';
                            ?>
                            <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="ps-4 text-muted fw-medium align-top pt-3">
                                    #<?php echo (int) ($m['id'] ?? 0); ?>
                                </td>
                                <td class="align-top pt-3 text-muted">
                                    <?php echo e((string) ($m['fecha'] ?? '')); ?>
                                </td>
                                <td class="align-top pt-3 fw-semibold">
                                    <?php if($tipo === 'COBRO'): ?>
                                        <span class="text-success"><i class="bi bi-arrow-down-left-circle me-1"></i>COBRO</span>
                                    <?php else: ?>
                                        <span class="text-danger"><i class="bi bi-arrow-up-right-circle me-1"></i>PAGO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-top pt-3 fw-bold text-dark">
                                    <?php echo e($tercero); ?>
                                </td>
                                <td class="text-center align-top pt-3">
                                    <span class="badge bg-light text-secondary border border-secondary-subtle">
                                        <?php echo e($origen); ?> #<?php echo (int) ($m['id_origen'] ?? 0); ?>
                                    </span>
                                </td>
                                <td class="align-top pt-3 text-muted small">
                                    <?php echo e($cuenta); ?>
                                </td>
                                <td class="text-end align-top pt-3 fw-bold <?php echo $montoColor; ?>">
                                    <?php echo $montoSigno; ?> <?php echo number_format((float) ($m['monto'] ?? 0), 2); ?>
                                </td>
                                <td class="text-center align-top pt-3">
                                    <?php if($estado === 'CONFIRMADO'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">CONFIRMADO</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><?php echo e($estado); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4 align-top pt-3">
                                    <?php if ($estado === 'CONFIRMADO' && tiene_permiso('tesoreria.movimientos.anular')): ?>
                                        <form method="post" action="<?php echo e(route_url('tesoreria/anular_movimiento')); ?>" class="d-inline js-form-confirm">
                                            <input type="hidden" name="id_movimiento" value="<?php echo (int) $m['id']; ?>">
                                            <input type="hidden" name="id_origen" value="<?php echo (int) $m['id_origen']; ?>">
                                            <input type="hidden" name="origen" value="<?php echo e($origen); ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 rounded-circle shadow-sm" data-bs-toggle="tooltip" title="Anular Transacción">
                                                <i class="bi bi-x-circle fs-5"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted opacity-25"><i class="bi bi-dash-circle"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="movimientosPaginationInfo">Calculando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="movimientosPaginationControls"></ul>
                </nav>
            </div>
            
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>