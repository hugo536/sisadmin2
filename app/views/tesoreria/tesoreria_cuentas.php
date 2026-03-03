<?php
$cuentas = $cuentas ?? [];
$bancos = $bancos ?? [];
$cuentaEditar = $cuentaEditar ?? null;
$esEdicion = is_array($cuentaEditar) && !empty($cuentaEditar['id']);
?>

<div class="container-fluid p-4" id="tesoreriaCuentasApp" data-es-edicion="<?php echo $esEdicion ? 'true' : 'false'; ?>">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bank me-2 text-primary"></i> Tesorería - Cuentas
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra cajas, bancos y billeteras para operar cobros/pagos.</p>
        </div>
        
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-text me-2 text-info"></i>Ver movimientos
            </a>
            
            <?php if ($esEdicion): ?>
                <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>" class="btn btn-primary shadow-sm fw-bold">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Cuenta
                </a>
            <?php else: ?>
                <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalCuentaTesoreria">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Cuenta
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-danger border-4 mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-5 me-2"></i> <?php echo e((string) $_GET['error']); ?>
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Listado de cuentas registradas</h6>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-3 py-2"><?php echo count($cuentas); ?> registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Nombre</th>
                            <th class="text-secondary fw-semibold">Tipo</th>
                            <th class="text-secondary fw-semibold">Moneda</th>
                            <th class="text-secondary fw-semibold">Saldo actual</th>
                            <th class="text-center text-secondary fw-semibold">Cobros / Pagos</th>
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
                                <tr class="border-bottom <?php echo !$esActiva ? 'bg-light opacity-75' : ''; ?>">
                                    <td class="ps-4 fw-bold text-primary pt-3"><?php echo e((string) ($c['codigo'] ?? '')); ?></td>
                                    <td class="fw-semibold text-dark pt-3">
                                        <?php echo e((string) ($c['nombre'] ?? '')); ?>
                                        <?php if ((int) ($c['principal'] ?? 0) === 1): ?> 
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle ms-1" style="font-size:0.65rem;">Principal</span>
                                        <?php endif; ?>
                                        <div class="small text-muted mt-1"><?php echo e((string) ($c['banco_nombre'] ?? '-')); ?></div>
                                    </td>
                                    <td class="text-muted pt-3"><?php echo e((string) ($c['tipo'] ?? '')); ?></td>
                                    <td class="text-muted pt-3 fw-bold"><?php echo e((string) ($c['moneda'] ?? '')); ?></td>
                                    <td class="text-muted pt-3 fw-bold"><?php echo e((string) ($c['moneda'] ?? '')); ?> <?php echo number_format((float) ($c['saldo_actual'] ?? 0), 2); ?></td>
                                    
                                    <td class="text-center pt-3">
                                        <span class="badge <?php echo ((int) ($c['permite_cobros'] ?? 0) === 1) ? 'text-success' : 'text-muted'; ?>" title="Permite Cobros"><i class="bi bi-box-arrow-in-down-right fs-6"></i></span>
                                        <span class="badge <?php echo ((int) ($c['permite_pagos'] ?? 0) === 1) ? 'text-danger' : 'text-muted'; ?>" title="Permite Pagos"><i class="bi bi-box-arrow-up-right fs-6"></i></span>
                                    </td>
                                    
                                    <td class="text-center pt-3">
                                        <span class="badge rounded-pill <?php echo $esActiva ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                            <?php echo $esActiva ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>&id=<?php echo (int) ($c['id'] ?? 0); ?>" 
                                           class="btn btn-sm btn-light text-primary border-0 bg-transparent rounded-circle"
                                           data-bs-toggle="tooltip" data-bs-placement="top" title="Editar cuenta">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCuentaTesoreria" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi <?php echo $esEdicion ? 'bi-pencil-square' : 'bi-plus-circle'; ?> me-2"></i>
                    <?php echo $esEdicion ? 'Editar Cuenta' : 'Nueva Cuenta'; ?>
                </h5>
                <?php if ($esEdicion): ?>
                    <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>" class="btn-close btn-close-white"></a>
                <?php else: ?>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                <?php endif; ?>
            </div>

            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('tesoreria/guardar_cuenta')); ?>" autocomplete="off" id="formCuentaTesoreria">
                    <input type="hidden" name="id" value="<?php echo (int) ($cuentaEditar['id'] ?? 0); ?>">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Datos Generales</h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-3">
                                    <label class="form-label small text-muted fw-bold mb-1">Código</label>
                                    <input type="text" maxlength="30" id="cuentaCodigo" name="codigo" class="form-control shadow-none font-monospace fw-bold" value="<?php echo e((string) ($cuentaEditar['codigo'] ?? '')); ?>" placeholder="Se genera automáticamente si se deja vacío">
                                </div>
                                <div class="col-12 col-md-5">
                                    <label class="form-label small text-muted fw-bold mb-1">Nombre <span class="text-danger">*</span></label>
                                    <input type="text" maxlength="120" name="nombre" required class="form-control shadow-none" value="<?php echo e((string) ($cuentaEditar['nombre'] ?? '')); ?>" placeholder="Ej. Caja Principal">
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1">Tipo <span class="text-danger">*</span></label>
                                    <?php $tipoActual = (string) ($cuentaEditar['tipo'] ?? 'CAJA'); ?>
                                    <select name="tipo" id="cuentaTipo" class="form-select shadow-none" required>
                                        <option value="CAJA" <?php echo $tipoActual === 'CAJA' ? 'selected' : ''; ?>>CAJA</option>
                                        <option value="BANCO" <?php echo $tipoActual === 'BANCO' ? 'selected' : ''; ?>>BANCO</option>
                                        <option value="BILLETERA" <?php echo $tipoActual === 'BILLETERA' ? 'selected' : ''; ?>>BILLETERA</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1">Moneda <span class="text-danger">*</span></label>
                                    <?php $monedaActual = (string) ($cuentaEditar['moneda'] ?? 'PEN'); ?>
                                    <select name="moneda" class="form-select shadow-none" required>
                                        <option value="PEN" <?php echo $monedaActual === 'PEN' ? 'selected' : ''; ?>>PEN</option>
                                        <option value="USD" <?php echo $monedaActual === 'USD' ? 'selected' : ''; ?>>USD</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Configuración Bancaria / Saldos</h6>
                            <div class="row g-3">
                                <div class="col-12 col-md-4 js-bank-field" id="cuentaEntidadWrap">
                                    <label class="form-label small text-muted fw-bold mb-1">Entidad (Banco/Caja)</label>
                                    <select name="config_banco_id" id="cuentaEntidad" class="form-select shadow-none">
                                        <option value="">Seleccionar...</option>
                                        <?php $configBancoActual = (int) ($cuentaEditar['config_banco_id'] ?? 0); ?>
                                        <?php foreach ($bancos as $b): ?>
                                            <option value="<?php echo (int) ($b['id'] ?? 0); ?>" data-tipo="<?php echo e((string) ($b['tipo'] ?? '')); ?>" <?php echo $configBancoActual === (int) ($b['id'] ?? 0) ? 'selected' : ''; ?>>
                                                <?php echo e((string) ($b['nombre'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4 js-bank-field" id="cuentaTipoCuentaWrap">
                                    <label class="form-label small text-muted fw-bold mb-1">Tipo de cuenta</label>
                                    <?php $tipoCuentaActual = (string) ($cuentaEditar['tipo_cuenta'] ?? ''); ?>
                                    <select name="tipo_cuenta" id="cuentaTipoCuenta" class="form-select shadow-none" data-selected="<?php echo e($tipoCuentaActual); ?>">
                                        <option value="">Seleccionar...</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-4 js-bank-field" id="cuentaNumeroWrap">
                                    <label class="form-label small text-muted fw-bold mb-1" id="cuentaNumeroLabel">N° de cuenta</label>
                                    <input type="text" maxlength="80" name="numero_cuenta" id="cuentaNumero" class="form-control shadow-none" value="<?php echo e((string) ($cuentaEditar['numero_cuenta'] ?? '')); ?>" placeholder="000-0000000">
                                </div>
                                <div class="col-12 col-md-6 js-bank-field" id="cuentaTitularWrap">
                                    <label class="form-label small text-muted fw-bold mb-1">Titular</label>
                                    <input type="text" maxlength="150" name="titular" class="form-control shadow-none" value="<?php echo e((string) ($cuentaEditar['titular'] ?? '')); ?>">
                                </div>
                                <div class="col-12 col-md-6 js-bank-field" id="cuentaCciWrap">
                                    <label class="form-label small text-muted fw-bold mb-1">CCI (Código de Cuenta Interbancario)</label>
                                    <input type="text" maxlength="80" name="cci" id="cuentaCci" class="form-control shadow-none" value="<?php echo e((string) ($cuentaEditar['cci'] ?? '')); ?>">
                                </div>
                                
                                <div class="col-12 col-md-6 mt-4">
                                    <label class="form-label small text-muted fw-bold mb-1">Saldo inicial</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" min="0" name="saldo_inicial" class="form-control shadow-none fw-bold" value="<?php echo e((string) ($cuentaEditar['saldo_inicial'] ?? '0.0000')); ?>">
                                    </div>
                                </div>
                                <div class="col-12 col-md-6 mt-4">
                                    <label class="form-label small text-muted fw-bold mb-1">Fecha saldo inicial</label>
                                    <input type="date" name="fecha_saldo_inicial" class="form-control shadow-none" value="<?php echo e((string) ($cuentaEditar['fecha_saldo_inicial'] ?? '')); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold mb-1">Observaciones / Notas</label>
                                    <textarea name="observaciones" maxlength="255" class="form-control shadow-none" rows="2" placeholder="Información adicional..."><?php echo e((string) ($cuentaEditar['observaciones'] ?? '')); ?></textarea>
                                </div>

                                <div class="col-12 d-flex flex-wrap gap-4 mt-3 pt-3 border-top">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input border-secondary-subtle" type="checkbox" name="permite_cobros" id="permiteCobros" <?php echo ((int) ($cuentaEditar['permite_cobros'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-medium text-dark" for="permiteCobros">Permite Cobros</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input border-secondary-subtle" type="checkbox" name="permite_pagos" id="permitePagos" <?php echo ((int) ($cuentaEditar['permite_pagos'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-medium text-dark" for="permitePagos">Permite Pagos</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input border-secondary-subtle" type="checkbox" name="principal" id="cuentaPrincipal" <?php echo ((int) ($cuentaEditar['principal'] ?? 0) === 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-medium text-dark" for="cuentaPrincipal">Cuenta Principal</label>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input border-secondary-subtle" type="checkbox" name="estado" id="cuentaEstado" <?php echo ((int) ($cuentaEditar['estado'] ?? 1) === 1) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-medium text-dark" for="cuentaEstado">Cuenta Activa</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 border-top">
                        <?php if ($esEdicion): ?>
                            <a href="<?php echo e(route_url('tesoreria/cuentas')); ?>" class="btn btn-light text-secondary me-2 fw-semibold border">Cancelar edición</a>
                        <?php else: ?>
                            <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <?php endif; ?>
                        
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="bi bi-save me-2"></i><?php echo $esEdicion ? 'Actualizar Cuenta' : 'Guardar Cuenta'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
