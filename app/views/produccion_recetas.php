<?php
$recetas = $recetas ?? [];
$itemsStockeables = $items_stockeables ?? [];
$etapasProduccion = [
    'Tratamiento Agua',
    'Jarabe',
    'Mezclado',
    'Pasteurización',
    'Carbonatación',
    'Envasado',
];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-check me-2 text-primary"></i> Recetas (BOM)
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo de fórmulas de producción.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearReceta">
                <i class="bi bi-plus-circle me-2"></i>Nueva receta
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="recetaSearch" placeholder="Buscar por código, producto...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="recetaFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                        <option value="2">Sin receta</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaRecetas" class="table align-middle mb-0 table-pro">
                    <thead>
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Producto Terminado</th>
                            <th>Versión</th>
                            <th>N° Insumos</th>
                            <th>Costo Teórico</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recetas as $receta): ?>
                            <tr data-search="<?php echo mb_strtolower($receta['codigo'] . ' ' . $receta['producto_nombre']); ?>"
                                data-estado="<?php echo (int) (($receta['sin_receta'] ?? 0) === 1 ? 2 : ($receta['estado'] ?? 0)); ?>">
                                <td class="ps-4 fw-semibold text-primary"><?php echo e((string) $receta['codigo']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e((string) $receta['producto_nombre']); ?></div>
                                    <div class="small text-muted"><?php echo e((string) ($receta['descripcion'] ?? '')); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border">v.<?php echo (int) $receta['version']; ?></span></td>
                                <td><?php echo (int) $receta['total_insumos']; ?> ítems</td>
                                <td>S/ <?php echo number_format((float) ($receta['costo_teorico'] ?? 0), 4); ?></td>
                                <td class="text-center">
                                    <?php if ((int) ($receta['sin_receta'] ?? 0) === 1): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Sin receta</span>
                                    <?php elseif ((int) ($receta['estado'] ?? 0) === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 rounded-pill">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ((int) ($receta['sin_receta'] ?? 0) === 1): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-warning js-agregar-receta"
                                                data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                data-producto="<?php echo e((string) ($receta['producto_nombre'] ?? '')); ?>"
                                                data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                data-version="<?php echo (int) ($receta['version'] ?? 1); ?>"
                                                title="Agregar receta inicial">
                                            <i class="bi bi-journal-plus me-1"></i>Agregar receta
                                        </button>
                                    <?php else: ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="accion" value="nueva_version">
                                            <input type="hidden" name="id_receta_base" value="<?php echo (int) $receta['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-primary" title="Crear nueva versión">
                                                <i class="bi bi-files me-1"></i>Nueva Versión
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3">
                <small class="text-muted">Mostrando <?php echo count($recetas); ?> recetas</small>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearReceta" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nueva receta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form method="post" id="formCrearReceta">
                    <input type="hidden" name="accion" value="crear_receta">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-3">Información General</h6>
                            <div class="row g-2">
                                <div class="col-md-3 form-floating">
                                    <input type="text" class="form-control" id="newCodigo" name="codigo" placeholder="Código" required>
                                    <label for="newCodigo">Código</label>
                                </div>
                                <div class="col-md-2 form-floating">
                                    <input type="number" class="form-control" id="newVersion" name="version" value="1" min="1">
                                    <label for="newVersion">Versión</label>
                                </div>
                                <div class="col-md-4 form-floating">
                                    <select class="form-select" id="newProducto" name="id_producto" required>
                                        <option value="" selected>Seleccionar...</option>
                                        <?php foreach ($itemsStockeables as $item): ?>
                                            <option value="<?php echo (int) $item['id']; ?>"><?php echo e((string) $item['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="newProducto">Producto Destino</label>
                                </div>
                                <div class="col-md-3 form-floating">
                                    <input type="number" step="0.0001" min="0" class="form-control" id="newRendimientoBase" name="rendimiento_base" placeholder="Rendimiento base">
                                    <label for="newRendimientoBase">Rendimiento base</label>
                                </div>
                                <div class="col-md-3 form-floating">
                                    <input type="text" class="form-control" id="newUnidadRendimiento" name="unidad_rendimiento" placeholder="Unidad">
                                    <label for="newUnidadRendimiento">Unidad rendimiento</label>
                                </div>
                                <div class="col-md-9 form-floating">
                                    <input type="text" class="form-control" id="newDescripcion" name="descripcion" placeholder="Descripción">
                                    <label for="newDescripcion">Descripción / Observaciones</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-3">Parámetros Finales</h6>
                            <div class="row g-2">
                                <div class="col-md-2 form-floating"><input type="number" step="0.0001" min="0" class="form-control" name="brix_objetivo" placeholder="Brix"><label>Brix objetivo</label></div>
                                <div class="col-md-2 form-floating"><input type="number" step="0.0001" min="0" class="form-control" name="ph_objetivo" placeholder="pH"><label>pH objetivo</label></div>
                                <div class="col-md-2 form-floating"><input type="number" step="0.0001" min="0" class="form-control" name="carbonatacion_vol" placeholder="CO2"><label>Carbonatación (vol)</label></div>
                                <div class="col-md-3 form-floating"><input type="number" step="0.0001" min="0" class="form-control" name="temp_pasteurizacion" placeholder="Temperatura"><label>Temp. pasteurización</label></div>
                                <div class="col-md-3 form-floating"><input type="number" step="0.0001" min="0" class="form-control" name="tiempo_pasteurizacion" placeholder="Tiempo"><label>Tiempo pasteurización</label></div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-muted mb-0">Etapas</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarDetalleReceta">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar línea
                                </button>
                            </div>
                            <div class="accordion" id="accordionEtapasReceta">
                                <?php foreach ($etapasProduccion as $index => $etapa): ?>
                                    <div class="accordion-item">
                                        <h2 class="accordion-header" id="heading-<?php echo $index; ?>">
                                            <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $index; ?>">
                                                <?php echo e($etapa); ?>
                                            </button>
                                        </h2>
                                        <div id="collapse-<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index === 0 ? 'show' : ''; ?>" data-bs-parent="#accordionEtapasReceta">
                                            <div class="accordion-body">
                                                <div class="d-flex justify-content-end mb-2">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-add-etapa="<?php echo e($etapa); ?>">
                                                        <i class="bi bi-plus-lg me-1"></i>Agregar insumo
                                                    </button>
                                                </div>
                                                <div data-etapa-container="<?php echo e($etapa); ?>"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-2">BOM resumen</h6>
                            <div id="bomResumen" class="small text-muted">0 líneas cargadas.</div>
                        </div>
                    </div>

                    <template id="detalleRecetaTemplate">
                        <div class="row g-2 mb-2 detalle-row align-items-center bg-white p-2 border rounded-2" data-etapa="">
                            <input type="hidden" name="detalle_etapa[]" value="">
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-0">Insumo</label>
                                <select class="form-select form-select-sm" name="detalle_id_insumo[]" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($itemsStockeables as $item): ?>
                                        <option value="<?php echo (int) $item['id']; ?>"><?php echo e((string) $item['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-0">Cantidad Base</label>
                                <input step="0.0001" min="0.0001" type="number" required name="detalle_cantidad[]" class="form-control form-control-sm" placeholder="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-0">Merma %</label>
                                <input step="0.01" min="0" type="number" name="detalle_merma[]" value="0" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm text-danger border-0 bg-transparent mt-3 js-remove-row" title="Quitar"><i class="bi bi-trash"></i></button>
                            </div>
                        </div>
                    </template>

                    <div class="d-flex justify-content-end pt-3">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Guardar Receta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>/assets/js/produccion.js?v=1.1"></script>
