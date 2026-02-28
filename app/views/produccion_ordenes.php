<?php
$ordenes = $ordenes ?? [];
$recetasActivas = $recetas_activas ?? [];
$almacenes = $almacenes ?? [];
$almacenesPlanta = $almacenes_planta ?? [];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>
<style>
    /* Elimina el delay y la animación de deslizamiento en los detalles de la OP */
    tr.collapse-faltantes.collapsing {
        transition: none !important;
        animation: none !important;
    }
</style>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Órdenes de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Planificación, ejecución y control de fabricación.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalPlanificarOP">
                <i class="bi bi-plus-circle me-2"></i>Nueva OP
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="opSearch" placeholder="Buscar OP, producto, receta...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="opFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="0">Borrador</option>
                        <option value="1">En Proceso</option>
                        <option value="2">Ejecutada</option>
                        <option value="9">Anulada</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                
                <table id="tablaOrdenes" class="table align-middle mb-0 table-pro"
                       data-erp-table="true"
                       data-rows-selector="#opTableBody tr.op-main-row"
                       data-search-input="#opSearch"
                       data-empty-text="No se encontraron órdenes de producción"
                       data-info-text-template="Mostrando {start} a {end} de {total} órdenes"
                       data-erp-filters='[{"el":"#opFiltroEstado","attr":"data-estado","match":"equals"}]'
                       data-erp-nested-rows="true"
                       data-pagination-controls="#opPaginationControls"
                       data-pagination-info="#opPaginationInfo">
                    <thead>
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código OP</th>
                            <th class="text-secondary fw-semibold">Producto / Receta</th>
                            <th class="text-secondary fw-semibold">Planificado / Real</th>
                            <th class="text-center text-secondary fw-semibold">Stock</th> 
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="opTableBody">
                        <?php if (empty($ordenes)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-gear fs-1 d-block mb-2 text-light"></i>
                                    No hay órdenes de producción registradas.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($ordenes as $orden): ?>
                                <?php 
                                    $estado = (int) ($orden['estado'] ?? 0); 
                                    $precheckOk = (int) ($orden['precheck_ok'] ?? 0) === 1;
                                    $precheckResumen = (string) ($orden['precheck_resumen'] ?? 'Sin información de faltantes');
                                    
                                    // Búsqueda inteligente: Incluye código, producto y receta
                                    $searchStr = strtolower($orden['codigo'] . ' ' . $orden['producto_nombre'] . ' ' . $orden['receta_codigo'] . ' ' . (string) ($orden['almacen_planta_nombre'] ?? ''));
                                ?>
                                
                                <tr class="border-bottom op-main-row" 
                                    data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>" 
                                    data-estado="<?php echo $estado; ?>">
                                    
                                    <td class="ps-4 fw-bold text-primary align-top pt-3 <?php echo $estado === 9 ?"text-decoration-line-through text-muted" : ""; ?>">
                                        <?php echo e((string) $orden['codigo']); ?>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <div class="fw-bold text-dark <?php echo $estado === 9 ? "text-decoration-line-through text-muted" : ""; ?>"><?php echo e((string) $orden['producto_nombre']); ?></div>
                                        <div class="small text-muted"><i class="bi bi-receipt me-1"></i><?php echo e((string) $orden['receta_codigo']); ?></div>
                                        <?php if (!empty($orden['justificacion_ajuste'])): ?>
                                            <div class="small text-warning mt-1"><i class="bi bi-exclamation-triangle"></i> Con ajuste de stock</div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <div class="d-flex flex-column align-items-start">
                                            <span class="badge bg-light text-dark border mb-1">Plan: <?php echo number_format((float) $orden['cantidad_planificada'], 4); ?></span>
                                            <?php if (!empty($orden['fecha_programada'])): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle mb-1"><i class="bi bi-calendar3 me-1"></i><?php echo e((string) $orden['fecha_programada']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($orden['turno_programado'])): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1"><i class="bi bi-clock me-1"></i><?php echo e((string) $orden['turno_programado']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($orden['almacen_planta_nombre'])): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle mb-1"><i class="bi bi-building me-1"></i><?php echo e((string) $orden['almacen_planta_nombre']); ?></span>
                                            <?php endif; ?>
                                            <?php if ((float) $orden['cantidad_producida'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check2-all me-1"></i>Real: <?php echo number_format((float) $orden['cantidad_producida'], 4); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <?php if ($estado === 0): // Solo evaluamos stock visualmente en Borrador ?>
                                            <?php if ($precheckOk): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Completo
                                                </span>
                                            <?php else: ?>
                                                <div class="d-inline-flex align-items-center">
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1 rounded-pill">
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Faltantes
                                                    </span>
                                                    <button class="btn btn-sm btn-light rounded-circle border ms-2 text-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#faltantes-<?php echo $orden['id']; ?>" aria-expanded="false" title="Ver detalles">
                                                        <i class="bi bi-chevron-down"></i>
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted small opacity-50">-</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-center align-top pt-3">
                                        <?php if ($estado === 0): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill">Borrador</span>
                                        <?php elseif ($estado === 1): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 rounded-pill">En proceso</span>
                                        <?php elseif ($estado === 2): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Ejecutada</span>
                                        <?php elseif ($estado === 9): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Anulada</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <?php if (in_array($estado, [0, 1], true)): ?>
                                            <div class="d-inline-flex align-items-center gap-1 acciones-op-wrap">
                                                <button class="btn btn-sm <?php echo $estado === 1 ? 'btn-info text-white' : 'btn-success'; ?> fw-semibold js-abrir-ejecucion"
                                                        data-id="<?php echo (int) $orden['id']; ?>"
                                                        data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                        data-receta="<?php echo (int) $orden['id_receta']; ?>"
                                                        data-planificada="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                        data-precheck-ok="<?php echo $precheckOk ? '1' : '0'; ?>"
                                                        data-precheck-msg="<?php echo e((string) ($orden['precheck_resumen'] ?? '')); ?>"
                                                        data-req-lote="<?php echo (int) ($orden['requiere_lote'] ?? 0); ?>"
                                                        data-req-venc="<?php echo (int) ($orden['requiere_vencimiento'] ?? 0); ?>"
                                                        data-unidad="<?php echo e((string) ($orden['unidad_base'] ?? 'UND')); ?>"
                                                        title="<?php echo $estado === 1 ? 'Continuar Producción' : 'Ejecutar Producción'; ?>">
                                                    <i class="bi bi-play-fill"></i>
                                                    <span class="d-none d-lg-inline ms-1"><?php echo $estado === 1 ? 'Continuar' : 'Ejecutar'; ?></span>
                                                </button>

                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="<?php echo e((string) route_url('inventario')); ?>"
                                                       class="btn btn-dark text-white <?php echo $precheckOk ? '' : 'btn-aprovisionar-alert'; ?>"
                                                       title="Aprovisionar Planta"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-placement="top">
                                                        <i class="bi bi-arrow-left-right"></i>
                                                    </a>

                                                    <?php if ($estado === 0): ?>
                                                    <button type="button"
                                                            class="btn btn-warning text-dark js-editar-op"
                                                            data-id="<?php echo (int) $orden['id']; ?>"
                                                            data-cantidad="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                            data-fecha="<?php echo e((string) ($orden['fecha_programada'] ?? '')); ?>"
                                                            data-turno="<?php echo e((string) ($orden['turno_programado'] ?? '')); ?>"
                                                            data-id-almacen="<?php echo (int) ($orden['id_almacen_planta'] ?? 0); ?>"
                                                            data-observaciones="<?php echo e((string) ($orden['observaciones'] ?? '')); ?>"
                                                            title="Editar borrador">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($estado === 0): ?>
                                                <form method="post" class="d-inline js-swal-confirm" data-confirm-title="¿Eliminar borrador?" data-confirm-text="Se ocultará la orden.">
                                                    <input type="hidden" name="accion" value="eliminar_borrador">
                                                    <input type="hidden" name="id_orden" value="<?php echo (int) $orden['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Eliminar borrador">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (in_array($estado, [2, 9], true)): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-light border js-ver-detalle"
                                                    data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                    data-estado="<?php echo $estado === 2 ? 'Ejecutada' : 'Anulada'; ?>"
                                                    data-producto="<?php echo e((string) $orden['producto_nombre']); ?>"
                                                    data-plan="<?php echo number_format((float) $orden['cantidad_planificada'], 4); ?>"
                                                    data-real="<?php echo number_format((float) $orden['cantidad_producida'], 4); ?>"
                                                    title="Ver detalle">
                                                <i class="bi bi-search text-secondary"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($estado === 0 && !$precheckOk): ?>
                                <tr class="collapse collapse-faltantes bg-light" id="faltantes-<?php echo $orden['id']; ?>" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td colspan="6" class="p-0 border-bottom">
                                        <div class="p-3 border-start border-4 border-warning m-2 bg-white shadow-sm rounded">
                                            <div class="d-flex align-items-start">
                                                <i class="bi bi-exclamation-triangle-fill text-warning fs-4 me-3 mt-1"></i>
                                                <div class="w-100">
                                                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-2 border-bottom pb-2">
                                                        <strong class="text-dark fs-6">Insumos a aprovisionar</strong>
                                                        <span class="badge bg-secondary-subtle text-secondary-emphasis fs-6 fw-normal">
                                                            <i class="bi bi-building me-1"></i> Destino: <strong><?php echo e((string) ($orden['almacen_planta_nombre'] ?? 'Planta no asignada')); ?></strong>
                                                        </span>
                                                    </div>
                                                    <div class="small text-muted">
                                                        <?php 
                                                            $textoLimpio = str_replace('Faltantes: ', '', $precheckResumen);
                                                            $listaFaltantes = explode(';', $textoLimpio);
                                                        ?>
                                                        <ul class="list-unstyled mb-0 ms-1 row">
                                                            <?php foreach ($listaFaltantes as $itemFaltante): ?>
                                                                <?php if (trim($itemFaltante) !== ''): ?>
                                                                    <li class="col-md-6 mb-1 py-1 border-bottom border-light">
                                                                        <i class="bi bi-box-seam text-secondary me-2"></i>
                                                                        <span class="text-dark fw-medium"><?php echo e(trim($itemFaltante)); ?></span>
                                                                    </li>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </ul>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>

                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($ordenes)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="opPaginationInfo">Procesando...</div>
                <nav aria-label="Paginación de OP">
                    <ul class="pagination mb-0 shadow-sm" id="opPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalPlanificarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    </div>

<div class="modal fade" id="modalEditarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
     </div>

<div class="modal fade" id="modalDetalleOP" tabindex="-1" aria-hidden="true">
     </div>

<div class="modal fade" id="modalEjecutarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
     </div>

<template id="tplSelectAlmacenes">
    <?php foreach ($almacenes as $a): ?>
        <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
    <?php endforeach; ?>
</template>

<script src="<?php echo base_url(); ?>/assets/js/produccion.js?v=2.6"></script>

