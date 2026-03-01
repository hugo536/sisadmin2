<?php
$movimientos = $movimientos ?? [];
$resumenCuentas = $resumenCuentas ?? [];
$filtros = $filtros ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 fw-bold mb-1"><i class="bi bi-journal-text me-2"></i>Tesorería - Movimientos</h1>
            <p class="text-muted mb-0">Ledger operativo de cobros y pagos.</p>
        </div>
        <div>
            <a href="<?php echo e(route_url('tesoreria/cxc')); ?>" class="btn btn-outline-primary">Ir a CxC</a>
            <a href="<?php echo e(route_url('tesoreria/cxp')); ?>" class="btn btn-outline-secondary">Ir a CxP</a>
        </div>
    </div>
    <?php if (!empty($_GET['ok'])): ?><div class="alert alert-success py-2">Movimiento anulado correctamente.</div><?php endif; ?>
    <?php if (!empty($_GET['error'])): ?><div class="alert alert-danger py-2"><?php echo e((string) $_GET['error']); ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="get" action="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="row g-2 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">Origen</label>
                    <select class="form-select" name="origen">
                        <option value="">Todos</option>
                        <option value="CXC" <?php echo (($filtros['origen'] ?? '') === 'CXC') ? 'selected' : ''; ?>>CXC</option>
                        <option value="CXP" <?php echo (($filtros['origen'] ?? '') === 'CXP') ? 'selected' : ''; ?>>CXP</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">ID Origen</label>
                    <input type="number" min="0" name="id_origen" class="form-control" value="<?php echo (int) ($filtros['id_origen'] ?? 0); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small text-muted mb-1">ID Tercero</label>
                    <input type="number" min="0" name="id_tercero" class="form-control" value="<?php echo (int) ($filtros['id_tercero'] ?? 0); ?>">
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary">Filtrar historial</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-white fw-semibold">Resumen rápido Caja/Banco (Saldo teórico del día)</div>
        <div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Cuenta</th><th>Moneda</th><th>Ingresos</th><th>Egresos</th><th>Saldo teórico</th></tr></thead><tbody>
            <?php foreach ($resumenCuentas as $r): ?>
                <tr>
                    <td><?php echo e((string) ($r['codigo'] ?? '') . ' - ' . (string) ($r['nombre'] ?? '')); ?></td>
                    <td><?php echo e((string) ($r['moneda'] ?? '')); ?></td>
                    <td><?php echo number_format((float) ($r['ingresos'] ?? 0), 4); ?></td>
                    <td><?php echo number_format((float) ($r['egresos'] ?? 0), 4); ?></td>
                    <td class="fw-bold"><?php echo number_format((float) ($r['saldo_teorico'] ?? 0), 4); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </div>

    <div class="card border-0 shadow-sm"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>ID</th><th>Tipo</th><th>Tercero</th><th>Origen</th><th>ID Origen</th><th>Cuenta</th><th>Fecha</th><th>Monto</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($movimientos as $m): ?>
        <tr>
            <td><?php echo (int) ($m['id'] ?? 0); ?></td>
            <td><?php echo e((string) ($m['tipo'] ?? '')); ?></td>
            <td><?php echo e((string) ($m['tercero_nombre'] ?? ('#' . (int) ($m['id_tercero'] ?? 0)))); ?></td>
            <td><?php echo e((string) ($m['origen'] ?? '')); ?></td>
            <td><?php echo (int) ($m['id_origen'] ?? 0); ?></td>
            <td><?php echo e((string) ($m['cuenta_codigo'] ?? '') . ' - ' . (string) ($m['cuenta_nombre'] ?? '')); ?></td>
            <td><?php echo e((string) ($m['fecha'] ?? '')); ?></td>
            <td><?php echo number_format((float) ($m['monto'] ?? 0), 4); ?></td>
            <td><span class="badge <?php echo (($m['estado'] ?? '') === 'CONFIRMADO') ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>"><?php echo e((string) ($m['estado'] ?? '')); ?></span></td>
            <td>
                <?php if (($m['estado'] ?? '') === 'CONFIRMADO' && tiene_permiso('tesoreria.movimientos.anular')): ?>
                    <form method="post" action="<?php echo e(route_url('tesoreria/anular_movimiento')); ?>" class="d-inline js-form-confirm">
                        <input type="hidden" name="id_movimiento" value="<?php echo (int) $m['id']; ?>">
                        <input type="hidden" name="id_origen" value="<?php echo (int) $m['id_origen']; ?>">
                        <input type="hidden" name="origen" value="<?php echo e((string) $m['origen']); ?>">
                        <button class="btn btn-sm btn-outline-danger">Anular</button>
                    </form>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
</div>
<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
