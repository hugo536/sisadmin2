<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1">Inventario - Stock Actual</h4>
        <p class="text-muted mb-0">Control de existencias por ítem y almacén.</p>
    </div>
    <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMovimientoInventario">
            <i class="fas fa-edit me-2"></i> Nuevo Movimiento
        </button>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0" id="tablaInventarioStock">
                <thead>
                    <tr>
                        <th>SKU</th>
                        <th>Producto</th>
                        <th>Almacén</th>
                        <th class="text-end">Stock Actual</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!empty($stockActual)): ?>
                    <?php foreach ($stockActual as $stock): ?>
                        <?php $stockActualItem = (float) ($stock['stock_actual'] ?? 0); ?>
                        <tr>
                            <td><?php echo e((string) ($stock['sku'] ?? '')); ?></td>
                            <td><?php echo e((string) ($stock['item_nombre'] ?? '')); ?></td>
                            <td><?php echo e((string) ($stock['almacen_nombre'] ?? '')); ?></td>
                            <td class="text-end fw-semibold <?php echo $stockActualItem <= 0 ? 'text-danger' : 'text-success'; ?>">
                                <?php echo number_format($stockActualItem, 4, '.', ','); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-4">No hay registros de stock disponibles.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMovimientoInventario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Registrar movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formMovimientoInventario" class="row g-3">
                    <div class="col-md-6">
                        <label for="tipoMovimiento" class="form-label">Tipo</label>
                        <select id="tipoMovimiento" name="tipo_movimiento" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <option value="INI">INI - Inicial</option>
                            <option value="AJ+">AJ+ - Ajuste positivo</option>
                            <option value="AJ-">AJ- - Ajuste negativo</option>
                            <option value="TRF">TRF - Transferencia</option>
                            <option value="CON">CON - Consumo</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="almacenMovimiento" class="form-label">Almacén</label>
                        <select id="almacenMovimiento" name="id_almacen" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 d-none" id="grupoAlmacenDestino">
                        <label for="almacenDestinoMovimiento" class="form-label">Almacén destino</label>
                        <select id="almacenDestinoMovimiento" name="id_almacen_destino" class="form-select">
                            <option value="">Seleccione...</option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="itemMovimiento" class="form-label">Ítem (SKU / Nombre)</label>
                        <input type="text" id="itemMovimiento" class="form-control" list="listaItemsInventario" placeholder="Buscar por SKU o nombre" required>
                        <input type="hidden" id="idItemMovimiento" name="id_item" required>
                        <datalist id="listaItemsInventario">
                            <?php foreach ($items as $item): ?>
                                <option
                                    data-id="<?php echo (int) ($item['id'] ?? 0); ?>"
                                    value="<?php echo e((string) ($item['sku'] ?? '')); ?> - <?php echo e((string) ($item['nombre'] ?? '')); ?>"
                                ></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="col-md-6">
                        <label for="cantidadMovimiento" class="form-label">Cantidad</label>
                        <input type="number" step="0.0001" min="0.0001" class="form-control" id="cantidadMovimiento" name="cantidad" required>
                    </div>
                    <div class="col-12">
                        <label for="referenciaMovimiento" class="form-label">Referencia</label>
                        <input type="text" class="form-control" id="referenciaMovimiento" name="referencia" maxlength="255" placeholder="N° documento / comentario">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 pt-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar movimiento</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/inventario.js')); ?>"></script>
