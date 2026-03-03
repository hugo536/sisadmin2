<?php
$cuentas = $cuentas ?? [];
$bancos = $bancos ?? [];
$cuentaEditar = $cuentaEditar ?? null;
$esEdicion = is_array($cuentaEditar) && !empty($cuentaEditar['id']);
?>

<div class="container-fluid p-4" id="tesoreriaCuentasApp">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bank me-2 text-primary"></i> Tesorería - Cuentas
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra cajas, bancos y billeteras para operar cobros/pagos.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-text me-2 text-primary"></i>Ver movimientos
            </a>
        </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success d-flex align-items-center py-2 shadow-sm border-0 fade-in mb-4">
            <i class="bi bi-check-circle-fill fs-5 me-2"></i> Cuenta guardada correctamente.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger d-flex align-items-center py-2 shadow-sm border-0 fade-in mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> <?php echo e((string) $_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white border-bottom py-3 px-4">
            <h2 class="h6 fw-bold text-dark mb-0">
                <i class="bi bi-pencil-square me-2 text-primary"></i><?php echo $esEdicion ? 'Editar cuenta' : 'Nueva cuenta'; ?>
            </h2>
        </div>
        <div class="card-body p-3 p-md-4">
            <form method="post" action="<?php echo e(route_url('tesoreria/guardar_cuenta')); ?>" class="row g-3">
                <input type="hidden" name="id" value="<?php echo (int) ($cuentaEditar['id'] ?? 0); ?>">

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Código *</label>
                    <input type="text" maxlength="30" name="codigo" required class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['codigo'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label small text-muted fw-bold mb-1">Nombre *</label>
                    <input type="text" maxlength="120" name="nombre" required class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['nombre'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Tipo *</label>
                    <?php $tipoActual = (string) ($cuentaEditar['tipo'] ?? 'CAJA'); ?>
                    <select name="tipo" id="cuentaTipo" class="form-select bg-light border-secondary-subtle shadow-sm" required>
                        <option value="CAJA" <?php echo $tipoActual === 'CAJA' ? 'selected' : ''; ?>>CAJA</option>
                        <option value="BANCO" <?php echo $tipoActual === 'BANCO' ? 'selected' : ''; ?>>BANCO</option>
                        <option value="BILLETERA" <?php echo $tipoActual === 'BILLETERA' ? 'selected' : ''; ?>>BILLETERA</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Moneda *</label>
                    <?php $monedaActual = (string) ($cuentaEditar['moneda'] ?? 'PEN'); ?>
                    <select name="moneda" class="form-select bg-light border-secondary-subtle shadow-sm" required>
                        <option value="PEN" <?php echo $monedaActual === 'PEN' ? 'selected' : ''; ?>>PEN</option>
                        <option value="USD" <?php echo $monedaActual === 'USD' ? 'selected' : ''; ?>>USD</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 js-bank-field">
                    <label class="form-label small text-muted fw-bold mb-1">Config. Caja/Banco</label>
                    <select name="config_banco_id" class="form-select bg-light border-secondary-subtle shadow-sm">
                        <option value="">Seleccionar</option>
                        <?php $configBancoActual = (int) ($cuentaEditar['config_banco_id'] ?? 0); ?>
                        <?php foreach ($bancos as $b): ?>
                            <option value="<?php echo (int) ($b['id'] ?? 0); ?>" <?php echo $configBancoActual === (int) ($b['id'] ?? 0) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($b['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4 js-bank-field">
                    <label class="form-label small text-muted fw-bold mb-1">Titular</label>
                    <input type="text" maxlength="150" name="titular" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['titular'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-4 js-bank-field">
                    <label class="form-label small text-muted fw-bold mb-1">Tipo cuenta</label>
                    <input type="text" maxlength="30" name="tipo_cuenta" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['tipo_cuenta'] ?? '')); ?>" placeholder="Ahorros, Corriente...">
                </div>
                <div class="col-12 col-md-4 js-bank-field">
                    <label class="form-label small text-muted fw-bold mb-1">N° cuenta</label>
                    <input type="text" maxlength="80" name="numero_cuenta" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['numero_cuenta'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-4 js-bank-field">
                    <label class="form-label small text-muted fw-bold mb-1">CCI</label>
                    <input type="text" maxlength="80" name="cci" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['cci'] ?? '')); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Saldo inicial</label>
                    <input type="number" step="0.0001" min="0" name="saldo_inicial" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['saldo_inicial'] ?? '0.0000')); ?>">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Fecha saldo inicial</label>
                    <input type="date" name="fecha_saldo_inicial" class="form-control bg-light border-secondary-subtle shadow-sm" value="<?php echo e((string) ($cuentaEditar['fecha_saldo_inicial'] ?? '')); ?>">
                </div>

                <div class="col-12">
                    <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                    <textarea name="observaciones" maxlength="255" class="form-control bg-light border-secondary-subtle shadow-sm" rows="2"><?php echo e((string) ($cuentaEditar['observaciones'] ?? '')); ?></textarea>
                </div>

                <div class="col-12 d-flex flex-wrap gap-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="permite_cobros" id="permiteCobros" <?php echo ((int) ($cuentaEditar['permite_cobros'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="permiteCobros">Permite cobros</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="permite_pagos" id="permitePagos" <?php echo ((int) ($cuentaEditar['permite_pagos'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="permitePagos">Permite pagos</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="principal" id="cuentaPrincipal" <?php echo ((int) ($cuentaEditar['principal'] ?? 0) === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cuentaPrincipal">Cuenta principal</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="estado" id="cuentaEstado" <?php echo ((int) ($cuentaEditar['estado'] ?? 1) === 1) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="cuentaEstado">Activa</label>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2">
                    <button type="submit" class="btn btn-primary shadow-sm fw-bold">
                        <i class="bi bi-save me-1"></i><?php echo $esEdicion ? 'Actualizar cuenta' : 'Guardar cuenta'; ?>
                    </button>
                    <?php if ($esEdicion): ?>
                        <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>" class="btn btn-light border shadow-sm">Cancelar edición</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Listado de cuentas</h6>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2"><?php echo count($cuentas); ?> registros</span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Código</th>
                        <th>Nombre</th>
                        <th>Tipo</th>
                        <th>Moneda</th>
                        <th>Banco/Caja</th>
                        <th class="text-center">Cobros</th>
                        <th class="text-center">Pagos</th>
                        <th class="text-center">Estado</th>
                        <th class="text-center pe-4">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($cuentas)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-5">No hay cuentas registradas.</td></tr>
                    <?php else: ?>
                        <?php foreach ($cuentas as $c): ?>
                            <tr>
                                <td class="ps-4 fw-semibold"><?php echo e((string) ($c['codigo'] ?? '')); ?></td>
                                <td><?php echo e((string) ($c['nombre'] ?? '')); ?><?php if ((int) ($c['principal'] ?? 0) === 1): ?> <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Principal</span><?php endif; ?></td>
                                <td><?php echo e((string) ($c['tipo'] ?? '')); ?></td>
                                <td><?php echo e((string) ($c['moneda'] ?? '')); ?></td>
                                <td><?php echo e((string) ($c['banco_nombre'] ?? '-')); ?></td>
                                <td class="text-center"><?php echo ((int) ($c['permite_cobros'] ?? 0) === 1) ? 'Sí' : 'No'; ?></td>
                                <td class="text-center"><?php echo ((int) ($c['permite_pagos'] ?? 0) === 1) ? 'Sí' : 'No'; ?></td>
                                <td class="text-center">
                                    <?php if ((int) ($c['estado'] ?? 0) === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>&id=<?php echo (int) ($c['id'] ?? 0); ?>" class="btn btn-sm btn-light border shadow-sm">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
