<?php
$reglas = $reglas ?? [];
$centros = $centros ?? [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<div class="container-fluid p-4" id="appProrrateos"
     data-reglas='<?php echo e(json_encode($reglas, JSON_UNESCAPED_UNICODE)); ?>'
     data-centros='<?php echo e(json_encode($centros, JSON_UNESCAPED_UNICODE)); ?>'
     data-url-guardar="<?php echo e(route_url('contabilidad/guardar_prorrateo')); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-pie-chart-fill me-2 text-primary"></i> Reglas de Prorrateo
            </h1>
            <p class="text-muted small mb-0 ms-1">Define plantillas automáticas para distribuir gastos compartidos entre centros de costo.</p>
        </div>

        <?php if (tiene_permiso('conta.centros_costo.gestionar')): ?>
            <button type="button" class="btn btn-primary shadow-sm" id="btnNuevaRegla" data-bs-toggle="modal" data-bs-target="#modalProrrateo">
                <i class="bi bi-plus-circle-fill me-2"></i>Nueva Regla
            </button>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro" id="tablaProrrateos">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 25%;">Regla</th>
                            <th class="text-secondary fw-semibold" style="width: 20%;">Origen</th>
                            <th class="text-secondary fw-semibold">Distribución</th>
                            <th class="text-center text-secondary fw-semibold" style="width: 10%;">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold" style="width: 10%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($reglas)): ?>
                        <?php foreach ($reglas as $r): ?>
                            <?php $esActivo = ((int)$r['estado'] === 1); ?>
                            <tr class="fila-regla border-bottom">
                                <td class="ps-4 fw-semibold text-dark"><?php echo e((string)$r['nombre']); ?></td>
                                <td class="text-primary fw-semibold"><?php echo e((string)$r['origen_codigo'] . ' - ' . (string)$r['origen_nombre']); ?></td>
                                <td>
                                    <?php if (!empty($r['detalles'])): ?>
                                        <?php foreach ($r['detalles'] as $d): ?>
                                            <span class="badge rounded-pill bg-light text-dark border me-1 mb-1"><?php echo e((string)$d['destino_codigo']); ?>: <?php echo number_format((float)$d['porcentaje'], 2); ?>%</span>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge px-3 py-2 rounded-pill <?php echo $esActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                        <?php echo $esActivo ? 'Activo' : 'Inactivo'; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <button type="button" class="btn btn-sm btn-light text-primary border-0 bg-transparent rounded-circle btn-editar-regla"
                                            data-id="<?php echo (int)$r['id']; ?>"
                                            data-regla='<?php echo e(json_encode($r, JSON_UNESCAPED_UNICODE)); ?>'
                                            title="Editar">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-5">
                                <i class="bi bi-pie-chart fs-1 d-block mb-2 text-light"></i>
                                No hay reglas de prorrateo registradas.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalProrrateo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold" id="tituloModalProrrateo"><i class="bi bi-pie-chart me-2"></i>Nueva Regla de Prorrateo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form id="formProrrateo" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?php echo e($_SESSION['csrf_token'] ?? ''); ?>">
                    <input type="hidden" name="id" id="prorrateo_id" value="0">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label class="form-label small text-muted fw-bold">Nombre de la regla <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control shadow-none" name="nombre" id="prorrateo_nombre" required placeholder="Ej. Distribución Luz Planta Agua y Gaseosa">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small text-muted fw-bold">Centro Origen <span class="text-danger">*</span></label>
                                    <select class="form-select shadow-none" name="centro_origen_id" id="prorrateo_origen" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($centros as $c): ?>
                                            <option value="<?php echo (int)$c['id']; ?>"><?php echo e((string)$c['codigo'] . ' - ' . (string)$c['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold">Estado</label>
                                    <select class="form-select shadow-none" name="estado" id="prorrateo_estado">
                                        <option value="1">Activo</option>
                                        <option value="0">Inactivo</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-2">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Detalle de Distribución</h6>
                                <div class="d-flex gap-2 align-items-center">
                                    <span class="fw-semibold" id="totalProrrateo">Total: 0.00%</span>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarDestino">
                                        <i class="bi bi-plus-circle me-1"></i>Agregar Destino
                                    </button>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0" id="tablaDetalleProrrateo">
                                    <thead>
                                        <tr>
                                            <th>Centro Destino</th>
                                            <th style="width: 160px;">Porcentaje (%)</th>
                                            <th class="text-end" style="width: 70px;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold" id="btnGuardarProrrateo">
                            <i class="bi bi-save me-2"></i>Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/contabilidad/prorrateos.js"></script>
