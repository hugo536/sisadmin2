<?php
$err = (string)($_GET['error'] ?? '');
$ok = (string)($_GET['ok'] ?? '');
$cuentas = $cuentas ?? [];
$cuentasMovimiento = $cuentasMovimiento ?? [];
?>
<div class="container-fluid p-4">

    <?php if ($err !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0 border-start border-danger border-4" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><strong>Error:</strong> <?php echo e($err); ?>

            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>
    
    <?php if ($ok !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show shadow-sm border-0 border-start border-success border-4" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><strong>¡Éxito!</strong> Operación realizada correctamente.
            <button type="button" class="btn-close shadow-none" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-bookmark-fill me-2 text-primary"></i> Plan Contable
            </h1>
            <p class="text-muted small mb-0 ms-1">Catálogo general de cuentas y configuración de parámetros financieros.</p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold" data-bs-toggle="modal" data-bs-target="#modalParametros">
                <i class="bi bi-gear-fill me-2 text-info"></i>Parámetros
            </button>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNuevaCuenta">
                <i class="bi bi-plus-circle-fill me-2"></i>Nueva Cuenta
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-3 pb-2">
            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-nested text-primary me-2"></i>Estructura de Cuentas</h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 15%;">Código</th>
                            <th class="text-secondary fw-semibold">Nombre de la Cuenta</th>
                            <th class="text-secondary fw-semibold">Clasificación (Tipo)</th>
                            <th class="text-center text-secondary fw-semibold">Nivel</th>
                            <th class="text-center text-secondary fw-semibold">Acepta Mov.</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($cuentas)): ?>
                            <?php foreach ($cuentas as $c): ?>
                                <?php 
                                    $esActivo = ((int)$c['estado'] === 1);
                                    $aceptaMov = ((int)$c['permite_movimiento'] === 1);
                                    
                                    // Colores por tipo para lectura rápida
                                    $tipoColor = 'text-secondary';
                                    switch(strtoupper($c['tipo'])) {
                                        case 'ACTIVO': $tipoColor = 'text-success'; break;
                                        case 'PASIVO': $tipoColor = 'text-danger'; break;
                                        case 'PATRIMONIO': $tipoColor = 'text-warning text-dark'; break;
                                        case 'INGRESO': $tipoColor = 'text-info text-dark'; break;
                                        case 'GASTO': $tipoColor = 'text-primary'; break;
                                    }
                                ?>
                                <tr class="border-bottom <?php echo !$esActivo ? 'opacity-50 bg-light' : ''; ?>">
                                    <td class="ps-4 font-monospace fw-bold <?php echo !$aceptaMov ? 'text-dark' : 'text-primary'; ?> pt-3">
                                        <?php echo e($c['codigo']); ?>
                                    </td>
                                    <td class="<?php echo !$aceptaMov ? 'fw-bold text-dark' : 'text-body'; ?> pt-3">
                                        <?php echo e($c['nombre']); ?>
                                    </td>
                                    <td class="fw-medium small <?php echo $tipoColor; ?> pt-3">
                                        <?php echo e($c['tipo']); ?>
                                    </td>
                                    <td class="text-center text-muted fw-bold pt-3">
                                        Lv. <?php echo (int)$c['nivel']; ?>
                                    </td>
                                    <td class="text-center pt-3">
                                        <span class="badge rounded-pill <?php echo $aceptaMov ? 'bg-success-subtle text-success' : 'bg-secondary-subtle text-secondary'; ?>">
                                            <?php echo $aceptaMov ? 'Sí (Transaccional)' : 'No (Agrupadora)'; ?>
                                        </span>
                                    </td>
                                    <td class="text-center pt-3">
                                        <span class="badge rounded-pill <?php echo $esActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>">
                                            <?php echo $esActivo ? 'Activa' : 'Inactiva'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <?php if ($esActivo): ?>
                                            <form method="post" action="<?php echo e(route_url('contabilidad/inactivar_cuenta')); ?>" class="m-0 form-inactivar-cuenta">
                                                <input type="hidden" name="id_cuenta" value="<?php echo (int)$c['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger px-3 rounded-pill fw-semibold">
                                                    <i class="bi bi-power me-1"></i> Inactivar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="small text-muted fst-italic"><i class="bi bi-slash-circle me-1"></i>Deshabilitada</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-light"></i>
                                    El plan contable está vacío. Registre su primera cuenta.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="modalNuevaCuenta" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar Cuenta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('contabilidad/guardar_cuenta')); ?>">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none font-monospace fw-bold" name="codigo" placeholder="Ej. 101" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted fw-bold">Nombre de la Cuenta <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="nombre" placeholder="Ej. Caja General" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Clasificación <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="tipo" required>
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="PASIVO">PASIVO</option>
                                        <option value="PATRIMONIO">PATRIMONIO</option>
                                        <option value="INGRESO">INGRESO</option>
                                        <option value="GASTO">GASTO</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Nivel Jerárquico <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control shadow-none" min="1" name="nivel" value="1" required>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">Cuenta Padre</label>
                                    <select class="form-select shadow-none" name="id_padre">
                                        <option value="0">-- Es una cuenta principal (Sin padre) --</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">¿Acepta Movimientos Directos? <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none border-primary" name="permite_movimiento" required>
                                        <option value="1">Sí (Cuenta transaccional para asientos)</option>
                                        <option value="0">No (Es una cuenta agrupadora/título)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar Cuenta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalParametros" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear-wide-connected me-2"></i>Configurar Parámetros</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <div class="alert alert-info small border-0 shadow-sm mb-4">
                    <i class="bi bi-info-circle-fill me-1"></i> Asigne las cuentas específicas que el sistema utilizará de forma predeterminada para operaciones automáticas.
                </div>
                
                <form method="post" action="<?php echo e(route_url('contabilidad/guardar_parametro')); ?>">
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold">Parámetro del Sistema <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none font-monospace text-primary fw-bold" name="clave" required>
                                        <option value="">Seleccione clave...</option>
                                        <option value="CTA_CAJA_DEFECTO">CTA_CAJA_DEFECTO (Caja Principal)</option>
                                        <option value="CTA_CXC">CTA_CXC (Cuentas por Cobrar)</option>
                                        <option value="CTA_CXP">CTA_CXP (Cuentas por Pagar)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold">Cuenta Contable a Asignar <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta" required>
                                        <option value="">Seleccione cuenta transaccional...</option>
                                        <?php foreach ($cuentasMovimiento as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'].' - '.$c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-secondary px-4 fw-bold"><i class="bi bi-check2-circle me-2"></i>Asignar Parámetro</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/plan_contable.js')); ?>"></script>