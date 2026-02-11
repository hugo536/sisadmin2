<?php
$stockActual = $stockActual ?? [];
$almacenes = $almacenes ?? [];
$items = $items ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary fs-5"></i>
                <span>Inventario - Stock Actual</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de existencias por ítem y almacén.</p>
        </div>

        <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
            <button type="button" class="btn btn-primary shadow-sm flex-shrink-0" data-bs-toggle="modal" data-bs-target="#modalMovimientoInventario">
                <i class="bi bi-plus-circle-fill me-0 me-sm-2"></i>
                <span class="d-none d-sm-inline">Nuevo Movimiento</span>
            </button>
        <?php endif; ?>
    </div>

    <div class="card border-0 shadow-sm mb-3 fade-in">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="inventarioSearch" placeholder="Buscar por SKU o producto...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="inventarioFiltroAlmacen">
                        <option value="">Todos los almacenes</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo e((string) ($almacen['nombre'] ?? '')); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="inventarioFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="disponible">Con stock</option>
                        <option value="agotado">Sin stock</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm fade-in">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaInventarioStock">
                    <thead>
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Producto</th>
                            <th>Almacén</th>
                            <th class="text-end pe-4">Stock Actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stockActual)): ?>
                            <?php foreach ($stockActual as $stock): ?>
                                <?php
                                $stockActualItem = (float) ($stock['stock_actual'] ?? 0);
                                $sku = (string) ($stock['sku'] ?? '');
                                $itemNombre = (string) ($stock['item_nombre'] ?? '');
                                $almacenNombre = (string) ($stock['almacen_nombre'] ?? '');
                                $estadoStock = $stockActualItem <= 0 ? 'agotado' : 'disponible';
                                ?>
                                <tr
                                    data-search="<?php echo e(mb_strtolower(trim($sku . ' ' . $itemNombre . ' ' . $almacenNombre))); ?>"
                                    data-almacen="<?php echo e($almacenNombre); ?>"
                                    data-estado="<?php echo e($estadoStock); ?>"
                                >
                                    <td class="ps-4 fw-semibold"><?php echo e($sku); ?></td>
                                    <td><?php echo e($itemNombre); ?></td>
                                    <td><?php echo e($almacenNombre); ?></td>
                                    <td class="text-end pe-4 fw-semibold <?php echo $stockActualItem <= 0 ? 'text-danger' : 'text-success'; ?>">
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
