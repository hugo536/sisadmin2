<?php
$adelantos = $adelantos ?? [];
$empleados = $empleados ?? [];
$cuentas = $cuentas ?? [];
$csrf_token = $csrf_token ?? '';
$puedeRegistrar = tiene_permiso('tesoreria.pagos.registrar');
?>

<div class="container-fluid p-4" id="adelantosApp">
    
    <!-- CABECERA -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-primary"></i> Adelantos a Personal
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de préstamos vinculados a cajas y planillas.</p>
        </div>
        <?php if ($puedeRegistrar): ?>
        <button type="button" class="btn btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalNuevoAdelanto">
            <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Adelanto
        </button>
        <?php endif; ?>
    </div>

    <!-- TARJETA DE FILTROS -->
    <div class="card border-0 shadow-sm mb-3 fade-in">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-lg-4">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" id="searchAdelantos" placeholder="Buscar empleado, DNI u observación...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- TARJETA DE TABLA PRINCIPAL -->
    <div class="card border-0 shadow-sm fade-in">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaAdelantos"
                       data-erp-table="true"
                       data-search-input="#searchAdelantos"
                       data-pagination-controls="#adelantosPaginationControls"
                       data-pagination-info="#adelantosPaginationInfo">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Empleado</th>
                            <th class="text-secondary fw-semibold">Cuenta Origen</th>
                            <th class="text-end text-secondary fw-semibold">Monto (S/)</th>
                            <th class="text-end text-secondary fw-semibold">Saldo Pdte.</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($adelantos)): ?>
                            <tr class="empty-msg-row">
                                <td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay adelantos registrados.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($adelantos as $ad): ?>
                                <tr class="border-bottom" data-search="<?php echo strtolower(htmlspecialchars((string)($ad['empleado'] ?? '') . ' ' . (string)($ad['numero_documento'] ?? '') . ' ' . (string)($ad['observacion'] ?? ''))); ?>">
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark" title="Fecha de Entrega: <?php echo !empty($ad['fecha']) ? date('d/m/Y', strtotime($ad['fecha'])) : '-'; ?>">
                                            <i class="bi bi-calendar3 me-1 text-muted"></i> <?php echo !empty($ad['fecha']) ? date('d/m/Y', strtotime($ad['fecha'])) : '-'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo htmlspecialchars($ad['empleado']); ?></div>
                                        <div class="small text-muted mt-1">DNI: <?php echo htmlspecialchars((string) ($ad['numero_documento'] ?? 'No registrado')); ?></div>
                                    </td>
                                    <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($ad['cuenta_origen'] ?? 'Desconocida'); ?></span></td>
                                    
                                    <td class="text-end fw-bold text-dark fs-6">S/ <?php echo number_format((float)$ad['monto'], 2); ?></td>
                                    <td class="text-end fw-bold fs-6 <?php echo $ad['saldo_pendiente'] > 0 ? 'text-danger' : 'text-success'; ?>">
                                        S/ <?php echo number_format((float)$ad['saldo_pendiente'], 2); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($ad['estado'] === 'PENDIENTE'): ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle">PENDIENTE</span>
                                        <?php else: ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle">PAGADO</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <!-- Botón Ver Detalles (Ojo) -->
                                            <button type="button" class="btn btn-sm btn-light text-primary border-0 rounded-circle btn-detalles" 
                                                    data-bs-toggle="modal" data-bs-target="#modalVerDetalle"
                                                    data-id="<?php echo $ad['id']; ?>"
                                                    data-empleado="<?php echo htmlspecialchars($ad['empleado']); ?>"
                                                    title="Ver Historial">
                                                <i class="bi bi-eye-fill fs-5"></i>
                                            </button>

                                            <?php if ($ad['saldo_pendiente'] > 0 && $puedeRegistrar): ?>
                                                <button type="button" class="btn btn-sm btn-light text-success border-0 rounded-circle btn-devolver" 
                                                        data-bs-toggle="modal" data-bs-target="#modalDevolver"
                                                        data-id="<?php echo $ad['id']; ?>"
                                                        data-empleado="<?php echo htmlspecialchars($ad['empleado']); ?>"
                                                        data-saldo="<?php echo $ad['saldo_pendiente']; ?>"
                                                        title="Devolver Efectivo">
                                                    <i class="bi bi-arrow-counterclockwise fs-5"></i>
                                                </button>
                                            <?php endif; ?>
                                            <!-- Se eliminó el "else" para que solo quede el ojito cuando esté pagado -->
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="adelantosPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación de adelantos">
                    <ul class="pagination mb-0 justify-content-end" id="adelantosPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: NUEVO ADELANTO -->
<!-- ============================================================== -->
<div class="modal fade" id="modalNuevoAdelanto" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-coin me-2"></i>Registrar Adelanto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form action="<?php echo e(route_url('tesoreria/adelantos/guardar')); ?>" method="POST" id="formNuevoAdelanto">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                    
                    <div class="card border-0 shadow-sm mb-0">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información del Préstamo</h6>
                            
                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold mb-1">Empleado <span class="text-danger">*</span></label>
                                    <!-- Agregamos id="selectEmpleado" para inicializar TomSelect -->
                                    <select class="form-select shadow-none" name="id_tercero" id="selectEmpleado" required>
                                        <option value="" disabled selected>Seleccione trabajador...</option>
                                        <?php foreach ($empleados as $emp): ?>
                                            <option value="<?php echo $emp['id']; ?>"><?php echo htmlspecialchars($emp['nombre_completo']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold mb-1">Cuenta de Origen (Tesorería) <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-secondary-subtle fw-medium" name="id_cuenta" required>
                                        <option value="" disabled selected>¿De dónde sale el dinero?</option>
                                        <?php foreach ($cuentas as $cta): ?>
                                            <!-- Corregido: Cambiamos $cta['saldo'] por $cta['saldo_actual'] -->
                                            <option value="<?php echo $cta['id']; ?>"><?php echo htmlspecialchars($cta['nombre']); ?> (Saldo: <?php echo $cta['moneda']; ?> <?php echo number_format((float)$cta['saldo_actual'], 2); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold mb-1">Monto a Prestar (S/) <span class="text-danger">*</span></label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle border-end-0 fw-bold">S/</span>
                                        <input type="number" step="0.01" min="1" class="form-control text-primary fw-bold border-secondary-subtle border-start-0 shadow-none" name="monto" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label text-muted small fw-bold mb-1">Fecha de Entrega <span class="text-danger">*</span></label>
                                    <div class="input-group shadow-sm">
                                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-calendar-event text-muted"></i></span>
                                        <input type="date" class="form-control fw-bold border-secondary-subtle border-start-0 shadow-none" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3 align-items-end">
                                <div class="col-md-12">
                                    <label class="form-label text-muted small fw-bold mb-1">Observaciones</label>
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" name="observacion" maxlength="180" placeholder="Motivo del adelanto...">
                                    <div class="form-text small mt-1"><i class="bi bi-info-circle text-primary me-1"></i> Este monto se descontará automáticamente en la próxima planilla.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-end align-items-center gap-2">
                <button type="button" class="btn btn-light text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formNuevoAdelanto" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Entregar Efectivo</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: DEVOLUCIÓN MANUAL -->
<!-- ============================================================== -->
<div class="modal fade" id="modalDevolver" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-counterclockwise me-2"></i>Devolución de Efectivo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form action="<?php echo e(route_url('tesoreria/adelantos/devolver')); ?>" method="POST" id="formDevolverAdelanto">
                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                    <input type="hidden" name="id_adelanto" id="devIdAdelanto">
                    
                    <div class="card border-0 shadow-sm mb-0">
                        <div class="card-body">
                            <p class="mb-3 text-dark">Registrar ingreso de dinero físico devuelto por el trabajador <strong id="devNombreEmpleado" class="text-success"></strong>.</p>

                            <div class="mb-3">
                                <label class="form-label text-muted small fw-bold mb-1">Monto a Devolver (S/) <span class="text-danger">*</span></label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-success-subtle text-success border-success-subtle border-end-0 fw-bold">S/</span>
                                    <input type="number" step="0.01" min="0.01" class="form-control text-success fw-bold border-success-subtle border-start-0 shadow-none" name="monto_devuelto" id="devMonto" required>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label class="form-label text-muted small fw-bold mb-1">Cuenta de Destino (Tesorería) <span class="text-danger">*</span></label>
                                <select class="form-select border-secondary-subtle shadow-none fw-medium" name="id_cuenta_destino" required>
                                    <option value="" disabled selected>¿A qué caja ingresa el dinero?</option>
                                    <?php foreach ($cuentas as $cta): ?>
                                        <option value="<?php echo $cta['id']; ?>"><?php echo htmlspecialchars($cta['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-end align-items-center gap-2">
                <button type="button" class="btn btn-light text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formDevolverAdelanto" class="btn btn-success px-4 fw-bold"><i class="bi bi-check-lg me-2"></i>Registrar Ingreso</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================== -->
<!-- MODAL: VER DETALLES (HISTORIAL DE PAGOS) -->
<!-- ============================================================== -->
<div class="modal fade" id="modalVerDetalle" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-3 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-text me-2"></i>Historial de Pagos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <!-- Tarjeta del Empleado -->
                <div class="mb-4 d-flex align-items-center bg-white p-3 rounded-3 shadow-sm border border-secondary-subtle">
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex justify-content-center align-items-center me-3 shadow-sm" style="width: 45px; height: 45px;">
                        <i class="bi bi-person-fill fs-4"></i>
                    </div>
                    <div>
                        <span class="text-muted fw-bold d-block text-uppercase mb-1" style="font-size: 0.65rem; letter-spacing: 0.5px;">Empleado</span>
                        <span class="fw-bold fs-6 text-dark lh-1" id="detNombreEmpleado">--</span>
                    </div>
                </div>
                
                <!-- Tabla de Historial -->
                <div class="card border border-secondary-subtle shadow-sm mb-0 rounded-3 overflow-hidden">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tablaHistorialAdelanto">
                                <thead class="table-light text-secondary" style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px;">
                                    <tr>
                                        <th class="ps-4 text-start py-3 text-nowrap border-bottom" style="width: 25%;">Fecha</th>
                                        <th class="py-3 text-start border-bottom" style="width: 50%;">Origen</th>
                                        <th class="pe-4 text-end py-3 text-nowrap border-bottom" style="width: 25%;">Monto (S/)</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyHistorialAdelanto">
                                    <!-- Se llena vía JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-light border-top-0 pt-0 pb-3 pe-4">
                <button type="button" class="btn btn-secondary fw-bold px-4 rounded-pill shadow-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
