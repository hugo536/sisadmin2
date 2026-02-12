<?php
$ordenes = $ordenes ?? [];
$filtros = $filtros ?? [];
$proveedores = $proveedores ?? [];
$items = $items ?? [];
$almacenes = $almacenes ?? [];

$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary'],
    1 => ['texto' => 'Pendiente', 'clase' => 'bg-warning text-dark'],
    2 => ['texto' => 'Aprobada', 'clase' => 'bg-primary'],
    3 => ['texto' => 'Recepcionada', 'clase' => 'bg-success'],
    9 => ['texto' => 'Anulada', 'clase' => 'bg-danger'],
];
?>
<div class="container-fluid p-4" id="comprasApp"
     data-url-index="<?php echo e(route_url('compra/index')); ?>"
     data-url-guardar="<?php echo e(route_url('compra/guardar')); ?>"
     data-url-aprobar="<?php echo e(route_url('compra/aprobar')); ?>"
     data-url-anular="<?php echo e(route_url('compra/anular')); ?>"
     data-url-recepcionar="<?php echo e(route_url('compra/recepcionar')); ?>">

    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart-check-fill me-2 text-primary fs-5"></i>
                <span>Compras y Recepción</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de órdenes de compra y entradas de mercadería.</p>
        </div>
        <button type="button" class="btn btn-primary shadow-sm" id="btnNuevaOrden">
            <i class="bi bi-plus-circle-fill me-2"></i>Nueva Orden
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3 fade-in">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroBusqueda" placeholder="Buscar código o proveedor..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="filtroEstado">
                        <option value="">Estado</option>
                        <option value="0" <?php echo ($filtros['estado'] ?? '') === '0' ? 'selected' : ''; ?>>Borrador</option>
                        <option value="1" <?php echo ($filtros['estado'] ?? '') === '1' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="2" <?php echo ($filtros['estado'] ?? '') === '2' ? 'selected' : ''; ?>>Aprobada</option>
                        <option value="3" <?php echo ($filtros['estado'] ?? '') === '3' ? 'selected' : ''; ?>>Recepcionada</option>
                        <option value="9" <?php echo ($filtros['estado'] ?? '') === '9' ? 'selected' : ''; ?>>Anulada</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <input type="date" class="form-control bg-light" id="filtroFechaDesde" value="<?php echo e((string) ($filtros['fecha_desde'] ?? '')); ?>">
                </div>
                <div class="col-6 col-md-3">
                    <input type="date" class="form-control bg-light" id="filtroFechaHasta" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? '')); ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm fade-in">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaCompras">
                    <thead>
                    <tr>
                        <th class="ps-4">Código</th>
                        <th>Proveedor</th>
                        <th>Fecha</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($ordenes)): ?>
                        <?php foreach ($ordenes as $orden): ?>
                            <?php $estado = (int) ($orden['estado'] ?? 0); $badge = $estadoLabels[$estado] ?? $estadoLabels[0]; ?>
                            <tr data-id="<?php echo (int) ($orden['id'] ?? 0); ?>" data-estado="<?php echo $estado; ?>">
                                <td class="ps-4 fw-semibold"><?php echo e((string) ($orden['codigo'] ?? '')); ?></td>
                                <td><?php echo e((string) ($orden['proveedor'] ?? '')); ?></td>
                                <td><?php echo e((string) ($orden['fecha_orden'] ?? '')); ?></td>
                                <td class="text-end">S/ <?php echo number_format((float) ($orden['total'] ?? 0), 2); ?></td>
                                <td class="text-center"><span class="badge <?php echo e($badge['clase']); ?>"><?php echo e($badge['texto']); ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1 acciones-compra">
                                        <?php if ($estado === 0): ?>
                                            <button class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar" title="Editar">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light text-danger border-0 bg-transparent btn-anular" title="Anular">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        <?php elseif ($estado === 1): ?>
                                            <button class="btn btn-sm btn-light text-success border-0 bg-transparent btn-aprobar" title="Aprobar">
                                                <i class="bi bi-check2-circle fs-5"></i>
                                            </button>
                                        <?php elseif ($estado === 2): ?>
                                            <button class="btn btn-sm btn-light text-info border-0 bg-transparent btn-recepcionar" title="Recibir mercadería">
                                                <i class="bi bi-truck fs-5"></i>
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted small">Sin acciones</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No hay órdenes de compra registradas.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOrdenCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Orden de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formOrdenCompra" class="row g-3" autocomplete="off">
                    <input type="hidden" id="ordenId" value="0">
                    <div class="col-md-6">
                        <label class="form-label">Proveedor</label>
                        <select id="idProveedor" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <option value="<?php echo (int) ($proveedor['id'] ?? 0); ?>"><?php echo e((string) ($proveedor['nombre_completo'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Fecha de Entrega</label>
                        <input type="date" class="form-control" id="fechaEntrega">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Observaciones</label>
                        <input type="text" class="form-control" id="observaciones" maxlength="180">
                    </div>

                    <div class="col-12">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0" id="tablaDetalleCompra">
                                <thead class="table-light">
                                <tr>
                                    <th style="min-width:280px;">Ítem</th>
                                    <th style="width: 140px;">Cantidad</th>
                                    <th style="width: 160px;">Costo</th>
                                    <th style="width: 160px;" class="text-end">Subtotal</th>
                                    <th style="width: 80px;"></th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-white border mt-2" id="btnAgregarFila">
                            <i class="bi bi-plus-circle me-2 text-primary"></i>Agregar ítem
                        </button>
                    </div>

                    <div class="col-12 text-end">
                        <h5 class="mb-0">Total: <span class="text-primary" id="ordenTotal">S/ 0.00</span></h5>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarOrden"><i class="bi bi-save me-2"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecepcionCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Recepcionar Orden</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="recepcionOrdenId" value="0">
                <div class="mb-3">
                    <label class="form-label">Almacén de ingreso</label>
                    <select id="recepcionAlmacen" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-info text-white" id="btnConfirmarRecepcion">
                    <i class="bi bi-check2-circle me-2"></i>Confirmar ingreso
                </button>
            </div>
        </div>
    </div>
</div>

<template id="templateFilaDetalle">
    <tr>
        <td>
            <select class="form-select form-select-sm detalle-item" required>
                <option value="">Seleccione un ítem...</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo (int) ($item['id'] ?? 0); ?>"><?php echo e((string) ($item['sku'] ?? '') . ' - ' . (string) ($item['nombre'] ?? '')); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
        <td><input type="number" class="form-control form-control-sm detalle-cantidad" min="0.01" step="0.01" value="1" required></td>
        <td><input type="number" class="form-control form-control-sm detalle-costo" min="0" step="0.01" value="0" required></td>
        <td class="text-end detalle-subtotal">S/ 0.00</td>
        <td class="text-center"><button class="btn btn-sm btn-link text-danger btn-quitar-fila" type="button"><i class="bi bi-x-circle"></i></button></td>
    </tr>
</template>

<script src="<?php echo e(base_url()); ?>/js/compras.js"></script>
