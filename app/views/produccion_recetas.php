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
            <p class="text-muted small mb-0 ms-1">Gestión de fórmulas de producción.</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearReceta">
            <i class="bi bi-plus-circle me-2"></i>Nueva receta
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaRecetas" class="table align-middle mb-0 table-pro">
                    <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Versión</th>
                        <th>Ítems BOM</th>
                        <th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($recetas as $receta): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e((string) $receta['codigo']); ?></td>
                            <td><?php echo e((string) $receta['producto_nombre']); ?></td>
                            <td><?php echo (int) $receta['version']; ?></td>
                            <td><?php echo (int) $receta['total_insumos']; ?></td>
                            <td>
                                <?php if ((int) ($receta['estado'] ?? 0) === 1): ?>
                                    <span class="badge bg-success-subtle text-success">Activa</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Inactiva</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearReceta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-sm">
            <form method="post">
                <input type="hidden" name="accion" value="crear_receta">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva receta</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label">Código</label><input required name="codigo" class="form-control"></div>
                        <div class="col-md-2"><label class="form-label">Versión</label><input type="number" min="1" name="version" value="1" class="form-control"></div>
                        <div class="col-md-6"><label class="form-label">Producto destino</label>
                            <select required name="id_producto" class="form-select">
                                <option value="">Seleccione</option>
                                <?php foreach ($itemsStockeables as $item): ?>
                                    <option value="<?php echo (int) $item['id']; ?>"><?php echo e((string) $item['nombre']); ?> (<?php echo e((string) $item['tipo_item']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12"><label class="form-label">Descripción</label><input name="descripcion" class="form-control"></div>
                    </div>

                    <hr>
                    <div id="detalleRecetaWrapper">
                        <div class="row g-2 mb-2 detalle-row">
                            <div class="col-md-6">
                                <label class="form-label">Insumo/Semielaborado</label>
                                <select class="form-select" name="detalle_id_insumo[]" required>
                                    <option value="">Seleccione</option>
                                    <?php foreach ($itemsStockeables as $item): ?>
                                        <option value="<?php echo (int) $item['id']; ?>"><?php echo e((string) $item['nombre']); ?> (<?php echo e((string) $item['tipo_item']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3"><label class="form-label">Cantidad por unidad</label><input step="0.0001" min="0.0001" type="number" required name="detalle_cantidad[]" class="form-control"></div>
                            <div class="col-md-3"><label class="form-label">Merma %</label><input step="0.01" min="0" type="number" name="detalle_merma[]" value="0" class="form-control"></div>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarDetalleReceta">+ Agregar línea</button>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Guardar receta</button>
                </div>
            </form>
        </div>
    </div>
</div>
