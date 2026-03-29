<?php
$ordenes = $ordenes ?? [];
$recetasActivas = $recetas_activas ?? [];
$almacenes = $almacenes ?? [];
$almacenesPlanta = $almacenes_planta ?? [];
$centros = $centros ?? []; // <-- Variable agregada para los centros de costo
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>
<style>
    /* Elimina el delay y la animación de deslizamiento en los detalles de la OP */
    tr.collapse-faltantes.collapsing {
        transition: none !important;
        animation: none !important;
    }

    /* ========================================================================= */
    /* ESTILOS DEL PLANIFICADOR (RESPONSIVO Y TARJETAS OPTIMIZADAS)              */
    /* ========================================================================= */
    
    .planificador-scroll-area {
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 15px;
    }

    /* Ajuste del Grid para PC: Scroll dinámico (Solo en semana) */
    @media (min-width: 768px) {
        #planificadorGrid.vista-semana {
            min-width: 1100px; /* Obliga al scroll en semana para mantener columnas anchas */
        }
        #planificadorGrid.vista-mes {
            min-width: 100%; /* Ajusta el mes exacto a la pantalla (Sin scroll) */
        }
    }

    /* DISEÑO PARA MÓVILES: El calendario se vuelve una lista (Agenda) */
    @media (max-width: 767px) {
        #modalPlanificadorProduccion .modal-body .d-grid.mb-2 { display: none !important; }
        
        #planificadorGrid {
            display: block !important; 
            min-width: 100% !important;
        }

        .plan-dia-card {
            margin-bottom: 15px !important;
            min-height: auto !important;
            border-style: solid !important; 
        }

        .plan-dia-card .fs-6 {
            background: #f8f9fa;
            display: block;
            margin: -8px -8px 8px -8px;
            padding: 5px 10px;
            border-bottom: 1px solid #ddd;
            font-size: 0.9rem !important;
        }
    }

    /* ESTILO DE LAS TARJETAS DE PRODUCTO (3 FILAS EN SEMANA) */
    .mini-card-op {
        transition: transform 0.2s;
        border-left-width: 4px !important;
    }
    .mini-card-op:hover {
        transform: scale(1.02);
    }
    .op-product-name {
        display: -webkit-box;
        -webkit-line-clamp: 3; /* <--- AHORA PERMITE 3 LÍNEAS EN SEMANA */
        -webkit-box-orient: vertical;
        overflow: hidden;
        line-height: 1.2;
        font-weight: 700;
        color: #2d3436;
    }

    /* ESTILO DE LAS TARJETAS MINIATURA (3 FILAS EN MES) */
    .op-product-name-mes {
        display: -webkit-box;
        -webkit-line-clamp: 3; /* <--- PERMITE 3 LÍNEAS EN EL MES */
        -webkit-box-orient: vertical;
        overflow: hidden;
        white-space: normal; /* Permite que el texto baje de línea */
        line-height: 1.2;
    }

    /* ========================================================================= */
    /* MODALES DE ÓRDENES: COMPORTAMIENTO CONSISTENTE EN MOBILE / TABLET / PC    */
    /* ========================================================================= */
    #modalPlanificarOP .modal-content,
    #modalEditarOP .modal-content,
    #modalDetalleOP .modal-content,
    #modalPlanificadorProduccion .modal-content,
    #modalEjecutarOP .modal-content {
        max-height: calc(100dvh - 2rem);
    }


    #modalEjecutarOP .modal-content {
        height: calc(100dvh - 2rem);
    }

    #modalEjecutarOP #formEjecutarOrden {
        display: flex;
        flex-direction: column;
        flex: 1 1 auto;
        min-height: 0;
    }

    #modalPlanificarOP .modal-body,
    #modalEditarOP .modal-body,
    #modalDetalleOP .modal-body,
    #modalPlanificadorProduccion .modal-body {
=======
    #modalPlanificarOP .modal-body,
    #modalEditarOP .modal-body,
    #modalDetalleOP .modal-body,
    #modalPlanificadorProduccion .modal-body,
    #modalEjecutarOP .modal-body {
        overflow-y: auto;
        overscroll-behavior: contain;
    }

    #modalEjecutarOP .modal-body {

        flex: 1 1 auto;
        min-height: 0;
        overflow-y: auto;
        overscroll-behavior: contain;
        padding-bottom: 1rem;
    }

    #modalEjecutarOP .modal-footer {
        position: sticky;
        bottom: 0;
        z-index: 2;
    }

    @media (max-width: 991.98px) {
        #modalPlanificarOP .modal-dialog,
        #modalEditarOP .modal-dialog,
        #modalDetalleOP .modal-dialog,
        #modalPlanificadorProduccion .modal-dialog,
        #modalEjecutarOP .modal-dialog {
            margin: 0.5rem auto;
            width: calc(100% - 1rem);
            max-width: none;
        }

        #modalPlanificarOP .modal-content,
        #modalEditarOP .modal-content,
        #modalDetalleOP .modal-content,
        #modalPlanificadorProduccion .modal-content,
        #modalEjecutarOP .modal-content {
            max-height: calc(100dvh - 1rem);
            border-radius: 0.9rem;
        }

        #modalEjecutarOP .modal-content {
            height: calc(100dvh - 1rem);
        }

    }

    @media (max-width: 767.98px) {
        #modalEjecutarOP .nav-link {
            font-size: 0.95rem;
            padding-left: 0.6rem;
            padding-right: 0.6rem;
        }

        #modalEjecutarOP .modal-footer {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        #modalEjecutarOP .modal-footer .btn {
            flex: 1 1 100%;
        }
    }
</style>

<div class="container-fluid p-4" id="ordenesProduccionApp">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Órdenes de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Planificación, ejecución y control de fabricación.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-dark shadow-sm fw-bold px-3 transition-hover" data-bs-toggle="modal" data-bs-target="#modalPlanificadorProduccion">
                <i class="bi bi-calendar3-week me-2"></i>Ver Planificador
            </button>
            <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" data-bs-toggle="modal" data-bs-target="#modalPlanificarOP">
                <i class="bi bi-plus-circle me-2"></i>Nueva OP
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="opSearch" placeholder="Buscar OP, producto, receta...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="opFiltroEstado">
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
                
                <table id="tablaOrdenes" class="table align-middle mb-0 table-pro table-hover"
                       data-erp-table="true"
                       data-rows-selector="#opTableBody tr.op-main-row"
                       data-search-input="#opSearch"
                       data-empty-text="No se encontraron órdenes de producción"
                       data-info-text-template="Mostrando {start} a {end} de {total} órdenes"
                       data-erp-filters='[{"el":"#opFiltroEstado","attr":"data-estado","match":"equals"}]'
                       data-erp-nested-rows="true"
                       data-pagination-controls="#opPaginationControls"
                       data-pagination-info="#opPaginationInfo"
                       data-rows-per-page="15">
                    <thead class="table-light border-bottom">
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
                                            <div class="small text-warning mt-1 fw-medium"><i class="bi bi-exclamation-triangle-fill me-1"></i>Con ajuste de stock</div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <span class="badge bg-light text-secondary border border-secondary-subtle shadow-sm px-2">Plan: <?php echo number_format((float) $orden['cantidad_planificada'], 4); ?></span>
                                            <?php if (!empty($orden['fecha_programada'])): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2"><i class="bi bi-calendar3 me-1"></i><?php echo e((string) $orden['fecha_programada']); ?></span>
                                            <?php endif; ?>
                                            <?php if (!empty($orden['almacen_planta_nombre'])): ?>
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2"><i class="bi bi-building me-1"></i><?php echo e((string) $orden['almacen_planta_nombre']); ?></span>
                                            <?php endif; ?>
                                            <?php if ((float) $orden['cantidad_producida'] > 0): ?>
                                                <span class="badge bg-success text-white px-2 shadow-sm"><i class="bi bi-check2-all me-1"></i>Real: <?php echo number_format((float) $orden['cantidad_producida'], 4); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <?php if ($estado === 0): // Solo evaluamos stock visualmente en Borrador ?>
                                            <?php if ($precheckOk): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">
                                                    <i class="bi bi-check-circle-fill me-1"></i> Completo
                                                </span>
                                            <?php else: ?>
                                                <div class="d-inline-flex align-items-center">
                                                    <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill">
                                                        <i class="bi bi-exclamation-triangle-fill me-1"></i> Faltantes
                                                    </span>
                                                    <button class="btn btn-sm btn-light rounded-circle border ms-2 text-secondary shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#faltantes-<?php echo $orden['id']; ?>" aria-expanded="false" title="Ver detalles de faltantes">
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
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-2 rounded-pill">Borrador</span>
                                        <?php elseif ($estado === 1): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill">En proceso</span>
                                        <?php elseif ($estado === 2): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Ejecutada</span>
                                        <?php elseif ($estado === 9): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">Anulada</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <?php if (in_array($estado, [0, 1], true)): ?>
                                            <div class="d-inline-flex align-items-center gap-2 acciones-op-wrap">
                                                <button class="btn btn-sm <?php echo $estado === 1 ? 'btn-info text-white' : 'btn-success'; ?> fw-bold px-3 shadow-sm js-abrir-ejecucion"
                                                        data-id="<?php echo (int) $orden['id']; ?>"
                                                        data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                        data-receta="<?php echo (int) $orden['id_receta']; ?>"
                                                        data-planificada="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                        data-precheck-ok="<?php echo $precheckOk ? '1' : '0'; ?>"
                                                        data-precheck-msg="<?php echo e((string) ($orden['precheck_resumen'] ?? '')); ?>"
                                                        data-req-lote="<?php echo (int) ($orden['requiere_lote'] ?? 0); ?>"
                                                        data-req-venc="<?php echo (int) ($orden['requiere_vencimiento'] ?? 0); ?>"
                                                        data-unidad="<?php echo e((string) ($orden['unidad_base'] ?? 'UND')); ?>"
                                                        data-id-almacen-planta="<?php echo (int) ($orden['id_almacen_planta'] ?? 0); ?>"
                                                        data-receta-centro-costo="<?php echo (int) ($orden['receta_id_centro_costo'] ?? 0); ?>"
                                                        title="<?php echo $estado === 1 ? 'Continuar Producción' : 'Ejecutar Producción'; ?>">
                                                    <i class="bi bi-play-fill"></i>
                                                    <span class="d-none d-lg-inline ms-1"><?php echo $estado === 1 ? 'Continuar' : 'Ejecutar'; ?></span>
                                                </button>

                                                <div class="btn-group btn-group-sm shadow-sm" role="group">
                                                    <a href="<?php echo e((string) route_url('inventario')); ?>"
                                                       class="btn btn-dark text-white <?php echo $precheckOk ? '' : 'btn-aprovisionar-alert'; ?>"
                                                       title="Aprovisionar Planta"
                                                       data-bs-toggle="tooltip">
                                                        <i class="bi bi-arrow-left-right"></i>
                                                    </a>

                                                    <?php if ($estado === 0): ?>
                                                    <button type="button"
                                                            class="btn btn-warning text-dark js-editar-op"
                                                            data-id="<?php echo (int) $orden['id']; ?>"
                                                            data-cantidad="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                            data-fecha="<?php echo e((string) ($orden['fecha_programada'] ?? '')); ?>"
                                                            data-id-almacen="<?php echo (int) ($orden['id_almacen_planta'] ?? 0); ?>"
                                                            data-observaciones="<?php echo e((string) ($orden['observaciones'] ?? '')); ?>"
                                                            data-bs-toggle="tooltip"
                                                            title="Editar borrador">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <?php endif; ?>
                                                </div>

                                                <?php if ($estado === 0): ?>
                                                <form method="post" class="d-inline js-swal-confirm" data-confirm-title="¿Eliminar borrador?" data-confirm-text="Se ocultará la orden.">
                                                    <input type="hidden" name="accion" value="eliminar_borrador">
                                                    <input type="hidden" name="id_orden" value="<?php echo (int) $orden['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-0 shadow-sm" data-bs-toggle="tooltip" title="Eliminar borrador">
                                                        <i class="bi bi-trash fs-6"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif (in_array($estado, [2, 9], true)): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-light border text-primary rounded-circle shadow-sm js-ver-detalle"
                                                    data-id="<?php echo (int) $orden['id']; ?>"
                                                    data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                    data-estado="<?php echo $estado === 2 ? 'Ejecutada' : 'Anulada'; ?>"
                                                    data-producto="<?php echo e((string) $orden['producto_nombre']); ?>"
                                                    data-plan="<?php echo number_format((float) $orden['cantidad_planificada'], 4); ?>"
                                                    data-real="<?php echo number_format((float) $orden['cantidad_producida'], 4); ?>"
                                                    data-md-real="<?php echo number_format((float) ($orden['costo_md_real'] ?? 0), 4); ?>"
                                                    data-mod-real="<?php echo number_format((float) ($orden['costo_mod_real'] ?? 0), 4); ?>"
                                                    data-cif-real="<?php echo number_format((float) ($orden['costo_cif_real'] ?? 0), 4); ?>"
                                                    data-total-real="<?php echo number_format((float) ($orden['costo_real_total'] ?? 0), 4); ?>"
                                                    data-md-teorico="<?php echo number_format(((float) ($orden['costo_md_teorico_unit'] ?? 0) * (float) ($orden['cantidad_planificada'] ?? 0)), 4); ?>"
                                                    data-mod-teorico="<?php echo number_format(((float) ($orden['costo_mod_teorico_unit'] ?? 0) * (float) ($orden['cantidad_planificada'] ?? 0)), 4); ?>"
                                                    data-cif-teorico="<?php echo number_format(((float) ($orden['costo_cif_teorico_unit'] ?? 0) * (float) ($orden['cantidad_planificada'] ?? 0)), 4); ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Ver detalle de costos y consumos">
                                                <i class="bi bi-search fs-6"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($estado === 0 && !$precheckOk): ?>
                                <tr class="collapse collapse-faltantes bg-light" id="faltantes-<?php echo $orden['id']; ?>" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td colspan="6" class="p-0 border-bottom">
                                        <div class="card border-0 border-start border-4 border-warning m-3 shadow-sm bg-white">
                                            <div class="card-body p-3">
                                                <div class="d-flex align-items-start">
                                                    <i class="bi bi-exclamation-triangle-fill text-warning fs-3 me-3 mt-1"></i>
                                                    <div class="w-100">
                                                        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 border-bottom pb-2">
                                                            <strong class="text-dark fs-6">Insumos a aprovisionar para ejecución</strong>
                                                            <span class="badge bg-secondary-subtle text-secondary-emphasis fs-6 fw-normal px-3 border border-secondary-subtle">
                                                                <i class="bi bi-building me-1"></i> Destino: <strong><?php echo e((string) ($orden['almacen_planta_nombre'] ?? 'Planta no asignada')); ?></strong>
                                                            </span>
                                                        </div>
                                                        <div class="small text-muted">
                                                            <?php 
                                                                $textoLimpio = str_replace('Faltantes: ', '', $precheckResumen);
                                                                $listaFaltantes = explode(';', $textoLimpio);
                                                            ?>
                                                            <ul class="list-unstyled mb-0 row g-2">
                                                                <?php foreach ($listaFaltantes as $itemFaltante): ?>
                                                                    <?php if (trim($itemFaltante) !== ''): ?>
                                                                        <li class="col-md-6 col-lg-4">
                                                                            <div class="d-flex align-items-center bg-light p-2 rounded border border-light-subtle">
                                                                                <i class="bi bi-box-seam text-secondary me-2"></i>
                                                                                <span class="text-dark fw-medium"><?php echo e(trim($itemFaltante)); ?></span>
                                                                            </div>
                                                                        </li>
                                                                    <?php endif; ?>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
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
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="opPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de OP">
                    <ul class="pagination mb-0 shadow-sm" id="opPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalPlanificarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Planificar Nueva Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" id="formCrearOP">
                    <input type="hidden" name="accion" value="crear_orden">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="newCodigoOP" class="form-label small text-muted fw-bold mb-1">Código OP <span class="text-danger">*</span></label>
                                    <input type="text" required name="codigo" id="newCodigoOP" class="form-control shadow-none border-secondary-subtle bg-light fw-bold text-primary" placeholder="Generando auto..." readonly>
                                </div>
                                <div class="col-md-8">
                                    <label for="newRecetaOP" class="form-label small text-muted fw-bold mb-1">Receta / Producto Terminado <span class="text-danger">*</span></label>
                                    <select name="id_receta" id="newRecetaOP" required class="form-select shadow-none border-secondary-subtle">
                                        <option value="">Seleccione fórmula...</option>
                                        <?php foreach ($recetasActivas as $r): ?>
                                            <option value="<?php echo (int) $r['id']; ?>"
                                                    data-rendimiento="<?php echo (float) ($r['rendimiento_base'] ?? 1); ?>" 
                                                    data-tiempo="<?php echo (float) ($r['tiempo_estimado_horas'] ?? 1); ?>"
                                                    data-id-almacen-planta="<?php echo (int) ($r['id_almacen_planta'] ?? 0); ?>">
                                                <?php echo e((string) $r['codigo']); ?> - <?php echo e((string) $r['producto_nombre']); ?> (v<?php echo (int) $r['version']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-12 mb-1 mt-3 border-top pt-3">
                                    <label class="form-label small text-muted fw-bold mb-2">Estrategia de Planificación <span class="text-danger">*</span></label>
                                    <div class="d-flex gap-4">
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="modo_planificacion" id="modoCant" value="cantidad" checked disabled>
                                            <label class="form-check-label small fw-bold text-dark" for="modoCant">
                                                <i class="bi bi-box-seam me-1 text-primary"></i> Por Cantidad a Producir
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="modo_planificacion" id="modoHoras" value="horas" disabled>
                                            <label class="form-check-label small fw-bold text-dark" for="modoHoras">
                                                <i class="bi bi-clock-history me-1 text-warning"></i> Por Horas de Máquina
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="newCantPlan" class="form-label small text-muted fw-bold mb-1">Cantidad a Producir <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input name="cantidad_planificada" id="newCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control shadow-none border-primary fw-bold bg-light text-muted" placeholder="Seleccione receta primero" readonly>
                                        <span class="input-group-text bg-light text-muted">Und/Kg</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="newHorasPlan" class="form-label small text-muted fw-bold mb-1">Horas de Trabajo <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input id="newHorasPlan" min="0.0001" step="0.0001" type="number" class="form-control shadow-none bg-light text-muted" placeholder="Seleccione receta primero" readonly>
                                        <span class="input-group-text bg-light text-muted">Hrs</span>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label for="newFechaProgramada" class="form-label small text-muted fw-bold mb-1">Fecha Programada <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_programada" id="newFechaProgramada" class="form-control shadow-none border-secondary-subtle" required>
                                </div>
                                <div class="col-md-6">
                                    <label for="newAlmacenPlanta" class="form-label small text-muted fw-bold mb-1">Planta de Trabajo <span class="text-danger">*</span></label>
                                    <select name="id_almacen_planta" id="newAlmacenPlanta" class="form-select shadow-none border-secondary-subtle" required>
                                        <option value="">Seleccione planta...</option>
                                        <?php foreach ($almacenesPlanta as $a): ?>
                                            <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo e((string) ($a['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label for="newObsOP" class="form-label small text-muted fw-bold mb-1">Observaciones / Lote Estimado</label>
                                    <input name="observaciones" id="newObsOP" class="form-control shadow-none border-secondary-subtle" placeholder="Notas adicionales o pre-asignación de lote...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-2">
                        <button type="button" class="btn btn-light text-secondary me-2 border px-4 fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Borrador OP</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Borrador de Producción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" id="formEditarOP">
                    <input type="hidden" name="accion" value="editar_orden">
                    <input type="hidden" name="id_orden" id="editIdOrden">

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="editCantPlan" class="form-label small text-muted fw-bold mb-1">Cantidad a Producir <span class="text-danger">*</span></label>
                                    <input name="cantidad_planificada" id="editCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control shadow-none border-warning fw-bold">
                                </div>
                                <div class="col-md-4">
                                    <label for="editFechaProgramada" class="form-label small text-muted fw-bold mb-1">Fecha Programada <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_programada" id="editFechaProgramada" class="form-control shadow-none border-secondary-subtle" required>
                                </div>
                                <div class="col-md-4">
                                    <label for="editAlmacenPlanta" class="form-label small text-muted fw-bold mb-1">Planta de Trabajo <span class="text-danger">*</span></label>
                                    <select name="id_almacen_planta" id="editAlmacenPlanta" class="form-select shadow-none border-secondary-subtle" required>
                                        <option value="">Seleccione planta...</option>
                                        <?php foreach ($almacenesPlanta as $a): ?>
                                            <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo e((string) ($a['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label for="editObsOP" class="form-label small text-muted fw-bold mb-1">Observaciones / Lote Estimado</label>
                                    <input name="observaciones" id="editObsOP" class="form-control shadow-none border-secondary-subtle" placeholder="Opcional">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-2">
                        <button type="button" class="btn btn-light text-secondary me-2 border px-4 fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleOP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-clipboard2-data me-2"></i>Análisis Financiero de OP</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3">
                        <div class="row g-2 align-items-center text-center text-md-start">
                            <div class="col-6 col-md-3 border-end">
                                <div class="small text-muted fw-bold text-uppercase">Código</div>
                                <div id="detalleCodigo" class="fw-bold fs-5 text-primary">-</div>
                            </div>
                            <div class="col-6 col-md-5 border-end">
                                <div class="small text-muted fw-bold text-uppercase">Producto Final</div>
                                <div id="detalleProducto" class="fw-bold fs-6 text-dark text-truncate">-</div>
                            </div>
                            <div class="col-6 col-md-2 border-end">
                                <div class="small text-muted fw-bold text-uppercase">Planificado</div>
                                <div id="detallePlan" class="fw-bold fs-5 text-secondary">0.0000</div>
                            </div>
                            <div class="col-6 col-md-2">
                                <div class="small text-muted fw-bold text-uppercase">Producido Real</div>
                                <div id="detalleReal" class="fw-bold fs-5 text-success">0.0000</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs px-3 pt-3 bg-white rounded-top" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-bold px-4 py-3 text-primary" data-bs-toggle="tab" data-bs-target="#tabDetalleMd" type="button"><i class="bi bi-box-seam me-2"></i>Materiales (MD)</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold px-4 py-3 text-secondary" data-bs-toggle="tab" data-bs-target="#tabDetalleMod" type="button"><i class="bi bi-people me-2"></i>Mano de Obra (MOD)</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold px-4 py-3 text-secondary" data-bs-toggle="tab" data-bs-target="#tabDetalleCif" type="button"><i class="bi bi-lightning-charge me-2"></i>Gastos Indirectos (CIF)</button>
                            </li>
                        </ul>

                        <div class="tab-content p-0" style="min-height:260px;">
                            <div class="tab-pane fade show active" id="tabDetalleMd" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Insumo Utilizado</th>
                                                <th class="text-end">Cant. Teórica / Real</th>
                                                <th class="text-end">Costo Unitario</th>
                                                <th class="text-end pe-4">Costo Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detalleTablaMd">
                                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-1"></i>Sin datos</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabDetalleMod" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Operario / Perfil</th>
                                                <th class="text-end">Horas Registradas</th>
                                                <th class="text-end">Costo por Hora</th>
                                                <th class="text-end pe-4">Costo Total</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detalleTablaMod">
                                            <tr><td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-1"></i>Sin datos</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="tabDetalleCif" role="tabpanel">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">Concepto / Activo</th>
                                                <th>Base (Horas)</th>
                                                <th class="text-end pe-4">Costo Aplicado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="detalleTablaCif">
                                            <tr><td colspan="3" class="text-center text-muted py-4"><i class="bi bi-inbox fs-3 d-block mb-1"></i>Sin datos</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border border-primary-subtle shadow-sm bg-primary-subtle bg-opacity-10">
                    <div class="card-body p-4">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted fw-bold text-uppercase small">Estado OP:</span>
                                <span id="detalleEstado" class="badge bg-secondary px-3 py-2 fs-6 rounded-pill">-</span>
                            </div>
                            
                            <div class="d-flex align-items-center flex-wrap gap-3 fs-5">
                                <div class="text-center px-3 border-end">
                                    <span class="d-block text-muted small fw-bold mb-1">Materiales (MD)</span>
                                    <span class="fw-semibold text-dark">S/ <span id="detalleMdReal">0.00</span></span>
                                </div>
                                <div class="text-center px-3 border-end">
                                    <span class="d-block text-muted small fw-bold mb-1">Mano Obra (MOD)</span>
                                    <span class="fw-semibold text-dark">S/ <span id="detalleModReal">0.00</span></span>
                                </div>
                                <div class="text-center px-3 border-end">
                                    <span class="d-block text-muted small fw-bold mb-1">Indirectos (CIF)</span>
                                    <span class="fw-semibold text-dark">S/ <span id="detalleCifReal">0.00</span></span>
                                </div>
                                <div class="text-center px-3">
                                    <span class="d-block text-primary small fw-bold mb-1">Costo Total</span>
                                    <span class="fw-bold text-primary fs-4">S/ <span id="detalleTotalReal">0.00</span></span>
                                </div>
                                <div class="text-center ps-3 border-start">
                                    <span class="d-block text-success small fw-bold mb-1">Costo Unitario</span>
                                    <span class="fw-bold text-success fs-4">S/ <span id="detalleUnitarioReal">0.00</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEjecutarOP" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-success text-white border-bottom-0">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-play-fill me-2"></i>Ejecutar Producción 
                    <span class="badge bg-white text-success ms-2" id="lblExecCodigo">OP-000000</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form method="post" id="formEjecutarOrden">
                <input type="hidden" name="accion" value="ejecutar_orden">
                <input type="hidden" name="id_orden" id="execIdOrden">
                <input type="hidden" id="execReqLote">
                <input type="hidden" id="execReqVenc">
                <input type="hidden" id="execUnidad">

                <div class="modal-body p-4 bg-light">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                            <h6 class="fw-bold text-dark mb-0"><i class="bi bi-clock-history text-primary me-2"></i>Control de Tiempos de Máquina</h6>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary"><i class="bi bi-play-circle text-success me-1"></i>Inicio del Trabajo <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="fecha_inicio" id="execFechaInicio" class="form-control form-control-sm border-secondary-subtle fw-medium" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary"><i class="bi bi-stop-circle text-danger me-1"></i>Fin del Trabajo <span class="text-danger">*</span></label>
                                    <input type="datetime-local" name="fecha_fin" id="execFechaFin" class="form-control form-control-sm border-secondary-subtle fw-medium" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold text-secondary"><i class="bi bi-pause-circle text-warning me-1"></i>Horas de Parada</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.01" min="0" name="horas_parada" id="execHorasParada" class="form-control border-secondary-subtle" placeholder="Ej: 0.5">
                                        <span class="input-group-text bg-light text-muted">Hrs</span>
                                    </div>
                                    <div class="form-text" style="font-size: 0.7rem;">Descansos, limpieza o fallas mecánicas.</div>
                                </div>
                            </div>
                            
                            <div class="mt-3 pt-3 border-top d-flex justify-content-between align-items-center bg-light rounded p-2 px-3">
                                <span class="text-muted fw-bold small"><i class="bi bi-calculator me-1"></i>Tiempo Efectivo a Costear por Tarifa de Planta:</span>
                                <span class="badge bg-dark fs-5 shadow-sm" id="lblTiempoNeto">0.00 <small class="fs-6 text-light fw-normal opacity-75">Horas</small></span>
                            </div>
                        </div>
                    </div>

                    <div id="boxJustificacionFaltante" style="display: none;"></div>

                    <ul class="nav nav-tabs nav-tabs-custom mb-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active text-danger fw-bold" data-bs-toggle="tab" data-bs-target="#tabConsumos" type="button" role="tab">
                                <i class="bi bi-box-arrow-up-right me-2"></i>1. Consumos (Salidas)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-success fw-bold" data-bs-toggle="tab" data-bs-target="#tabIngresos" type="button" role="tab">
                                <i class="bi bi-box-arrow-in-down-left me-2"></i>2. Ingresos (Producto Final)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content bg-white p-3 rounded shadow-sm border mb-4">
                        <div class="tab-pane fade show active" id="tabConsumos" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Insumos Utilizados</h6>
                                    <p class="small text-muted mb-0">Material que se descontará del almacén.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="btnAgregarConsumo">
                                    <i class="bi bi-plus-lg me-1"></i> Añadir Fila
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle table-bordered mb-0" id="tablaConsumosDynamic">
                                    <thead class="table-light text-muted small text-uppercase">
                                        <tr>
                                            <th>Insumo (ID / Nombre)</th>
                                            <th style="width: 25%;">Almacén Origen</th>
                                            <th style="width: 15%; text-align: center;">Cantidad</th>
                                            <th style="width: 20%;">Lote (Opcional)</th>
                                            <th style="width: 5%; text-align: center;">Quitar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tabIngresos" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Productos a Ingresar</h6>
                                    <p class="small text-muted mb-0">Unidades terminadas que entrarán al almacén.</p>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarIngreso">
                                    <i class="bi bi-plus-lg me-1"></i> Añadir Fila
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle table-bordered mb-0" id="tablaIngresosDynamic">
                                    <thead class="table-light text-muted small text-uppercase">
                                        <tr>
                                            <th style="width: 25%;">Almacén Destino</th>
                                            <th style="width: 20%; text-align: center;">Cantidad Producida</th>
                                            <th>Lote Asignado</th>
                                            <th>F. Vencimiento</th>
                                            <th style="width: 5%; text-align: center;">Quitar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-2">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Centro de Costos <span class="text-danger">*</span></label>
                            <select name="id_centro_costo" id="execCentroCosto" class="form-select shadow-none border-secondary-subtle" required>
                                <option value="">Seleccione a dónde irá el gasto...</option>
                                <?php if (!empty($centros)): ?>
                                    <?php foreach ($centros as $c): ?>
                                        <?php if ((int)$c['estado'] === 1): // Solo mostramos los activos ?>
                                            <option value="<?php echo (int)$c['id']; ?>">
                                                <?php echo e($c['codigo']); ?> - <?php echo e($c['nombre']); ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <option value="" disabled>No hay centros de costo registrados</option>
                                <?php endif; ?>
                            </select>
                            <div class="form-text" style="font-size: 0.7rem;">Requerido para la salida de insumos por consumo.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Nota o Justificación de Cierre (Opcional)</label>
                            <input type="text" name="justificacion" class="form-control shadow-none border-secondary-subtle" placeholder="Ej: Se utilizó material alternativo...">
                        </div>
                    </div>
                    </div>

                <div class="modal-footer bg-white border-top shadow-sm">
                    <button type="button" class="btn btn-light border fw-semibold px-4" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold px-5"><i class="bi bi-check-circle-fill me-2"></i>Guardar y Ejecutar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPlanificadorProduccion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"> 
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-range me-2 text-info"></i>Planificador de Operaciones</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1">
                            <button type="button" class="btn btn-sm btn-light text-secondary border-0 px-3 transition-hover" id="btnPlanAnterior"><i class="bi bi-chevron-left"></i></button>
                            <span class="mx-3 fw-bold text-primary fs-5 text-uppercase text-center" id="lblPlanActual" style="min-width: 180px;">Cargando...</span>
                            <button type="button" class="btn btn-sm btn-light text-secondary border-0 px-3 transition-hover" id="btnPlanSiguiente"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        
                        <div class="btn-group shadow-sm" role="group">
                            <input type="radio" class="btn-check" name="vistaPlanificador" id="vistaMes" value="mes" checked autocomplete="off">
                            <label class="btn btn-outline-primary fw-semibold px-4" for="vistaMes"><i class="bi bi-calendar-month me-1"></i> Mes</label>

                            <input type="radio" class="btn-check" name="vistaPlanificador" id="vistaSemana" value="semana" autocomplete="off">
                            <label class="btn btn-outline-primary fw-semibold px-4" for="vistaSemana"><i class="bi bi-calendar-week me-1"></i> Semana</label>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-3 rounded shadow-sm border text-center min-vh-50 position-relative overflow-hidden">
                    <div id="planLoader" class="position-absolute w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex justify-content-center align-items-center d-none" style="z-index: 10;">
                        <div class="spinner-border text-primary shadow-sm" role="status" style="width: 3rem; height: 3rem;"></div>
                    </div>

                    <div class="d-grid mb-2 border-bottom pb-2" style="grid-template-columns: repeat(7, 1fr); gap: 10px;">
                        <div class="fw-bold text-secondary text-uppercase small">Lunes</div>
                        <div class="fw-bold text-secondary text-uppercase small">Martes</div>
                        <div class="fw-bold text-secondary text-uppercase small">Miércoles</div>
                        <div class="fw-bold text-secondary text-uppercase small">Jueves</div>
                        <div class="fw-bold text-secondary text-uppercase small">Viernes</div>
                        <div class="fw-bold text-secondary text-uppercase small">Sábado</div>
                        <div class="fw-bold text-danger text-uppercase small">Domingo</div>
                    </div>
                    
                    <div class="planificador-scroll-area mt-3">
                        <div id="planificadorGrid" class="d-grid" style="grid-template-columns: repeat(7, 1fr); gap: 10px; min-height: 400px;">
                        </div>
                    </div>
                </div>

                <div class="card mt-4 border-0 shadow-sm bg-white">
                    <div class="card-body p-3">
                        <h6 class="small fw-bold text-muted text-uppercase mb-3"><i class="bi bi-info-circle-fill me-2 text-info"></i>Leyenda de Planificación</h6>
                        <div class="d-flex flex-wrap gap-4 small fw-semibold text-dark">
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-light border border-secondary border-dashed me-2 shadow-sm" style="width: 16px; height: 16px;"></span> Día Libre / Sin Actividad</div>
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-primary-subtle border border-primary me-2 shadow-sm" style="width: 16px; height: 16px;"></span> Turno Normal</div>
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-warning-subtle border border-warning me-2 shadow-sm" style="width: 16px; height: 16px;"></span> Turno Extendido (Excepción)</div>
                            <div class="d-flex align-items-center"><span class="badge bg-dark ms-2 me-2 border px-2 py-1 shadow-sm">OP</span> Órdenes Asignadas al día</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<template id="tplSelectAlmacenes">
    <?php foreach ($almacenes as $a): ?>
        <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
    <?php endforeach; ?>
</template>
