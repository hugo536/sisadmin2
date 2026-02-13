<?php
$ordenes = $ordenes ?? [];
$filtros = $filtros ?? [];
$proveedores = $proveedores ?? [];
$items = $items ?? [];
$almacenes = $almacenes ?? [];

$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary'],
    1 => ['texto' => 'Pendiente', 'clase' => 'bg-warning text-dark'], // Opcional si usas flujo de 3 pasos
    2 => ['texto' => 'Aprobada', 'clase' => 'bg-primary'],
    3 => ['texto' => 'Recepcionada', 'clase' => 'bg-success'],
    9 => ['texto' => 'Anulada', 'clase' => 'bg-danger'],
];
?>
<div class="container-fluid p-4" id="comprasApp"
     data-url-index="<?php echo e(route_url('compras/index')); ?>"
     data-url-guardar="<?php echo e(route_url('compras/guardar')); ?>"
     data-url-aprobar="<?php echo e(route_url('compras/aprobar')); ?>"
     data-url-anular="<?php echo e(route_url('compras/anular')); ?>"
     data-url-recepcionar="<?php echo e(route_url('compras/recepcionar')); ?>">

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
                <table class="table align-middle mb-0 table-hover" id="tablaCompras">
                    <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Código</th>
                        <th>Proveedor</th>
                        <th>Fecha Emisión</th>
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
                                <td class="ps-4 fw-semibold text-primary"><?php echo e((string) ($orden['codigo'] ?? '')); ?></td>
                                <td><?php echo e((string) ($orden['proveedor'] ?? '')); ?></td>
                                <td><?php echo e((string) ($orden['fecha_orden'] ?? '')); ?></td>
                                <td class="text-end fw-bold">S/ <?php echo number_format((float) ($orden['total'] ?? 0), 2); ?></td>
                                <td class="text-center"><span class="badge rounded-pill <?php echo e($badge['clase']); ?>"><?php echo e($badge['texto']); ?></span></td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex align-items-center gap-1 acciones-compra">
                                        
                                        <?php if ($estado === 0): ?>
                                            <button class="btn btn-sm btn-light text-primary border-0 btn-editar" title="Editar" data-bs-toggle="tooltip">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light text-success border-0 btn-aprobar" title="Aprobar Orden" data-bs-toggle="tooltip">
                                                <i class="bi bi-check2-circle fs-5"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light text-danger border-0 btn-anular" title="Anular" data-bs-toggle="tooltip">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        
                                        <?php elseif ($estado === 2): ?>
                                            <button class="btn btn-sm btn-light text-info border-0 btn-recepcionar" title="Recepcionar Mercadería" data-bs-toggle="tooltip">
                                                <i class="bi bi-box-seam fs-5"></i>
                                            </button>
                                            <button class="btn btn-sm btn-light text-secondary border-0 btn-editar" title="Ver Detalle" data-bs-toggle="tooltip">
                                                <i class="bi bi-eye fs-5"></i>
                                            </button>

                                        <?php else: ?>
                                            <button class="btn btn-sm btn-light text-secondary border-0 btn-editar" title="Ver Detalle" data-bs-toggle="tooltip">
                                                <i class="bi bi-eye fs-5"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-5">
                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                            No se encontraron órdenes de compra.
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalOrdenCompra" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-receipt-cutoff me-2"></i>Orden de Compra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <form id="formOrdenCompra" class="row g-3" autocomplete="off">
                    <input type="hidden" id="ordenId" value="0">
                    
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Proveedor <span class="text-danger">*</span></label>
                        <select id="idProveedor" class="form-select" autocomplete="off" placeholder="Buscar proveedor...">
                            <option value="">Seleccione...</option>
                            <?php foreach ($proveedores as $proveedor): ?>
                                <option value="<?php echo (int) ($proveedor['id'] ?? 0); ?>">
                                    <?php echo e((string) ($proveedor['nombre_completo'] ?? '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">F. Entrega Estimada</label>
                        <input type="date" class="form-control" id="fechaEntrega">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Observaciones</label>
                        <input type="text" class="form-control" id="observaciones" maxlength="180" placeholder="Opcional">
                    </div>

                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-muted">Detalle de Productos</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarFila">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar Ítem
                                </button>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0" id="tablaDetalleCompra">
                                        <thead class="table-light">
                                        <tr>
                                            <th style="min-width:300px;" class="ps-3">Ítem / Producto</th>
                                            <th style="width: 120px;">Cantidad</th>
                                            <th style="width: 140px;">Costo Unit.</th>
                                            <th style="width: 140px;" class="text-end">Subtotal</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                        </thead>
                                        <tbody></tbody>
                                        <tfoot class="table-light">
                                            <tr>
                                                <td colspan="3" class="text-end fw-bold py-3">TOTAL ESTIMADO:</td>
                                                <td class="text-end fw-bold py-3 fs-5 text-primary" id="ordenTotal">S/ 0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i> Los costos deben incluir impuestos si aplica.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cerrar</button>
                <button class="btn btn-primary" id="btnGuardarOrden">
                    <i class="bi bi-save me-2"></i>Guardar Borrador
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRecepcionCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Recepcionar Mercadería</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info d-flex align-items-center" role="alert">
                    <i class="bi bi-info-circle-fill flex-shrink-0 me-2"></i>
                    <div>
                        Esta acción ingresará el stock al almacén y cerrará la orden de compra.
                    </div>
                </div>
                <input type="hidden" id="recepcionOrdenId" value="0">
                <div class="mb-3">
                    <label class="form-label fw-bold">Almacén de Destino</label>
                    <select id="recepcionAlmacen" class="form-select form-select-lg" required>
                        <option value="">Seleccione almacén...</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-info text-white fw-bold" id="btnConfirmarRecepcion">
                    <i class="bi bi-check-lg me-2"></i>Confirmar Ingreso
                </button>
            </div>
        </div>
    </div>
</div>

<template id="templateFilaDetalle">
    <tr>
        <td class="ps-3">
            <select class="form-select form-select-sm detalle-item" required>
                <option value="">Buscar ítem...</option>
                <?php foreach ($items as $item): ?>
                    <option value="<?php echo (int) ($item['id'] ?? 0); ?>">
                        <?php echo htmlspecialchars((string) ($item['sku'] ?? '') . ' - ' . (string) ($item['nombre'] ?? '')); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad" min="0.01" step="0.01" value="1" required>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-costo" min="0" step="0.01" value="0" required>
            </div>
        </td>
        <td class="text-end align-middle fw-semibold detalle-subtotal">S/ 0.00</td>
        <td class="text-center align-middle">
            <button class="btn btn-sm btn-link text-danger p-0 btn-quitar-fila" type="button" title="Quitar">
                <i class="bi bi-x-circle-fill fs-6"></i>
            </button>
        </td>
    </tr>
</template>
