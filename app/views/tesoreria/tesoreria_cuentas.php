<?php
$cuentas = $cuentas ?? [];
$cuentasActivas = $cuentasActivas ?? [];
$bancos = $bancos ?? [];
$cuentaEditar = $cuentaEditar ?? null;
$esEdicion = is_array($cuentaEditar) && !empty($cuentaEditar['id']);
$swalError = !empty($_GET['error']) ? (string) $_GET['error'] : null;
$camposBloqueadosEdicion = $esEdicion;

// Mock simulado: Si ya tienes los métodos guardados en BD (ej. en un JSON), los extraemos.
$metodosPermitidos = $cuentaEditar['metodos_pago'] ?? []; 
if (is_string($metodosPermitidos)) {
    $metodosPermitidos = json_decode($metodosPermitidos, true) ?? [];
}
?>

<div class="container-fluid p-4" id="tesoreriaCuentasApp" data-es-edicion="<?= $esEdicion ? 'true' : 'false' ?>">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bank me-2 text-primary"></i> Tesorería - Cuentas
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra cajas, bancos y billeteras para operar cobros/pagos.</p>
        </div>
        
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalTransferenciaInterna">
                <i class="bi bi-arrow-left-right me-2 text-success"></i>Transferir
            </button>
            <a href="<?= e(route_url('tesoreria/movimientos')) ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-text me-2 text-info"></i>Ver movimientos
            </a>
            
            <?php if ($esEdicion): ?>
                <a href="<?= e(route_url('tesoreria/cuentas')) ?>" class="btn btn-primary shadow-sm fw-bold">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Cuenta
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCuentaTesoreria">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Cuenta
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($swalError !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: <?= json_encode($swalError) ?>,
                        confirmButtonText: 'Entendido'
                    });
                }
            });
        </script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Listado de cuentas registradas</h6>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2"><?= count($cuentas) ?> registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro" id="tablaCuentas"
                       data-erp-table="true"
                       data-pagination-controls="#cuentasPaginationControls"
                       data-pagination-info="#cuentasPaginationInfo">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Nombre</th>
                            <th class="text-secondary fw-semibold">Tipo</th>
                            <th class="text-secondary fw-semibold">Moneda</th>
                            <th class="text-secondary fw-semibold">Saldo actual</th>
                            <th class="text-center text-secondary fw-semibold">Cobros/Pagos</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cuentas)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-safe fs-1 d-block mb-2 text-light"></i>No hay cuentas registradas.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cuentas as $c): ?>
                                <?php $esActiva = ((int) ($c['estado'] ?? 0) === 1); ?>
                                <tr class="border-bottom <?= !$esActiva ? 'bg-light opacity-75' : '' ?>">
                                    <td class="ps-4 fw-bold text-primary pt-3"><?= e((string) ($c['codigo'] ?? '')) ?></td>
                                    <td class="fw-semibold text-dark pt-3">
                                        <?= e((string) ($c['nombre'] ?? '')) ?>
                                        <?php if (empty($c['id_cuenta_contable'])): ?>
                                            <i class="bi bi-exclamation-triangle-fill text-warning ms-1 fs-6" 
                                            data-bs-toggle="tooltip" 
                                            title="Falta vincular con un libro contable."
                                            style="cursor: help;"></i>
                                        <?php endif; ?>
                                        <?php $bancoNombre = trim((string) ($c['banco_nombre'] ?? '')); ?>
                                        <?php if ($bancoNombre !== ''): ?>
                                            <div class="small text-muted mt-1"><?= e($bancoNombre) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted pt-3"><?= e((string) ($c['tipo'] ?? '')) ?></td>
                                    <td class="text-muted pt-3 fw-bold"><?= e((string) ($c['moneda'] ?? '')) ?></td>
                                    <td class="text-muted pt-3 fw-bold"><?= e((string) ($c['moneda'] ?? '')) ?> <?= number_format((float) ($c['saldo_actual'] ?? 0), 2) ?></td>
                                    
                                    <td class="text-center pt-3">
                                        <span class="badge <?= ((int) ($c['permite_cobros'] ?? 0) === 1) ? 'text-success' : 'text-muted' ?>" title="Permite Cobros"><i class="bi bi-box-arrow-in-down-right fs-6"></i></span>
                                        <span class="badge <?= ((int) ($c['permite_pagos'] ?? 0) === 1) ? 'text-danger' : 'text-muted' ?>" title="Permite Pagos"><i class="bi bi-box-arrow-up-right fs-6"></i></span>
                                    </td>
                                    
                                    <td class="text-center pt-3">
                                        <span class="badge rounded-pill <?= $esActiva ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?>">
                                            <?= $esActiva ? 'Activa' : 'Inactiva' ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <div class="d-inline-flex align-items-center gap-2">
                                            <form method="post" action="<?= e(route_url('tesoreria/cambiar_estado_cuenta')) ?>" class="d-inline">
                                                <input type="hidden" name="id_cuenta" value="<?= (int) ($c['id'] ?? 0) ?>">
                                                <div class="form-check form-switch m-0" data-bs-toggle="tooltip" title="Activar/Inactivar">
                                                    <input class="form-check-input js-switch-estado-cuenta" type="checkbox" name="estado" value="1" <?= $esActiva ? 'checked' : '' ?>>
                                                </div>
                                            </form>

                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                            <div class="d-inline-flex align-items-center gap-1">
                                                <?php $urlEditar = route_url('tesoreria/cuentas'); ?>
                                                <a href="<?= e($urlEditar . (str_contains($urlEditar, '?') ? '&' : '?') . 'editar=' . (int) $c['id']) ?>" 
                                                   class="btn btn-sm btn-light border-0 bg-transparent rounded-circle text-primary js-editar-cuenta-link" 
                                                   data-bs-toggle="tooltip" title="Editar Cuenta">
                                                    <i class="bi bi-pencil-square fs-5"></i>
                                                </a>

                                                <?php $puedeEliminar = ((int) ($c['total_movimientos'] ?? 0) === 0); ?>
                                                <?php if ($puedeEliminar): ?>
                                                    <form method="post" action="<?= e(route_url('tesoreria/eliminar_cuenta')) ?>" class="d-inline js-form-delete-cuenta">
                                                        <input type="hidden" name="id_cuenta" value="<?= (int) ($c['id'] ?? 0) ?>">
                                                        <button type="submit" class="btn btn-sm btn-light border-0 bg-transparent rounded-circle text-danger" data-bs-toggle="tooltip" title="Eliminar">
                                                            <i class="bi bi-trash fs-5"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-sm btn-light border-0 bg-transparent rounded-circle text-secondary" disabled data-bs-toggle="tooltip" title="Tiene movimientos">
                                                        <i class="bi bi-trash fs-5"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="cuentasPaginationInfo">Cargando...</small>
                <nav><ul class="pagination mb-0 justify-content-end" id="cuentasPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCuentaTesoreria" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi <?= $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle' ?> me-2"></i>
                    <?= $esEdicion ? 'Editar Cuenta' : 'Nueva Cuenta' ?>
                </h5>
                <?php if ($esEdicion): ?>
                    <a href="<?= e(route_url('tesoreria/cuentas')) ?>" class="btn-close btn-close-white"></a>
                <?php else: ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                <?php endif; ?>
            </div>

            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?= e(route_url('tesoreria/guardar_cuenta')) ?>" autocomplete="off" id="formCuentaTesoreria">
                    <input type="hidden" name="id" value="<?= (int) ($cuentaEditar['id'] ?? 0) ?>">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Datos Generales</h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-3">
                                    <label class="form-label small text-muted fw-bold mb-1">Código</label>
                                    <input type="text" name="codigo" class="form-control shadow-none font-monospace fw-bold" value="<?= e((string) ($cuentaEditar['codigo'] ?? '')) ?>" placeholder="Auto" readonly disabled>
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label small text-muted fw-bold mb-1">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" name="nombre" required class="form-control shadow-none" value="<?= e((string) ($cuentaEditar['nombre'] ?? '')) ?>">
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1">Tipo <span class="text-danger">*</span></label>
                                    <?php $tipoActual = (string) ($cuentaEditar['tipo'] ?? 'CAJA'); ?>
                                    <select name="tipo" class="form-select shadow-none" required>
                                        <option value="CAJA" <?= $tipoActual === 'CAJA' ? 'selected' : '' ?>>CAJA</option>
                                        <option value="BANCO" <?= $tipoActual === 'BANCO' ? 'selected' : '' ?>>BANCO</option>
                                        <option value="BILLETERA" <?= $tipoActual === 'BILLETERA' ? 'selected' : '' ?>>BILLETERA</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1">Moneda <span class="text-danger">*</span></label>
                                    <?php $monedaActual = (string) ($cuentaEditar['moneda'] ?? 'PEN'); ?>
                                    <select name="moneda" class="form-select shadow-none" required>
                                        <option value="PEN" <?= $monedaActual === 'PEN' ? 'selected' : '' ?>>PEN</option>
                                        <option value="USD" <?= $monedaActual === 'USD' ? 'selected' : '' ?>>USD</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3 border-start border-4 border-info">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Métodos de Pago Vinculados</h6>
                            <p class="small text-muted mb-3">Selecciona qué métodos de pago aparecerán en el "Pago Rápido" de ventas al elegir esta cuenta.</p>
                            
                            <div class="d-flex flex-wrap gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Efectivo" id="mpEfectivo" <?= in_array('Efectivo', $metodosPermitidos) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-medium text-dark" for="mpEfectivo"><i class="bi bi-cash text-success me-1"></i>Efectivo</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Transferencia" id="mpTransferencia" <?= in_array('Transferencia', $metodosPermitidos) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-medium text-dark" for="mpTransferencia"><i class="bi bi-bank2 text-primary me-1"></i>Transferencia</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Yape/Plin" id="mpYape" <?= in_array('Yape/Plin', $metodosPermitidos) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-medium text-dark" for="mpYape"><i class="bi bi-phone text-purple me-1"></i>Yape/Plin</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Tarjeta" id="mpTarjeta" <?= in_array('Tarjeta', $metodosPermitidos) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-medium text-dark" for="mpTarjeta"><i class="bi bi-credit-card text-warning me-1"></i>Tarjeta</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="metodos_pago[]" value="Cheque" id="mpCheque" <?= in_array('Cheque', $metodosPermitidos) ? 'checked' : '' ?>>
                                    <label class="form-check-label small fw-medium text-dark" for="mpCheque"><i class="bi bi-journal-check text-secondary me-1"></i>Cheque</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12 d-flex flex-wrap gap-4 mt-3 pt-3 border-top">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input border-secondary-subtle" type="checkbox" name="permite_cobros" id="permiteCobros" value="1" <?= ((int) ($cuentaEditar['permite_cobros'] ?? 1) === 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-medium text-dark" for="permiteCobros">Permite Cobros</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input border-secondary-subtle" type="checkbox" name="permite_pagos" id="permitePagos" value="1" <?= ((int) ($cuentaEditar['permite_pagos'] ?? 1) === 1) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-medium text-dark" for="permitePagos">Permite Pagos</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 border-top">
                        <?php if ($esEdicion): ?>
                            <a href="<?= e(route_url('tesoreria/cuentas')) ?>" class="btn btn-light text-secondary me-2 fw-semibold border">Cancelar edición</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="bi bi-save me-2"></i><?= $esEdicion ? 'Actualizar Cuenta' : 'Guardar Cuenta' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?= e(base_url()) ?>/assets/js/tesoreria.js"></script>

<script>
    if (typeof window.ERPTable !== 'undefined' && window.ERPTable.autoInitFromDataset) {
        window.ERPTable.autoInitFromDataset(document.getElementById('tesoreriaCuentasApp') || document);
    }
</script>

<?php if ($esEdicion): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var modalEl = document.getElementById('modalCuentaTesoreria');
        if (modalEl) {
            var myModal = new bootstrap.Modal(modalEl);
            myModal.show();
        }
    });
</script>
<?php endif; ?>