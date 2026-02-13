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
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroBusqueda" placeholder="Buscar código o cliente..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
                    </div>
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
                <table class="table align-middle mb-0 table-hover" id="tablaVentas">
                    <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Código</th>
                        <th>Cliente</th>
                        <th>Fecha Emisión</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Estado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($ventas)): ?>
                        <?php foreach ($ventas as $venta): ?>
                            <?php $estado = (int) ($venta['estado'] ?? 0); $badge = $estadoLabels[$estado] ?? $estadoLabels[0]; ?>
                            <tr data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-estado="<?php echo $estado; ?>">
                                <td class="ps-4 fw-semibold text-primary"><?php echo e((string) ($venta['codigo'] ?? '')); ?></td>
                                <td><?php echo e((string) ($venta['cliente'] ?? '')); ?></td>
                                <td><?php echo e((string) ($venta['fecha_emision'] ?? $venta['fecha_documento'] ?? '')); ?></td>
                                <td class="text-end fw-bold">S/ <?php echo number_format((float) ($venta['total'] ?? 0), 2); ?></td>
                                <td class="text-center"><span class="badge rounded-pill <?php echo e($badge['clase']); ?>"><?php echo e($badge['texto']); ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1">
                                        <?php if ($estado === 0): ?>
                                            <button class="btn btn-sm btn-light text-primary border-0 btn-editar" title="Editar" data-bs-toggle="tooltip"><i class="bi bi-pencil-square fs-5"></i></button>
                                            <button class="btn btn-sm btn-light text-success border-0 btn-aprobar" title="Aprobar" data-bs-toggle="tooltip"><i class="bi bi-check2-circle fs-5"></i></button>
                                            <button class="btn btn-sm btn-light text-danger border-0 btn-anular" title="Anular" data-bs-toggle="tooltip"><i class="bi bi-trash fs-5"></i></button>
                                        <?php elseif ($estado === 2): ?>
                                            <button class="btn btn-sm btn-light text-info border-0 btn-despachar" title="Despachar Mercadería" data-bs-toggle="tooltip">
                                                <i class="bi bi-truck fs-5"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light text-secondary border-0 btn-editar" title="Ver Detalle" data-bs-toggle="tooltip"><i class="bi bi-eye fs-5"></i></button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-light text-secondary border-0 btn-editar" title="Ver Detalle" data-bs-toggle="tooltip"><i class="bi bi-eye fs-5"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2"></i>No hay pedidos registrados.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVenta" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-file-earmark-text me-2"></i>Pedido de Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <form id="formVenta" class="row g-3" autocomplete="off">
                    <input type="hidden" id="ventaId" value="0">
                    
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Cliente <span class="text-danger">*</span></label>
                        <select id="idCliente" class="form-select" placeholder="Escriba para buscar cliente..." required>
                            </select>
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Fecha Emisión</label>
                        <input type="date" class="form-control" id="fechaEmision" value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold">Observaciones</label>
                        <input type="text" class="form-control" id="ventaObservaciones" maxlength="180" placeholder="Opcional">
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-muted">Productos</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarFilaVenta">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar Producto
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0" id="tablaDetalleVenta">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="min-width:300px;" class="ps-3">Producto (Buscar)</th>
                                            <th style="width: 100px;" class="text-end">Stock</th>
                                            <th style="width: 120px;">Cantidad</th>
                                            <th style="width: 140px;">Precio</th>
                                            <th style="width: 140px;" class="text-end">Subtotal</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="4" class="text-end fw-bold py-3">TOTAL:</td>
                                                <td class="text-end fw-bold py-3 fs-5 text-primary" id="ventaTotal">S/ 0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cerrar</button>
                <button class="btn btn-primary" id="btnGuardarVenta"><i class="bi bi-save me-2"></i>Guardar Pedido</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDespacho" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-truck me-2"></i>Despachar Pedido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <input type="hidden" id="despachoDocumentoId" value="0">
                
                <select id="despachoAlmacen" class="d-none">
                    <option value="">Seleccione...</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="alert alert-info d-flex align-items-center mb-3">
                    <i class="bi bi-info-circle-fill me-2 fs-4"></i>
                    <div>
                        <strong>Modo Multi-Almacén:</strong> Puede seleccionar diferentes almacenes para completar el pedido.
                        <br><small>Use el botón (+) si necesita sacar un mismo producto de dos lugares distintos.</small>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-12">
                        <label class="form-label fw-bold">Observaciones / Guía Remisión</label>
                        <input type="text" class="form-control" id="despachoObservaciones" maxlength="180" placeholder="Ej: Guía de Remisión 001-456">
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tablaDetalleDespacho">
                                <thead class="table-light">
                                <tr>
                                    <th class="ps-3" style="min-width: 250px;">Producto / Pendiente</th>
                                    <th class="text-center" style="width: 200px;">Almacén Origen</th>
                                    <th class="text-center" style="width: 100px;">Stock</th>
                                    <th style="width: 160px;" class="text-end pe-3">A Despachar</th>
                                </tr>
                                </thead>
                                <tbody>
                                    </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white d-flex justify-content-between align-items-center">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" id="cerrarForzado">
                    <label class="form-check-label fw-bold text-danger" for="cerrarForzado" style="cursor:pointer;">
                        Forzar cierre (Cancelar pendientes)
                    </label>
                </div>
                <div>
                    <button class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-info text-white fw-bold" id="btnGuardarDespacho">
                        <i class="bi bi-check2-circle me-2"></i>Confirmar Despacho
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="templateFilaVenta">
    <tr>
        <td class="ps-3">
            <select class="form-select form-select-sm detalle-item" placeholder="Buscar producto..." required></select>
        </td>
        <td class="text-end text-muted small detalle-stock">0.00</td>
        <td>
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad" min="0.01" step="0.01" value="1" required>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-precio" min="0" step="0.01" value="0" required>
            </div>
        </td>
        <td class="text-end fw-semibold detalle-subtotal">S/ 0.00</td>
        <td class="text-center">
            <button type="button" class="btn btn-sm btn-link text-danger p-0 btn-quitar-fila" title="Quitar">
                <i class="bi bi-x-circle-fill fs-6"></i>
            </button>
        </td>
    </tr>
</template>