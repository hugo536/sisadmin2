<?php
$activos = $activos ?? [];
$cuentas = $cuentas ?? [];
?>
<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-building-fill-gear me-2 text-primary"></i> Activos Fijos
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de bienes, depreciación acumulada y valor en libros.</p>
        </div>

        <div class="d-flex gap-2">
            <?php if (tiene_permiso('activos.gestionar')): // Asumiendo que usas tu control de permisos ?>
                <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalActivoFijo">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Activo
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="searchActivos" placeholder="Buscar activo por código o nombre...">
                    </div>
                </div>
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
                            <th class="text-end text-secondary fw-semibold">Valor en Libros</th>
                            <th class="text-center pe-4 text-secondary fw-semibold">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($activos)): ?>
                            <?php foreach ($activos as $a): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4 fw-semibold text-primary pt-3"><?php echo e($a['codigo_activo']); ?></td>
                                    <td class="fw-semibold text-dark pt-3"><?php echo e($a['nombre']); ?></td>
                                    <td class="text-end pt-3"><?php echo number_format((float)$a['costo_adquisicion'], 4); ?></td>
                                    <td class="text-end text-danger opacity-75 pt-3"><?php echo number_format((float)$a['depreciacion_acumulada'], 4); ?></td>
                                    <td class="text-end fw-bold text-success pt-3"><?php echo number_format((float)$a['valor_libros'], 4); ?></td>
                                    <td class="text-center pe-4 pt-3">
                                        <?php 
                                        $estadoLimpio = strtolower(trim($a['estado']));
                                        $esActivo = ($estadoLimpio === 'activo');
                                        ?>
                                        <span class="badge px-3 py-2 rounded-pill <?php echo $esActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                            <?php echo e(ucfirst($a['estado'])); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No hay registros de activos fijos.
                                </td>
                            </tr>
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
                <h5 class="modal-title fw-bold"><i class="bi bi-building-add me-2"></i>Registrar Activo Fijo</h5>
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
                                    <label class="form-label small text-muted fw-bold">Código del Activo <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="codigo_activo" id="af_codigo" placeholder="Ej. MAQ-001" required>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label small text-muted fw-bold">Nombre / Descripción <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="nombre" id="af_nombre" placeholder="Ej. Computadora HP ProBook" required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Datos Financieros y Depreciación</h6>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Fecha de Adquisición <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control shadow-none" name="fecha_adquisicion" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Costo Adquisición <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" min="0" class="form-control shadow-none" name="costo_adquisicion" placeholder="0.00" required>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Valor Residual</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">$</span>
                                        <input type="number" step="0.0001" min="0" class="form-control shadow-none" name="valor_residual" placeholder="0.00" value="0">
                                    </div>
                                </div>
                                
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Vida Útil (Meses) <span class="text-danger">*</span></label>
                                    <input type="number" min="1" class="form-control shadow-none" name="vida_util_meses" placeholder="Ej. 60" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Cuenta de Activo <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta_activo" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($cuentas as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Cuenta Depreciación <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="id_cuenta_depreciacion" required>
                                        <option value="">Seleccione...</option>
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