<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$cuentas = $cuentas ?? [];
$metodos = $metodos ?? [];
$proveedores = $proveedores ?? [];

$badge = static function (string $estado): string {
    if ($estado === 'PAGADA') {
        return 'bg-success-subtle text-success border border-success-subtle';
    }

    if ($estado === 'PARCIAL') {
        return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    }

    if ($estado === 'VENCIDA') {
        return 'bg-danger-subtle text-danger border border-danger-subtle';
    }

    if ($estado === 'ANULADA') {
        return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    }

    return 'bg-primary-subtle text-primary border border-primary-subtle';
};
?>
<?php
$swalIcon = null;
$swalMessage = null;

if (!empty($_GET['error'])) {
    $swalIcon = 'error';
    $swalMessage = (string) $_GET['error'];
} elseif (!empty($_GET['ok'])) {
    $swalIcon = 'success';
    $swalMessage = 'Pago registrado correctamente.';
}
?>

<div class="container-fluid p-4" id="tesoreriaCxpApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-shop me-2 text-warning"></i> Tesorería - Cuentas por Pagar
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de saldos por proveedor y registro de pagos.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bank me-2 text-primary"></i>Ir a Cuentas
            </a>
            <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-clock-history me-2 text-info"></i>Historial Global
            </a>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalPagoManual">
                <i class="bi bi-plus-circle me-2"></i>Registrar Pago Manual
            </button>
        </div>
    </div>
    <?php if ($swalMessage !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal === 'undefined') {
                    return;
                }
                Swal.fire({
                    icon: <?php echo json_encode($swalIcon); ?>,
                    title: <?php echo json_encode($swalIcon === 'error' ? 'Error' : 'Éxito'); ?>,
                    text: <?php echo json_encode($swalMessage); ?>,
                    confirmButtonText: 'Entendido'
                });
            });
        </script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="" class="row g-3 align-items-end" id="formFiltrosCxp">
                <input type="hidden" name="ruta" value="tesoreria/cxp">

                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">Estado de Cuenta</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="estado">
                        <option value="">Todos los estados</option>
                        <?php foreach (['PENDIENTE', 'ABIERTA', 'PARCIAL', 'PAGADA', 'VENCIDA', 'ANULADA'] as $estado): ?>
                            <option value="<?php echo e($estado); ?>" <?php echo (($filtros['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo e($estado); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">Moneda</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="moneda">
                        <option value="">Todas las monedas</option>
                        <option value="PEN" <?php echo (($filtros['moneda'] ?? '') === 'PEN') ? 'selected' : ''; ?>>PEN (Soles)</option>
                        <option value="USD" <?php echo (($filtros['moneda'] ?? '') === 'USD') ? 'selected' : ''; ?>>USD (Dólares)</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">Vencimiento</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="vencimiento">
                        <option value="">Todos los registros</option>
                        <option value="vencidas" <?php echo (($filtros['vencimiento'] ?? '') === 'vencidas') ? 'selected' : ''; ?>>Solo cuentas vencidas</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Detalle de Cuentas por Pagar</h2>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill ms-3" id="badgeRegistros"><?php echo count($registros); ?> Registros</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchCxp" placeholder="Buscar proveedor o documento...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="cxpTable"
                       data-erp-table="true"
                       data-manager-global="cxpManager"
                       data-rows-selector="#cxpTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchCxp"
                       data-empty-text="No hay cuentas por pagar registradas"
                       data-info-text-template="Mostrando {start} a {end} de {total} registros"
                       data-pagination-controls="#cxpPaginationControls"
                       data-pagination-info="#cxpPaginationInfo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Proveedor</th>
                            <th class="text-secondary fw-semibold">Recepción</th>
                            <th class="text-center text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-end text-secondary fw-semibold">Pagado</th>
                            <th class="text-end text-dark fw-bold">Saldo</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cxpTableBody">
                        <?php if (empty($registros)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No se encontraron cuentas por pagar con los filtros actuales.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $r): ?>
                                <?php 
                                    // CAMBIO: El valor por defecto al fallar ahora es PENDIENTE
                                    $estadoStr = (string) ($r['estado'] ?? 'PENDIENTE');
                                    // Búsqueda para JS
                                    $searchStr = strtolower(($r['proveedor'] ?? '') . ' ' . ($r['id_recepcion'] ?? '') . ' ' . $estadoStr);
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 fw-bold text-dark align-top pt-3">
                                        <?php echo e((string) ($r['proveedor'] ?? '')); ?>
                                    </td>
                                    <td class="align-top pt-3 text-muted fw-medium">
                                        #<?php echo str_pad((string)($r['id_recepcion'] ?? 0), 6, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    <td class="text-center align-top pt-3 text-muted">
                                        <i class="bi bi-calendar-event small me-1 opacity-50"></i><?php echo e((string) ($r['fecha_vencimiento'] ?? '')); ?>
                                    </td>
                                    <td class="text-end align-top pt-3 fw-medium text-secondary">
                                        <span class="small text-muted me-1"><?php echo e($r['moneda'] ?? ''); ?></span><?php echo number_format((float) ($r['monto_total'] ?? 0), 2); ?>
                                    </td>
                                    <td class="text-end align-top pt-3 fw-medium text-success opacity-75">
                                        <span class="small me-1"><?php echo e($r['moneda'] ?? ''); ?></span><?php echo number_format((float) ($r['monto_pagado'] ?? 0), 2); ?>
                                    </td>
                                    <td class="text-end align-top pt-3 fw-bold text-danger">
                                        <span class="small text-muted me-1"><?php echo e($r['moneda'] ?? ''); ?></span><?php echo number_format((float) ($r['saldo'] ?? 0), 2); ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo e($badge($estadoStr)); ?>">
                                            <?php echo e($estadoStr); ?>
                                        </span>
                                    </td>
                                    <td class="text-center pe-4 align-top pt-3">
                                        <?php if (in_array($estadoStr, ['PENDIENTE', 'ABIERTA', 'PARCIAL', 'VENCIDA'], true)): ?>
                                            <button type="button" class="btn btn-sm btn-light text-warning border-0 rounded-circle js-open-pago shadow-sm me-1" 
                                                data-bs-toggle="modal" data-bs-target="#modalPago"
                                                data-id-origen="<?php echo (int) $r['id']; ?>" 
                                                data-moneda="<?php echo e((string) $r['moneda']); ?>" 
                                                data-saldo="<?php echo (float) $r['saldo']; ?>"
                                                data-bs-toggle="tooltip" title="Registrar Pago">
                                                <i class="bi bi-cash-coin fs-5"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>&origen=CXP&id_origen=<?php echo (int) $r['id']; ?>&id_tercero=<?php echo (int) ($r['id_proveedor'] ?? 0); ?>" 
                                           class="btn btn-sm btn-light text-primary border-0 rounded-circle shadow-sm"
                                           data-bs-toggle="tooltip" title="Ver Historial">
                                            <i class="bi bi-journal-text fs-5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="cxpPaginationInfo">Calculando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="cxpPaginationControls"></ul>
                </nav>
            </div>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalPagoManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar Pago Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_pago_manual')); ?>" class="js-form-confirm">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                            <select name="id_tercero" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione proveedor...</option>
                                <?php foreach($proveedores as $prov): ?>
                                    <option value="<?php echo (int) $prov['id']; ?>"><?php echo e((string) $prov['nombre_completo']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Moneda <span class="text-danger">*</span></label>
                            <select name="moneda" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione moneda...</option>
                                <option value="PEN">PEN (Soles)</option>
                                <option value="USD">USD (Dólares)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Monto a Pagar <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="montoPagarManual" class="form-control shadow-sm border-secondary-subtle fw-bold text-primary" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Cuenta Origen <span class="text-danger">*</span></label>
                            <select name="id_cuenta" id="selectCuentaOrigenManual" class="form-select shadow-none" required>
                                <option value="" data-saldo="0" selected disabled>Seleccione una cuenta...</option>
                                <?php foreach ($cuentas as $cta): ?>
                                    <option value="<?php echo $cta['id']; ?>" data-saldo="<?php echo $cta['saldo'] ?? 0; ?>">
                                        <?php echo htmlspecialchars($cta['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="textoSaldoDisponibleManual" class="text-primary fw-bold mt-1 d-block"></small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Método de Pago <span class="text-danger">*</span></label>
                            <select name="id_metodo_pago" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione un método...</option>
                                <?php foreach($metodos as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Pago <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Referencia / N° Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. EGR-2026-001">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                            <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Se aplicará automáticamente a las deudas más antiguas."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light border shadow-sm text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-check-circle me-2"></i>Confirmar Pago Manual</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Registrar Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_pago')); ?>" class="js-form-confirm js-form-monto">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="id_origen" id="pagoIdOrigen">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Moneda</label>
                            <input name="moneda" id="pagoMoneda" class="form-control bg-white shadow-sm border-secondary-subtle fw-bold text-secondary" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Saldo Pendiente</label>
                            <input id="pagoSaldo" class="form-control bg-white shadow-sm border-secondary-subtle fw-bold text-danger" readonly data-saldo-target="1">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Cuenta Origen <span class="text-danger">*</span></label>
                            <select name="id_cuenta" id="selectCuentaOrigen" class="form-select shadow-none" required>
                                <option value="" data-saldo="0" selected disabled>Seleccione una cuenta...</option>
                                <?php foreach ($cuentas as $cta): ?>
                                    <option value="<?php echo $cta['id']; ?>" data-saldo="<?php echo $cta['saldo'] ?? 0; ?>">
                                        <?php echo htmlspecialchars($cta['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small id="textoSaldoDisponible" class="text-primary fw-bold mt-1 d-block"></small>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Método de Pago <span class="text-danger">*</span></label>
                            <select name="id_metodo_pago" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione un método...</option>
                                <?php foreach($metodos as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Pago <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Monto a Pagar <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="pagoMonto" class="form-control shadow-sm border-secondary-subtle fw-bold text-warning-emphasis" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Referencia / N° Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. TRF-849392">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                            <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Notas adicionales del pago..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light border shadow-sm text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold shadow-sm"><i class="bi bi-check-circle me-2"></i>Confirmar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
