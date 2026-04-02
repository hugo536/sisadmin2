<?php
$periodos = $periodos ?? [];
?>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar2-check-fill me-2 text-primary"></i> Cierres Contables
            </h1>
            <p class="text-muted small mb-0 ms-1">Ejecución de depreciaciones, y bloqueo de periodos mensuales y anuales.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            
            <div class="card border-0 shadow-sm mb-4 border-top border-primary border-3">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="bi bi-graph-down-arrow text-primary me-2"></i>Depreciación Automática
                    </h6>
                    <p class="text-muted small mt-1 mb-0">Calcula y registra la depreciación de activos fijos del mes.</p>
                </div>
                <div class="card-body mt-2">
                    <form method="post" action="<?php echo e(route_url('cierre_contable/ejecutar_depreciacion')); ?>" class="d-flex flex-column gap-3 btn-submit-loader">
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Periodo a depreciar</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-calendar-event text-muted"></i></span>
                                <input type="month" class="form-control shadow-none border-start-0 ps-0" name="periodo" value="<?php echo date('Y-m'); ?>" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm w-100 btn-confirmar-depreciacion">
                            <i class="bi bi-gear-fill me-2"></i>Ejecutar Depreciación
                        </button>
                    </form>
                </div>
            </div>

            <div class="card border-0 shadow-sm border-top border-danger border-3 bg-danger-subtle bg-opacity-10">
                <div class="card-header bg-transparent border-bottom-0 pt-4 pb-0">
                    <h6 class="fw-bold text-danger mb-0">
                        <i class="bi bi-shield-lock-fill me-2"></i>Cierre Anual (Definitivo)
                    </h6>
                    <p class="text-muted small mt-1 mb-0">Bloquea todas las operaciones del año seleccionado. Esta acción es irreversible.</p>
                </div>
                <div class="card-body mt-2">
                    <form method="post" action="<?php echo e(route_url('cierre_contable/cierre_anual')); ?>" class="d-flex flex-column gap-3">
                        <div>
                            <label class="form-label small fw-bold text-muted mb-1">Año a cerrar</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-calendar-x text-danger"></i></span>
                                <input type="number" class="form-control shadow-none border-start-0 ps-0 text-danger fw-bold" name="anio" value="<?php echo date('Y'); ?>" min="2000" max="2100" required>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-danger fw-bold shadow-sm w-100 btn-confirmar-anual">
                            <i class="bi bi-lock-fill me-2"></i>Ejecutar Cierre Anual
                        </button>
                    </form>
                </div>
            </div>

        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-bold text-dark mb-0"><i class="bi bi-calendar-month text-info me-2"></i>Gestión de Periodos Mensuales</h6>
                        <p class="text-muted small mt-1 mb-0">Cierra los periodos para evitar modificaciones en la contabilidad.</p>
                    </div>
                </div>
                
                <div class="card-body p-0 mt-3">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-hover table-pro">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="ps-4 text-secondary fw-semibold" style="width: 25%;">Periodo (Año-Mes)</th>
                                    <th class="text-center text-secondary fw-semibold" style="width: 30%;">Estado</th>
                                    <th class="text-end pe-4 text-secondary fw-semibold">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($periodos)): ?>
                                    <?php foreach ($periodos as $p): ?>
                                        <?php 
                                            $estado = strtoupper(trim($p['estado']));
                                            $esAbierto = ($estado === 'ABIERTO');
                                        ?>
                                        <tr class="border-bottom">
                                            <td class="ps-4 fw-bold text-dark pt-3">
                                                <i class="bi bi-calendar4-week me-2 text-muted"></i>
                                                <?php echo e($p['anio'] . '-' . str_pad((string)$p['mes'], 2, '0', STR_PAD_LEFT)); ?>
                                            </td>
                                            <td class="text-center pt-3">
                                                <span class="badge px-3 py-2 rounded-pill <?php echo $esAbierto ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                                    <?php echo $esAbierto ? '<i class="bi bi-unlock-fill me-1"></i> Abierto' : '<i class="bi bi-lock-fill me-1"></i> Cerrado'; ?>
                                                </span>
                                            </td>
                                            <td class="text-end pe-4 pt-3">
                                                <?php if ($esAbierto): ?>
                                                    <form method="post" action="<?php echo e(route_url('cierre_contable/cierre_mensual')); ?>" class="d-inline">
                                                        <input type="hidden" name="id_periodo" value="<?php echo (int)$p['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill fw-semibold px-3 btn-confirmar-mensual">
                                                            Cerrar Mes
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted small fst-italic"><i class="bi bi-check2-all me-1"></i>Completado</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-5">
                                            <i class="bi bi-calendar-x fs-1 d-block mb-2 text-light"></i>
                                            No hay periodos registrados en el sistema.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script src="<?php echo e(asset_url('js/cierres.js')); ?>"></script>