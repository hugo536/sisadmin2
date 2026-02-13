<?php
$recetas = $recetas ?? [];
$itemsStockeables = $items_stockeables ?? [];
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
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recetas as $receta): ?>
                            <tr data-search="<?php echo mb_strtolower($receta['codigo'] . ' ' . $receta['producto_nombre']); ?>" 
                                data-estado="<?php echo (int) ($receta['estado'] ?? 0); ?>">
                                
                                <td class="ps-4 fw-semibold text-primary"><?php echo e((string) $receta['codigo']); ?></td>
                                
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e((string) $receta['producto_nombre']); ?></div>
                                    <div class="small text-muted"><?php echo e((string) ($receta['descripcion'] ?? '')); ?></div>
                                </td>
                                
                                <td><span class="badge bg-light text-dark border">v.<?php echo (int) $receta['version']; ?></span></td>
                                
                                <td><?php echo (int) $receta['total_insumos']; ?> ítems</td>
                                
                                <td class="text-center">
                                    <?php if ((int) ($receta['estado'] ?? 0) === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 rounded-pill">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Inactiva</span>
                                    <?php endif; ?>
                                </td>

                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-light text-info border-0 bg-transparent" title="Ver detalles">
                                        <i class="bi bi-eye fs-5"></i>
                                    </button>
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

<div class="modal fade" id="modalCrearReceta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
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
                                <div class="col-md-4 form-floating">
                                    <input type="text" class="form-control" id="newCodigo" name="codigo" placeholder="Código" required>
                                    <label for="newCodigo">Código</label>
                                </div>
                                <div class="col-md-2 form-floating">
                                    <input type="number" class="form-control" id="newVersion" name="version" value="1" min="1">
                                    <label for="newVersion">Versión</label>
                                </div>
                                <div class="col-md-6 form-floating">
                                    <select class="form-select" id="newProducto" name="id_producto" required>
                                        <option value="" selected>Seleccionar...</option>
                                        <?php foreach ($itemsStockeables as $item): ?>
                                            <option value="<?php echo (int) $item['id']; ?>">
                                                <?php echo e((string) $item['nombre']); ?> (<?php echo e((string) $item['tipo_item']); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="newProducto">Producto Destino</label>
                                </div>
                                <div class="col-12 form-floating mt-2">
                                    <input type="text" class="form-control" id="newDescripcion" name="descripcion" placeholder="Descripción">
                                    <label for="newDescripcion">Descripción / Observaciones</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-muted mb-0">Detalle de Insumos (BOM)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarDetalleReceta">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar línea
                                </button>
                            </div>
                            
                            <div id="detalleRecetaWrapper">
                                <div class="row g-2 mb-2 detalle-row align-items-center bg-white p-2 border rounded-2">
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
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-3">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Guardar Receta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>/assets/js/produccion.js?v=1.0"></script>