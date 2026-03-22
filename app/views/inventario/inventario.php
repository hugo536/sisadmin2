<?php
$stockActual = $stockActual ?? [];
$almacenes = $almacenes ?? [];
$proveedores = $proveedores ?? []; 
$centros_costo = $centros_costo ?? []; 
$categorias = $categorias ?? []; // <-- RECIBIMOS TODAS LAS CATEGORÍAS AQUÍ
$idAlmacenFiltro = (int) ($id_almacen_filtro ?? 0);

$tipoItemLabel = static function (string $tipo): string {
    if ($tipo === 'producto' || $tipo === 'producto_terminado') {
        return 'Producto terminado';
    }
    if ($tipo === 'materia_prima') {
        return 'Materia prima';
    }
    if ($tipo === 'material_empaque') {
        return 'Material de empaque';
    }
    if ($tipo === 'servicio') {
        return 'Servicio';
    }
    if ($tipo === 'semielaborado') {
        return 'Semielaborado';
    }
    if ($tipo === 'insumo') {
        return 'Insumo';
    }
    return $tipo;
};
?>

<div class="container-fluid p-4" id="inventarioApp">
    
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Inventario de Productos
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de existencias, kardex y movimientos de almacén.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <a href="<?php echo e(route_url('inventario/kardex')); ?>" class="btn btn-light shadow-sm text-secondary fw-semibold border">
                <i class="bi bi-journal-text me-2 text-info"></i>Kardex
            </a>
            
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-light border text-secondary fw-semibold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-info"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                    <li><a class="dropdown-item fw-medium" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=csv&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-filetype-csv me-2 text-muted"></i>CSV</a></li>
                    <li><a class="dropdown-item fw-medium" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=excel&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel</a></li>
                    <li><a class="dropdown-item fw-medium" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=pdf&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-file-pdf me-2 text-danger"></i>PDF</a></li>
                </ul>
            </div>

            <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
                <button type="button" class="btn btn-primary shadow-sm fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#modalMovimientoInventario">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Movimiento
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4 col-lg-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="inventarioSearch" placeholder="Buscar SKU o nombre...">
                    </div>
                </div>
                
                <div class="col-12 col-md-4 col-lg-3">
                    <select class="form-select bg-light" id="inventarioFiltroCategoria">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo (int) ($cat['id'] ?? 0); ?>"><?php echo e((string) ($cat['nombre'] ?? '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-2">
                    <select class="form-select bg-light" id="inventarioFiltroTipoItem">
                        <option value="">Todos los tipos</option>
                        <option value="producto_terminado">Producto terminado</option>
                        <option value="materia_prima">Materia prima</option>
                        <option value="insumo">Insumo</option>
                        <option value="semielaborado">Semielaborado</option>
                        <option value="material_empaque">Material de empaque</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>

                <div class="col-12 col-md-4 col-lg-2">
                    <select class="form-select bg-light" id="inventarioFiltroAlmacen">
                        <option value="" <?php echo $idAlmacenFiltro === 0 ? 'selected' : ''; ?>>Todos los almacenes</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>" <?php echo $idAlmacenFiltro === (int) ($almacen['id'] ?? 0) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($almacen['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-md-4 col-lg-2">
                    <select class="form-select bg-light" id="inventarioFiltroEstado">
                        <option value="">Situación / Alertas</option>
                        <option value="disponible">Disponible (Verde)</option>
                        <option value="próximo_a_vencer">Próximo a Vencer (Amarillo)</option>
                        <option value="bajo_mínimo">Bajo Mínimo (Amarillo)</option>
                        <option value="agotado">Agotado (Rojo)</option>
                        <option value="vencido">Vencido (Rojo)</option>
                        <option value="sin_movimientos">Sin Movimientos (Gris)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive inventario-table-wrapper">
               <table class="table align-middle mb-0 table-pro table-hover" id="tablaInventarioStock"
                       data-erp-table="true"
                       data-search-input="#inventarioSearch"
                       data-pagination-controls="#inventarioPaginationControls"
                       data-pagination-info="#inventarioPaginationInfo"
                       data-erp-filters='[
                           {"el":"#inventarioFiltroAlmacen","attr":"data-almacen","match":"equals"},
                           {"el":"#inventarioFiltroCategoria","attr":"data-categoria","match":"equals"},
                           {"el":"#inventarioFiltroTipoItem","attr":"data-tipo-item","match":"equals"},
                           {"el":"#inventarioFiltroEstado","attr":"data-estado","match":"equals"}
                       ]'>
                    <thead class="inventario-sticky-thead border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">SKU</th>
                            <th class="text-secondary fw-semibold">Producto (Nombre Completo)</th>
                            <th class="text-secondary fw-semibold">Almacén</th>
                            <th class="text-secondary fw-semibold">Lote</th>
                            <th class="text-end text-secondary fw-semibold">Stock Actual</th>
                            <th class="text-center text-secondary fw-semibold">Situación / Alertas</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($stockActual)): ?>
                            <?php foreach ($stockActual as $stock): ?>
                                <?php
                                $sku = (string) ($stock['sku'] ?? '');
                                $itemNombreCompleto = (string) ($stock['item_nombre'] ?? '');
                                $almacenNombre = (string) ($stock['almacen_nombre'] ?? '');
                                $loteActual = trim((string) ($stock['lote_actual'] ?? ''));
                                $idAlmacen = (int) ($stock['id_almacen'] ?? 0);
                                $tipoRegistro = (string) ($stock['tipo_registro'] ?? 'item');
                                $idCategoria = (int) ($stock['id_categoria'] ?? 0);
                                $categoriaNombre = trim((string) ($stock['categoria_nombre'] ?? ''));
                                $tipoItem = trim((string) ($stock['tipo_item'] ?? ''));
                                $tipoItem = $tipoItem === 'producto' ? 'producto_terminado' : $tipoItem;
                                
                                $stockFormateado = (string) ($stock['stock_formateado'] ?? '0');
                                $badgeColor = (string) ($stock['badge_color'] ?? '');
                                $badgeTexto = (string) ($stock['badge_estado'] ?? '');
                                $requiereFactorConversion = (int) ($stock['requiere_factor_conversion'] ?? 0) === 1;

                                $detalleAlerta = trim((string) ($stock['detalle_alerta'] ?? ''));

                                $search = mb_strtolower($sku . ' ' . $itemNombreCompleto . ' ' . $almacenNombre . ' ' . $loteActual);
                                ?>
                                <tr data-search="<?php echo e($search); ?>"
                                    data-item-id="<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                    data-tipo-registro="<?php echo e($tipoRegistro); ?>"
                                    data-categoria="<?php echo (int) $idCategoria; ?>"
                                    data-tipo-item="<?php echo e($tipoItem); ?>"
                                    data-estado="<?php echo strtolower(str_replace(' ', '_', $badgeTexto)); ?>"
                                    data-almacen="<?php echo (int) $idAlmacen; ?>" class="border-bottom">
                                    
                                    <td class="ps-4 fw-bold text-primary"><?php echo e($sku); ?></td>
                                    <td class="text-dark">
                                        <span class="d-block fw-bold mb-1"><?php echo e($itemNombreCompleto); ?></span>
                                        
                                        <div class="d-flex flex-wrap align-items-center gap-1">
                                            <?php if ($categoriaNombre !== ''): ?>
                                                <span class="badge bg-light text-secondary border fw-medium" style="font-size: 0.65rem;"><i class="bi bi-tag me-1"></i><?php echo e($categoriaNombre); ?></span>
                                            <?php endif; ?>
                                            <?php if ($tipoItem !== ''): ?>
                                                <span class="badge bg-light text-secondary border fw-medium" style="font-size: 0.65rem;"><i class="bi bi-layers me-1"></i><?php echo e($tipoItemLabel($tipoItem)); ?></span>
                                            <?php endif; ?>
                                            <?php if($tipoRegistro === 'pack'): ?>
                                                <span class="badge bg-info-subtle text-info border border-info-subtle fw-bold" style="font-size: 0.65rem;"><i class="bi bi-box-seam me-1"></i>PACK</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td class="text-muted small"><?php echo e($almacenNombre); ?></td>
                                    <td class="text-muted"><?php echo e($loteActual !== '' ? $loteActual : '-'); ?></td>
                                    
                                    <td class="text-end">
                                        <div class="fw-bold fs-6 text-dark"><?php echo $stockFormateado; ?></div>
                                        <?php if ($requiereFactorConversion && !empty($stock['desglose']) && is_array($stock['desglose'])): ?>
                                            <div class="d-flex flex-column align-items-end mt-1 pb-1" style="gap: 3px;">
                                                <?php foreach ($stock['desglose'] as $d): ?>
                                                    <div class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-medium" style="font-size: 0.7rem;">
                                                        <?php echo e($d['texto']); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo $badgeColor; ?>">
                                            <?php echo e($badgeTexto); ?>
                                        </span>
                                        <?php if ($detalleAlerta !== ''): ?>
                                            <div class="small text-muted mt-1" style="font-size: 0.75rem;"><?php echo e($detalleAlerta); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <?php $itemActivo = (int) ($stock['item_estado'] ?? 0) === 1; ?>
                                            <span class="badge rounded-pill me-2 <?php echo $itemActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>"
                                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                                  title="Estado referencial del ítem (solo lectura en inventario)">
                                                <?php echo $itemActivo ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                            
                                            <?php if (in_array($tipoRegistro, ['item', 'pack'], true)): ?>
                                                <a href="<?php echo e(route_url('inventario/kardex')); ?>&item_id=<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                                   class="btn btn-sm btn-light text-info border-0 rounded-circle btn-kardex"
                                                   data-bs-toggle="tooltip" title="Ver Kardex">
                                                    <i class="bi bi-eye fs-5"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small px-2" data-bs-toggle="tooltip" title="Kardex disponible para ítems base">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-msg-row"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de stock disponibles.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center px-4">
                <small class="text-muted fw-semibold" id="inventarioPaginationInfo">Cargando...</small>
                <nav aria-label="Paginación de inventario">
                    <ul class="pagination mb-0 justify-content-end" id="inventarioPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalMovimientoInventario" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Registrar Movimiento</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form id="formMovimientoInventario" autocomplete="off" novalidate>
                    <input type="hidden" id="idItemMovimiento" name="id_item" value="0">
                    <input type="hidden" id="idPackMovimiento" name="id_pack" value="0">
                    <input type="hidden" id="tipoRegistroMovimiento" name="tipo_registro" value="item">
                    <input type="hidden" name="lote" id="loteFinalEnviar">

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Datos del Movimiento</h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="tipoMovimiento" class="form-label text-muted small fw-bold mb-1">Tipo de Movimiento <span class="text-danger">*</span></label>
                                    <select id="tipoMovimiento" name="tipo_movimiento" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <option value="INI">INI - Inicial</option>
                                        <option value="AJ+">AJ+ - Ajuste positivo</option>
                                        <option value="AJ-">AJ- - Ajuste negativo</option>
                                        <option value="TRF">TRF - Transferencia</option>
                                        <option value="CON">CON - Consumo</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="almacenMovimiento" class="form-label text-muted small fw-bold mb-1">Almacén Origen <span class="text-danger">*</span></label>
                                    <select id="almacenMovimiento" name="id_almacen" class="form-select" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($almacenes as $almacen): ?>
                                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 mt-3 d-none" id="grupoProveedorMovimiento">
                                    <label for="proveedorMovimiento" class="form-label text-muted small fw-bold mb-1">Proveedor (Opcional / Para compras)</label>
                                    <select id="proveedorMovimiento" name="id_proveedor" class="form-select">
                                        <option value="">Seleccione proveedor...</option>
                                        <?php foreach (($proveedores ?? []) as $proveedor): ?>
                                            <option value="<?php echo (int) ($proveedor['id'] ?? 0); ?>"><?php echo e((string) ($proveedor['nombre_completo'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 d-none mt-3" id="grupoAlmacenDestino">
                                    <label for="almacenDestinoMovimiento" class="form-label text-muted small fw-bold mb-1 text-primary">Almacén Destino (Solo Transferencias)</label>
                                    <select id="almacenDestinoMovimiento" name="id_almacen_destino" class="form-select border-primary-subtle">
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($almacenes as $almacen): ?>
                                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>"><?php echo e((string) ($almacen['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 d-none mt-3" id="grupoMotivoMovimiento">
                                    <label for="motivoMovimiento" class="form-label text-muted small fw-bold mb-1">Motivo del Movimiento</label>
                                    <select id="motivoMovimiento" name="motivo" class="form-select">
                                        <option value="">Seleccione motivo...</option>
                                        <option value="Merma recuperada">Merma recuperada</option>
                                        <option value="Conteo físico">Conteo físico</option>
                                        <option value="Error anterior">Error anterior</option>
                                        <option value="Devolución interna">Devolución interna</option>
                                        <option value="Merma">Merma</option>
                                        <option value="Robo">Robo</option>
                                        <option value="Caducado">Caducado</option>
                                        <option value="Desperdicio">Desperdicio</option>
                                        <option value="Producción">Producción</option>
                                        <option value="Muestras">Muestras</option>
                                        <option value="Pruebas laboratorio">Pruebas laboratorio</option>
                                        <option value="Consumo administrativo">Consumo administrativo</option>
                                        <option value="Otro">Otro</option>
                                    </select>
                                </div>
                                
                                <div class="col-12 d-none mt-3" id="grupoCentroCostoMovimiento">
                                    <label for="centroCostoMovimiento" class="form-label small fw-bold text-warning-emphasis mb-1">
                                        Centro de Costos <span class="text-danger">*</span>
                                    </label>
                                    <select id="centroCostoMovimiento" name="id_centro_costo" class="form-select border-warning-subtle bg-warning-subtle text-dark">
                                        <option value="">Seleccione centro de costos...</option>
                                        <?php foreach ($centros_costo as $cc): ?>
                                            <option value="<?php echo (int) ($cc['id'] ?? 0); ?>">
                                                <?php echo e((string) ($cc['codigo'] ?? '') . ' - ' . (string) ($cc['nombre'] ?? '')); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text mt-1"><i class="bi bi-info-circle me-1"></i>Obligatorio para salidas por consumo interno (Ej. Suministros, Repuestos).</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Detalle del Producto</h6>
                            
                            <div class="row g-3 align-items-end mb-4">
                                <div class="col-md-8">
                                    <label class="form-label text-muted small fw-bold mb-1">Buscar Ítem (SKU / Nombre) <span class="text-danger">*</span></label>
                                    <select id="itemMovimiento" class="form-select" placeholder="Escriba para buscar...">
                                        <option value="">Escriba para buscar...</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label text-muted small fw-bold mb-1">Stock en Almacén</label>
                                    <input type="text" class="form-control bg-light text-primary fw-bold" id="stockActualItemSeleccionado" value="0.0000" readonly>
                                </div>
                            </div>

                            <div class="row g-3 align-items-start">
                                <div class="col-md-4">
                                    <label for="cantidadMovimiento" class="form-label text-muted small fw-bold mb-1">Cantidad a Mover <span class="text-danger">*</span></label>
                                    <input type="number" step="0.0001" min="0.0001" class="form-control bg-light" id="cantidadMovimiento" name="cantidad" disabled>
                                    <div class="form-text mt-1 text-primary fw-medium" id="stockDisponibleHint"></div>
                                </div>

                                <div class="col-md-4" id="grupoUnidadMovimiento">
                                    <label for="unidadMovimiento" class="form-label text-muted small fw-bold mb-1">Unidad transaccional</label>
                                    <select id="unidadMovimiento" class="form-select bg-light" disabled>
                                        <option value="">Unidad base</option>
                                    </select>
                                </div>

                                <div class="col-md-4 d-flex align-items-end" id="grupoUnidadInfoMovimiento">
                                    <div class="form-text small text-muted mt-0" id="unidadMovimientoInfo"></div>
                                </div>

                                <div class="col-md-4 d-none" id="grupoCostoUnitarioMovimiento">
                                    <label for="costoUnitarioMovimiento" class="form-label text-muted small fw-bold mb-1">Costo Unitario (S/)</label>
                                    <input type="number" step="0.0001" min="0" class="form-control" id="costoUnitarioMovimiento" name="costo_unitario" value="0">
                                </div>

                                <div class="col-md-4 d-none" id="grupoLoteInput">
                                    <label for="loteMovimientoInput" class="form-label text-muted small fw-bold mb-1">Nuevo Lote</label>
                                    <input type="text" class="form-control" id="loteMovimientoInput" maxlength="100" placeholder="Ej. LOTE-001">
                                </div>

                                <div class="col-md-4 d-none" id="grupoLoteSelect">
                                    <label for="loteMovimientoSelect" class="form-label text-muted small fw-bold mb-1">Lote Existente</label>
                                    <select class="form-select" id="loteMovimientoSelect">
                                        <option value="">Seleccione lote...</option>
                                    </select>
                                    <div class="form-text small text-danger d-none mt-1" id="msgSinLotes"><i class="bi bi-exclamation-circle"></i> Sin lotes disponibles.</div>
                                </div>

                                <div class="col-md-4 d-none" id="grupoVencimientoMovimiento">
                                    <label for="vencimientoMovimiento" class="form-label text-muted small fw-bold mb-1">Fecha Vencimiento</label>
                                    <input type="date" class="form-control" id="vencimientoMovimiento" name="fecha_vencimiento">
                                </div>
                            </div>

                            <div class="row mt-4">
                                <div class="col-12 text-end mb-2">
                                    <button type="button" class="btn btn-outline-primary fw-semibold btn-sm w-100 w-md-auto" id="btnAgregarLineaMovimiento">
                                        <i class="bi bi-plus-circle me-1"></i>Agregar ítem a la operación
                                    </button>
                                </div>
                                <div class="col-12">
                                    <div class="table-responsive border rounded-3 bg-white">
                                        <table class="table table-sm align-middle mb-0 table-pro" id="tablaLineasMovimiento">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="ps-3 text-secondary fw-semibold">Ítem</th>
                                                    <th class="text-end text-secondary fw-semibold">Cantidad</th>
                                                    <th class="text-secondary fw-semibold">Unidad</th>
                                                    <th class="text-secondary fw-semibold">Lote</th>
                                                    <th class="text-secondary fw-semibold">Vencimiento</th>
                                                    <th class="text-end text-secondary fw-semibold">Costo Unit.</th>
                                                    <th class="text-center text-secondary fw-semibold" style="width: 80px;">Acción</th>
                                                </tr>
                                            </thead>
                                            <tbody id="movimientosDetalleBody">
                                                <tr data-empty="1">
                                                    <td colspan="7" class="text-center text-muted py-4">Aún no hay ítems agregados.</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                    <small class="text-muted d-block mt-1">Máximo 100 líneas por operación.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating shadow-sm rounded">
                        <textarea class="form-control border-0" id="referenciaMovimiento" name="referencia" style="height: 80px" maxlength="255" placeholder="Ref"></textarea>
                        <label for="referenciaMovimiento" class="fw-semibold text-muted">Referencia / Comentario <small class="text-muted">(opcional)</small></label>
                    </div>

                </form>
            </div>
            <div class="modal-footer bg-white border-top-0">
                <button type="button" class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formMovimientoInventario" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnGuardarMovimiento"><i class="bi bi-save me-2"></i>Guardar</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function(){
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        })
    });
</script>
<script src="<?php echo e(asset_url('js/tablas/iconos_accion.js')); ?>"></script>
<script src="<?php echo e(asset_url('js/inventario/inventario.js')); ?>?v=<?php echo time(); ?>"></script>