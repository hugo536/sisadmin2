<?php
$ventas = $ventas ?? [];
$filtros = $filtros ?? [];
$almacenes = $almacenes ?? [];

// Configuración de Estados con diseño "Subtle" (Estándar del sistema)
$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    1 => ['texto' => 'Pendiente', 'clase' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
    2 => ['texto' => 'Aprobado', 'clase' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    3 => ['texto' => 'Cerrado/Entregado', 'clase' => 'bg-success-subtle text-success border border-success-subtle'],
    9 => ['texto' => 'Anulado', 'clase' => 'bg-danger-subtle text-danger border border-danger-subtle'],
];
?>

<style>
    #tablaDetalleDespacho td {
        vertical-align: middle;
    }

    #tablaDetalleDespacho .despacho-acciones-row {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
    }

    #tablaDetalleDespacho .despacho-cantidad {
        max-width: 92px;
    }

    #tablaDetalleDespacho .btn-split {
        line-height: 1.1;
    }
</style>

<div class="container-fluid p-4" id="ventasApp"
     data-url-index="<?php echo e(route_url('ventas/index')); ?>"
     data-url-guardar="<?php echo e(route_url('ventas/guardar')); ?>"
     data-url-aprobar="<?php echo e(route_url('ventas/aprobar')); ?>"
     data-url-anular="<?php echo e(route_url('ventas/anular')); ?>"
     data-url-despachar="<?php echo e(route_url('ventas/despachar')); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart4 me-2 text-primary"></i> Ventas y Despacho
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de pedidos, facturación y salidas de almacén.</p>
        </div>
        <button type="button" class="btn btn-primary shadow-sm fw-semibold" id="btnNuevaVenta">
            <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Pedido
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="filtroBusqueda" placeholder="Buscar código, cliente..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
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
                <table class="table align-middle mb-0 table-hover table-borderless table-pro" id="tablaVentas">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Cliente</th>
                            <th class="text-secondary fw-semibold">Fecha Emisión</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($ventas)): ?>
                            <?php foreach ($ventas as $venta): ?>
                                <?php 
                                    $estado = (int) ($venta['estado'] ?? 0); 
                                    $badge = $estadoLabels[$estado] ?? $estadoLabels[0]; 
                                ?>
                                <tr data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-estado="<?php echo $estado; ?>" class="border-bottom">
                                    <td class="ps-4 fw-bold text-primary"><?php echo e((string) ($venta['codigo'] ?? '')); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e((string) ($venta['cliente'] ?? '')); ?></td>
                                    <td><?php echo e((string) ($venta['fecha_emision'] ?? $venta['fecha_documento'] ?? '')); ?></td>
                                    <td class="text-end fw-bold">S/ <?php echo number_format((float) ($venta['total'] ?? 0), 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo e($badge['clase']); ?>">
                                            <?php echo e($badge['texto']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($estado === 0): ?> 
                                                <button class="btn btn-sm btn-light text-primary border-0 btn-editar rounded-circle" data-bs-toggle="tooltip" title="Editar Pedido"><i class="bi bi-pencil-square fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-success border-0 btn-aprobar rounded-circle" data-bs-toggle="tooltip" title="Aprobar Pedido"><i class="bi bi-check2-circle fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-bs-toggle="tooltip" title="Anular Pedido"><i class="bi bi-trash fs-5"></i></button>
                                            <?php elseif ($estado === 2): ?> 
                                                <button class="btn btn-sm btn-light text-info border-0 btn-despachar rounded-circle" data-bs-toggle="tooltip" title="Despachar Mercadería"><i class="bi bi-truck fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php else: ?> 
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay pedidos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3">
                <small class="text-muted fw-semibold">Mostrando <?php echo count($ventas); ?> registros</small>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVenta" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-file-earmark-text me-2"></i>Pedido de Venta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form id="formVenta" autocomplete="off">
                    <input type="hidden" id="ventaId" value="0">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información General</h6>
                            <div class="row g-3 align-items-end">
                                
                                <div class="col-md-5">
                                    <label for="idCliente" class="form-label text-muted small fw-bold mb-1">Cliente <span class="text-danger">*</span></label>
                                    <select id="idCliente" class="form-select" required></select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="fechaEmision" class="form-label text-muted small fw-bold mb-1">Fecha Emisión</label>
                                    <input type="date" class="form-control" id="fechaEmision" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="col-md-4">
                                    <label for="ventaObservaciones" class="form-label text-muted small fw-bold mb-1">Observaciones</label>
                                    <input type="text" class="form-control" id="ventaObservaciones" maxlength="180" placeholder="Opcional">
                                </div>
                                
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="d-flex justify-content-between align-items-center p-3 border-bottom bg-white rounded-top">
                                <h6 class="mb-0 fw-bold text-dark">Detalle de Productos</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnAgregarFilaVenta">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar Producto
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" id="tablaDetalleVenta">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th style="min-width:300px;" class="ps-3 text-secondary">Producto</th>
                                            <th style="width: 100px;" class="text-end text-secondary">Stock</th>
                                            <th style="width: 120px;" class="text-center text-secondary">Cantidad</th>
                                            <th style="width: 140px;" class="text-center text-secondary">Precio Unit.</th>
                                            <th style="width: 140px;" class="text-end text-secondary">Subtotal</th>
                                            <th style="width: 60px;" class="text-center"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="4" class="text-end fw-bold py-3 text-secondary align-middle">TOTAL PEDIDO:</td>
                                            <td class="text-end fw-bold py-3 fs-4 text-primary align-middle" id="ventaTotal">S/ 0.00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-top-0">
                <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-primary px-4 fw-bold" id="btnGuardarVenta"><i class="bi bi-save me-2"></i>Guardar Pedido</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDespacho" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Despachar Pedido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <input type="hidden" id="despachoDocumentoId" value="0">
                
                <select id="despachoAlmacen" class="d-none">
                    <option value="">Seleccione...</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="alert alert-info d-flex align-items-center mb-4 shadow-sm border-0 rounded-3">
                    <i class="bi bi-info-circle-fill me-3 fs-4"></i>
                    <div>
                        <strong>Modo Multi-Almacén:</strong> Puede fraccionar el despacho desde diferentes almacenes.
                    </div>
                </div>
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div>
                            <label for="despachoObservaciones" class="form-label text-muted small fw-bold mb-1">Observaciones / Guía de Remisión</label>
                            <input type="text" class="form-control" id="despachoObservaciones" maxlength="180" placeholder="Opcional - Ingresar número de guía">
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0" id="tablaDetalleDespacho">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="ps-3 text-secondary" style="min-width: 250px;">Producto / Pendiente</th>
                                        <th class="text-center text-secondary" style="width: 200px;">Almacén Origen</th>
                                        <th class="text-center text-secondary" style="width: 100px;">Stock</th>
                                        <th style="width: 160px;" class="text-end pe-3 text-secondary">A Despachar</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between align-items-center">
                <div class="form-check form-switch m-0 ps-5">
                    <input class="form-check-input" type="checkbox" id="cerrarForzado" style="cursor: pointer;">
                    <label class="form-check-label fw-semibold text-danger small" for="cerrarForzado">
                        Forzar cierre (Cancelar pendientes restantes)
                    </label>
                </div>
                <div>
                    <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-info text-white fw-bold px-4" id="btnGuardarDespacho">
                        <i class="bi bi-check-lg me-2"></i>Confirmar Despacho
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<template id="templateFilaVenta">
    <tr class="border-bottom">
        <td class="ps-3 py-3 align-top">
            <select class="form-select form-select-sm detalle-item shadow-none border-secondary-subtle" required></select>
        </td>
        <td class="text-end text-muted small fw-bold py-3 px-2 align-top detalle-stock">0.00</td>
        <td class="align-top py-3 px-2">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-primary shadow-none border-secondary-subtle" min="0.01" step="0.01" value="1" required>
        </td>
        <td class="align-top py-3 px-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted bg-light border-secondary-subtle">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-precio shadow-none border-secondary-subtle" min="0" step="0.01" value="0.00" required>
            </div>
        </td>
        <td class="text-end align-top py-3 fw-bold text-dark detalle-subtotal fs-6">S/ 0.00</td>
        <td class="text-center align-top py-3">
            <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-fila p-1" data-bs-toggle="tooltip" title="Quitar fila" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    </tr>
</template>

<script src="<?php echo base_url(); ?>/assets/js/ventas.js?v=1.0"></script>
