<?php
$adelantos = $adelantos ?? [];
$empleados = $empleados ?? [];
$cuentas = $cuentas ?? [];
$csrf_token = $csrf_token ?? '';
$puedeRegistrar = tiene_permiso('tesoreria.pagos.registrar');
?>

<div class="container-fluid p-4 fade-in" id="adelantosApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-primary"></i> Adelantos a Personal
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de préstamos vinculados a cajas y planillas.</p>
        </div>
        <?php if ($puedeRegistrar): ?>
        <button type="button" class="btn btn-primary fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#modalNuevoAdelanto">
            <i class="bi bi-plus-lg me-2"></i>Registrar Adelanto
        </button>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center px-4">
            <div class="input-group input-group-sm bg-white shadow-sm rounded-2" style="max-width: 350px;">
                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 shadow-none py-2" id="searchAdelantos" placeholder="Buscar empleado o detalle...">
            </div>
        </div>

        <div class="table-responsive" style="max-height: calc(100vh - 250px);">
            <table class="table table-hover align-middle mb-0 text-center" id="tablaAdelantos">
                <thead class="table-light sticky-top shadow-sm" style="font-size: 0.8rem; text-transform: uppercase;">
                    <tr>
                        <th class="py-3 ps-4 text-start">Fecha</th>
                        <th class="py-3 text-start">Empleado</th>
                        <th class="py-3">Cuenta Origen</th>
                        <th class="py-3 text-end">Monto (S/)</th>
                        <th class="py-3 text-end">Saldo Pdte.</th>
                        <th class="py-3">Estado</th>
                        <th class="py-3 pe-4">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($adelantos)): ?>
                        <tr class="empty-msg-row">
                            <td colspan="7" class="py-5 text-muted">No hay adelantos registrados en el sistema.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($adelantos as $ad): ?>
                            <tr data-search="<?php echo strtolower(htmlspecialchars($ad['empleado'] . ' ' . $ad['numero_documento'] . ' ' . $ad['observacion'])); ?>">
                                <td class="ps-4 text-start fw-medium text-muted"><?php echo date('d/m/Y', strtotime($ad['fecha'])); ?></td>
                                <td class="text-start">
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($ad['empleado']); ?></div>
                                    <div class="small text-muted">DNI: <?php echo htmlspecialchars($ad['numero_documento']); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ad['cuenta_origen'] ?? 'Desconocida'); ?></span></td>
                                <td class="text-end fw-bold text-dark"><?php echo number_format((float)$ad['monto'], 2); ?></td>
                                <td class="text-end fw-bold <?php echo $ad['saldo_pendiente'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                    <?php echo number_format((float)$ad['saldo_pendiente'], 2); ?>
                                </td>
                                <td>
                                    <?php if ($ad['estado'] === 'PENDIENTE'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">PENDIENTE</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle">PAGADO</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-4">
                                    <?php if ($ad['saldo_pendiente'] > 0 && $puedeRegistrar): ?>
                                        <button type="button" class="btn btn-sm btn-outline-success rounded-pill fw-bold btn-devolver" 
                                                data-bs-toggle="modal" data-bs-target="#modalDevolver"
                                                data-id="<?php echo $ad['id']; ?>"
                                                data-empleado="<?php echo htmlspecialchars($ad['empleado']); ?>"
                                                data-saldo="<?php echo $ad['saldo_pendiente']; ?>">
                                            <i class="bi bi-arrow-counterclockwise me-1"></i> Devolver
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted small"><i class="bi bi-check2-all"></i> Cancelado</span>
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

<!-- ============================================================== -->
<!-- MODAL: NUEVO ADELANTO -->
<!-- ============================================================== -->
<div class="modal fade" id="modalNuevoAdelanto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-3 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Registrar Adelanto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo e(route_url('tesoreria/adelantos/guardar')); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                
                <div class="modal-body p-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Empleado</label>
                        <select class="form-select border-secondary-subtle" name="id_tercero" required>
                            <option value="" disabled selected>Seleccione trabajador...</option>
                            <?php foreach ($empleados as $emp): ?>
                                <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['nombre_completo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Cuenta de Origen (Tesorería)</label>
                        <select class="form-select border-secondary-subtle fw-medium" name="id_cuenta" required>
                            <option value="" disabled selected>¿De dónde sale el dinero?</option>
                            <?php foreach ($cuentas as $cta): ?>
                                <option value="<?php echo $cta['id']; ?>"><?php echo htmlspecialchars($cta['nombre']); ?> (Saldo: <?php echo $cta['moneda']; ?> <?php echo number_format((float)$cta['saldo_actual'], 2); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Monto a Prestar (S/)</label>
                            <input type="number" step="0.01" min="1" class="form-control text-primary fw-bold border-secondary-subtle" name="monto" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Fecha de Entrega</label>
                            <input type="date" class="form-control fw-bold border-secondary-subtle" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small text-muted">Observación</label>
                        <input type="text" class="form-control border-secondary-subtle" name="observacion" placeholder="Motivo del adelanto...">
                        <div class="form-text small mt-1"><i class="bi bi-info-circle text-primary"></i> Este monto se descontará automáticamente en la próxima planilla.</div>
                    </div>

                </div>
                <div class="modal-footer bg-white border-top shadow-sm rounded-bottom-4">
                    <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4">Entregar Efectivo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: DEVOLUCIÓN MANUAL -->
<!-- ============================================================== -->
<div class="modal fade" id="modalDevolver" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-success text-white border-bottom-0 pb-3 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-counterclockwise me-2"></i>Devolución de Efectivo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?php echo e(route_url('tesoreria/adelantos/devolver')); ?>" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                <input type="hidden" name="id_adelanto" id="devIdAdelanto">
                
                <div class="modal-body p-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    
                    <p class="mb-3 text-dark">Registrar ingreso de dinero físico devuelto por el trabajador <strong id="devNombreEmpleado"></strong>.</p>

                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Monto a Devolver (S/)</label>
                        <input type="number" step="0.01" min="0.01" class="form-control text-success fw-bold border-secondary-subtle" name="monto_devuelto" id="devMonto" required>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small text-muted">Cuenta de Destino (Tesorería)</label>
                        <select class="form-select border-secondary-subtle fw-medium" name="id_cuenta_destino" required>
                            <option value="" disabled selected>¿A qué caja ingresa el dinero?</option>
                            <?php foreach ($cuentas as $cta): ?>
                                <option value="<?php echo $cta['id']; ?>"><?php echo htmlspecialchars($cta['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
                <div class="modal-footer bg-white border-top shadow-sm rounded-bottom-4">
                    <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-4">Registrar Ingreso</button>
                </div>
            </form>
        </div>
    </div>
</div>
