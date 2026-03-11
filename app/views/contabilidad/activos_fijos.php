<?php
$activos = $activos ?? [];
$cuentas = $cuentas ?? [];
$centrosCosto = $centrosCosto ?? [];
?>
<link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-building-fill-gear me-2 text-primary"></i> Activos Fijos
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de bienes, depreciación acumulada y valor en libros.</p>
        </div>

        <div class="d-flex gap-2">
            <?php if (tiene_permiso('activos.gestionar')): ?>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalActivoFijo" id="btnNuevoActivo">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Activo
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="input-group" style="max-width: 400px;">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-start-0 ps-0" id="searchActivos" placeholder="Buscar activo...">
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro" id="tablaActivosFijos">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Activo</th>
                            <th class="text-end text-secondary fw-semibold">Costo Adquisición</th>
                            <th class="text-end text-secondary fw-semibold">Dep. Acumulada</th>
                            <th class="text-end text-secondary fw-semibold">Valor Libros</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activos)): ?>
                            <?php foreach ($activos as $a): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 fw-semibold text-primary pt-3"><?php echo e($a['codigo_activo']); ?></td>
                                    <td class="pt-3">
                                        <span class="fw-semibold text-dark d-block"><?php echo e($a['nombre']); ?></span>
                                        <?php if(!empty($a['centro_costo_codigo'])): ?>
                                            <span class="badge bg-light text-secondary border mt-1"><i class="bi bi-diagram-3 me-1"></i><?php echo e($a['centro_costo_codigo']); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pt-3"><?php echo number_format((float)$a['costo_adquisicion'], 4); ?></td>
                                    <td class="text-end text-danger opacity-75 pt-3"><?php echo number_format((float)$a['depreciacion_acumulada'], 4); ?></td>
                                    <td class="text-end fw-bold text-success pt-3"><?php echo number_format((float)$a['valor_libros'], 4); ?></td>
                                    <td class="text-center pt-3">
                                        <span class="badge px-3 py-2 rounded-pill bg-light text-dark border">
                                            <?php echo e(ucfirst($a['estado'])); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <button type="button" class="btn btn-sm btn-light text-primary border-0 bg-transparent rounded-circle btn-editar-activo"
                                                data-id="<?php echo (int)$a['id']; ?>"
                                                data-codigo="<?php echo e($a['codigo_activo']); ?>"
                                                data-nombre="<?php echo e($a['nombre']); ?>"
                                                data-fecha="<?php echo e($a['fecha_adquisicion']); ?>"
                                                data-costo="<?php echo (float)$a['costo_adquisicion']; ?>"
                                                data-residual="<?php echo (float)$a['valor_residual']; ?>"
                                                data-depacum="<?php echo (float)$a['depreciacion_acumulada']; ?>" 
                                                data-vida="<?php echo (int)$a['vida_util_meses']; ?>"
                                                data-cta-activo="<?php echo (int)$a['id_cuenta_activo']; ?>"
                                                data-cta-dep="<?php echo (int)$a['id_cuenta_depreciacion']; ?>"
                                                data-cta-gasto="<?php echo (int)$a['id_cuenta_gasto']; ?>" 
                                                data-centro="<?php echo (int)$a['id_centro_costo']; ?>"
                                                data-estado="<?php echo e($a['estado']); ?>"
                                                title="Editar">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
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

<div class="modal fade" id="modalActivoFijo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold" id="tituloModalActivo"><i class="bi bi-building-add me-2"></i>Registrar Activo Fijo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('activos/guardar')); ?>" id="formActivoFijo" autocomplete="off">
                    <input type="hidden" name="id" id="af_id" value="0">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información Principal</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Código <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="codigo_activo" id="af_codigo" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted fw-bold">Nombre / Descripción <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="nombre" id="af_nombre" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Centro de Costo</label>
                                    <select class="form-select shadow-none" name="id_centro_costo" id="af_centro">
                                        <option value="0">Sin centro asignado</option>
                                        <?php foreach ($centrosCosto as $cc): ?>
                                            <option value="<?php echo (int)$cc['id']; ?>"><?php echo e($cc['codigo'] . ' - ' . $cc['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Estado</label>
                                    <select class="form-select shadow-none" name="estado" id="af_estado">
                                        <option value="ACTIVO">ACTIVO</option>
                                        <option value="DEPRECIADO">DEPRECIADO</option>
                                        <option value="INACTIVO">INACTIVO / VENDIDO</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Datos Financieros y Depreciación</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold">Fecha Adquisición <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control shadow-none" name="fecha_adquisicion" id="af_fecha" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold">Costo Adquisición <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" min="0" class="form-control shadow-none" name="costo_adquisicion" id="af_costo" required>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold text-primary">Dep. Acumulada Previa</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" min="0" class="form-control shadow-none border-primary" name="depreciacion_acumulada" id="af_dep_acumulada" value="0">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold">Vida Útil (Meses) <span class="text-danger">*</span></label>
                                    <input type="number" min="1" class="form-control shadow-none" name="vida_util_meses" id="af_vida" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Valor Residual</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" min="0" class="form-control shadow-none" name="valor_residual" id="af_residual" value="0">
                                    </div>
                                </div>
                                
                                <div class="col-md-8">
                                    <label class="form-label small text-muted fw-bold">1. Cuenta de Activo Fijo (Balance) <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta_activo" id="af_cta_activo" required>
                                        <option value="">Buscar cuenta...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">2. Cuenta Dep. Acumulada (Haber) <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta_depreciacion" id="af_cta_dep" required>
                                        <option value="">Buscar cuenta...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">3. Cuenta de Gasto (Debe) <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta_gasto" id="af_cta_gasto" required>
                                        <option value="">Buscar cuenta...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-3 border-top mt-4">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar Activo</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script src="<?php echo e(base_url()); ?>/assets/js/contabilidad/activos_fijos.js"></script>