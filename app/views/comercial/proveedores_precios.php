<?php
$acuerdos = $acuerdos ?? [];
$acuerdoSeleccionado = $acuerdo_seleccionado ?? null;
$preciosMatriz = $precios_matriz ?? [];
?>

<div class="container-fluid p-4" id="acuerdosProveedoresApp"
     data-url-proveedores-disponibles="<?php echo e(route_url('comercial/proveedoresDisponiblesAjax')); ?>"
     data-url-crear-acuerdo="<?php echo e(route_url('comercial/crearListaProveedor')); ?>"
     data-url-obtener-matriz="<?php echo e(route_url('comercial/obtenerMatrizProveedorAjax')); ?>"
     data-url-items-disponibles="<?php echo e(route_url('comercial/itemsProveedorDisponiblesAjax')); ?>"
     data-url-agregar-producto="<?php echo e(route_url('comercial/agregarProductoProveedorAjax')); ?>"
     data-url-actualizar-precio="<?php echo e(route_url('comercial/actualizarPrecioProveedorAjax')); ?>"
     data-url-eliminar-precio="<?php echo e(route_url('comercial/eliminarPrecioProveedorAjax')); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 d-flex align-items-center text-dark">
                <i class="bi bi-truck me-2 text-primary"></i> Acuerdos con Proveedores
            </h1>
            <p class="text-muted small mb-0">Precios recomendados por proveedor para autocompletar compras.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0">Proveedores Vinculados</h6>
                    <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalVincularProveedor">
                        <i class="bi bi-plus-lg me-1"></i> Nuevo
                    </button>
                </div>
                <div class="p-2 border-bottom bg-light">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control border-start-0 ps-0 shadow-none" id="filtroProveedoresAcuerdo" placeholder="Buscar proveedor...">
                    </div>
                </div>
                <div class="list-group list-group-flush overflow-auto" id="acuerdosProveedorSidebarList" style="max-height: 65vh;">
                    <?php if (empty($acuerdos)): ?>
                        <div class="px-3 py-4 text-center text-muted small" id="sidebarProveedorNoResults">No hay proveedores vinculados.</div>
                    <?php else: ?>
                        <?php foreach ($acuerdos as $acuerdo): ?>
                            <?php $isSelected = $acuerdoSeleccionado && (int)$acuerdoSeleccionado['id'] === (int)$acuerdo['id']; ?>
                            <?php $isActive = (int)($acuerdo['estado'] ?? 1) === 1; ?>
                            <button type="button"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-start proveedor-sidebar-item <?php echo $isSelected ? 'active' : ''; ?>"
                                    data-id-acuerdo="<?php echo (int)$acuerdo['id']; ?>"
                                    data-search="<?php echo e(mb_strtolower($acuerdo['proveedor_nombre'])); ?>">
                                <div class="me-2">
                                    <div class="fw-semibold"><?php echo e($acuerdo['proveedor_nombre']); ?></div>
                                    <small class="text-muted"><?php echo (int)($acuerdo['total_productos'] ?? 0); ?> productos</small>
                                </div>
                                <span class="rounded-circle mt-1 flex-shrink-0" style="width:10px;height:10px;background:<?php echo $isActive ? '#22c55e' : '#9ca3af'; ?>;"></span>
                            </button>
                        <?php endforeach; ?>
                        <div class="px-3 py-4 text-center text-muted small d-none" id="sidebarProveedorNoResults">No hay coincidencias.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <?php if ($acuerdoSeleccionado): ?>
                        <div>
                            <h5 class="mb-1 fw-bold text-primary" id="acuerdoProveedorTitulo"><?php echo e($acuerdoSeleccionado['proveedor_nombre']); ?></h5>
                            <small class="text-muted" id="acuerdoProveedorResumen"><?php echo count($preciosMatriz); ?> productos configurados</small>
                        </div>
                        <button class="btn btn-primary btn-sm" id="btnAgregarProductoProveedor" type="button">
                            <i class="bi bi-plus-lg me-1"></i>Agregar Producto
                        </button>
                    <?php else: ?>
                        <p class="text-muted mb-0">Selecciona o vincula un proveedor para configurar sus recomendaciones.</p>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($acuerdoSeleccionado): ?>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-pro" id="tablaMatrizProveedor" data-id-acuerdo="<?php echo (int)$acuerdoSeleccionado['id']; ?>">
                                <thead>
                                    <tr>
                                        <th class="ps-4 col-w-120">Código</th>
                                        <th>Producto</th>
                                        <th class="col-w-220">Precio Recomendado</th>
                                        <th class="text-end pe-4 col-w-90">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="matrizProveedorBodyRows">
                                    <?php if (empty($preciosMatriz)): ?>
                                        <tr id="emptyMatrizProveedorRow">
                                            <td colspan="4" class="text-center text-muted py-5">
                                                <i class="bi bi-exclamation-circle text-warning fs-1 d-block mb-2"></i>
                                                Este proveedor aún no tiene productos recomendados.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($preciosMatriz as $row): ?>
                                            <tr data-id-detalle="<?php echo (int)$row['id']; ?>">
                                                <td class="ps-4"><span class="badge bg-light text-dark border"><?php echo e($row['codigo_presentacion'] ?: 'N/A'); ?></span></td>
                                                <td class="fw-semibold text-dark"><?php echo e($row['producto_nombre']); ?></td>
                                                <td>
                                                    <div class="input-group input-group-sm" style="max-width: 140px;">
                                                        <span class="input-group-text bg-light border-end-0">S/</span>
                                                        <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-proveedor" value="<?php echo number_format((float)$row['precio_recomendado'], 4, '.', ''); ?>" data-original="<?php echo number_format((float)$row['precio_recomendado'], 4, '.', ''); ?>">
                                                    </div>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-precio-proveedor" type="button" title="Eliminar producto">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVincularProveedor" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="bi bi-link-45deg me-2"></i>Vincular Proveedor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVincularProveedor">
                <div class="modal-body">
                    <label class="form-label">Proveedor</label>
                    <select class="form-select" id="selectProveedorVincular" required></select>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar Acuerdo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgregarProductoProveedor" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="bi bi-box-seam me-2"></i>Agregar Producto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAgregarProductoProveedor">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Producto / Ítem</label>
                        <select class="form-select" id="selectProductoProveedor" required></select>
                    </div>
                    <div>
                        <label class="form-label">Precio recomendado</label>
                        <input type="number" min="0.0001" step="0.0001" class="form-control" id="inputPrecioProveedor" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>
