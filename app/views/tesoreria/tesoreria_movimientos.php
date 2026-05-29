<?php
$movimientos = $movimientos ?? [];
$resumenCuentas = $resumenCuentas ?? []; 
$filtros = $filtros ?? [];

// --- LÓGICA: Detectar si estamos viendo una cuenta específica ---
$idCuentaFiltro = $filtros['id_cuenta'] ?? '';
$cuentaSeleccionada = null;

if ($idCuentaFiltro !== '') {
    foreach ($resumenCuentas as $cuenta) {
        if ((string)($cuenta['id'] ?? '') === (string)$idCuentaFiltro) {
            $cuentaSeleccionada = $cuenta;
            break;
        }
    }
}

$swalIcon = null;
$swalMessage = null;

// Sanitización básica de las alertas
if (!empty($_GET['error'])) {
    $swalIcon = 'error';
    $swalMessage = htmlspecialchars((string) $_GET['error'], ENT_QUOTES, 'UTF-8');
} elseif (!empty($_GET['ok'])) {
    $swalIcon = 'success';
    $swalMessage = 'Movimiento anulado correctamente.';
}
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
            <a href="<?= e(route_url('tesoreria/cuentas')) ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bank me-2 text-primary"></i>Ir a Cuentas
            </a>
            <a href="<?= e(route_url('tesoreria/cxc')) ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-cash-stack me-2 text-success"></i>Ir a CxC
            </a>
            <a href="<?= e(route_url('tesoreria/cxp')) ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-shop me-2 text-warning"></i>Ir a CxP
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="<?= e(route_url('tesoreria/movimientos')) ?>" class="row g-3" id="formFiltrosMovimientos">
                <input type="hidden" name="ruta" value="tesoreria/movimientos">

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Cuenta / Caja</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="id_cuenta">
                        <option value="">Todas las cuentas</option>
                        <?php foreach ($resumenCuentas as $c): ?>
                            <option value="<?= (int)($c['id'] ?? 0) ?>" <?= ((string)$idCuentaFiltro === (string)($c['id'] ?? 0)) ? 'selected' : '' ?>>
                                <?= e((string) ($c['codigo'] ?? '') . ' - ' . (string) ($c['nombre'] ?? '')) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Módulo Origen</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="origen">
                        <option value="">Todos</option>
                        <option value="CXC" <?= (($filtros['origen'] ?? '') === 'CXC') ? 'selected' : '' ?>>Cobros (CXC)</option>
                        <option value="CXP" <?= (($filtros['origen'] ?? '') === 'CXP') ? 'selected' : '' ?>>Pagos (CXP)</option>
                        <option value="TRANSFERENCIA" <?= (($filtros['origen'] ?? '') === 'TRANSFERENCIA') ? 'selected' : '' ?>>Transferencias</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Desde</label>
                    <input type="date" name="fecha_desde" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?= e($filtros['fecha_desde'] ?? date('Y-m-01')) ?>">
                </div>
                
                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?= e($filtros['fecha_hasta'] ?? date('Y-m-t')) ?>">
                </div>
                
            </form>
        </div>
    </div>

    <div id="contenedorDinamicoMovimientos">

        <?php if ($cuentaSeleccionada !== null): ?>
            <div class="card border-0 shadow-sm mb-4 bg-primary text-white overflow-hidden fade-in">
                <div class="card-body p-4 position-relative">
                    <i class="bi bi-wallet2 position-absolute opacity-25" style="font-size: 8rem; right: -20px; top: -30px;"></i>
                    <div class="row align-items-center position-relative z-1">
                        <div class="col-md-6 border-md-end border-light border-opacity-25">
                            <span class="badge bg-white text-primary mb-2 rounded-pill px-3 py-1 fw-bold shadow-sm">
                                <?= e((string) ($cuentaSeleccionada['moneda'] ?? '')) ?>
                            </span>
                            <h3 class="fw-bold mb-0 text-white"><?= e((string) ($cuentaSeleccionada['nombre'] ?? '')) ?></h3>
                            <p class="mb-0 text-white-50 small">Código: <?= e((string) ($cuentaSeleccionada['codigo'] ?? '')) ?></p>
                        </div>
                        <div class="col-md-6 text-md-end mt-3 mt-md-0">
                            <p class="mb-1 text-white-50 fw-semibold text-uppercase tracking-wide small">Saldo Actual en Cuenta</p>
                            <h2 class="display-6 fw-bold mb-0 text-white">
                                <?= number_format((float) ($cuentaSeleccionada['saldo_actual'] ?? 0), 2) ?>
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm" id="contenedorTablaMovimientos">
            <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center">
                    <h2 class="h6 fw-bold text-dark mb-0">Historial Detallado</h2>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?= count($movimientos) ?> Transacciones</span>
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
                                    $origen = strtoupper((string) ($m['origen'] ?? ''));
                                    
                                    if ($origen === 'TRANSFERENCIA') {
                                        $tercero = 'Cuentas Propias (Interno)';
                                    } else {
                                        $tercero = (string) ($m['tercero_nombre'] ?? ('#' . (int) ($m['id_tercero'] ?? 0)));
                                    }

                                    $cuenta = (string) ($m['cuenta_codigo'] ?? '') . ' - ' . (string) ($m['cuenta_nombre'] ?? '');
                                    $observacionTercero = trim((string) ($m['observaciones'] ?? '')); 
                                    
                                    $searchStr = strtolower($tipo . ' ' . $tercero . ' ' . $origen . ' ' . $cuenta . ' ' . $estado . ' ' . $observacionTercero);
                                    
                                    $montoColor = $tipo === 'COBRO' ? 'text-success' : 'text-danger';
                                    $montoSigno = $tipo === 'COBRO' ? '+' : '-';
                                ?>
                                <tr class="border-bottom" data-search="<?= htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8') ?>">
                                    <td class="ps-4 text-muted fw-medium align-top pt-3">#<?= (int) ($m['id'] ?? 0) ?></td>
                                    <td class="align-top pt-3 text-muted"><?= e((string) ($m['fecha'] ?? '')) ?></td>
                                    <td class="align-top pt-3 fw-semibold">
                                        <?php if($tipo === 'COBRO'): ?>
                                            <span class="text-success"><i class="bi bi-arrow-down-left-circle me-1"></i>COBRO</span>
                                        <?php else: ?>
                                            <span class="text-danger"><i class="bi bi-arrow-up-right-circle me-1"></i>PAGO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-top pt-3">
                                        <span class="fw-bold text-dark d-block"><?= e($tercero) ?></span>
                                        <?php if ($observacionTercero !== ''): ?>
                                            <small class="text-muted fw-normal d-block mt-1" style="font-size: 0.8rem;">
                                                <?= e($observacionTercero) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <?php if($origen === 'TRANSFERENCIA'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle" title="ID Transferencia: <?= (int) ($m['id_origen'] ?? 0) ?>">
                                                <i class="bi bi-arrow-left-right me-1"></i> TRF #<?= (int) ($m['id_origen'] ?? 0) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-light text-secondary border border-secondary-subtle">
                                                <?= e($origen) ?> #<?= (int) ($m['id_origen'] ?? 0) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="align-top pt-3 text-muted small"><?= e($cuenta) ?></td>
                                    <td class="text-end align-top pt-3 fw-bold <?= $montoColor ?>">
                                        <?= $montoSigno ?> <?= number_format((float) ($m['monto'] ?? 0), 2) ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <?php if($estado === 'CONFIRMADO'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">CONFIRMADO</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1 rounded-pill"><?= e($estado) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center pe-4 align-top pt-3">
                                        <?php if ($estado === 'CONFIRMADO' && tiene_permiso('tesoreria.movimientos.anular')): ?>
                                            <form method="post" action="<?= e(route_url('tesoreria/anular_movimiento')) ?>" class="d-inline js-form-confirm">
                                                <input type="hidden" name="id_movimiento" value="<?= (int) $m['id'] ?>">
                                                <input type="hidden" name="id_origen" value="<?= (int) $m['id_origen'] ?>">
                                                <input type="hidden" name="origen" value="<?= e($origen) ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger border-0 rounded-circle shadow-sm" data-bs-toggle="tooltip" title="Anular Transacción">
                                                    <i class="bi bi-slash-circle fs-5"></i>
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
        
    </div> </div>

<?php if ($swalMessage !== null): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Swal === 'undefined') return;
            Swal.fire({
                icon: <?= json_encode($swalIcon) ?>,
                title: <?= json_encode($swalIcon === 'error' ? 'Error' : 'Éxito') ?>,
                text: <?= json_encode($swalMessage) ?>,
                confirmButtonText: 'Entendido',
                confirmButtonColor: '#0d6efd'
            });
        });
    </script>
<?php endif; ?>