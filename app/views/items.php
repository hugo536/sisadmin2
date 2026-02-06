<?php $items = $items ?? []; ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> Ítems y Servicios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo maestro de ítems.</p>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#crearItemCollapse">
            <i class="bi bi-plus-circle me-2"></i>Nuevo ítem
        </button>
    </div>

    <div class="collapse mb-4" id="crearItemCollapse">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Registrar ítem</h6>
            </div>
            <div class="card-body p-4 bg-light">
                <form method="post" class="row g-3" id="formCrearItem">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newSku" name="sku" placeholder="SKU">
                            <label for="newSku">SKU (opcional)</label>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newNombre" name="nombre" placeholder="Nombre" required>
                            <label for="newNombre">Nombre</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating">
                            <select class="form-select" id="newTipo" name="tipo_item" required>
                                <option value="" selected>Seleccionar...</option>
                                <option value="PRODUCTO">Producto</option>
                                <option value="SERVICIO">Servicio</option>
                            </select>
                            <label for="newTipo">Tipo de ítem</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newMarca" name="marca" placeholder="Marca">
                            <label for="newMarca">Marca</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newUnidad" name="unidad_base" value="UND">
                            <label for="newUnidad">Unidad base</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="number" step="0.0001" class="form-control" id="newStockMin" name="stock_minimo" value="0.0000">
                            <label for="newStockMin">Stock mín.</label>
                        </div>
                    </div>
                    <div class="col-md-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-2"></i>Guardar ítem
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="itemSearch" placeholder="Buscar ítem...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="itemFiltroTipo">
                        <option value="">Todos los tipos</option>
                        <option value="PRODUCTO">Producto</option>
                        <option value="SERVICIO">Servicio</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="itemFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="itemsTable">
                    <thead>
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Precio</th>
                            <th>Stock mín.</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr data-estado="<?php echo (int) $item['estado']; ?>"
                                data-tipo="<?php echo e($item['tipo_item']); ?>"
                                data-search="<?php echo e(mb_strtolower($item['sku'].' '.$item['nombre'].' '.($item['descripcion'] ?? '').' '.($item['marca'] ?? ''))); ?>">
                                <td class="ps-4 fw-semibold"><?php echo e($item['sku']); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" style="width:40px; height:40px; border-radius:50%;">
                                            <?php echo strtoupper(substr((string) $item['nombre'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold text-dark"><?php echo e($item['nombre']); ?></div>
                                            <div class="small text-muted"><?php echo e($item['descripcion'] ?? ''); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($item['tipo_item']); ?></span></td>
                                <td><?php echo e(number_format((float) $item['precio_venta'], 2)); ?></td>
                                <td><?php echo e(number_format((float) $item['stock_minimo'], 2)); ?></td>
                                <td class="text-center">
                                    <?php if ((int) $item['estado'] === 1): ?>
                                        <span class="badge-status status-active" id="badge_status_item_<?php echo (int) $item['id']; ?>">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive" id="badge_status_item_<?php echo (int) $item['id']; ?>">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="form-check form-switch pt-1" title="Cambiar estado">
                                            <input class="form-check-input switch-estado-item" type="checkbox" role="switch"
                                                   style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   data-id="<?php echo (int) $item['id']; ?>"
                                                   <?php echo (int) $item['estado'] === 1 ? 'checked' : ''; ?>>
                                        </div>

                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarItem"
                                            data-id="<?php echo (int) $item['id']; ?>"
                                            data-sku="<?php echo e($item['sku']); ?>"
                                            data-nombre="<?php echo e($item['nombre']); ?>"
                                            data-descripcion="<?php echo e($item['descripcion'] ?? ''); ?>"
                                            data-tipo="<?php echo e($item['tipo_item']); ?>"
                                            data-marca="<?php echo e($item['marca'] ?? ''); ?>"
                                            data-precio="<?php echo e((string) $item['precio_venta']); ?>"
                                            data-controla-stock="<?php echo (int) $item['controla_stock']; ?>"
                                            data-estado="<?php echo (int) $item['estado']; ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <form method="post" class="d-inline m-0">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="itemsPaginationInfo">Cargando...</small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end" id="itemsPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>
