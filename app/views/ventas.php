<?php
$ventas = $ventas ?? [];
$filtros = $filtros ?? [];
$almacenes = $almacenes ?? [];

// Configuración de Estados Optimizada
$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    2 => ['texto' => 'Aprobado (Por Despachar)', 'clase' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    3 => ['texto' => 'Cerrado/Entregado', 'clase' => 'bg-success-subtle text-success border border-success-subtle'],
    4 => ['texto' => 'Devuelto Total', 'clase' => 'bg-danger-subtle text-danger border border-danger-subtle'],
    5 => ['texto' => 'Dev. Parcial', 'clase' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'], 
    6 => ['texto' => 'Despacho Parcial', 'clase' => 'bg-info-subtle text-info-emphasis border border-info-subtle'], // <-- NUEVO ESTADO
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

<link rel="stylesheet" href="<?php echo e(base_url('assets/css/ventas.css')); ?>?v=<?php echo time(); ?>">
<link rel="stylesheet" href="<?php echo e(asset_url('css/ventas.css')); ?>?v=1.0">

<div class="container-fluid p-4" id="ventasApp"
     data-url-index="<?php echo e(route_url('ventas/index')); ?>"
     data-url-guardar="<?php echo e(route_url('ventas/guardar')); ?>"
     data-url-aprobar="<?php echo e(route_url('ventas/aprobar')); ?>"
     data-url-anular="<?php echo e(route_url('ventas/anular')); ?>"
     data-url-despachar="<?php echo e(route_url('ventas/despachar')); ?>"
     data-cuentas="<?php echo htmlspecialchars(json_encode($cuentas ?? []), ENT_QUOTES, 'UTF-8'); ?>"
     data-metodos="<?php echo htmlspecialchars(json_encode($metodos ?? []), ENT_QUOTES, 'UTF-8'); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart4 me-2 text-primary"></i> Ventas y Despacho
            </h1>
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
            <form method="get" action="" class="row g-2 align-items-center" id="formFiltrosVentas">
                <input type="hidden" name="ruta" value="ventas/index"> 
                
                <div class="col-12 col-lg-3">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" name="q" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="filtroBusqueda" placeholder="Buscar código, cliente..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-lg-2">
                    <select name="estado" class="form-select bg-light border-secondary-subtle shadow-sm text-secondary" id="filtroEstado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadoLabels as $key => $info): ?>
                            <option value="<?php echo (int) $key; ?>" <?php echo ($filtros['estado'] ?? '') === (string) $key ? 'selected' : ''; ?>>
                                <?php echo e($info['texto']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-2">
                    <select class="form-select text-secondary fw-semibold border-secondary-subtle" id="filtroOrdenFecha" style="min-width: 140px;">
                        <option value="emision" <?php echo ($filtros['orden_fecha'] ?? 'emision') === 'emision' ? 'selected' : ''; ?>>Fecha: Emisión</option>
                        <option value="registro" <?php echo ($filtros['orden_fecha'] ?? '') === 'registro' ? 'selected' : ''; ?>>Fecha: Registro</option>
                        <option value="despacho" <?php echo ($filtros['orden_fecha'] ?? '') === 'despacho' ? 'selected' : ''; ?>>Fecha: Despacho</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-12 col-lg-5">  
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" id="filtroFechaDesde" class="form-control bg-light border-start-0 border-end-0 border-secondary-subtle" value="<?php echo e($filtros['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days'))); ?>">
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" id="filtroFechaHasta" class="form-control bg-light border-start-0 border-secondary-subtle" value="<?php echo e($filtros['fecha_hasta'] ?? date('Y-m-d')); ?>">
                        
                        <button type="button" id="btnFiltrarFechas" class="btn btn-light border text-primary px-3 transition-hover" title="Aplicar filtros" style="z-index: 0;">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        
                        <a href="<?php echo e(route_url('ventas/index')); ?>" class="btn btn-light border text-danger px-3 transition-hover d-flex align-items-center spa-link" title="Limpiar filtros" style="z-index: 0;">
                            <i class="bi bi-eraser-fill"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaVentas"
                       data-manager-global="ventasManager" 
                       data-erp-table="true"
                       data-search-input="#filtroBusqueda"
                       data-pagination-controls="#ventasPaginationControls"
                       data-pagination-info="#ventasPaginationInfo"
                       data-erp-filters='[{"el":"#filtroEstado","attr":"data-estado","match":"equals"}]'>
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Cliente</th>
                            <th class="text-secondary fw-semibold">Fechas</th>
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
                                    
                                    <!-- 👇 COLUMNA 1: CÓDIGO Y HUELLA DE REGISTRO 👇 -->
                                    <td class="ps-4">
                                        <div class="fw-bold text-primary"><?php echo e((string) ($venta['codigo'] ?? '')); ?></div>
                                        <div class="text-muted mt-1" style="font-size: 0.7rem;" title="Huella de registro en el sistema">
                                            <i class="bi bi-clock"></i> Reg: <?php echo isset($venta['created_at']) ? date('d/m/Y H:i', strtotime($venta['created_at'])) : '-'; ?>
                                        </div>
                                    </td>
                                    
                                    <!-- 👇 COLUMNA 2: CLIENTE Y OBSERVACIONES 👇 -->
                                    <td>
                                        <div class="fw-semibold text-dark"><?php echo e((string) ($venta['cliente'] ?? '')); ?></div>
                                        
                                        <?php 
                                            $obsVenta = trim((string) ($venta['observaciones'] ?? ''));
                                            $obsDespacho = trim((string) ($venta['observaciones_despacho'] ?? '')); 
                                        ?>

                                        <?php if ($obsVenta !== '' || $obsDespacho !== ''): ?>
                                            <div class="small text-muted mt-1 d-flex flex-wrap gap-2 align-items-center">
                                                
                                                <?php if ($obsVenta !== ''): ?>
                                                    <span><?php echo e($obsVenta); ?></span>
                                                <?php endif; ?>
                                                
                                                <?php if ($obsVenta !== '' && $obsDespacho !== ''): ?>
                                                    <span class="text-secondary opacity-50">|</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($obsDespacho !== ''): ?>
                                                    <span title="Nota de guía / despacho">
                                                        <i class="bi bi-truck text-info me-1"></i><?php echo e($obsDespacho); ?>
                                                    </span>
                                                <?php endif; ?>
                                                
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- 👇 COLUMNA 3: FECHAS COMERCIALES 👇 -->
                                    <td>
                                        <!-- Fecha de Emisión (Realidad Comercial) -->
                                        <div class="fw-bold text-dark mb-1">
                                            <i class="bi bi-calendar3 me-1 text-primary"></i> <?= e($formatearFechaDMY($venta['fecha_emision'])) ?>
                                        </div>
                                        
                                        <!-- Fecha de Despacho -->
                                        <?php if (!empty($venta['fecha_despacho'])): ?>
                                            <div class="text-success small fw-semibold">
                                                <i class="bi bi-truck me-1"></i> Despachado: <?= e($formatearFechaDMY($venta['fecha_despacho'])) ?>
                                            </div>
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
                                                
                                            <?php elseif ($estado === 2 || $estado === 6): ?>
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-revertir rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Volver a Borrador">
                                                    <i class="bi bi-arrow-counterclockwise fs-5"></i>
                                                </button>

                                                <button class="btn btn-sm btn-light text-info border-0 btn-despachar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Despachar Mercadería"><i class="bi bi-truck fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                                <button class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-id="<?php echo (int) ($venta['id'] ?? 0); ?>" data-bs-toggle="tooltip" title="Anular Pedido"><i class="bi bi-trash fs-5"></i></button>
                                                
                                            <?php elseif ($estado === 3 || $estado === 5): ?>
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
                            
                            <div class="row g-3 mt-1 align-items-end">
                                <div class="col-12">
                                    <label for="ventaObservaciones" class="form-label text-muted small fw-bold mb-1">Observaciones / Motivo</label>
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="ventaObservaciones" maxlength="180" placeholder="Ej: Donación para evento benéfico local">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-white rounded-top d-flex align-items-center gap-3">
                                <h6 class="mb-0 fw-bold text-dark">Detalle de Productos</h6>
                                <div id="alertaBorradorContenedor"></div> 
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleVenta">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th class="text-center text-secondary col-w-40 py-2">#</th>
                                            <th class="ps-3 text-secondary col-min-w-300 py-2">Producto</th>
                                            <th class="text-end text-secondary col-w-100 py-2">Stock</th>
                                            <th class="text-center text-secondary col-w-120 py-2">Cantidad</th>
                                            <th class="text-center text-secondary col-w-140 py-2">Precio Unit.</th>
                                            <th class="text-end text-secondary col-w-140 py-2">Subtotal</th>
                                            <th class="text-center col-w-60 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="3" class="ps-3 py-3 align-middle border-bottom-0">
                                                <div class="d-flex align-items-center gap-3">
                                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnAgregarFilaVenta">
                                                        <i class="bi bi-plus-lg me-1"></i>Agregar Producto
                                                    </button>
                                                    
                                                    <!-- NUEVO BOTÓN AQUÍ -->
                                                    <button type="button" class="btn btn-sm btn-outline-info fw-semibold" id="btnMostrarTablaRegalos">
                                                        <i class="bi bi-gift me-1"></i>Añadir Bonificación
                                                    </button>
                                                    
                                                    <div class="d-none d-sm-flex align-items-center bg-white border border-secondary-subtle rounded-2 px-2 py-1 shadow-sm">
                                                        <i class="bi bi-box-seam text-muted me-2"></i>
                                                        <span class="text-muted small fw-bold me-1">Peso est:</span>
                                                        <span class="text-dark fw-bold small" id="ventaPesoTotal">0.000 kg</span>
                                                    </div>
                                                </div>
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

                    <!-- NUEVA SECCIÓN DE REGALOS -->
                    <div class="card border-info-subtle shadow-sm mt-3 d-none fade-in" id="seccionRegalos">
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-info-subtle rounded-top d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-gift text-info-emphasis me-2 fs-5"></i>
                                    <h6 class="mb-0 fw-bold text-info-emphasis">Productos de Regalo / Bonificaciones</h6>
                                </div>
                                <button type="button" class="btn-close btn-sm" id="btnCerrarTablaRegalos" aria-label="Cerrar"></button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-pro" id="tablaDetalleRegalos">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th class="text-center text-secondary col-w-40 py-2">#</th>
                                            <th class="ps-3 text-secondary col-min-w-300 py-2">Producto a Regalar</th>
                                            <th class="text-end text-secondary col-w-100 py-2">Stock</th>
                                            <th class="text-center text-secondary col-w-120 py-2">Cantidad</th>
                                            <th class="text-center text-secondary col-w-140 py-2">Valor Ref.</th>
                                            <th class="text-end text-secondary col-w-140 py-2">Subtotal</th>
                                            <th class="text-center col-w-60 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="7" class="ps-3 py-3 align-middle border-bottom-0">
                                                <div class="d-flex align-items-center">
                                                    <button type="button" class="btn btn-sm btn-info text-white fw-semibold shadow-sm" id="btnAgregarFilaRegalo">
                                                        <i class="bi bi-plus-lg me-1"></i>Agregar Regalo
                                                    </button>
                                                    <small class="text-info-emphasis ms-3 fw-semibold">
                                                        <i class="bi bi-info-circle-fill me-1"></i>Estos productos descontarán stock pero no sumarán al total a cobrar.
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <!-- FIN SECCIÓN DE REGALOS -->
                    
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

                <div class="card border-success-subtle shadow-sm mt-4 d-none fade-in" id="seccionCobroInmediato">
                    <div class="card-body p-3 bg-success-subtle rounded">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-success-emphasis">Registro de Pago Rápido</h6>
                                <small class="text-success-emphasis opacity-75">Selecciona cómo está pagando el cliente en este momento.</small>
                            </div>
                        </div>

                        <div id="alertaSaldoFavorContenedor" class="mb-3"></div>

                        <div id="contenedorMetodosPago" class="d-flex flex-column gap-2 mb-2">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" id="btnAgregarPagoInmediato">
                                <i class="bi bi-plus-circle me-1"></i> Añadir otro método
                            </button>
                            <div class="text-end">
                                <small class="text-success-emphasis fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL PAGADO</small>
                                <span class="fw-bold text-dark fs-5" id="totalPagadoInmediato">S/ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="form-check form-switch m-0 ps-5" id="switchCobroContainer">
                    <input class="form-check-input" type="checkbox" id="switchCobroInmediato" style="cursor: pointer;">
                    <label class="form-check-label fw-bold text-primary small" for="switchCobroInmediato" style="cursor: pointer;">
                        Cobrar Al Contado (Inmediato)
                    </label>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary px-4 fw-bold" id="btnGuardarVenta"><i class="bi bi-save me-2"></i>Guardar Pedido</button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDespacho" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            
            <div class="modal-header bg-info text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold d-flex flex-wrap align-items-center gap-2"><span><i class="bi bi-truck me-2"></i>Despachar Pedido</span><small id="despachoClienteNombre" class="fw-semibold text-white-50"></small></h5>
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
                        <div class="table-responsive border rounded-3 mb-0">
                            <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleDespacho">
                                <thead>
                                    <tr>
                                        <th class="ps-3 text-secondary col-min-w-200 py-2">Producto / Pendiente</th>
                                        <th class="text-center text-secondary col-w-180 py-2">Almacén Origen</th>
                                        <th class="text-center text-secondary col-w-80 py-2">Stock</th>
                                        <th class="text-center text-secondary col-w-120 py-2">A Despachar</th>
                                        <th class="text-center text-secondary col-w-100 py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-none fade-in" id="seccionRetornoEnvasesDespacho">
                    <h6 class="fw-bold text-dark mb-2 d-flex align-items-center" style="font-size: 0.95rem;">
                        <i class="bi bi-recycle text-success me-2 fs-5"></i>Retorno de Envases Vacíos
                    </h6>
                    <div id="contenedorRetornoEnvases" class="d-flex flex-column gap-2"></div>
                </div>

                <div class="card border-success-subtle shadow-sm mt-4 d-none fade-in" id="seccionCobroDespacho">
                    <div class="card-body p-3 bg-success-subtle rounded">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-success-emphasis">Registro de Pago Rápido</h6>
                                <small class="text-success-emphasis opacity-75">Selecciona cómo está pagando el cliente al recibir la mercadería.</small>
                            </div>
                        </div>

                        <div id="contenedorMetodosPagoDespacho" class="d-flex flex-column gap-2 mb-2">
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" id="btnAgregarPagoDespacho">
                                <i class="bi bi-plus-circle me-1"></i> Añadir otro método
                            </button>
                            <div class="text-end">
                                <small class="text-success-emphasis fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL A COBRAR</small>
                                <span class="fw-bold text-dark fs-5" id="totalPagadoDespacho">S/ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-4 ps-2">
                    <div class="form-check m-0 d-flex align-items-center">
                        <input class="form-check-input border-danger me-2" type="checkbox" id="cerrarForzado" style="transform: scale(1.2); cursor: pointer;">
                        <label class="form-check-label text-danger fw-bold small mt-1" for="cerrarForzado" style="cursor: pointer;" title="El pedido pasará a Cerrado y los productos faltantes quedarán cancelados.">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Finalizar pedido y anular saldo
                        </label>
                    </div>
                    
                    <div id="contenedorCobroDespacho">
                        <div class="form-check form-switch m-0" id="switchCobroDespachoContainer">
                            <input class="form-check-input border-success" type="checkbox" id="switchCobroDespacho" style="cursor: pointer;">
                            <label class="form-check-label fw-bold text-success small" for="switchCobroDespacho" style="cursor: pointer;">
                                Cobrar al entregar
                            </label>
                        </div>

                        <div id="mensajePagoCompletoDespacho" class="d-none">
                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                                <i class="bi bi-check-circle-fill me-1"></i> Pago completado en Borrador
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
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
                    <!-- Columna 1: El Motivo (Lo único que el usuario debe elegir) -->
                    <div class="col-md-7">
                        <label class="form-label fw-bold small text-muted">Motivo de Devolución <span class="text-danger">*</span></label>
                        <select id="devolucionVentaMotivo" class="form-select border-warning-subtle shadow-sm" required>
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
                        <small id="devolucionVentaMotivoHint" class="text-muted d-block mt-1">Selecciona por qué regresa la mercadería.</small>
                    </div>
                    
                    <!-- Columna 2: Mensaje Automático (Reemplaza al Select) -->
                    <div class="col-md-5">
                        <label class="form-label fw-bold small text-muted">Impacto Financiero</label>
                        <div class="p-2 bg-light border border-secondary-subtle rounded text-secondary" style="font-size: 0.8rem; line-height: 1.4;">
                            <i class="bi bi-info-circle-fill text-primary me-1"></i>
                            El valor devuelto <strong>descontará la deuda</strong> automáticamente. Si el pedido ya está pagado, se generará un <strong>Saldo a Favor</strong> para el cliente.
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive border rounded-3 mb-0">
                            <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleDevolucionVenta">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="ps-3 border-bottom-0 py-2">Producto / Ítem</th>
                                        <th class="text-center border-bottom-0 col-w-150 py-2">Cant. Despachada</th>
                                        <th class="text-center border-bottom-0 col-w-150 py-2">Precio Unit.</th>
                                        <th class="text-center border-bottom-0 col-w-140 py-2">Cantidad</th>
                                        <th class="text-end pe-4 border-bottom-0 col-w-150 py-2">Monto Devuelto</th>
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
                        <option value="imprimir_nota_venta">Nota de Venta / Liquidación (Detalle y Deuda)</option>
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
        if (selectTipo) selectTipo.value = 'imprimir'; 

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
            const selectTipo = document.getElementById('tipoDocumentoImprimir'); 
            
            if (!app || !inputPaginas || window.pedidoIdPendienteImpresion <= 0) return;

            const baseUrl = app.dataset.urlIndex;
            const paginas = Math.max(1, Math.min(20, Number(inputPaginas.value) || 1));
            const accionImpresion = selectTipo ? selectTipo.value : 'imprimir';

            const modalEl = document.getElementById('modalImpresionPedido');
            if (modalEl && typeof bootstrap !== 'undefined') {
                bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            }

            window.open(`${baseUrl}&accion=${accionImpresion}&id=${window.pedidoIdPendienteImpresion}&paginas=${paginas}`, '_blank');
        });
    })();
</script>

<div class="modal fade" id="modalResumenVenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg bg-light">
            
            <!-- Encabezado -->
            <div class="modal-header bg-success text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-check-circle-fill me-2"></i>Resumen de Venta Finalizada
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Cuerpo del Modal -->
            <div class="modal-body p-3 p-md-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1.2rem; border-top-right-radius: 1.2rem; position: relative;">
                
                <!-- Tarjeta de Información General -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 p-md-4">
                        
                        <!-- Título del Pedido -->
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <h6 class="fw-bold text-dark mb-0">Información del Pedido</h6>
                            <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill" id="resumenVentaCodigo">OC-0000</span>
                        </div>
                        
                        <!-- Grid de Datos -->
                        <div class="row g-4">
                            
                            <!-- Bloque Izquierdo (Cliente, Fechas, Obs) -->
                            <div class="col-12 col-lg-8">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Cliente / Beneficiario</small>
                                        <div class="fw-semibold text-dark text-break" id="resumenVentaCliente">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Tipo de Operación</small>
                                        <div class="fw-semibold text-dark text-break" id="resumenVentaOperacion">-</div>
                                    </div>
                                    
                                    <div class="col-12"><hr class="text-muted opacity-25 my-1"></div>
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Registro / Venta</small>
                                        <div class="text-dark small text-truncate" title="Usuario que registró el pedido">
                                            <i class="bi bi-person-circle text-secondary me-1"></i><span id="resumenVentaUsuarioRegistro" class="fw-medium">-</span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Despacho</small>
                                        <div class="text-dark small text-truncate" title="Usuario que despachó la mercadería">
                                            <i class="bi bi-person-check-fill text-success me-1"></i><span id="resumenVentaUsuarioDespacho" class="fw-medium">-</span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-2">Observaciones</small>
                                        <div class="text-secondary small mb-1 text-break" id="resumenVentaObsPedido">
                                            <i class="bi bi-file-earmark-text me-1"></i><strong class="d-none d-sm-inline">Pedido:</strong> <span>-</span>
                                        </div>
                                        <div class="text-secondary small text-break" id="resumenVentaObsDespacho">
                                            <i class="bi bi-truck me-1"></i><strong class="d-none d-sm-inline">Despacho:</strong> <span>-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bloque Derecho (Estado de Pago) - Estilo Tarjeta Interna -->
                            <div class="col-12 col-lg-4">
                                <div class="bg-light p-3 rounded-3 border h-100">
                                    <small class="text-muted fw-bold d-block mb-2">Estado de Pago</small>
                                    
                                    <!-- Badge de Estado -->
                                    <div id="resumenVentaEstadoPagoBadge" class="mb-2"></div>
                                    
                                    <!-- Monto abonado -->
                                    <div class="small text-muted mb-3" id="resumenVentaMontoPendiente" style="line-height: 1.3;"></div>

                                    <!-- Contenedor para el desglose de multipagos -->
                                    <div id="resumenVentaModalidadPago" class="small mb-2 bg-white p-2 rounded border-secondary border-opacity-25" style="display: none; border: 1px solid;">
                                        <div class="text-muted fw-bold mb-1"><i class="bi bi-wallet2 text-success me-1"></i>Detalle de Pago:</div>
                                        <ul id="lista_pagos_detallados" class="mb-0 ps-3 text-dark" style="font-size: 0.85rem;">
                                            <!-- JS inyectará los <li> aquí -->
                                        </ul>
                                    </div>

                                    <!-- Contenedor para la deuda -->
                                    <div id="resumenVentaDeuda" class="small mt-auto" style="display: none; background-color: #fff5f5; padding: 10px; border-radius: 6px; border: 1px solid #ffeeba;">
                                        <i class="bi bi-exclamation-circle text-danger me-1"></i>
                                        <span class="text-danger fw-semibold">Falta pagar:</span>
                                        <strong class="text-danger fs-5 d-block mt-1" id="val_deuda_pendiente">-</strong>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- TARJETA: HISTORIAL DE ENTREGAS / DESPACHOS -->
                <!-- ========================================================= -->
                <div class="card border-info-subtle shadow-sm mb-4 d-none fade-in" id="resumenVentaDespachosContenedor">
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom bg-info-subtle rounded-top d-flex align-items-center justify-content-between">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-truck text-info-emphasis me-2 fs-5"></i>
                                <h6 class="mb-0 fw-bold text-info-emphasis">Historial de Entregas Realizadas</h6>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover" id="tablaResumenDespachos">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 text-secondary small fw-bold" style="min-width: 150px;">Fecha / Hora</th>
                                        <th class="text-secondary small fw-bold">Guía / Observación</th>
                                        <th class="text-secondary small fw-bold">Almacén Origen</th>
                                        <th class="text-secondary small fw-bold" style="min-width: 250px;">Productos Entregados</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <!-- Contenido inyectado por JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- ========================================================= -->

                <!-- Tarjeta de Productos -->
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom bg-white rounded-top d-flex align-items-center">
                            <i class="bi bi-box-seam text-primary me-2 fs-5"></i>
                            <h6 class="mb-0 fw-bold text-dark">Productos Despachados</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover" id="tablaResumenProductos">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 text-secondary small fw-bold text-nowrap">Producto</th>
                                        <th class="text-center text-secondary small fw-bold text-nowrap">Cant. Sol</th>
                                        <th class="text-center text-secondary small fw-bold text-nowrap">Cant. Desp</th>
                                        <th class="text-end text-secondary small fw-bold text-nowrap">Precio Unit.</th>
                                        <th class="text-end pe-3 text-secondary small fw-bold text-nowrap">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <!-- Contenido inyectado por JS -->
                                </tbody>
                                <tfoot class="bg-light border-top">
                                    <tr>
                                        <td colspan="2" class="ps-3 py-3">
                                            <span class="badge rounded-pill text-bg-light border text-secondary fw-semibold px-3 py-2" id="resumenVentaPesoTotal">Peso total: 0.000 kg</span>
                                        </td>
                                        <td colspan="2" class="text-end fw-bold py-3 text-secondary align-middle">TOTAL FINAL:</td>
                                        <td class="text-end fw-bold py-3 fs-5 text-primary pe-3 text-nowrap align-middle" id="resumenVentaTotalFinal">S/ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta de Historial de Devoluciones (Aparece solo si hay devoluciones) -->
                <div class="card border-warning-subtle shadow-sm mt-4 d-none fade-in" id="resumenVentaDevolucionesContenedor">
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom bg-warning-subtle rounded-top d-flex align-items-center">
                            <i class="bi bi-arrow-return-left text-warning-emphasis me-2 fs-5"></i>
                            <h6 class="mb-0 fw-bold text-warning-emphasis">Historial de Devoluciones</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-hover" id="tablaResumenDevoluciones">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 text-secondary small fw-bold">Fecha / Hora</th>
                                        <th class="text-secondary small fw-bold">Motivo</th>
                                        <th class="text-secondary small fw-bold">Productos Devueltos</th>
                                        <th class="text-end pe-3 text-secondary small fw-bold">Monto Devuelto</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                    <!-- Contenido inyectado por JS -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Pie del Modal -->
            <div class="modal-footer bg-white border-top-0 rounded-bottom">
                <button type="button" class="btn btn-secondary fw-semibold px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
            
        </div>
    </div>
</div>

<template id="templateFilaVenta">
    <tr class="border-bottom">
        <td class="text-center fw-bold text-muted align-top py-3 fila-numero bg-light-subtle" style="font-size: 0.85rem;">1</td>
        <td class="ps-3 py-3 align-top" data-label="Producto">
            <select class="form-select form-select-sm detalle-item shadow-none border-secondary-subtle" required></select>
            
            <div class="mt-1 d-flex flex-column gap-1">
                <small class="text-muted d-none detalle-peso-info" style="font-size: 0.75rem;">
                    <i class="bi bi-box-seam me-1"></i><span class="peso-unitario">0.00</span> kg c/u (Total: <span class="peso-subtotal">0.00</span> kg)
                </small>
            </div>
        </td>
        <td class="text-end text-muted small fw-bold py-3 px-2 align-top detalle-stock" data-label="Stock Disponible">0.00</td>
        <td class="align-top py-3 px-2" data-label="Cantidad">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-primary shadow-none border-secondary-subtle" min="0" step="1" value="" required>
        </td>
        <td class="align-top py-3 px-2" data-label="Precio Unit.">
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted bg-light border-secondary-subtle">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-precio shadow-none border-secondary-subtle" min="0" step="0.0001" value="0.00" required>
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

<!-- TEMPLATE PARA FILAS DE REGALO -->
<template id="templateFilaRegalo">
    <tr class="border-bottom bg-info bg-opacity-10">
        <td class="text-center fw-bold text-muted align-top py-3 fila-numero bg-light-subtle" style="font-size: 0.85rem;">1</td>
        <td class="ps-3 py-3 align-top" data-label="Producto">
            <select class="form-select form-select-sm detalle-item shadow-none border-info-subtle" required></select>
            
            <div class="mt-1 d-flex flex-column gap-1">
                <small class="text-muted d-none detalle-peso-info" style="font-size: 0.75rem;">
                    <i class="bi bi-box-seam me-1"></i><span class="peso-unitario">0.00</span> kg c/u (Total: <span class="peso-subtotal">0.00</span> kg)
                </small>
            </div>
        </td>
        <td class="text-end text-muted small fw-bold py-3 px-2 align-top detalle-stock" data-label="Stock Disponible">0.00</td>
        <td class="align-top py-3 px-2" data-label="Cantidad">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-info shadow-none border-info-subtle" min="0" step="1" value="" required>
        </td>
        <td class="align-top py-3 px-2" data-label="Valor Ref.">
            <div class="input-group input-group-sm opacity-75" title="Valor referencial - No se cobra">
                <span class="input-group-text border-end-0 text-muted bg-light border-info-subtle">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-precio shadow-none border-info-subtle text-muted bg-light" min="0" step="0.0001" value="0.00" readonly tabindex="-1">
            </div>
        </td>
        <td class="text-end align-top py-3 fw-bold text-success detalle-subtotal fs-6" data-label="Subtotal">
            S/ 0.00 <br><span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 0.65rem;">GRATIS</span>
        </td>
        <td class="text-center align-top py-3" data-label="Acción">
            <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-fila p-1" data-bs-toggle="tooltip" title="Quitar regalo" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    </tr>
</template>

<template id="templateFilaPago">
    <div class="d-flex align-items-center gap-2 mb-2 fila-pago fade-in">
        <!-- Select de Cuentas -->
        <select class="form-select form-select-sm shadow-none pago-cuenta border-success-subtle select-cuenta-inmediato" required>
            <option value="" selected disabled>Cuenta Destino...</option>
            <?php foreach ($cuentas as $cuenta): ?>
                <option value="<?php echo (int) ($cuenta['id'] ?? 0); ?>">
                    <?php echo e($cuenta['nombre'] ?? ''); ?> (<?php echo e($cuenta['moneda'] ?? 'PEN'); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        
        <!-- Select de Métodos (AHORA VACÍO PARA FILTRADO DINÁMICO) -->
        <select class="form-select form-select-sm shadow-none pago-metodo border-success-subtle select-metodo-inmediato" required disabled>
            <option value="" selected disabled>Método...</option>
        </select>
        
        <!-- Monto -->
        <div class="input-group input-group-sm" style="width: 150px;">
            <span class="input-group-text bg-success-subtle text-success border-success-subtle fw-bold">S/</span>
            <input type="number" class="form-control text-end shadow-none pago-monto border-success-subtle fw-bold text-dark input-monto-inmediato" min="0.01" step="0.01" placeholder="0.00" required readonly>
        </div>
        
        <!-- Botón Eliminar Fila -->
        <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-pago p-1" data-bs-toggle="tooltip" title="Quitar método" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-trash-fill"></i>
        </button>
    </div>
</template>