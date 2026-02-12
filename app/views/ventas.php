<?php
$ventas = $ventas ?? [];
$filtros = $filtros ?? [];
$almacenes = $almacenes ?? [];

$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary'],
    1 => ['texto' => 'Pendiente', 'clase' => 'bg-warning text-dark'],
    2 => ['texto' => 'Aprobado', 'clase' => 'bg-primary'],
    3 => ['texto' => 'Cerrado/Entregado', 'clase' => 'bg-success'],
    9 => ['texto' => 'Anulado', 'clase' => 'bg-danger'],
];
?>
<div class="container-fluid p-4" id="ventasApp"
     data-url-index="<?php echo e(route_url('ventas/index')); ?>"
     data-url-guardar="<?php echo e(route_url('ventas/guardar')); ?>"
     data-url-aprobar="<?php echo e(route_url('ventas/aprobar')); ?>"
     data-url-anular="<?php echo e(route_url('ventas/anular')); ?>"
     data-url-despachar="<?php echo e(route_url('ventas/despachar')); ?>">

    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart4 me-2 text-primary fs-5"></i>
                <span>Ventas y Despacho</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de pedidos, aprobación y salidas de almacén.</p>
        </div>
        <button type="button" class="btn btn-primary shadow-sm" id="btnNuevaVenta">
            <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Pedido
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3 fade-in">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <input type="search" class="form-control bg-light" id="filtroBusqueda" placeholder="Buscar código o cliente..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light" id="filtroEstado">
                        <option value="">Estado</option>
                        <?php foreach ($estadoLabels as $key => $info): ?>
                            <option value="<?php echo (int) $key; ?>" <?php echo ($filtros['estado'] ?? '') === (string) $key ? 'selected' : ''; ?>><?php echo e($info['texto']); ?></option>
                        <?php endforeach; ?>
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
                <table class="table align-middle mb-0 table-pro" id="tablaVentas">
                    <thead>
                    <tr>
                        <th class="ps-4">Código</th>
                        <th>Cliente</th>
                        <th>Fecha</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ventas as $venta): ?>
                        <?php $estado = (int) ($venta['estado'] ?? 0); $badge = $estadoLabels[$estado] ?? $estadoLabels[0]; ?>
                        <tr data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-estado="<?php echo $estado; ?>">
                            <td class="ps-4 fw-semibold"><?php echo e((string) ($venta['codigo'] ?? '')); ?></td>
                            <td><?php echo e((string) ($venta['cliente'] ?? '')); ?></td>
                            <td><?php echo e((string) ($venta['fecha_documento'] ?? '')); ?></td>
                            <td class="text-end">S/ <?php echo number_format((float) ($venta['total'] ?? 0), 2); ?></td>
                            <td class="text-center"><span class="badge <?php echo e($badge['clase']); ?>"><?php echo e($badge['texto']); ?></span></td>
                            <td class="text-end pe-4">
                                <?php if ($estado === 0): ?>
                                    <button class="btn btn-sm btn-link text-primary btn-editar" title="Editar"><i class="bi bi-pencil-square"></i></button>
                                    <button class="btn btn-sm btn-link text-danger btn-anular" title="Anular"><i class="bi bi-trash"></i></button>
                                    <button class="btn btn-sm btn-link text-success btn-aprobar" title="Aprobar"><i class="bi bi-check2-circle"></i></button>
                                <?php endif; ?>
                                <?php if ($estado === 2): ?>
                                    <button class="btn btn-sm btn-outline-primary btn-despachar" title="Despachar">
                                        <i class="bi bi-truck me-1"></i>Despachar
                                    </button>
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

<div class="modal fade" id="modalVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Pedido de Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formVenta" class="row g-3" autocomplete="off">
                    <input type="hidden" id="ventaId" value="0">
                    <div class="col-md-7">
                        <label class="form-label">Cliente (Ajax)</label>
                        <div class="input-group">
                            <input type="search" class="form-control" id="buscarCliente" placeholder="Buscar cliente por nombre o documento...">
                            <button class="btn btn-outline-secondary" type="button" id="btnBuscarCliente"><i class="bi bi-search"></i></button>
                        </div>
                        <select id="idCliente" class="form-select mt-2" required>
                            <option value="">Seleccione cliente...</option>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label">Observaciones</label>
                        <input type="text" class="form-control" id="ventaObservaciones" maxlength="180">
                    </div>

                    <div class="col-12">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0" id="tablaDetalleVenta">
                                <thead class="table-light">
                                <tr>
                                    <th style="min-width:280px;">Producto</th>
                                    <th style="width: 120px;" class="text-end">Stock Actual</th>
                                    <th style="width: 130px;">Cantidad</th>
                                    <th style="width: 150px;">Precio</th>
                                    <th style="width: 150px;" class="text-end">Subtotal</th>
                                    <th style="width: 70px;"></th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-white border mt-2" id="btnAgregarFilaVenta">
                            <i class="bi bi-plus-circle me-2 text-primary"></i>Agregar producto
                        </button>
                    </div>

                    <div class="col-12 text-end">
                        <h5 class="mb-0">Total: <span class="text-primary" id="ventaTotal">S/ 0.00</span></h5>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary" id="btnGuardarVenta"><i class="bi bi-save me-2"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDespacho" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Despachar Pedido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="despachoDocumentoId" value="0">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Almacén</label>
                        <select class="form-select" id="despachoAlmacen" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($almacenes as $almacen): ?>
                                <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label">Observaciones</label>
                        <input type="text" class="form-control" id="despachoObservaciones" maxlength="180">
                    </div>
                    <div class="col-12">
                        <div class="table-responsive border rounded">
                            <table class="table table-sm align-middle mb-0" id="tablaDetalleDespacho">
                                <thead class="table-light">
                                <tr>
                                    <th>Producto</th>
                                    <th class="text-end">Solicitado</th>
                                    <th class="text-end">Ya Despachado</th>
                                    <th class="text-end">Pendiente</th>
                                    <th class="text-end">Stock</th>
                                    <th style="width:160px;">Despachar Ahora</th>
                                </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch border rounded p-3 bg-light">
                            <input class="form-check-input" type="checkbox" id="cerrarForzado">
                            <label class="form-check-label fw-bold text-danger" for="cerrarForzado">
                                Cerrar pedido tras este despacho (Cancelar saldos pendientes)
                            </label>
                            <div class="small text-muted">Si está marcado, el pedido pasará a estado Cerrado aunque aún haya pendiente.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-info text-white" id="btnGuardarDespacho">
                    <i class="bi bi-check2-circle me-2"></i>Registrar despacho
                </button>
            </div>
        </div>
    </div>
</div>

<template id="templateFilaVenta">
    <tr>
        <td>
            <input type="search" class="form-control form-control-sm detalle-item-search" placeholder="Buscar producto...">
            <select class="form-select form-select-sm mt-1 detalle-item" required></select>
        </td>
        <td class="text-end detalle-stock">0.00</td>
        <td><input type="number" class="form-control form-control-sm detalle-cantidad" min="0.01" step="0.01" value="1" required></td>
        <td><input type="number" class="form-control form-control-sm detalle-precio" min="0" step="0.01" value="0" required></td>
        <td class="text-end detalle-subtotal">S/ 0.00</td>
        <td class="text-center"><button type="button" class="btn btn-sm btn-link text-danger btn-quitar-fila"><i class="bi bi-x-circle"></i></button></td>
    </tr>
</template>

<script src="<?php echo e(base_url()); ?>/js/ventas.js"></script>
