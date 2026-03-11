<?php
$cuentas = $cuentas ?? [];
$conciliaciones = $conciliaciones ?? [];
$detalle = $detalle ?? [];
$idConciliacionActiva = (int)($idConciliacionActiva ?? 0);
?>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-bank2 me-2 text-primary"></i> Conciliación Bancaria
            </h1>
            <p class="text-muted small mb-0 ms-1">Cuadratura entre los saldos del sistema y el estado de cuenta bancario.</p>
        </div>

        <div class="d-flex gap-2">
            <?php if (tiene_permiso('conta.conciliacion.gestionar')): ?>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalConciliacion">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nueva Conciliación
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-2">
                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-check text-primary me-2"></i>Historial de Conciliaciones</h6>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover table-pro">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-4 text-secondary fw-semibold">Periodo</th>
                                    <th class="text-secondary fw-semibold">Cuenta Bancaria</th>
                                    <th class="text-end text-secondary fw-semibold">Sistema</th>
                                    <th class="text-end text-secondary fw-semibold">Cartola / Banco</th>
                                    <th class="text-end text-secondary fw-semibold">Diferencia</th>
                                    <th class="text-center pe-4 text-secondary fw-semibold">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($conciliaciones)): ?>
                                    <?php foreach ($conciliaciones as $c): ?>
                                        <?php 
                                            $esCerrada = (strtoupper(trim($c['estado'])) === 'CERRADA'); 
                                            $esActiva = ($idConciliacionActiva === (int)$c['id']);
                                        ?>
                                        <tr class="border-bottom <?php echo $esActiva ? 'bg-primary bg-opacity-10' : ''; ?>">
                                            <td class="ps-4 fw-bold text-dark pt-3">
                                                <?php echo e($c['periodo']); ?>
                                                <span class="d-block mt-1 badge <?php echo $esCerrada ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning-emphasis'; ?>" style="width: max-content;">
                                                    <?php echo e($c['estado']); ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small pt-3 fw-medium"><?php echo e($c['cuenta_nombre']); ?></td>
                                            <td class="text-end pt-3"><?php echo number_format((float)$c['saldo_sistema'], 4); ?></td>
                                            <td class="text-end text-primary fw-semibold pt-3"><?php echo number_format((float)$c['saldo_estado_cuenta'], 4); ?></td>
                                            <td class="text-end pt-3">
                                                <?php 
                                                    $diferencia = (float)$c['diferencia'];
                                                    $colorDiff = $diferencia == 0 ? 'text-success' : 'text-danger';
                                                ?>
                                                <span class="<?php echo $colorDiff; ?> fw-bold"><?php echo number_format($diferencia, 4); ?></span>
                                            </td>
                                            <td class="text-center pe-4 pt-3">
                                                <a class="btn btn-sm <?php echo $esActiva ? 'btn-primary' : 'btn-light text-primary border'; ?> rounded-pill px-3" href="<?php echo e(route_url('conciliacion/index?id='.(int)$c['id'])); ?>">
                                                    <?php echo $esActiva ? '<i class="bi bi-eye-fill me-1"></i>Viendo' : 'Ver'; ?>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-5">
                                            <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                            Aún no hay conciliaciones registradas.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <?php if ($idConciliacionActiva > 0): ?>
                
                <div class="card border-0 shadow-sm mb-3 border-top border-info border-3">
                    <div class="card-body">
                        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-tools text-info me-2"></i>Acciones de Conciliación</h6>
                        
                        <form method="post" action="<?php echo e(route_url('conciliacion/importar')); ?>" enctype="multipart/form-data" class="bg-light p-3 rounded-3 mb-3 border">
                            <label class="form-label small fw-bold text-muted mb-2 d-block">1. Cargar Estado de Cuenta (CSV)</label>
                            <div class="input-group">
                                <input type="hidden" name="id_conciliacion" value="<?php echo $idConciliacionActiva; ?>">
                                <input type="file" class="form-control form-control-sm bg-white" name="archivo" accept=".csv" required>
                                <button class="btn btn-sm btn-outline-primary fw-semibold px-3" type="submit">
                                    <i class="bi bi-upload me-1"></i> Importar
                                </button>
                            </div>
                        </form>

                        <form method="post" action="<?php echo e(route_url('conciliacion/cerrar')); ?>">
                            <input type="hidden" name="id_conciliacion" value="<?php echo $idConciliacionActiva; ?>">
                            <button class="btn btn-success w-100 fw-bold shadow-sm btn-cerrar-conciliacion" type="submit">
                                <i class="bi bi-check-circle-fill me-2"></i> Finalizar y Cerrar Conciliación
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-list-ul text-secondary me-2"></i>Movimientos Importados</h6>
                        <span class="badge bg-primary rounded-pill"><?php echo count($detalle); ?> regs</span>
                    </div>
                    <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                        <?php if (!empty($detalle)): ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($detalle as $d): ?>
                                    <?php $esConciliado = ((int)$d['conciliado'] === 1); ?>
                                    <li class="list-group-item py-3 <?php echo $esConciliado ? 'bg-success bg-opacity-10' : ''; ?>">
                                        <form method="post" action="<?php echo e(route_url('conciliacion/marcar_detalle')); ?>" class="d-flex align-items-center justify-content-between">
                                            <input type="hidden" name="id_conciliacion" value="<?php echo $idConciliacionActiva; ?>">
                                            <input type="hidden" name="id_detalle" value="<?php echo (int)$d['id']; ?>">
                                            <input type="hidden" name="conciliado" value="<?php echo $esConciliado ? 0 : 1; ?>">
                                            
                                            <div class="me-3">
                                                <div class="small fw-bold text-dark mb-1">
                                                    <?php echo $esConciliado ? '<i class="bi bi-check2-all text-success me-1"></i>' : '<i class="bi bi-dash text-muted me-1"></i>'; ?>
                                                    <?php echo e($d['fecha']); ?> - <?php echo e($d['descripcion']); ?>
                                                </div>
                                                <div class="small text-muted font-monospace bg-light d-inline-block px-2 py-1 rounded border">
                                                    Ref: <?php echo e((string)$d['referencia']); ?>
                                                </div>
                                            </div>
                                            
                                            <div class="text-end d-flex flex-column align-items-end gap-2">
                                                <span class="fw-bold <?php echo ((float)$d['monto'] < 0) ? 'text-danger' : 'text-success'; ?>">
                                                    <?php echo number_format((float)$d['monto'], 4); ?>
                                                </span>
                                                <button type="submit" class="btn btn-sm <?php echo $esConciliado ? 'btn-success' : 'btn-outline-secondary'; ?> rounded-pill px-3" style="font-size: 0.75rem;">
                                                    <?php echo $esConciliado ? 'Conciliado' : 'Marcar'; ?>
                                                </button>
                                            </div>
                                        </form>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-file-earmark-excel fs-2 d-block mb-2 opacity-50"></i>
                                <small>No hay movimientos importados.<br>Cargue un archivo CSV para comenzar.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>
                <div class="card border-0 shadow-sm bg-light h-100 d-flex justify-content-center align-items-center text-center p-5">
                    <div>
                        <div class="bg-white rounded-circle d-inline-flex p-4 shadow-sm mb-3">
                            <i class="bi bi-hand-index text-primary fs-1"></i>
                        </div>
                        <h5 class="fw-bold text-dark">Seleccione una conciliación</h5>
                        <p class="text-muted small mb-0">Haga clic en el botón "Ver" de la tabla de la izquierda para importar archivos y marcar movimientos.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="modalConciliacion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-plus me-2"></i>Aperturar Conciliación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('conciliacion/guardar')); ?>">
                    <input type="hidden" name="id" value="0">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">Cuenta Bancaria <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta_bancaria" required>
                                        <option value="">Seleccione cuenta...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'].' - '.$c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Periodo a conciliar <span class="text-danger">*</span></label>
                                    <input type="month" class="form-control shadow-none" name="periodo" value="<?php echo date('Y-m'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Saldo de Cartola <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" class="form-control shadow-none" name="saldo_estado_cuenta" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small text-muted fw-bold">Observaciones</label>
                                    <textarea class="form-control shadow-none" name="observaciones" rows="2" placeholder="Notas opcionales..."></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Crear Conciliación</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/contabilidad/conciliaciones.js?v=<?php echo time(); ?>"></script>