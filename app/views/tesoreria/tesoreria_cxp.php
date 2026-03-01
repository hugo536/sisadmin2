<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$cuentas = $cuentas ?? [];
$metodos = $metodos ?? [];

$badge = static function (string $estado): string {
    return match ($estado) {
        'PAGADA' => 'bg-success-subtle text-success border border-success-subtle',
        'PARCIAL' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
        'VENCIDA' => 'bg-danger-subtle text-danger border border-danger-subtle',
        'ANULADA' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        default => 'bg-primary-subtle text-primary border border-primary-subtle',
    };
};
?>
<div class="container-fluid p-4" id="tesoreriaCxpApp">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center"><i class="bi bi-wallet2 me-2 text-primary"></i>Tesorería - Cuentas por Pagar</h1>
            <p class="text-muted small mb-0 ms-1">Control de saldos por proveedor y registro de pagos.</p>
        </div>
        <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="btn btn-outline-primary shadow-sm fw-semibold"><i class="bi bi-clock-history me-1"></i>Movimientos</a>
    </div>
    <?php if (!empty($_GET['ok'])): ?><div class="alert alert-success py-2">Pago registrado correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger py-2"><?php echo e((string) $_GET['error']); ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3"><div class="card-body p-3">
      <form method="get" action="<?php echo e(route_url('tesoreria/cxp')); ?>" class="row g-2 align-items-center">
        <div class="col-md-3"><select class="form-select bg-light" name="estado"><option value="">Estado (todos)</option><?php foreach (['ABIERTA','PARCIAL','PAGADA','VENCIDA','ANULADA'] as $estado): ?><option value="<?php echo e($estado); ?>" <?php echo (($filtros['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo e($estado); ?></option><?php endforeach; ?></select></div>
        <div class="col-md-3"><select class="form-select bg-light" name="moneda"><option value="">Moneda (todas)</option><option value="PEN" <?php echo (($filtros['moneda'] ?? '') === 'PEN') ? 'selected' : ''; ?>>PEN</option><option value="USD" <?php echo (($filtros['moneda'] ?? '') === 'USD') ? 'selected' : ''; ?>>USD</option></select></div>
        <div class="col-md-3"><select class="form-select bg-light" name="vencimiento"><option value="">Vencimiento (todos)</option><option value="vencidas" <?php echo (($filtros['vencimiento'] ?? '') === 'vencidas') ? 'selected' : ''; ?>>Solo vencidas</option></select></div>
        <div class="col-md-3 d-grid"><button class="btn btn-primary">Filtrar</button></div>
      </form>
    </div></div>

    <div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0 table-pro">
      <thead><tr><th class="ps-4 text-secondary fw-semibold">Proveedor</th><th class="text-secondary fw-semibold">Recepción</th><th class="text-secondary fw-semibold">Vence</th><th class="text-end text-secondary fw-semibold">Total</th><th class="text-end text-secondary fw-semibold">Pagado</th><th class="text-end text-secondary fw-semibold">Saldo</th><th class="text-center text-secondary fw-semibold">Estado</th><th class="text-end pe-4 text-secondary fw-semibold">Acción</th></tr></thead><tbody>
      <?php foreach ($registros as $r): ?>
      <tr class="border-bottom">
        <td class="ps-4 fw-semibold text-dark"><?php echo e((string) ($r['proveedor'] ?? '')); ?></td>
        <td>#<?php echo (int) ($r['id_recepcion'] ?? 0); ?></td>
        <td><?php echo e((string) ($r['fecha_vencimiento'] ?? '')); ?></td>
        <td class="text-end"><?php echo number_format((float) ($r['monto_total'] ?? 0), 4); ?></td>
        <td class="text-end"><?php echo number_format((float) ($r['monto_pagado'] ?? 0), 4); ?></td>
        <td class="text-end fw-bold"><?php echo number_format((float) ($r['saldo'] ?? 0), 4); ?></td>
        <td class="text-center"><span class="badge <?php echo e($badge((string) ($r['estado'] ?? 'ABIERTA'))); ?>"><?php echo e((string) ($r['estado'] ?? '')); ?></span></td>
        <td class="text-end pe-4"><?php if (in_array((string) ($r['estado'] ?? ''), ['ABIERTA','PARCIAL','VENCIDA'], true)): ?><button class="btn btn-sm btn-warning js-open-pago" data-id-origen="<?php echo (int) $r['id']; ?>" data-moneda="<?php echo e((string) $r['moneda']); ?>" data-saldo="<?php echo e((string) $r['saldo']); ?>">Registrar pago</button><?php endif; ?> <a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route_url('tesoreria/movimientos')); ?>&origen=CXP&id_origen=<?php echo (int) $r['id']; ?>&id_tercero=<?php echo (int) ($r['id_proveedor'] ?? 0); ?>">Historial</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody></table></div></div></div>
</div>

<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content border-0 shadow">
<form method="post" action="<?php echo e(route_url('tesoreria/registrar_pago')); ?>" class="js-form-confirm js-form-monto"><div class="modal-header"><h5 class="modal-title">Registrar Pago</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body row g-2">
<input type="hidden" name="id_origen" id="pagoIdOrigen"><div class="col-6"><label class="form-label">Moneda</label><input name="moneda" id="pagoMoneda" class="form-control" readonly></div><div class="col-6"><label class="form-label">Saldo</label><input id="pagoSaldo" class="form-control" readonly data-saldo-target="1"></div>
<div class="col-12"><label class="form-label">Cuenta</label><select name="id_cuenta" class="form-select" required><?php foreach($cuentas as $c): ?><option value="<?php echo (int) $c['id']; ?>"><?php echo e($c['codigo'].' - '.$c['nombre'].' ('.$c['moneda'].')'); ?></option><?php endforeach; ?></select></div>
<div class="col-12"><label class="form-label">Método</label><select name="id_metodo_pago" class="form-select" required><?php foreach($metodos as $m): ?><option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option><?php endforeach; ?></select></div>
<div class="col-6"><label class="form-label">Fecha</label><input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
<div class="col-6"><label class="form-label">Monto</label><input type="number" step="0.0001" min="0.0001" name="monto" id="pagoMonto" class="form-control" required></div>
<div class="col-12"><label class="form-label">Referencia</label><input name="referencia" class="form-control"></div><div class="col-12"><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control"></textarea></div>
</div><div class="modal-footer"><button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-warning">Confirmar pago</button></div></form>
</div></div></div>
<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
