<?php $items = $items ?? []; ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> Productos y Servicios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo maestro de ítems.</p>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#crearProductoCollapse" aria-expanded="false" aria-controls="crearProductoCollapse">
            <i class="bi bi-plus-circle me-2"></i>Nuevo ítem
        </button>
    </div>

    <div class="collapse mb-4" id="crearProductoCollapse">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-plus-circle me-2"></i>Registrar ítem</h6>
            </div>
            <div class="card-body p-4 bg-light">
                <form method="post" class="row g-3" id="formCrearProducto">
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
                            <input type="text" class="form-control" id="newUnidad" name="unidad_base" placeholder="UND" value="UND">
                            <label for="newUnidad">Unidad base</label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-floating">
                            <input type="number" step="0.0001" class="form-control" id="newStockMin" name="stock_minimo" placeholder="0.0000" value="0.0000">
                            <label for="newStockMin">Stock mínimo</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" step="0.0001" class="form-control" id="newPrecio" name="precio_venta" placeholder="0.0000" value="0.0000">
                            <label for="newPrecio">Precio venta</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating">
                            <input type="number" step="0.0001" class="form-control" id="newCosto" name="costo_referencial" placeholder="0.0000" value="0.0000">
                            <label for="newCosto">Costo referencial</label>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <div class="form-floating">
                            <input type="text" class="form-control" id="newDescripcion" name="descripcion" placeholder="Descripción">
                            <label for="newDescripcion">Descripción</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating">
                            <input type="number" class="form-control" id="newCategoria" name="id_categoria" placeholder="Categoría">
                            <label for="newCategoria">ID Categoría</label>
                        </div>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="newControlaStock" name="controla_stock" value="1">
                            <label class="form-check-label" for="newControlaStock">Controla stock</label>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-floating">
                            <select class="form-select" id="newEstado" name="estado">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                            <label for="newEstado">Estado</label>
                        </div>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-check-circle me-2"></i>Guardar ítem
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
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="productoSearch" placeholder="Buscar ítem...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="productoFiltroTipo">
                        <option value="">Todos los tipos</option>
                        <option value="PRODUCTO">Producto</option>
                        <option value="SERVICIO">Servicio</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="productoFiltroEstado">
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
                <table class="table align-middle mb-0 table-pro" id="productosTable">
                    <thead>
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Precio</th>
                            <th>Stock mínimo</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr data-estado="<?php echo (int) $item['estado']; ?>"
                                data-tipo="<?php echo e($item['tipo_item']); ?>"
                                data-search="<?php echo e(mb_strtolower($item['sku'].' '.$item['nombre'].' '.($item['descripcion'] ?? '').' '.($item['marca'] ?? ''))); ?>">
                                <td class="ps-4 fw-semibold" data-label="SKU"><?php echo e($item['sku']); ?></td>
                                <td data-label="Nombre">
                                    <div class="fw-bold text-dark"><?php echo e($item['nombre']); ?></div>
                                    <div class="small text-muted"><?php echo e($item['descripcion'] ?? ''); ?></div>
                                </td>
                                <td data-label="Tipo"><span class="badge bg-light text-dark border"><?php echo e($item['tipo_item']); ?></span></td>
                                <td data-label="Precio"><?php echo e(number_format((float) $item['precio_venta'], 4)); ?></td>
                                <td data-label="Stock mínimo"><?php echo e(number_format((float) $item['stock_minimo'], 4)); ?></td>
                                <td class="text-center" data-label="Estado">
                                    <?php if ((int) $item['estado'] === 1): ?>
                                        <span class="badge-status status-active">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4" data-label="Acciones">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarProducto"
                                            data-id="<?php echo (int) $item['id']; ?>"
                                            data-sku="<?php echo e($item['sku']); ?>"
                                            data-nombre="<?php echo e($item['nombre']); ?>"
                                            data-descripcion="<?php echo e($item['descripcion'] ?? ''); ?>"
                                            data-tipo="<?php echo e($item['tipo_item']); ?>"
                                            data-categoria="<?php echo e((string) ($item['id_categoria'] ?? '')); ?>"
                                            data-marca="<?php echo e($item['marca'] ?? ''); ?>"
                                            data-unidad="<?php echo e($item['unidad_base'] ?? ''); ?>"
                                            data-controla-stock="<?php echo (int) $item['controla_stock']; ?>"
                                            data-stock-minimo="<?php echo e((string) $item['stock_minimo']); ?>"
                                            data-precio="<?php echo e((string) $item['precio_venta']); ?>"
                                            data-costo="<?php echo e((string) $item['costo_referencial']); ?>"
                                            data-estado="<?php echo (int) $item['estado']; ?>"
                                            title="Editar">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <form method="post" class="d-inline m-0">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent" title="Eliminar">
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
                <small class="text-muted" id="productosPaginationInfo">Cargando...</small>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0 justify-content-end" id="productosPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarProducto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">Editar ítem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" id="formEditarProducto" class="row g-3">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editId">
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editSku" name="sku" readonly>
                        <label for="editSku">SKU (inmutable)</label>
                    </div>
                    <div class="col-md-8 form-floating">
                        <input class="form-control" id="editNombre" name="nombre" required>
                        <label for="editNombre">Nombre</label>
                    </div>
                    <div class="col-md-12 form-floating">
                        <input class="form-control" id="editDescripcion" name="descripcion">
                        <label for="editDescripcion">Descripción</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editTipo" name="tipo_item" required>
                            <option value="PRODUCTO">Producto</option>
                            <option value="SERVICIO">Servicio</option>
                        </select>
                        <label for="editTipo">Tipo de ítem</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editCategoria" name="id_categoria" type="number">
                        <label for="editCategoria">ID Categoría</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editMarca" name="marca">
                        <label for="editMarca">Marca</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editUnidad" name="unidad_base">
                        <label for="editUnidad">Unidad base</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editStockMin" name="stock_minimo" type="number" step="0.0001">
                        <label for="editStockMin">Stock mínimo</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editPrecio" name="precio_venta" type="number" step="0.0001">
                        <label for="editPrecio">Precio venta</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <input class="form-control" id="editCosto" name="costo_referencial" type="number" step="0.0001">
                        <label for="editCosto">Costo referencial</label>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" id="editControlaStock" name="controla_stock" value="1">
                            <label class="form-check-label" for="editControlaStock">Controla stock</label>
                        </div>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <label for="editEstado">Estado</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end">
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-save me-2"></i>Actualizar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('modalEditarProducto').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) {
            return;
        }
        document.getElementById('editId').value = button.getAttribute('data-id') || '';
        document.getElementById('editSku').value = button.getAttribute('data-sku') || '';
        document.getElementById('editNombre').value = button.getAttribute('data-nombre') || '';
        document.getElementById('editDescripcion').value = button.getAttribute('data-descripcion') || '';
        document.getElementById('editTipo').value = button.getAttribute('data-tipo') || 'PRODUCTO';
        document.getElementById('editCategoria').value = button.getAttribute('data-categoria') || '';
        document.getElementById('editMarca').value = button.getAttribute('data-marca') || '';
        document.getElementById('editUnidad').value = button.getAttribute('data-unidad') || '';
        document.getElementById('editStockMin').value = button.getAttribute('data-stock-minimo') || '';
        document.getElementById('editPrecio').value = button.getAttribute('data-precio') || '';
        document.getElementById('editCosto').value = button.getAttribute('data-costo') || '';
        document.getElementById('editEstado').value = button.getAttribute('data-estado') || '1';
        document.getElementById('editControlaStock').checked = (button.getAttribute('data-controla-stock') || '0') === '1';
    });
</script>
