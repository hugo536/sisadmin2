<?php
/** @var array $ordenes */
/** @var array $filtros */
/** @var array $proveedores */
/** @var array $items */
/** @var array $almacenes */
/** @var array $centros_costo */
/** @var array $cuentas */
/** @var array $metodos */

$ordenes = $ordenes ?? [];
$filtros = $filtros ?? [];
$proveedores = $proveedores ?? [];
$items = $items ?? [];
$almacenes = $almacenes ?? [];
$centros_costo = $centros_costo ?? [];
$cuentas = $cuentas ?? []; 
$metodos = $metodos ?? []; 

// Configuración de Estados (Estilo Subtle alineado con Ventas)
$estadoLabels = [
    0 => ['texto' => 'Borrador', 'clase' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    1 => ['texto' => 'Pendiente', 'clase' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
    2 => ['texto' => 'Aprobada', 'clase' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    3 => ['texto' => 'Recepcionada', 'clase' => 'bg-success-subtle text-success border border-success-subtle'],
    9 => ['texto' => 'Anulada', 'clase' => 'bg-dark-subtle text-dark border border-dark-subtle'],
];

// Formateador de fechas
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

<!-- Si tienes un CSS específico para compras o prefieres reusar el de ventas -->
<link rel="stylesheet" href="<?= e(base_url('assets/css/compras.css')) ?>?v=<?= time() ?>">
<link rel="stylesheet" href="<?= e(asset_url('css/compras.css')) ?>?v=1.0">

<div class="container-fluid p-4" id="comprasApp"
     data-url-index="<?= e(route_url('compras')) ?>"
     data-url-guardar="<?= e(route_url('compras/guardar')) ?>"
     data-url-aprobar="<?= e(route_url('compras/aprobar')) ?>"
     data-url-revertir-borrador="<?= e(route_url('compras/revertirBorrador')) ?>"
     data-url-anular="<?= e(route_url('compras/anular')) ?>"
     data-url-recepcionar="<?= e(route_url('compras/recepcionar')) ?>"
     data-url-unidades-item="<?= e(route_url('compras')) ?>"
     data-url-precio-sugerido="<?= e(route_url('compras')) ?>"
     data-cuentas="<?php echo htmlspecialchars(json_encode($cuentas ?? []), ENT_QUOTES, 'UTF-8'); ?>"
     data-metodos="<?php echo htmlspecialchars(json_encode($metodos ?? []), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- ENCABEZADO Y BOTONES -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cart-check-fill me-2 text-primary"></i> Compras y Recepción
            </h1>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="<?= e(route_url('reportes/compras')) ?>" class="btn btn-light shadow-sm text-secondary fw-semibold border">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Reporte Compras
            </a>
            <button type="button" class="btn btn-primary shadow-sm fw-semibold" id="btnNuevaOrden">
                <i class="bi bi-plus-circle-fill me-2"></i>Nueva Orden
            </button>
        </div>
    </div>

    <!-- TARJETA DE FILTROS ESTILO VENTAS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" action="" class="row g-2 align-items-center" id="formFiltrosCompras">
                <input type="hidden" name="ruta" value="compras">

                <div class="col-12 col-lg-3">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" name="q" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="filtroBusqueda" placeholder="Buscar código, proveedor..." value="<?= e((string) ($filtros['q'] ?? '')) ?>">
                    </div>
                </div>
                
                <div class="col-12 col-lg-2">
                    <select name="estado" class="form-select bg-light border-secondary-subtle shadow-sm text-secondary" id="filtroEstado">
                        <option value="">Todos los estados</option>
                        <?php foreach ($estadoLabels as $key => $info): ?>
                            <option value="<?= (int) $key ?>" <?= ($filtros['estado'] ?? '') === (string) $key ? 'selected' : '' ?>>
                                <?= e($info['texto']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-lg-2">
                    <select name="orden_fecha" class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-semibold" id="filtroOrdenFecha">
                        <option value="orden" <?= (($filtros['orden_fecha'] ?? 'orden') === 'orden') ? 'selected' : '' ?>>Fecha: Orden</option>
                        <option value="recepcion" <?= (($filtros['orden_fecha'] ?? '') === 'recepcion') ? 'selected' : '' ?>>Fecha: Recepción</option>
                    </select>
                </div>

                <div class="col-12 col-md-12 col-lg-5">  
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" id="filtroFechaDesde" class="form-control bg-light border-start-0 border-end-0 border-secondary-subtle" value="<?= e((string) ($filtros['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days')))) ?>">
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" id="filtroFechaHasta" class="form-control bg-light border-start-0 border-secondary-subtle" value="<?= e((string) ($filtros['fecha_hasta'] ?? date('Y-m-d'))) ?>">
                        
                        <button type="button" id="btnFiltrarFechas" class="btn btn-light border text-primary px-3 transition-hover" title="Aplicar filtros" style="z-index: 0;">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        
                        <a href="<?= e(route_url('compras')) ?>" class="btn btn-light border text-danger px-3 transition-hover d-flex align-items-center spa-link" title="Limpiar filtros" style="z-index: 0;">
                            <i class="bi bi-eraser-fill"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA PRINCIPAL DE COMPRAS -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="tablaCompras"
                       data-manager-global="comprasManager"
                       data-erp-table="true"
                       data-search-input="#filtroBusqueda"
                       data-pagination-controls="#comprasPaginationControls"
                       data-pagination-info="#comprasPaginationInfo"
                       data-erp-filters='[{"el":"#filtroEstado","attr":"data-estado","match":"equals"}]'>
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Proveedor</th>
                            <th class="text-secondary fw-semibold">Fechas</th>
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
                                    if ($estado === 4) { $estado = 3; } // Parche migración

                                    if ($estado === 9 && (!isset($filtros['estado']) || $filtros['estado'] !== '9')) { continue; }
                                    $badge = $estadoLabels[$estado] ?? $estadoLabels[0]; 
                                ?>
                                <tr data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-estado="<?= $estado ?>" class="border-bottom" data-search="<?= e(mb_strtolower((string) ($orden['codigo'] ?? '') . ' ' . (string) ($orden['proveedor'] ?? ''))) ?>">
                                    
                                    <!-- COLUMNA CÓDIGO Y HUELLA -->
                                    <td class="ps-4">
                                        <div class="fw-bold text-primary"><?= e((string) ($orden['codigo'] ?? '')) ?></div>
                                        <div class="text-muted mt-1" style="font-size: 0.7rem;" title="Huella de registro en el sistema">
                                            <i class="bi bi-clock"></i> Reg: <?= isset($orden['created_at']) ? date('d/m/Y H:i', strtotime($orden['created_at'])) : '-' ?>
                                        </div>
                                    </td>
                                    
                                    <!-- COLUMNA PROVEEDOR Y OBSERVACIONES -->
                                    <td>
                                        <div class="fw-semibold text-dark"><?= e((string) ($orden['proveedor'] ?? '')) ?></div>
                                        <?php if (!empty($orden['observacion_subtitulo'])): ?>
                                            <div class="small text-muted mt-1 d-flex flex-wrap gap-2 align-items-center">
                                                <span title="Nota de la orden"><i class="bi bi-file-earmark-text text-secondary me-1"></i><?= e((string) $orden['observacion_subtitulo']) ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- COLUMNA FECHAS -->
                                    <td>
                                        <div class="fw-bold text-dark mb-1">
                                            <i class="bi bi-calendar3 me-1 text-primary"></i> 
                                            <?= e($formatearFechaDMY($orden['fecha_orden'] ?? $orden['fecha_documento'] ?? '')) ?>
                                        </div>
                                        <?php if (!empty($orden['fecha_recepcion'])): ?>
                                            <div class="text-success small fw-semibold">
                                                <i class="bi bi-box-arrow-in-down me-1"></i> Recepción: <?= e($formatearFechaDMY($orden['fecha_recepcion'])) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- COLUMNA TOTAL -->
                                    <td class="text-end fw-bold text-dark fs-6">
                                        <?= strtoupper($orden['moneda'] ?? 'PEN') === 'USD' ? '$' : 'S/' ?> 
                                        <?= number_format((float) ($orden['total'] ?? 0), 2) ?>
                                    </td>

                                    <!-- COLUMNA ESTADO -->
                                    <td class="text-center">
                                        <span class="badge px-3 py-2 rounded-pill <?= e($badge['clase']) ?>">
                                            <?= e($badge['texto']) ?>
                                        </span>
                                    </td>

                                    <!-- COLUMNA ACCIONES -->
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php if ($estado === 0): ?> 
                                                <button type="button" class="btn btn-sm btn-light text-primary border-0 btn-editar rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Editar Orden"><i class="bi bi-pencil-square fs-5"></i></button>
                                                <button type="button" class="btn btn-sm btn-light text-success border-0 btn-aprobar rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Aprobar Orden"><i class="bi bi-check2-circle fs-5"></i></button>
                                                <button type="button" class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Anular Orden"><i class="bi bi-trash fs-5"></i></button>
                                            <?php elseif ($estado === 2): ?> 
                                                <button type="button" class="btn btn-sm btn-light text-secondary border-0 btn-revertir-borrador rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Revertir a Borrador"><i class="bi bi-arrow-counterclockwise fs-5"></i></button>
                                                <button type="button" class="btn btn-sm btn-light text-info border-0 btn-recepcionar rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Recepcionar Mercadería"><i class="bi bi-box-seam fs-5"></i></button>
                                                <button type="button" class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php elseif ($estado === 3): ?> 
                                                <button type="button" class="btn btn-sm btn-light text-warning border-0 btn-devolver rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Registrar Devolución/Ajuste"><i class="bi bi-arrow-return-left fs-5"></i></button>
                                                <button type="button" class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
                                            <?php else: ?> 
                                                <button type="button" class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle" data-id="<?= (int) ($orden['id'] ?? 0) ?>" data-bs-toggle="tooltip" title="Ver Detalle"><i class="bi bi-eye fs-5"></i></button>
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

<!-- ============================================== -->
<!-- MODAL: NUEVA / EDITAR ORDEN DE COMPRA          -->
<!-- ============================================== -->
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
                    
                    <!-- Tarjeta Info General -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información General</h6>
                            <div class="row g-3 align-items-end mb-3">
                                <div class="col-md-4">
                                    <label for="idProveedor" class="form-label text-muted small fw-bold mb-1">Proveedor <span class="text-danger">*</span></label>
                                    <select id="idProveedor" class="form-select shadow-none" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($proveedores as $proveedor): ?>
                                            <option value="<?= (int) ($proveedor['id'] ?? 0) ?>">
                                                <?= e((string) ($proveedor['nombre_completo'] ?? '')) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-3">
                                    <label for="fechaEntrega" class="form-label text-muted small fw-bold mb-1">Fecha Emisión <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control shadow-none" id="fechaEntrega" value="<?= date('Y-m-d') ?>" required>
                                </div>

                                <div class="col-md-2">
                                    <label for="ordenMoneda" class="form-label text-primary small fw-bold mb-1">Moneda <span class="text-danger">*</span></label>
                                    <select id="ordenMoneda" class="form-select border-primary-subtle fw-bold text-primary shadow-none" required>
                                        <option value="PEN" selected>PEN (S/)</option>
                                        <option value="USD">USD ($)</option>
                                    </select>
                                </div>

                                <div class="col-md-3">
                                    <label for="tipoImpuesto" class="form-label text-muted small fw-bold mb-1">Impuestos <span class="text-danger">*</span></label>
                                    <select id="tipoImpuesto" class="form-select shadow-none" required>
                                        <option value="incluido" selected>Incluyen IGV</option>
                                        <option value="mas_igv">NO incluyen IGV (+18%)</option>
                                        <option value="exonerado">Exonerado (0%)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-12">
                                    <label for="observaciones" class="form-label text-muted small fw-bold mb-1">Observaciones</label>
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="observaciones" maxlength="180" placeholder="Ej: Condiciones de crédito, referencias, etc.">
                                </div>        
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta Detalle Productos -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-white rounded-top d-flex align-items-center">
                                <h6 class="mb-0 fw-bold text-dark">Detalle de Productos</h6>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleCompra">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th class="text-center text-secondary col-w-40 py-2">#</th>
                                            <th class="ps-3 text-secondary col-min-w-320 py-2">Ítem / Producto</th>
                                            <th class="text-center text-secondary col-w-140 py-2">Cantidad</th>
                                            <th class="text-center text-secondary col-w-160 py-2">Costo Unit.</th>
                                            <th class="text-center text-secondary col-w-200 py-2">Centro de Costo</th>
                                            <th class="text-end text-secondary col-w-150 py-2">Subtotal</th>
                                            <th class="text-center col-w-60 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="4" class="ps-3 py-3 align-middle border-bottom-0">
                                                <div class="d-flex align-items-center gap-3">
                                                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" id="btnAgregarFila">
                                                        <i class="bi bi-plus-lg me-1"></i>Agregar Ítem
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-info fw-semibold" id="btnMostrarTablaBonificaciones">
                                                        <i class="bi bi-gift me-1"></i>Añadir Bonificación
                                                    </button>
                                                </div>
                                            </td>
                                            <td colspan="3" class="pe-4 py-3 align-middle border-bottom-0">
                                                <div class="d-flex flex-wrap justify-content-end align-items-center gap-3 gap-md-4">
                                                    <div class="d-flex flex-column text-end">
                                                        <span class="text-muted small fw-bold mb-1">SUBTOTAL</span>
                                                        <span class="text-dark fw-bold" id="ordenSubtotal">S/ 0.00</span>
                                                    </div>
                                                    
                                                    <div class="vr d-none d-sm-block bg-secondary opacity-25" style="width: 2px;"></div>
                                                    
                                                    <div class="d-flex flex-column text-end">
                                                        <span class="text-muted small fw-bold mb-1">IGV (18%)</span>
                                                        <span class="text-dark fw-bold" id="ordenIgv">S/ 0.00</span>
                                                    </div>
                                                    
                                                    <div class="vr d-none d-sm-block bg-secondary opacity-25" style="width: 2px;"></div>
                                                    
                                                    <div class="d-flex flex-column text-end">
                                                        <span class="text-secondary small fw-bold mb-1">TOTAL ORDEN</span>
                                                        <span class="text-primary fw-bold fs-5 lh-1" id="ordenTotal">S/ 0.00</span>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- NUEVA SECCIÓN DE BONIFICACIONES -->
                    <div class="card border-info-subtle shadow-sm mt-3 d-none fade-in" id="seccionBonificaciones">
                        <div class="card-body p-0">
                            <div class="p-3 border-bottom bg-info-subtle rounded-top d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-gift text-info-emphasis me-2 fs-5"></i>
                                    <h6 class="mb-0 fw-bold text-info-emphasis">Productos de Bonificación (Costo Cero)</h6>
                                </div>
                                <button type="button" class="btn-close btn-sm" id="btnCerrarTablaBonificaciones" aria-label="Cerrar"></button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-pro" id="tablaDetalleBonificaciones">
                                    <thead class="table-light border-bottom">
                                        <tr>
                                            <th class="text-center text-secondary col-w-40 py-2">#</th>
                                            <th class="ps-3 text-secondary col-min-w-320 py-2">Producto Bonificado</th>
                                            <th class="text-center text-secondary col-w-140 py-2">Cantidad</th>
                                            <th class="text-center text-secondary col-w-160 py-2">Valor Ref.</th>
                                            <th class="text-center text-secondary col-w-200 py-2">Centro de Costo</th>
                                            <th class="text-end text-secondary col-w-150 py-2">Subtotal</th>
                                            <th class="text-center col-w-60 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white"></tbody>
                                    <tfoot class="bg-light border-top">
                                        <tr>
                                            <td colspan="7" class="ps-3 py-3 align-middle border-bottom-0">
                                                <div class="d-flex align-items-center">
                                                    <button type="button" class="btn btn-sm btn-info text-white fw-semibold shadow-sm" id="btnAgregarFilaBonificacion">
                                                        <i class="bi bi-plus-lg me-1"></i>Agregar Bonificación
                                                    </button>
                                                    <small class="text-info-emphasis ms-3 fw-semibold">
                                                        <i class="bi bi-info-circle-fill me-1"></i>Estos productos ingresarán al almacén pero no sumarán a la deuda.
                                                    </small>
                                                </div>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Tarjeta Cobro / Pago Inmediato (Verde) -->
                    <div class="card border-success-subtle shadow-sm mt-4 d-none fade-in" id="seccionCobroInmediatoCompra">
                        <div class="card-body p-3 bg-success-subtle rounded">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                    <i class="bi bi-cash-stack fs-5"></i>
                                </div>
                                <div>
                                    <h6 class="mb-0 fw-bold text-success-emphasis">Registro de Pago Rápido</h6>
                                    <small class="text-success-emphasis opacity-75">Selecciona cómo estás pagando al proveedor en este momento.</small>
                                </div>
                            </div>

                            <div id="contenedorMetodosPagoCompra" class="d-flex flex-column gap-2 mb-2"></div>

                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" id="btnAgregarPagoInmediatoCompra">
                                    <i class="bi bi-plus-circle me-1"></i> Añadir otro método
                                </button>
                                <div class="text-end">
                                    <small class="text-success-emphasis fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL PAGADO</small>
                                    <span class="fw-bold text-dark fs-5" id="totalPagadoInmediatoCompra">S/ 0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="form-check form-switch m-0 ps-5" id="switchCobroContainerCompra">
                    <input class="form-check-input border-success" type="checkbox" id="switchCobroInmediatoCompra" style="cursor: pointer;" disabled>
                    <label class="form-check-label fw-bold text-success small" for="switchCobroInmediatoCompra" style="cursor: pointer;">
                        Pagar Al Contado (Inmediato)
                    </label>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button class="btn btn-light text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary px-4 fw-bold" id="btnGuardarOrden"><i class="bi bi-save me-2"></i>Guardar Orden</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL: RECEPCIONAR COMPRA                      -->
<!-- ============================================== -->
<div class="modal fade" id="modalRecepcionCompra" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-info text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold d-flex flex-wrap align-items-center gap-2">
                    <span><i class="bi bi-box-seam me-2"></i>Recepcionar Mercadería</span>
                    <small id="recepcionProveedorNombre" class="fw-semibold text-white-50"></small>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <input type="hidden" id="recepcionOrdenId" value="0">
                
                <select id="recepcionAlmacen" class="d-none">
                    <option value="">Seleccione almacén...</option>
                    <?php foreach ($almacenes as $almacen): ?>
                        <option value="<?= (int) ($almacen['id'] ?? 0) ?>"><?= e((string) ($almacen['nombre'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-4">
                                <label for="recepcionFecha" class="form-label text-muted small fw-bold mb-1">Fecha de Recepción <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-calendar-check text-muted"></i></span>
                                    <input type="date" class="form-control border-secondary-subtle shadow-none" id="recepcionFecha" value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label for="recepcionObservaciones" class="form-label text-muted small fw-bold mb-1">Observaciones / Guía de Remisión</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-secondary-subtle"><i class="bi bi-file-earmark-text text-muted"></i></span>
                                    <input type="text" class="form-control border-secondary-subtle shadow-none" id="recepcionObservaciones" maxlength="180" placeholder="Opcional - Ingresar número de guía">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-white border-0 px-3 pt-3 pb-2 d-flex flex-wrap align-items-center gap-2">
                        <h6 class="fw-bold text-dark mb-0">Detalle de Productos</h6>
                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle fw-medium px-2 py-1">
                            <i class="bi bi-info-circle me-1"></i>Modo Recepción Parcial: Puede editar la cantidad a ingresar
                        </span>
                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-medium px-2 py-1">
                            <i class="bi bi-info-circle me-1"></i>Borrador: No descuenta stock físico
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive border rounded-3 mb-0">
                            <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleRecepcion">
                                <thead>
                                    <tr>
                                        <th class="ps-3 text-secondary col-min-w-300 py-2">Producto / Pedido</th>
                                        <th class="text-center text-secondary col-w-250 py-2">Almacén Destino</th>
                                        <th class="text-center text-secondary col-w-100 py-2">Pendiente</th>
                                        <th class="text-center text-secondary col-w-160 py-2">A Ingresar (Base)</th>
                                        <th class="text-center text-secondary col-w-80 py-2">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-4 ps-2">
                    <div class="form-check m-0 d-flex align-items-center">
                        <input class="form-check-input border-danger me-2" type="checkbox" id="cerrarForzadoRecepcion" style="transform: scale(1.2); cursor: pointer;">
                        <label class="form-check-label text-danger fw-bold small mt-1" for="cerrarForzadoRecepcion" style="cursor: pointer;" title="Cancelar saldos no recibidos.">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> Forzar cierre de orden
                        </label>
                    </div>
                </div>
                <div>
                    <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-info text-white fw-bold px-4" id="btnConfirmarRecepcion">
                        <i class="bi bi-check-lg me-2"></i>Confirmar Ingreso
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL: DEVOLUCION O AJUSTE COMPRA              -->
<!-- ============================================== -->
<div class="modal fade" id="modalDevolucionCompra" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-return-left me-2"></i>Registrar Devolución o Ajuste</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <input type="hidden" id="devolucionOrdenId" value="0">

                <div id="alertaDevolucionesPrevias" class="alert alert-info border-info-subtle d-none shadow-sm mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i><strong>Atención:</strong> Esta orden ya tiene devoluciones pasadas. La columna "Cant. Recibida" te muestra el <strong>stock neto actual</strong> que aún tienes disponible para devolver.
                </div>
                
                <div class="row mb-4 g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Motivo de Devolución <span class="text-danger">*</span></label>
                        <select id="devolucionMotivo" class="form-select border-warning-subtle shadow-sm" required>
                            <option value="">Seleccione un motivo...</option>
                            <option value="Error de conteo / Auditoría">Error de conteo al recibir (Auditoría)</option>
                            <option value="Producto defectuoso / Garantía">Producto defectuoso o dañado (Garantía)</option>
                            <option value="Vencimiento corto">Fecha de vencimiento muy corta</option>
                            <option value="Producto incorrecto">Llegó un producto diferente al solicitado</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">Resolución con el Proveedor <span class="text-danger">*</span></label>
                        <select id="devolucionResolucion" class="form-select border-warning-subtle shadow-sm" required>
                            <option value="descuento_cxp" selected>Aplicar NOTA DE CRÉDITO (baja tu deuda con el proveedor)</option>
                            <option value="reembolso_dinero">Pedir REEMBOLSO (el proveedor te devuelve dinero)</option>
                        </select>
                        <div id="devolucionResolucionHint" class="form-text text-secondary mt-1" style="font-size: 0.75rem;">
                            ✅ Recomendado cuando tienes facturas pendientes: reduce tu cuenta por pagar automáticamente.
                        </div>
                    </div>
                </div>

                <div class="row mb-4" id="filaSwitchReemplazoCompra">
                    <div class="col-12">
                        <div class="form-check form-switch bg-white border border-secondary-subtle rounded-3 p-3 d-flex align-items-center shadow-sm">
                            <input class="form-check-input ms-0 me-3" type="checkbox" id="devolucionEsperarReemplazo" checked style="cursor: pointer; transform: scale(1.3); margin-top: 0;">
                            <div>
                                <label class="form-check-label fw-bold text-dark d-block" for="devolucionEsperarReemplazo" style="cursor: pointer;">
                                    Esperar mercadería de reemplazo
                                </label>
                                <small class="text-muted" id="devolucionEsperarReemplazoHint">
                                    La orden volverá a estado "Aprobada" para que puedas recepcionar los productos faltantes después.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="table-responsive border rounded-3 mb-0">
                            <table class="table table-sm align-middle mb-0 table-pro table-pastel" id="tablaDetalleDevolucion">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="ps-3 border-bottom-0 py-2">Producto / Ítem</th>
                                        <th class="text-center border-bottom-0 col-w-150 py-2">Cant. Recibida</th>
                                        <th class="text-center border-bottom-0 col-w-150 py-2">Costo Compra</th>
                                        <th class="text-center border-bottom-0 col-w-180 py-2">Unidad de Devolución</th>
                                        <th class="text-center border-bottom-0 col-w-140 py-2">Cantidad</th>
                                        <th class="text-end pe-4 border-bottom-0 col-w-150 py-2">Costo Recuperado</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white"></tbody>
                                <tfoot class="bg-light border-top">
                                    <tr>
                                        <td colspan="5" class="text-end fw-bold py-3 text-secondary">TOTAL A RECUPERAR:</td>
                                        <td class="text-end fw-bold py-3 fs-5 text-warning-emphasis pe-4" id="devolucionTotal">S/ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0">
                <button class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button class="btn btn-warning text-dark fw-bold px-4" id="btnConfirmarDevolucion">
                    <i class="bi bi-check-circle-fill me-2"></i>Procesar Devolución
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- MODAL: RESUMEN DE COMPRA                       -->
<!-- ============================================== -->
<div class="modal fade" id="modalResumenCompra" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-check-circle-fill me-2"></i>Resumen de Compra Recepcionada</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1.2rem; border-top-right-radius: 1.2rem; position: relative;">
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 p-md-4">
                        <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                            <h6 class="fw-bold text-dark mb-0">Información de la Orden</h6>
                            <span class="badge bg-success-subtle text-success fs-6 px-3 py-2 rounded-pill" id="resumenCompraCodigo">OC-0000</span>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">Proveedor</small>
                                <div class="fw-semibold text-dark text-break" id="resumenCompraProveedor">-</div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">Estado</small>
                                <div class="fw-semibold text-success" id="resumenCompraEstado">Recepcionada</div>
                            </div>
                            <div class="col-12"><hr class="text-muted opacity-25 my-1"></div>
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">Fechas</small>
                                <div class="small mb-1"><i class="bi bi-calendar3 text-muted me-1"></i> Orden: <span class="fw-semibold text-dark" id="resumenCompraFechaOrden">-</span></div>
                                <div class="small"><i class="bi bi-box-arrow-in-down text-info me-1"></i> Recepción: <span class="fw-semibold text-info" id="resumenCompraFechaRecepcion">-</span></div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">Observaciones</small>
                                <div class="text-secondary small text-break" id="resumenCompraObservaciones">Sin observaciones.</div>
                            </div>
                            
                            <div class="col-12"><hr class="text-muted opacity-25 my-1"></div>
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">Registro / Orden</small>
                                <div class="text-dark small text-truncate" title="Usuario que creó la orden">
                                    <i class="bi bi-person-circle text-secondary me-1"></i><span id="resumenCompraUsuarioRegistro" class="fw-medium">-</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted fw-bold d-block mb-1">Recepción</small>
                                <div class="text-dark small text-truncate" title="Usuario que recibió la mercadería">
                                    <i class="bi bi-person-check-fill text-info me-1"></i><span id="resumenCompraUsuarioRecepcion" class="fw-medium">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="contenedorHistorialDevoluciones" class="card border-warning-subtle shadow-sm mt-4 d-none fade-in mb-4">
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom bg-warning-subtle rounded-top d-flex align-items-center">
                            <i class="bi bi-arrow-return-left text-warning-emphasis me-2 fs-5"></i>
                            <h6 class="mb-0 fw-bold text-warning-emphasis">Historial de Devoluciones Realizadas</h6>
                        </div>
                        <div class="p-3 bg-white rounded-bottom">
                            <ul class="mb-0 small text-dark" id="listaHistorialDevoluciones" style="padding-left: 1.2rem;"></ul>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm">
                    <div class="card-body p-0">
                        <div class="p-3 border-bottom bg-white rounded-top d-flex align-items-center">
                            <i class="bi bi-box-seam text-primary me-2 fs-5"></i>
                            <h6 class="mb-0 fw-bold text-dark">Productos Recepcionados</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 table-hover" id="tablaResumenProductosCompra">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 text-secondary small fw-bold" style="min-width: 180px;">Producto</th>
                                        <th class="text-center text-secondary small fw-bold text-nowrap" style="min-width: 130px;">Cant. Pedida</th>
                                        <th class="text-center text-secondary small fw-bold text-nowrap" style="min-width: 130px;">Cant. Recibida</th>
                                        <th class="text-center text-danger small fw-bold text-nowrap" style="width: 100px;">Devuelto</th>
                                        <th class="text-end text-secondary small fw-bold text-nowrap" style="width: 100px;">Costo Unit.</th>
                                        <th class="text-end pe-3 text-secondary small fw-bold text-nowrap" style="width: 110px;">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white">
                                </tbody>
                                <tfoot class="bg-light border-top">
                                    <tr>
                                        <td colspan="5" class="text-end fw-bold py-3 text-secondary align-middle">TOTAL FINAL:</td>
                                        <td class="text-end fw-bold py-3 fs-5 text-primary pe-3 text-nowrap align-middle" id="resumenCompraTotalFinal">S/ 0.00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer bg-white border-top-0 rounded-bottom">
                <button type="button" class="btn btn-secondary fw-semibold px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ============================================== -->
<!-- TEMPLATES JS                                   -->
<!-- ============================================== -->

<template id="templateFilaDetalle">
    <tr class="border-bottom">
        <td class="text-center fw-bold text-muted align-top py-3 fila-numero bg-light-subtle" style="font-size: 0.85rem;">1</td>
        <td class="ps-3 py-3 align-top" data-label="Producto">
            <div class="mb-2">
                <select class="form-select form-select-sm detalle-item shadow-none border-secondary-subtle" required>
                    <option value="">Buscar ítem o producto...</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= (int) ($item['id'] ?? 0) ?>"
                                data-unidad-base="<?= e((string) ($item['unidad_base'] ?? 'UND')) ?>"
                                data-requiere-factor-conversion="<?= (int) ($item['requiere_factor_conversion'] ?? 0) ?>"
                                data-costo-referencial="<?= (float) ($item['costo_referencial'] ?? 0) ?>">
                            <?= htmlspecialchars((string) ($item['nombre'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex flex-column gap-1">
                <select class="form-select form-select-sm detalle-unidad-compra d-none shadow-none border-secondary-subtle" disabled>
                    <option value="">Unidad de compra...</option>
                </select>
                <div class="detalle-conversion-info text-end small text-muted"></div> 
            </div>
        </td>
        
        <td class="align-top py-3 px-2" data-label="Cantidad">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-primary shadow-none border-secondary-subtle" min="0.01" step="0.01" value="1" required>
        </td>
        
        <td class="align-top py-3 px-2" data-label="Costo Unit.">
            <div class="input-group input-group-sm">
                <span class="input-group-text border-end-0 text-muted bg-light border-secondary-subtle simbolo-moneda">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-costo shadow-none border-secondary-subtle" min="0" step="0.01" value="0.00" required>
            </div>
        </td>

        <td class="align-top py-3 px-2" data-label="Centro Costo">
            <select class="form-select form-select-sm detalle-centro-costo shadow-none border-secondary-subtle">
                <option value="">Sin centro de costo</option>
                <?php foreach ($centros_costo as $centro): ?>
                    <option value="<?= (int) ($centro['id'] ?? 0) ?>">
                        <?= e((string) ($centro['codigo'] ?? '')) ?> - <?= e((string) ($centro['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>

        <td class="text-end align-top py-3 fw-bold text-dark detalle-subtotal fs-6" data-label="Subtotal">
            <span class="simbolo-moneda">S/</span> 0.00
        </td>

        <td class="text-center align-top py-3" data-label="Acción">
            <button class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-fila p-1" type="button" data-bs-toggle="tooltip" title="Quitar fila" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    </tr>
</template>

<!-- TEMPLATE PARA FILAS DE BONIFICACIÓN -->
<template id="templateFilaBonificacion">
    <tr class="border-bottom bg-info bg-opacity-10 fila-bonificacion">
        <td class="text-center fw-bold text-muted align-top py-3 fila-numero bg-light-subtle" style="font-size: 0.85rem;">1</td>
        <td class="ps-3 py-3 align-top" data-label="Producto">
            <div class="mb-2">
                <select class="form-select form-select-sm detalle-item shadow-none border-info-subtle" required>
                    <option value="">Buscar ítem bonificado...</option>
                    <?php foreach ($items as $item): ?>
                        <option value="<?= (int) ($item['id'] ?? 0) ?>"
                                data-unidad-base="<?= e((string) ($item['unidad_base'] ?? 'UND')) ?>"
                                data-requiere-factor-conversion="<?= (int) ($item['requiere_factor_conversion'] ?? 0) ?>"
                                data-costo-referencial="<?= (float) ($item['costo_referencial'] ?? 0) ?>">
                            <?= htmlspecialchars((string) ($item['nombre'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex flex-column gap-1">
                <select class="form-select form-select-sm detalle-unidad-compra d-none shadow-none border-info-subtle" disabled>
                    <option value="">Unidad...</option>
                </select>
                <div class="detalle-conversion-info text-end small text-muted"></div> 
            </div>
        </td>
        
        <td class="align-top py-3 px-2" data-label="Cantidad">
            <input type="number" class="form-control form-control-sm text-center detalle-cantidad fw-bold text-info shadow-none border-info-subtle" min="0.01" step="0.01" value="1" required>
        </td>
        
        <td class="align-top py-3 px-2" data-label="Valor Ref.">
            <div class="input-group input-group-sm opacity-75" title="Valor referencial - No suma al total a pagar">
                <span class="input-group-text border-end-0 text-muted bg-light border-info-subtle simbolo-moneda">S/</span>
                <input type="number" class="form-control border-start-0 text-end detalle-costo shadow-none border-info-subtle text-muted bg-light" min="0" step="0.01" value="0.00" readonly tabindex="-1">
            </div>
        </td>

        <td class="align-top py-3 px-2" data-label="Centro Costo">
            <select class="form-select form-select-sm detalle-centro-costo shadow-none border-info-subtle">
                <option value="">Sin centro de costo</option>
                <?php foreach ($centros_costo as $centro): ?>
                    <option value="<?= (int) ($centro['id'] ?? 0) ?>">
                        <?= e((string) ($centro['codigo'] ?? '')) ?> - <?= e((string) ($centro['nombre'] ?? '')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </td>

        <td class="text-end align-top py-3 fw-bold text-success detalle-subtotal fs-6" data-label="Subtotal">
            <span class="simbolo-moneda">S/</span> 0.00 <br>
            <span class="badge bg-success-subtle text-success border border-success-subtle mt-1" style="font-size: 0.65rem;">BONIFICACIÓN</span>
        </td>

        <td class="text-center align-top py-3" data-label="Acción">
            <button class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-fila p-1" type="button" data-bs-toggle="tooltip" title="Quitar bonificación" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </td>
    </tr>
</template>

<template id="templateFilaPagoCompra">
    <div class="d-flex align-items-center gap-2 mb-2 fila-pago fade-in">
        <select class="form-select form-select-sm shadow-none pago-cuenta border-success-subtle" required>
            <option value="" selected disabled>Cuenta Origen...</option>
            <?php foreach ($cuentas as $cuenta): ?>
                <option value="<?= (int) ($cuenta['id'] ?? 0) ?>">
                    <?= e($cuenta['nombre'] ?? '') ?> (<?= e($cuenta['moneda'] ?? 'PEN') ?>)
                </option>
            <?php endforeach; ?>
        </select>
        
        <select class="form-select form-select-sm shadow-none pago-metodo border-success-subtle" required>
            <option value="" selected disabled>Método...</option>
            <?php foreach ($metodos as $metodo): ?>
                <option value="<?= (int) ($metodo['id'] ?? 0) ?>">
                    <?= e($metodo['nombre'] ?? '') ?>
                </option>
            <?php endforeach; ?>
        </select>
        
        <div class="input-group input-group-sm" style="width: 150px;">
            <span class="input-group-text bg-success-subtle text-success border-success-subtle fw-bold">S/</span>
            <input type="number" class="form-control text-end shadow-none pago-monto border-success-subtle fw-bold text-dark input-monto-inmediato" min="0.01" step="0.01" placeholder="0.00" required>
        </div>
        
        <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-pago p-1" data-bs-toggle="tooltip" title="Quitar método" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
            <i class="bi bi-trash-fill"></i>
        </button>
    </div>
</template>