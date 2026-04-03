<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$cuentas = $cuentas ?? [];
$metodos = $metodos ?? [];
$proveedores = $proveedores ?? [];
$entidades_catalogo = $entidades_catalogo ?? [];
$centros_costo = $centros_costo ?? [];
?>

<div class="container-fluid p-4" id="tesoreriaPrestamosApp">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-building-fill-check me-2 text-primary"></i> Tesorería - Préstamos Bancarios
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de obligaciones financieras con bancos y registro de pago de cuotas.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?php echo e(route_url('reportes/costos_produccion')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-graph-up-arrow me-2 text-success"></i>Costos de Producción
            </a>
            <a href="<?php echo e(route_url('contabilidad/asientos')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-check me-2 text-info"></i>Asientos Contables
            </a>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalPrestamoNuevo">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Préstamo
            </button>
        </div>
    </div>

    <?php if (!empty($_GET['ok'])): ?>
        <div class="alert alert-success border-0 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i>Operación realizada correctamente.
        </div>
    <?php endif; ?>
    <?php if (!empty($_GET['error'])): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e((string) $_GET['error']); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-end">
                <input type="hidden" name="ruta" value="tesoreria/prestamos">
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">Estado</label>
                    <select class="form-select bg-light border-secondary-subtle" name="estado">
                        <option value="">Todos</option>
                        <?php foreach (['PENDIENTE', 'PARCIAL', 'PAGADA', 'VENCIDA', 'ANULADA'] as $estado): ?>
                            <option value="<?php echo e($estado); ?>" <?php echo (($filtros['estado'] ?? '') === $estado) ? 'selected' : ''; ?>><?php echo e($estado); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <button class="btn btn-primary w-100 shadow-sm" type="submit">
                        <i class="bi bi-funnel me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between">
            <h2 class="h6 fw-bold text-dark mb-0">Detalle de préstamos</h2>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">
                <?php echo count($registros); ?> registros
            </span>
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-pro">
                <thead>
                    <tr>
                        <th class="ps-4">Entidad / Contrato</th>
                        <th>Proveedor</th>
                        <th>Desembolso</th>
                        <th class="text-end">Monto</th>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($registros)): ?>
                    <tr class="empty-msg-row">
                        <td colspan="8" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay préstamos bancarios registrados.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($registros as $r): ?>
                        <?php
                            $estado = strtoupper((string) ($r['estado'] ?? 'PENDIENTE'));
                            $badge = 'bg-primary-subtle text-primary border border-primary-subtle';
                            if ($estado === 'PAGADA') $badge = 'bg-success-subtle text-success border border-success-subtle';
                            if ($estado === 'PARCIAL') $badge = 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                            if ($estado === 'VENCIDA') $badge = 'bg-danger-subtle text-danger border border-danger-subtle';
                            if ($estado === 'ANULADA') $badge = 'bg-secondary-subtle text-secondary border border-secondary-subtle';
                        ?>
                        <tr>
                            <td class="ps-4">
                                <div class="fw-bold text-dark"><?php echo e((string) ($r['entidad_financiera'] ?? '')); ?></div>
                                <div class="small text-muted">
                                    Contrato: <?php echo e((string) ($r['numero_contrato'] ?? 'S/N')); ?>
                                    · <?php echo e((string) ($r['tipo_tasa'] ?? 'FIJA')); ?> <?php echo number_format((float) ($r['tasa_anual'] ?? 0), 2); ?>%
                                </div>
                            </td>
                            <td><?php echo e((string) ($r['proveedor'] ?? '')); ?></td>
                            <td class="small text-muted"><?php echo e((string) ($r['fecha_desembolso'] ?? '')); ?></td>
                            <td class="text-end fw-semibold"><?php echo e((string) ($r['moneda'] ?? 'PEN')); ?> <?php echo number_format((float) ($r['monto_total'] ?? 0), 2); ?></td>
                            <td class="text-end text-success fw-semibold"><?php echo e((string) ($r['moneda'] ?? 'PEN')); ?> <?php echo number_format((float) ($r['monto_pagado'] ?? 0), 2); ?></td>
                            <td class="text-end text-danger fw-bold"><?php echo e((string) ($r['moneda'] ?? 'PEN')); ?> <?php echo number_format((float) ($r['saldo'] ?? 0), 2); ?></td>
                            <td class="text-center"><span class="badge <?php echo $badge; ?>"><?php echo e($estado); ?></span></td>
                            <td class="text-end pe-4">
                                <?php if ($estado !== 'PAGADA' && $estado !== 'ANULADA'): ?>
                                <button
                                    class="btn btn-sm btn-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#modalPago"
                                    data-id-origen="<?php echo (int) ($r['id_cxp'] ?? 0); ?>"
                                    data-moneda="<?php echo e((string) ($r['moneda'] ?? 'PEN')); ?>"
                                    data-saldo="<?php echo (float) ($r['saldo'] ?? 0); ?>">
                                    <i class="bi bi-cash-stack me-1"></i>Pagar
                                </button>
                                <?php else: ?>
                                <span class="text-muted small">Sin acciones</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPrestamoNuevo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg" method="post" action="<?php echo e(route_url('tesoreria/guardar_prestamo')); ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-bank me-2 text-primary"></i>Registrar nuevo préstamo</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Proveedor (tercero) <span class="text-danger">*</span></label>
                        <select name="id_proveedor" class="form-select shadow-none" required>
                            <option value="" selected disabled>Seleccione...</option>
                            <?php foreach ($proveedores as $prov): ?>
                                <option value="<?php echo (int) ($prov['id'] ?? 0); ?>"><?php echo e((string) ($prov['nombre_completo'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Entidad financiera (Catálogo)</label>
                        <select class="form-select shadow-none" id="prestamoEntidadCatalogo">
                            <option value="" selected>Seleccione...</option>
                            <?php foreach ($entidades_catalogo as $entidad): ?>
                                <option
                                    value="<?php echo e((string) ($entidad['nombre'] ?? '')); ?>"
                                    data-tipo="<?php echo e((string) ($entidad['tipo'] ?? '')); ?>"
                                    data-codigo="<?php echo e((string) ($entidad['codigo'] ?? '')); ?>">
                                    <?php echo e((string) ($entidad['nombre'] ?? '')); ?> (<?php echo e((string) ($entidad['tipo'] ?? '')); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Nombre entidad financiera <span class="text-danger">*</span></label>
                        <input type="text" id="prestamoEntidadNombre" name="entidad_financiera" class="form-control shadow-none" maxlength="160" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">N° contrato</label>
                        <input type="text" name="numero_contrato" class="form-control shadow-none" maxlength="80">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Moneda <span class="text-danger">*</span></label>
                        <select name="moneda" class="form-select shadow-none" required>
                            <option value="PEN">PEN</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Monto desembolsado <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="monto_total" class="form-control shadow-none" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Fecha desembolso <span class="text-danger">*</span></label>
                        <input type="date" name="fecha_desembolso" value="<?php echo date('Y-m-d'); ?>" class="form-control shadow-none" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Fecha primera cuota</label>
                        <input type="date" name="fecha_primera_cuota" value="<?php echo date('Y-m-d'); ?>" class="form-control shadow-none">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">N° cuotas <span class="text-danger">*</span></label>
                        <input type="number" min="1" max="600" name="numero_cuotas" value="12" class="form-control shadow-none" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Tipo tasa <span class="text-danger">*</span></label>
                        <select name="tipo_tasa" class="form-select shadow-none" required>
                            <option value="FIJA">FIJA</option>
                            <option value="VARIABLE">VARIABLE</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Tasa anual (%)</label>
                        <input type="number" step="0.0001" min="0" name="tasa_anual" class="form-control shadow-none" value="0">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                        <textarea name="observaciones" class="form-control shadow-none" rows="2" maxlength="255"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save2 me-2"></i>Guardar préstamo</button>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form class="modal-content border-0 shadow-lg js-form-confirm" method="post" id="formPago" action="<?php echo e(route_url('tesoreria/registrar_pago_prestamo')); ?>">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-credit-card me-2 text-warning"></i>Registrar pago de cuota</h5>
                <button type="button" class="btn-close shadow-none" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="id_origen" id="pagoIdOrigen">
                <input type="hidden" name="moneda" id="pagoMoneda">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Saldo pendiente</label>
                        <input type="text" id="pagoSaldo" class="form-control bg-light" readonly>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label fw-bold small text-muted">Cuenta Origen <span class="text-danger">*</span></label>
                        <select name="id_cuenta" id="selectCuentaOrigen" class="form-select shadow-none" required>
                            <option value="" data-saldo="0" selected disabled>Seleccione una cuenta...</option>
                            <?php foreach ($cuentas as $cta): ?>
                                <option value="<?php echo (int) $cta['id']; ?>" data-saldo="<?php echo (float) ($cta['saldo'] ?? 0); ?>">
                                    <?php echo e((string) ($cta['nombre'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small id="textoSaldoDisponible" class="text-primary fw-bold mt-1 d-block"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold mb-1">Método de Pago <span class="text-danger">*</span></label>
                        <select name="id_metodo_pago" class="form-select shadow-sm border-secondary-subtle" required>
                            <option value="" selected disabled>Seleccione un método...</option>
                            <?php foreach ($metodos as $m): ?>
                                <option value="<?php echo (int) ($m['id'] ?? 0); ?>"><?php echo e((string) ($m['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold mb-1">Fecha de Pago <span class="text-danger">*</span></label>
                        <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold mb-1">Naturaleza del Pago <span class="text-danger">*</span></label>
                        <select name="naturaleza_pago" id="pagoNaturaleza" class="form-select shadow-sm border-secondary-subtle" required>
                            <option value="DOCUMENTO" selected>Pago de cuota (documento)</option>
                            <option value="CAPITAL">Solo capital (amortización)</option>
                            <option value="INTERES">Solo interés (gasto financiero)</option>
                            <option value="MIXTO">Mixto (capital + interés)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small text-muted fw-bold mb-1">Monto a Pagar <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" name="monto" id="pagoMonto" class="form-control shadow-sm border-secondary-subtle fw-bold text-warning-emphasis" required>
                    </div>

                    <div class="col-md-6 d-none" id="grupoPagoCapital">
                        <label class="form-label small text-muted fw-bold mb-1">Desglose: Capital</label>
                        <input type="number" step="0.01" min="0" name="monto_capital" id="pagoMontoCapital" class="form-control shadow-sm border-secondary-subtle" value="0">
                    </div>

                    <div class="col-md-6 d-none" id="grupoPagoInteres">
                        <label class="form-label small text-muted fw-bold mb-1">Desglose: Interés</label>
                        <input type="number" step="0.01" min="0" name="monto_interes" id="pagoMontoInteres" class="form-control shadow-sm border-secondary-subtle text-danger" value="0">
                    </div>

                    <div class="col-md-12 d-none" id="grupoCentroCostoInteres">
                        <label class="form-label small text-muted fw-bold mb-1">Centro de Costo (Gasto Financiero) <span class="text-danger">*</span></label>
                        <select name="id_centro_costo" id="pagoCentroCosto" class="form-select shadow-sm border-secondary-subtle bg-warning-subtle">
                            <option value="" selected disabled>Seleccione...</option>
                            <?php foreach ($centros_costo as $cc): ?>
                                <option value="<?php echo (int) ($cc['id'] ?? 0); ?>"><?php echo e((string) ($cc['codigo'] ?? '') . ' - ' . (string) ($cc['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small text-muted fw-bold mb-1">Referencia / N° operación</label>
                        <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. TRF-0001">
                    </div>
                    <div class="col-md-12">
                        <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                        <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Notas del pago..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-light border shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success fw-bold shadow-sm"><i class="bi bi-save2-fill me-2"></i>Confirmar Pago</button>
            </div>
        </form>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
