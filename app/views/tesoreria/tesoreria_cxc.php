<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$cuentas = $cuentas ?? [];
$metodos = $metodos ?? [];
$clientes = $clientes ?? [];

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

    // CAMBIO: Se añade PENDIENTE y se mantiene ABIERTA por retrocompatibilidad
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
    $swalMessage = 'Cobro registrado correctamente.';
}
?>

<div class="container-fluid p-4" id="tesoreriaCxcApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cash-stack me-2 text-primary"></i> Tesorería - Cuentas por Cobrar
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de saldos por cliente y registro de cobros.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bank me-2 text-primary"></i>Ir a Cuentas
            </a>
            <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-clock-history me-2 text-info"></i>Historial Global
            </a>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCobroManual">
                <i class="bi bi-plus-circle me-2"></i>Registrar Cobro Manual
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
            <form method="get" action="" class="row g-3 align-items-end" id="formFiltrosCxc">
                <input type="hidden" name="ruta" value="tesoreria/cxc">

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
                <h2 class="h6 fw-bold text-dark mb-0">Detalle de Cuentas por Cobrar</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3" id="badgeRegistros"><?php echo count($registros); ?> Registros</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchCxc" placeholder="Buscar cliente o documento...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="cxcTable"
                       data-erp-table="true"
                       data-manager-global="cxcManager"
                       data-rows-selector="#cxcTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchCxc"
                       data-empty-text="No hay cuentas por cobrar registradas"
                       data-info-text-template="Mostrando {start} a {end} de {total} registros"
                       data-pagination-controls="#cxcPaginationControls"
                       data-pagination-info="#cxcPaginationInfo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cliente</th>
                            <th class="text-secondary fw-semibold">Documento</th>
                            <th class="text-center text-secondary fw-semibold">Vencimiento</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-end text-secondary fw-semibold">Pagado</th>
                            <th class="text-end text-dark fw-bold">Saldo</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cxcTableBody">
                        <?php if (empty($registros)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-clipboard-check fs-1 d-block mb-2 text-light"></i>
                                    No se encontraron cuentas por cobrar con los filtros actuales.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registros as $r): ?>
                                <?php 
                                    // CAMBIO: El valor por defecto al fallar ahora es PENDIENTE
                                    $estadoStr = (string) ($r['estado'] ?? 'PENDIENTE');
                                    // String de búsqueda para JS
                                    $searchStr = strtolower(($r['cliente'] ?? '') . ' ' . ($r['id_documento_venta'] ?? '') . ' ' . ($r['documento_referencia'] ?? '') . ' ' . $estadoStr);
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 fw-bold text-dark align-top pt-3">
                                        <?php echo e((string) ($r['cliente'] ?? '')); ?>
                                    </td>
                                    <td class="align-top pt-3 text-muted fw-medium">
                                        <?php if (($r['origen'] ?? 'SISTEMA') === 'MIGRACION'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle mb-1"><i class="bi bi-clock-history"></i> Saldo Inicial</span><br>
                                            <small class="text-dark fw-bold"><?php echo e((string) ($r['documento_referencia'] ?? '')); ?></small>
                                            <i class="bi bi-info-circle ms-1" data-bs-toggle="tooltip" title="<?php echo e((string) ($r['observaciones'] ?? '')); ?>"></i>
                                        <?php else: ?>
                                            #<?php echo str_pad((string) ($r['id_documento_venta'] ?? 0), 6, '0', STR_PAD_LEFT); ?>
                                        <?php endif; ?>
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
                                    <td class="text-end align-top pt-3 fw-bold text-primary">
                                        <span class="small text-muted me-1"><?php echo e($r['moneda'] ?? ''); ?></span><?php echo number_format((float) ($r['saldo'] ?? 0), 2); ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo e($badge($estadoStr)); ?>">
                                            <?php echo e($estadoStr); ?>
                                        </span>
                                    </td>
                                    <td class="text-center pe-4 align-top pt-3">
                                        <?php if (in_array($estadoStr, ['PENDIENTE', 'ABIERTA', 'PARCIAL', 'VENCIDA'], true)): ?>
                                            <button type="button" class="btn btn-sm btn-light text-success border-0 rounded-circle js-open-cobro shadow-sm me-1" 
                                                data-bs-toggle="modal" data-bs-target="#modalCobro"
                                                data-id-origen="<?php echo (int) $r['id']; ?>" 
                                                data-moneda="<?php echo e((string) $r['moneda']); ?>" 
                                                data-saldo="<?php echo (float) $r['saldo']; ?>"
                                                data-bs-toggle="tooltip" title="Registrar Cobro">
                                                <i class="bi bi-currency-dollar fs-5"></i>
                                            </button>
                                        <?php endif; ?>
                                        <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>&origen=CXC&id_origen=<?php echo (int) $r['id']; ?>&id_tercero=<?php echo (int) ($r['id_cliente'] ?? 0); ?>" 
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
                <div class="small text-muted fw-medium" id="cxcPaginationInfo">Calculando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="cxcPaginationControls"></ul>
                </nav>
            </div>
            
        </div>
    </div>
</div>


<div class="modal fade" id="modalCobroManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar Cobro Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_cobro_manual')); ?>" class="js-form-confirm">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Cliente <span class="text-danger">*</span></label>
                            <select name="id_tercero" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione un cliente...</option>
                                <?php foreach($clientes as $cli): ?>
                                    <option value="<?php echo (int) $cli['id']; ?>"><?php echo e((string) $cli['nombre_completo']); ?></option>
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
                            <label class="form-label small text-muted fw-bold mb-1">Monto a Cobrar <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto" class="form-control shadow-sm border-secondary-subtle fw-bold text-primary" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Cuenta Destino <span class="text-danger">*</span></label>
                            <select name="id_cuenta" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione cuenta destino...</option>
                                <?php foreach($cuentas as $c): ?>
                                    <?php if (!empty($c['id_cuenta_contable'])): ?>
                                        <option value="<?php echo (int) $c['id']; ?>" data-tipo="<?php echo e($c['tipo']); ?>">
                                            <?php echo e($c['codigo'].' - '.$c['nombre'].' ('.$c['moneda'].')'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
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
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Cobro <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Referencia / N° Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. DEP-2026-001">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                            <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Se aplicará automáticamente a las deudas más antiguas."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light border shadow-sm text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-check-circle me-2"></i>Confirmar Cobro Manual</button>
                </div>
            </form>
        </div>
    </div>
</div>


<div class="modal fade" id="modalCobro" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Registrar Cobro</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_cobro')); ?>" class="js-form-confirm js-form-monto" id="formCobro">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="id_origen" id="cobroIdOrigen">
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Moneda</label>
                            <input name="moneda" id="cobroMoneda" class="form-control bg-white shadow-sm border-secondary-subtle fw-bold text-secondary" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Saldo Pendiente</label>
                            <input id="cobroSaldo" class="form-control bg-white shadow-sm border-secondary-subtle fw-bold text-danger" readonly data-saldo-target="1">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Cuenta Destino <span class="text-danger">*</span></label>
                            <select name="id_cuenta" id="selectCuentaDestino" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione cuenta destino...</option>
                                <?php foreach($cuentas as $c): ?>
                                    <?php if (!empty($c['id_cuenta_contable'])): ?>
                                        <option value="<?php echo (int) $c['id']; ?>" data-tipo="<?php echo e($c['tipo']); ?>">
                                            <?php echo e($c['codigo'].' - '.$c['nombre'].' ('.$c['moneda'].')'); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Método de Cobro <span class="text-danger">*</span></label>
                            <select name="id_metodo_pago" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione un método...</option>
                                <?php foreach($metodos as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Cobro <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Naturaleza del Cobro <span class="text-danger">*</span></label>
                            <select name="naturaleza_pago" id="cobroNaturaleza" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="DOCUMENTO" selected>Cobro de deuda (documento completo)</option>
                                <option value="CAPITAL">Solo capital (reduce obligación)</option>
                                <option value="INTERES">Solo interés (mora/ingreso extra)</option>
                                <option value="MIXTO">Mixto (capital + mora)</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Monto a Cobrar (Total que entra al banco) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="cobroMonto" class="form-control shadow-sm border-secondary-subtle fw-bold text-success fs-5" required>
                        </div>

                        <div class="col-md-6 d-none" id="grupoCobroCapital">
                            <label class="form-label small text-muted fw-bold mb-1">Desglose: Capital</label>
                            <input type="number" step="0.01" min="0" name="monto_capital" id="cobroMontoCapital" class="form-control shadow-sm border-secondary-subtle" value="0">
                        </div>

                        <div class="col-md-6 d-none" id="grupoCobroInteres">
                            <label class="form-label small text-muted fw-bold mb-1">Desglose: Interés/Mora</label>
                            <input type="number" step="0.01" min="0" name="monto_interes" id="cobroMontoInteres" class="form-control shadow-sm border-secondary-subtle text-success" value="0">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1 mt-2">Referencia / N° Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. TRF-849392">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                            <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Notas adicionales del cobro..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light border shadow-sm text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm"><i class="bi bi-check-circle me-2"></i>Confirmar Cobro</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
