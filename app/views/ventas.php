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
    4 => ['texto' => 'Devuelto Total', 'clase' => 'bg-danger-subtle text-danger border border-danger-subtle'],
    5 => ['texto' => 'Dev. Parcial', 'clase' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'], // <-- NUEVO
    9 => ['texto' => 'Anulado', 'clase' => 'bg-dark-subtle text-dark border border-dark-subtle'],
];

$formatearFechaDMY = static function ($fecha): string {
    $texto = trim((string) $fecha);
    if ($texto === '') {
        return '-';
    }
    $timestamp = strtotime($texto);
    if ($timestamp === false) {
        return $texto;
    }
    return date('d/m/Y', $timestamp);
};
?>


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
        <div class="d-flex align-items-center gap-2">
            <a href="<?php echo e(route_url('reportes/ventas')); ?>" class="btn btn-light shadow-sm text-secondary fw-semibold border">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Reporte Ventas
            </a>
            <button type="button" class="btn btn-primary shadow-sm fw-semibold" id="btnNuevaVenta">
                <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Pedido
            </button>
        </div>
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
                <div class="col-12 col-md-3">
                    <select class="form-select bg-light" id="filtroOrdenFecha" title="Ordenar por fecha">
                        <option value="pedido" <?php echo (($filtros['orden_fecha'] ?? 'pedido') === 'pedido') ? 'selected' : ''; ?>>Ordenar por fecha de pedido</option>
                        <option value="emision" <?php echo (($filtros['orden_fecha'] ?? '') === 'emision') ? 'selected' : ''; ?>>Ordenar por fecha de emisión</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaVentas"
                       data-erp-table="true"
                       data-search-input="#filtroBusqueda"
                       data-pagination-controls="#ventasPaginationControls"
                       data-pagination-info="#ventasPaginationInfo"
                       data-erp-filters='[{"el":"#filtroEstado","attr":"data-estado","match":"equals"}]'>
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Cliente</th>
                            <th class="text-secondary fw-semibold">Fecha Pedido</th>
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
                                <tr data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-estado="<?php echo $estado; ?>" class="border-bottom" data-search="<?php echo e(mb_strtolower((string) ($venta['codigo'] ?? '') . ' ' . (string) ($venta['cliente'] ?? ''))); ?>">
                                    <td class="ps-4 fw-bold text-primary"><?php echo e((string) ($venta['codigo'] ?? '')); ?></td>
                                    <td class="fw-semibold text-dark"><?php echo e((string) ($venta['cliente'] ?? '')); ?></td>
                                    <td>
                                        <div class="fw-semibold text-dark" title="Fecha en la que se registró el pedido en el sistema">
                                            <?php echo date('d/m/Y H:i', strtotime($venta['created_at'])); ?>
                                        </div>
                                        <small class="text-muted d-block" title="Fecha comercial del documento">
                                            Emisión: <?php echo e($formatearFechaDMY($venta['fecha_emision'] ?? $venta['fecha_documento'] ?? '')); ?>
                                        </small>
                                        <?php if (!empty($venta['fecha_despacho'])): ?>
                                            <small class="text-info fw-bold d-block mt-1" title="Fecha en la que la mercadería salió del almacén">
                                                <i class="bi bi-truck me-1"></i>Despacho: <?php echo e($formatearFechaDMY($venta['fecha_despacho'])); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end fw-bold">S/ <?php echo number_format((float) ($venta['total'] ?? 0), 2); ?></td>
                                    <td class="text-center">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo e($badge['clase']); ?>">
                                            <?php echo e($badge['texto']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($estado === 0): ?> 
                                                <button class="btn btn-sm btn-light text-primary border-0 btn-editar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Editar Pedido"><i class="bi bi-pencil-square fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-success border-0 btn-aprobar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Aprobar Pedido"><i class="bi bi-check2-circle fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Anular Pedido"><i class="bi bi-trash fs-5"></i></button>
                                                
                                            <?php elseif ($estado === 2): ?> 
                                                <button class="btn btn-sm btn-light text-info border-0 btn-despachar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Despachar Mercadería"><i class="bi bi-truck fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Anular Pedido"><i class="bi bi-trash fs-5"></i></button>
                                                
                                            <?php elseif ($estado === 3 || $estado === 4 || $estado === 5): ?>
                                                <button class="btn btn-sm btn-light text-warning border-0 btn-devolucion rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Registrar Devolución"><i class="bi bi-arrow-return-left fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                                
                                            <?php else: ?> 
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-light text-dark border-0 rounded-circle btn-imprimir-modal" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Imprimir PDF"><i class="bi bi-printer fs-5"></i></button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-msg-row"><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay pedidos registrados.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="ventasPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación de ventas">
                    <ul class="pagination mb-0 justify-content-end" id="ventasPaginationControls"></ul>
                </nav>
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
                                <div class="col-md-3">
                                    <label for="tipoOperacion" class="form-label text-muted small fw-bold mb-1">Tipo Operación <span class="text-danger">*</span></label>
                                    <select id="tipoOperacion" name="tipo_operacion" class="form-select shadow-none border-primary-subtle" required>
                                        <option value="VENTA" selected>Venta Comercial</option>
                                        <option value="DONACION">Donación / Muestra Gratuita</option>
                                    </select>
                                </div>

                                <div class="col-md-4">
                                    <label for="idCliente" class="form-label text-muted small fw-bold mb-1">Cliente / Beneficiario <span class="text-danger">*</span></label>
                                    <select id="idCliente" class="form-select shadow-none" required></select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label for="fechaEmision" class="form-label text-muted small fw-bold mb-1">Fecha Emisión</label>
                                    <input type="date" class="form-control shadow-none" id="fechaEmision" value="<?php echo date('Y-m-d'); ?>">
                                </div>

                                <div class="col-md-3">
                                    <label for="tipoImpuesto" class="form-label text-muted small fw-bold mb-1">Impuestos <span class="text-danger">*</span></label>
                                    <select id="tipoImpuesto" class="form-select shadow-none" required>
                                        <option value="incluido" selected>Incluyen IGV (Boleta/Factura)</option>
                                        <option value="mas_igv">NO incluyen IGV (+18%)</option>
                                        <option value="exonerado">Exonerado (0%)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-1">
                                <div class="col-12">
                                    <label for="ventaObservaciones" class="form-label text-muted small fw-bold mb-1">Observaciones / Motivo</label>
                                    <input type="text" class="form-control shadow-none" id="ventaObservaciones" maxlength="180" placeholder="Ej: Donación para evento benéfico local">
                                </div>
                            </div>
                            </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-white rounded-top">
                                <h6 class="mb-0 fw-bold text-dark">Detalle de Productos</h6>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table align-middle mb-0 table-pro" id="tablaDetalleVenta">
                                    <thead>
                                        <tr>
                                            <th class="ps-3 text-secondary col-min-w-300">Producto</th>
                                            <th class="text-end text-secondary col-w-100">Stock</th>
                                            <th class="text-center text-secondary col-w-120">Cantidad</th>
                                            <th class="text-center text-secondary col-w-140">Precio Unit.</th>
                                            <th class="text-end text-secondary col-w-140">Subtotal</th>
                                            <th class="text-center col-w-60"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="2" class="ps-3 py-3 align-middle border-bottom-0">
                                                <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnAgregarFilaVenta">
                                                    <i class="bi bi-plus-lg me-1"></i>Agregar Producto
                                                </button>
                                            </td>
                                            <td colspan="4" class="pe-4 py-3 align-middle border-bottom-0">
                                                <div class="d-flex flex-wrap justify-content-end align-items-center gap-3 gap-md-4">
                                                    <div class="d-flex flex-column text-end">
                                                        <span class="text-muted small fw-bold mb-1">SUBTOTAL</span>
                                                        <span class="text-dark fw-bold" id="ventaSubtotal">S/ 0.00</span>
                                                    </div>
                                                    
                                                    <div class="vr d-none d-sm-block bg-secondary opacity-25" style="width: 2px;"></div>
                                                    
                                                    <div class="d-flex flex-column text-end">
                                                        <span class="text-muted small fw-bold mb-1">IGV (18%)</span>
                                                        <span class="text-dark fw-bold" id="ventaIgv">S/ 0.00</span>
                                                    </div>
                                                    
                                                    <div class="vr d-none d-sm-block bg-secondary opacity-25" style="width: 2px;"></div>
                                                    
                                                    <div class="d-flex flex-column text-end">
                                                        <span class="text-secondary small fw-bold mb-1">TOTAL PEDIDO</span>
                                                        <span class="text-primary fw-bold fs-5 lh-1" id="ventaTotal">S/ 0.00</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card border-0 shadow-sm mt-4 d-none" id="seccionDevolucionesVenta">
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-warning-subtle rounded-top d-flex align-items-center">
                                <i class="bi bi-arrow-return-left text-warning-emphasis me-2 fs-5"></i>
                                <h6 class="mb-0 fw-bold text-dark">Historial de Devoluciones</h6>
                            </div>
                            <div class="table-responsive">
                                <table class="table align-middle mb-0" id="tablaDevolucionesHistorico">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="ps-3 text-secondary small fw-bold">Fecha</th>
                                            <th class="text-secondary small fw-bold">Motivo y Resolución</th>
                                            <th class="text-secondary small fw-bold">Productos Devueltos</th>
                                            <th class="text-end pe-4 text-secondary small fw-bold">Monto</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
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
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <label for="despachoFecha" class="form-label text-muted small fw-bold mb-1">Fecha de Despacho <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-calendar-check text-muted"></i></span>
                                    <input type="date" class="form-control border-secondary-subtle" id="despachoFecha" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="despachoObservaciones" class="form-label text-muted small fw-bold mb-1">Observaciones / Guía de Remisión</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-file-earmark-text text-muted"></i></span>
                                    <input type="text" class="form-control border-secondary-subtle" id="despachoObservaciones" maxlength="180" placeholder="Opcional - Ingresar número de guía">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-pro" id="tablaDetalleDespacho">
                                <thead>
                                    <tr>
                                        <th class="ps-3 text-secondary col-min-w-250">Producto / Pendiente</th>
                                        <th class="text-center text-secondary col-w-200">Almacén Origen</th>
                                        <th class="text-center text-secondary col-w-100">Stock</th>
                                        <th class="text-end pe-3 text-secondary col-w-160">A Despachar</th>
                                        <th class="text-center text-secondary col-w-80">Acciones</th>
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

<div class="modal fade" id="modalDevolucionVenta" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Registrar Devolución de Venta</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <input type="hidden" id="devolucionVentaDocumentoId" value="0">

                <div class="row mb-4 g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Motivo de Devolución <span class="text-danger">*</span></label>
                        <select id="devolucionVentaMotivo" class="form-select border-warning-subtle" required>
                            <option value="">Seleccione un motivo...</option>
                            <optgroup label="📦 Restaura al Inventario Vendible">
                                <option value="producto_incorrecto">Producto incorrecto entregado</option>
                                <option value="error_despacho">Error de despacho / cantidad excedente</option>
                                <option value="cliente_rechaza">Cliente rechaza pedido (Packs sellados e intactos)</option>
                            </optgroup>

                            <optgroup label="⚠️ Descuenta o Va a Cuarentena / Mermas">
                                <option value="producto_defectuoso">Producto defectuoso, roto o dañado</option>
                            </optgroup>
                        </select>
                        <small id="devolucionVentaMotivoHint" class="text-muted d-block mt-1"></small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Resolución Comercial <span class="text-danger">*</span></label>
                        <select id="devolucionVentaResolucion" class="form-select border-warning-subtle" required>
                            <optgroup label="💳 Saldo a Favor (No sale dinero)">
                                <option value="descuento_cxc" selected>Nota de Crédito (Descontar de futuras compras / CxC)</option>
                            </optgroup>
                            
                            <optgroup label="💵 Salida de Dinero (Tesorería)">
                                <option value="reembolso_dinero">Reembolso al cliente (Efectivo / Transferencia)</option>
                            </optgroup>
                        </select>
                        <small id="devolucionVentaResolucionHint" class="text-muted d-block mt-1"></small>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-bordered" id="tablaDetalleDevolucionVenta">
                                <thead class="table-secondary text-dark">
                                    <tr>
                                        <th class="ps-3 border-bottom-0">Producto / Ítem</th>
                                        <th class="text-center border-bottom-0 col-w-150">Cant. Despachada</th>
                                        <th class="text-center border-bottom-0 col-w-150">Precio Unit.</th>
                                        <th class="text-center border-bottom-0 col-w-140">Cantidad</th>
                                        <th class="text-end pe-4 border-bottom-0 col-w-150">Monto Devuelto</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white"></tbody>
                                <tfoot class="bg-light border-top">
                                    <tr>
                                        <td colspan="4" class="text-end fw-bold py-3 text-secondary">TOTAL A DEVOLVER:</td>
                                        <td class="text-end fw-bold py-3 fs-5 text-warning-emphasis pe-4" id="devolucionVentaTotal">S/ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-white border-top-0">
                <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning text-dark fw-bold px-4" id="btnConfirmarDevolucionVenta">
                    <i class="bi bi-check-circle-fill me-2"></i>Procesar Devolución
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalImpresionPedido" tabindex="-1" aria-labelledby="modalImpresionPedidoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0">
                <h5 class="modal-title fw-bold" id="modalImpresionPedidoLabel">
                    <i class="bi bi-printer me-2"></i>Imprimir Documento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">Tipo de Documento</label>
                    <select class="form-select shadow-none border-primary-subtle" id="tipoDocumentoImprimir">
                        <option value="imprimir">Pedido Interno (Despacho / Almacén)</option>
                        <option value="imprimir_proforma">Proforma / Cotización (Para el Cliente)</option>
                    </select>
                </div>

                <div>
                    <label for="cantidadPaginasPedido" class="form-label fw-bold text-dark">Cantidad de copias por hoja</label>
                    <input type="number" class="form-control shadow-none" id="cantidadPaginasPedido" min="1" max="20" step="1" value="1">
                    <small class="text-muted d-block mt-1">Se imprimirán esta cantidad de copias en el PDF.</small>
                </div>
            </div>
            <div class="modal-footer bg-light border-top-0">
                <button type="button" class="btn btn-outline-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary fw-bold px-4" id="btnConfirmarImpresionPedido">
                    <i class="bi bi-file-earmark-pdf me-2"></i>Generar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.pedidoIdPendienteImpresion = window.pedidoIdPendienteImpresion || 0;

    window.imprimirPedido = function(id) {
        const app = document.getElementById('ventasApp');
        if (!app) return;

        window.pedidoIdPendienteImpresion = Number(id) || 0;

        const inputPaginas = document.getElementById('cantidadPaginasPedido');
        const selectTipo = document.getElementById('tipoDocumentoImprimir');
        if (inputPaginas) inputPaginas.value = 1;
        if (selectTipo) selectTipo.value = 'imprimir'; // Por defecto Pedido Interno

        const modalEl = document.getElementById('modalImpresionPedido');
        if (!modalEl || typeof bootstrap === 'undefined') return;

        bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }; 

    (function() {
        const btnConfirmar = document.getElementById('btnConfirmarImpresionPedido');
        if (!btnConfirmar) return;

        const nuevoBtnConfirmar = btnConfirmar.cloneNode(true);
        btnConfirmar.parentNode.replaceChild(nuevoBtnConfirmar, btnConfirmar);

        nuevoBtnConfirmar.addEventListener('click', () => {
            const app = document.getElementById('ventasApp');
            const inputPaginas = document.getElementById('cantidadPaginasPedido');
            const selectTipo = document.getElementById('tipoDocumentoImprimir'); // Capturamos el tipo
            
            if (!app || !inputPaginas || window.pedidoIdPendienteImpresion <= 0) return;

            const baseUrl = app.dataset.urlIndex;
            const paginas = Math.max(1, Math.min(20, Number(inputPaginas.value) || 1));
            const accionImpresion = selectTipo ? selectTipo.value : 'imprimir';

            const modalEl = document.getElementById('modalImpresionPedido');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            // Pasamos la acción dinámica (imprimir vs imprimir_proforma)
            window.open(`${baseUrl}&accion=${accionImpresion}&id=${window.pedidoIdPendienteImpresion}&paginas=${paginas}`, '_blank');
        });
    })();
</script>

<template id="templateFilaVenta">
    <tr class="border-bottom">
        <td class="ps-3 py-3 align-top" data-label="Producto">
            <select class="form-select form-select-sm detalle-item shadow-none border-secondary-subtle" required></select>
        </td>
        <td class="text-end text-muted small fw-bold py-3 px-2 align-top detalle-stock" data-label="Stock Disponible">0.00</td>
        <td class="align-top py-3 px-2" data-label="Cantidad">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-primary shadow-none border-secondary-subtle" min="0" step="1" value="" required>
        </td>
        <td class="align-top py-3 px-2" data-label="Precio Unit.">
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted bg-light border-secondary-subtle">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-precio shadow-none border-secondary-subtle" min="0" step="0.01" value="0.00" required>
            </div>
        </td>
        <td class="text-end align-top py-3 fw-bold text-dark detalle-subtotal fs-6" data-label="Subtotal">S/ 0.00</td>
        <td class="text-center align-top py-3" data-label="Acción">
            <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-fila p-1" data-bs-toggle="tooltip" title="Quitar fila" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    </tr>
</template>