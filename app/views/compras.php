<?php
$ordenes = $ordenes ?? [];
$filtros = $filtros ?? [];
$proveedores = $proveedores ?? [];
$items = $items ?? [];
$almacenes = $almacenes ?? [];

// Configuración de Estados (Estilo Subtle)
$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    1 => ['texto' => 'Pendiente', 'clase' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
    2 => ['texto' => 'Aprobada', 'clase' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    3 => ['texto' => 'Recepcionada', 'clase' => 'bg-success-subtle text-success border border-success-subtle'],
    9 => ['texto' => 'Anulada', 'clase' => 'bg-danger-subtle text-danger border border-danger-subtle'],
];
?>

<div class="container-fluid p-4" id="comprasApp"
     data-url-index="<?php echo e(route_url('compras/index')); ?>"
     data-url-guardar="<?php echo e(route_url('compras/guardar')); ?>"
     data-url-aprobar="<?php echo e(route_url('compras/aprobar')); ?>"
     data-url-anular="<?php echo e(route_url('compras/anular')); ?>"
     data-url-recepcionar="<?php echo e(route_url('compras/recepcionar')); ?>"
     data-url-unidades-item="<?php echo e(route_url('compras/index')); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart-check-fill me-2 text-primary"></i> Compras y Recepción
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de órdenes de compra y abastecimiento.</p>
        </div>
        <button type="button" class="btn btn-primary shadow-sm fw-semibold" id="btnNuevaOrden">
            <i class="bi bi-plus-circle-fill me-2"></i>Nueva Orden
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroBusqueda" placeholder="Buscar código, proveedor..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="filtroEstado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadoLabels as $key => $info): ?>
                            <option value="<?php echo (int) $key; ?>" <?php echo ($filtros['estado'] ?? '') === (string) $key ? 'selected' : ''; ?>>
                                <?php echo e($info['texto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input type="date" class="form-control bg-light" id="filtroFechaDesde" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>" title="Fecha Desde">
                </div>
                <div class="col-6 col-md-3">
                    <input type="date" class="form-control bg-light" id="filtroFechaHasta" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>" title="Fecha Hasta">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaCompras"
                       data-erp-table="true"
                       data-search-input="#filtroBusqueda"
                       data-pagination-controls="#comprasPaginationControls"
                       data-pagination-info="#comprasPaginationInfo"
                       data-erp-filters='[{"el":"#filtroEstado","attr":"data-estado","match":"equals"}]'>
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Proveedor</th>
                            <th class="text-secondary fw-semibold">Fecha Emisión</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ordenes)): ?>
                            <?php foreach ($ordenes as $orden): ?>
                                <?php 
                                    $estado = (int) ($orden['estado'] ?? 0); 
                                    $badge = $estadoLabels[$estado] ?? $estadoLabels[0]; 
                                ?>
                                <tr data-id="<?php echo (int) ($orden['id'] ?? 0); ?>" data-estado="<?php echo $estado; ?>" class="border-bottom" data-search="<?php echo e(mb_strtolower((string) ($orden['codigo'] ?? '') . ' ' . (string) ($orden['proveedor'] ?? ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary"><?php echo e((string) ($orden['codigo'] ?? '')); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e((string) ($orden['proveedor'] ?? '')); ?></td>
                                    <td><?php echo e((string) ($orden['fecha_orden'] ?? '')); ?></td>
                                    <td class="text-end fw-bold">S/ <?php echo number_format((float) ($orden['total'] ?? 0), 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo e($badge['clase']); ?>">
                                            <?php echo e($badge['texto']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($estado === 0): ?> <button class="btn btn-sm btn-light text-primary border-0 btn-editar rounded-circle" data-bs-toggle="tooltip" title="Editar Orden"><i class="bi bi-pencil-square fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-success border-0 btn-aprobar rounded-circle" data-bs-toggle="tooltip" title="Aprobar Orden"><i class="bi bi-check2-circle fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-bs-toggle="tooltip" title="Anular Orden"><i class="bi bi-trash fs-5"></i></button>
                                            <?php elseif ($estado === 2): ?> <button class="btn btn-sm btn-light text-info border-0 btn-recepcionar rounded-circle" data-bs-toggle="tooltip" title="Recepcionar Mercadería"><i class="bi bi-box-seam fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php else: ?> <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-msg-row"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay órdenes registradas.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="comprasPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación de compras">
                    <ul class="pagination mb-0 justify-content-end" id="comprasPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOrdenCompra" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff me-2"></i>Orden de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form id="formOrdenCompra" autocomplete="off">
                    <input type="hidden" id="ordenId" value="0">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información General</h6>
                            <div class="row g-3 align-items-end">
                                
                                <div class="col-md-5">
                                    <label for="idProveedor" class="form-label text-muted small fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                                    <select id="idProveedor" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($proveedores as $proveedor): ?>
                                            <option value="<?php echo (int) ($proveedor['id'] ?? 0); ?>">
                                                <?php echo e((string) ($proveedor['nombre_completo'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="fechaEntrega" class="form-label text-muted small fw-bold mb-1">Fecha Entrega Est. <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" id="fechaEntrega" required>
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="observaciones" class="form-label text-muted small fw-bold mb-1">Observaciones</label>
                                    <input type="text" class="form-control" id="observaciones" maxlength="180" placeholder="Opcional">
                                </div>
                                
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-white rounded-top">
                                <h6 class="mb-0 fw-bold text-dark">Detalle de Productos</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnAgregarFila">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar Ítem
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 table-pro" id="tablaDetalleCompra">
                                    <thead>
                                        <tr>
                                            <th class="ps-3 text-secondary col-min-w-320">Ítem / Producto</th>
                                            <th class="text-secondary text-center col-w-140">Cantidad</th>
                                            <th class="text-secondary text-center col-w-160">Costo Unit.</th>
                                            <th class="text-end text-secondary col-w-150">Subtotal</th>
                                            <th class="text-center col-w-60"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="3" class="text-end fw-bold py-3 text-secondary align-middle">TOTAL ORDEN:</td>
                                            <td class="text-end fw-bold py-3 fs-4 text-primary align-middle" id="ordenTotal">S/ 0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2 ms-2"><i class="bi bi-info-circle me-1 text-primary"></i> Los costos ingresados deben incluir impuestos.</div>
                </form>
            </div>
            <div class="modal-footer bg-white border-top-0">
                <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary px-4 fw-bold" id="btnGuardarOrden"><i class="bi bi-save me-2"></i>Guardar Orden</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecepcionCompra" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Recepcionar Mercadería</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="alert alert-info d-flex align-items-center mb-4 shadow-sm border-0 rounded-3">
                    <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                    <div>Esta acción ingresará el stock físico al inventario y cerrará la orden de compra.</div>
                </div>
                
                <input type="hidden" id="recepcionOrdenId" value="0">
                
                <div class="form-floating mb-2 shadow-sm rounded">
                    <select id="recepcionAlmacen" class="form-select border-0" required>
                        <option value="">Seleccione el almacén...</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <label for="recepcionAlmacen" class="fw-semibold text-muted">Almacén de Ingreso (Origen en Kardex) <span class="text-danger">*</span></label>
                </div>
            </div>
            <div class="modal-footer bg-white border-top-0">
                <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-info text-white fw-bold px-4" id="btnConfirmarRecepcion">
                    <i class="bi bi-check-lg me-2"></i>Confirmar Ingreso
                </button>
            </div>
        </div>
    </div>
</div>

<template id="templateFilaDetalle">
    <tr class="border-bottom">
        <td class="ps-3 py-3 align-top">
            <div class="mb-2">
                <select class="form-select form-select-sm detalle-item shadow-none border-secondary-subtle" required>
                    <option value="">Buscar ítem o producto...</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?php echo (int) ($item['id'] ?? 0); ?>"
                                data-unidad-base="<?php echo e((string) ($item['unidad_base'] ?? 'UND')); ?>"
                                data-requiere-factor-conversion="<?php echo (int) ($item['requiere_factor_conversion'] ?? 0); ?>"
                                data-costo-referencial="<?php echo (float) ($item['costo_referencial'] ?? 0); ?>">
                            <?php echo htmlspecialchars((string) ($item['sku'] ?? '') . ' - ' . (string) ($item['nombre'] ?? '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="d-flex flex-column gap-1 bg-light p-2 rounded border">
                <select class="form-select form-select-sm detalle-unidad-compra d-none border-primary-subtle shadow-none" disabled>
                    <option value="">Unidad de compra...</option>
                </select>
                <div class="detalle-conversion-info text-end"></div> 
            </div>
        </td>
        <td class="align-top py-3 px-2">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-primary shadow-none border-secondary-subtle" min="0.01" step="0.01" value="1" required>
        </td>
        <td class="align-top py-3 px-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted bg-light border-secondary-subtle">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-costo shadow-none border-secondary-subtle" min="0" step="0.01" value="0.00" required>
            </div>
        </td>
        <td class="text-end align-top py-3 fw-bold text-dark detalle-subtotal fs-6">S/ 0.00</td>
        <td class="text-center align-top py-3">
            <button class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-fila p-1" type="button" data-bs-toggle="tooltip" title="Quitar fila" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    </tr>
</template>
