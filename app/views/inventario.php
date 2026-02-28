<?php
$stockActual = $stockActual ?? [];
$almacenes = $almacenes ?? [];
$proveedores = $proveedores ?? []; 
$idAlmacenFiltro = (int) ($id_almacen_filtro ?? 0);
?>
<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam-fill me-2 text-primary"></i> Inventario de Productos
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de existencias, kardex y movimientos de almacén.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('inventario/kardex')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-journal-text me-2 text-info"></i>Kardex
            </a>
            
            <div class="btn-group shadow-sm">
                <button type="button" class="btn btn-white border text-secondary fw-semibold dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-file-earmark-arrow-down me-2 text-info"></i>Exportar
                </button>
                <ul class="dropdown-menu dropdown-menu-end border-0 shadow">
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=csv&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-filetype-csv me-2"></i>CSV</a></li>
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=excel&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-file-earmark-excel me-2"></i>Excel</a></li>
                    <li><a class="dropdown-item" href="<?php echo e(route_url('inventario/exportar')); ?>&formato=pdf&id_almacen=<?php echo (int) $idAlmacenFiltro; ?>"><i class="bi bi-file-pdf me-2"></i>PDF</a></li>
                </ul>
            </div>

            <?php if (tiene_permiso('inventario.movimiento.crear')): ?>
                <button type="button" class="btn btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalMovimientoInventario">
                    <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Movimiento
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-3">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="inventarioSearch" placeholder="Buscar SKU o nombre...">
                    </div>
                </div>
                
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light border-secondary-subtle shadow-sm" id="inventarioFiltroTipoRegistro">
                        <option value="">Todos los Tipos</option>
                        <option value="item">Productos Base / Insumos</option>
                        <option value="pack">Presentaciones Comerciales (Packs)</option>
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-sm" id="inventarioFiltroAlmacen">
                        <option value="" <?php echo $idAlmacenFiltro === 0 ? 'selected' : ''; ?>>Todos los almacenes</option>
                        <?php foreach ($almacenes as $almacen): ?>
                            <option value="<?php echo (int) ($almacen['id'] ?? 0); ?>" <?php echo $idAlmacenFiltro === (int) ($almacen['id'] ?? 0) ? 'selected' : ''; ?>>
                                <?php echo e((string) ($almacen['nombre'] ?? '')); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <select class="form-select bg-light border-secondary-subtle shadow-sm" id="inventarioFiltroEstado">
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
                <table class="table align-middle mb-0 table-pro" id="tablaInventarioStock"
                       data-erp-table="true"
                       data-rows-selector="#inventarioTableBody tr:not(.empty-msg-row)"
                       data-search-input="#inventarioSearch"
                       data-empty-text="No hay stock coincidente"
                       data-info-text-template="Mostrando {start} a {end} de {total} registros"
                       data-erp-filters='[{"el":"#inventarioFiltroTipoRegistro","attr":"data-tipo-registro","match":"equals"},{"el":"#inventarioFiltroEstado","attr":"data-estado","match":"equals"}]'
                       data-pagination-controls="#inventarioPaginationControls"
                       data-pagination-info="#inventarioPaginationInfo">
                    <thead class="inventario-sticky-thead bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">SKU</th>
                            <th class="text-secondary fw-semibold">Producto (Nombre Completo)</th>
                            <th class="text-secondary fw-semibold">Almacén</th>
                            <th class="text-secondary fw-semibold">Lote</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Stock Actual</th>
                            <th class="text-center text-secondary fw-semibold">Situación / Alertas</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="inventarioTableBody">
                        <?php if (!empty($stockActual)): ?>
                            <?php foreach ($stockActual as $stock): ?>
                                <?php
                                $sku = (string) ($stock['sku'] ?? '');
                                $itemNombreCompleto = (string) ($stock['item_nombre'] ?? '');
                                $almacenNombre = (string) ($stock['almacen_nombre'] ?? '');
                                $loteActual = trim((string) ($stock['lote_actual'] ?? ''));
                                $idAlmacen = (int) ($stock['id_almacen'] ?? 0);
                                $tipoRegistro = (string) ($stock['tipo_registro'] ?? 'item');
                                
                                $stockFormateado = (string) ($stock['stock_formateado'] ?? '0');
                                $badgeColor = (string) ($stock['badge_color'] ?? '');
                                $badgeTexto = (string) ($stock['badge_estado'] ?? '');
                                $requiereFactorConversion = (int) ($stock['requiere_factor_conversion'] ?? 0) === 1;

                                $detalleAlerta = trim((string) ($stock['detalle_alerta'] ?? ''));

                                $search = mb_strtolower($sku . ' ' . $itemNombreCompleto . ' ' . $almacenNombre . ' ' . $loteActual);
                                // Generamos un id de estado limpio para que coincida con el option value del select
                                $estadoLimpio = strtolower(str_replace(' ', '_', $badgeTexto));
                                if ($estadoLimpio === 'proximo_a_vencer') $estadoLimpio = 'próximo_a_vencer';
                                ?>
                                <tr data-search="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-item-id="<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                    data-tipo-registro="<?php echo e($tipoRegistro); ?>"
                                    data-estado="<?php echo htmlspecialchars($estadoLimpio, ENT_QUOTES, 'UTF-8'); ?>"
                                    data-almacen="<?php echo (int) $idAlmacen; ?>" class="border-bottom">
                                    
                                    <td class="ps-4 fw-semibold text-primary align-top pt-3"><?php echo e($sku); ?></td>
                                    <td class="fw-semibold text-dark align-top pt-3">
                                        <?php echo e($itemNombreCompleto); ?>
                                        <?php if($tipoRegistro === 'pack'): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis ms-1 border border-info-subtle" style="font-size: 0.65rem;">PACK</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-muted small align-top pt-3 fw-medium"><i class="bi bi-building me-1 opacity-50"></i><?php echo e($almacenNombre); ?></td>
                                    <td class="align-top pt-3 fw-medium"><?php echo e($loteActual !== '' ? $loteActual : '-'); ?></td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <div class="fw-bold fs-6 text-primary"><?php echo $stockFormateado; ?></div>
                                        <?php if ($requiereFactorConversion && !empty($stock['desglose']) && is_array($stock['desglose'])): ?>
                                            <div class="d-flex flex-column align-items-end mt-1 pb-1" style="gap: 3px;">
                                                <?php foreach ($stock['desglose'] as $d): ?>
                                                    <div class="badge bg-white text-secondary border border-secondary-subtle shadow-sm px-2 py-1 fw-medium" style="font-size: 0.7rem;">
                                                        <?php echo e($d['texto']); ?>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo str_replace('bg-', 'bg-opacity-10 text-', str_replace('text-dark', '', $badgeColor)); ?>" style="border: 1px solid currentColor;">
                                            <?php echo e($badgeTexto); ?>
                                        </span>
                                        <?php if ($detalleAlerta !== ''): ?>
                                            <div class="small text-muted mt-1 fw-medium" style="font-size: 0.75rem;"><i class="bi bi-exclamation-circle me-1"></i><?php echo e($detalleAlerta); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            <?php $itemActivo = (int) ($stock['item_estado'] ?? 0) === 1; ?>
                                            <span class="badge rounded-pill <?php echo $itemActivo ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>"
                                                  data-bs-toggle="tooltip" data-bs-placement="top"
                                                  title="Estado referencial del ítem (solo lectura en inventario)">
                                                <?php echo $itemActivo ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                            <div class="vr bg-secondary opacity-25" style="height:20px;"></div>
                                            <?php if (in_array($tipoRegistro, ['item', 'pack'], true)): ?>
                                                <a href="<?php echo e(route_url('inventario/kardex')); ?>&item_id=<?php echo (int) ($stock['id_item'] ?? 0); ?>"
                                                   class="btn btn-sm btn-light text-primary border-0 bg-transparent rounded-circle"
                                                   data-bs-toggle="tooltip" data-bs-placement="top" title="Ver Kardex">
                                                    <i class="bi bi-journal-text fs-5"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small" data-bs-toggle="tooltip" data-bs-placement="top" title="Kardex disponible para ítems base">-</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-msg-row border-bottom-0"><td colspan="7" class="text-center text-muted py-5"><i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>No hay registros de stock disponibles.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($stockActual)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="inventarioPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación de inventario">
                    <ul class="pagination mb-0 shadow-sm" id="inventarioPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<div class="modal fade" id="modalMovimientoInventario" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    </div>

<script src="<?php echo e(asset_url('js/inventario.js')); ?>"></script>
