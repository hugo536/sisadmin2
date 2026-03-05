<?php
$ordenes = $ordenes ?? [];
$recetasActivas = $recetas_activas ?? [];
$almacenes = $almacenes ?? [];
$almacenesPlanta = $almacenes_planta ?? [];
$turnosProgramables = $turnos_programables ?? [];
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
            <button class="btn btn-dark shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#modalPlanificadorProduccion">
                <i class="bi bi-calendar3-week me-2"></i>Ver Planificador
            </button>
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
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Planificar Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" class="modal-body p-4 bg-light">
                <input type="hidden" name="accion" value="crear_orden">
                
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="newCodigoOP" class="form-label small text-muted fw-bold mb-1">Código OP <span class="text-danger fs-6">*</span></label>
                                <input type="text" required name="codigo" id="newCodigoOP" class="form-control bg-light fw-bold text-primary" placeholder="Generando..." readonly>
                            </div>
                            <div class="col-md-8">
                                <label for="newRecetaOP" class="form-label small text-muted fw-bold mb-1">Receta / Producto Terminado <span class="text-danger fs-6">*</span></label>
                                <select name="id_receta" id="newRecetaOP" required class="form-select">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($recetasActivas as $r): ?>
                                        <option value="<?php echo (int) $r['id']; ?>">
                                            <?php echo e((string) $r['codigo']); ?> - <?php echo e((string) $r['producto_nombre']); ?> (v<?php echo (int) $r['version']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="newCantPlan" class="form-label small text-muted fw-bold mb-1">Cantidad Planificada <span class="text-danger fs-6">*</span></label>
                                <input name="cantidad_planificada" id="newCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control border-primary" placeholder="Ej: 100">
                            </div>
                            <div class="col-md-4">
                                <label for="newFechaProgramada" class="form-label small text-muted fw-bold mb-1">Fecha Programada <span class="text-danger fs-6">*</span></label>
                                <input type="date" name="fecha_programada" id="newFechaProgramada" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label for="newTurnoProgramado" class="form-label small text-muted fw-bold mb-1">Turno Programado <span class="text-danger fs-6">*</span></label>
                                <select name="turno_programado" id="newTurnoProgramado" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($turnosProgramables as $turno): ?>
                                        <option value="<?php echo e((string) ($turno['nombre'] ?? '')); ?>"><?php echo e((string) ($turno['nombre'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="newAlmacenPlanta" class="form-label small text-muted fw-bold mb-1">Almacén Planta <span class="text-danger fs-6">*</span></label>
                                <select name="id_almacen_planta" id="newAlmacenPlanta" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($almacenesPlanta as $a): ?>
                                        <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo e((string) ($a['nombre'] ?? '')); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-8">
                                <label for="newObsOP" class="form-label small text-muted fw-bold mb-1">Observaciones / Lote Estimado</label>
                                <input name="observaciones" id="newObsOP" class="form-control" placeholder="Opcional">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-2">
                    <button type="button" class="btn btn-light text-secondary me-2 border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar Borrador</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>Editar Borrador OP</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" class="modal-body p-4 bg-light" id="formEditarOP">
                <input type="hidden" name="accion" value="editar_orden">
                <input type="hidden" name="id_orden" id="editIdOrden">

                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="editCantPlan" class="form-label small text-muted fw-bold mb-1">Cantidad Planificada <span class="text-danger fs-6">*</span></label>
                        <input name="cantidad_planificada" id="editCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control border-primary">
                    </div>
                    <div class="col-md-4">
                        <label for="editFechaProgramada" class="form-label small text-muted fw-bold mb-1">Fecha Programada <span class="text-danger fs-6">*</span></label>
                        <input type="date" name="fecha_programada" id="editFechaProgramada" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label for="editTurnoProgramado" class="form-label small text-muted fw-bold mb-1">Turno Programado <span class="text-danger fs-6">*</span></label>
                        <select name="turno_programado" id="editTurnoProgramado" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($turnosProgramables as $turno): ?>
                                <option value="<?php echo e((string) ($turno['nombre'] ?? '')); ?>"><?php echo e((string) ($turno['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="editAlmacenPlanta" class="form-label small text-muted fw-bold mb-1">Almacén Planta <span class="text-danger fs-6">*</span></label>
                        <select name="id_almacen_planta" id="editAlmacenPlanta" class="form-select" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($almacenesPlanta as $a): ?>
                                <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo e((string) ($a['nombre'] ?? '')); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-8">
                        <label for="editObsOP" class="form-label small text-muted fw-bold mb-1">Observaciones / Lote Estimado</label>
                        <input name="observaciones" id="editObsOP" class="form-control" placeholder="Opcional">
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-4">
                    <button type="button" class="btn btn-light text-secondary me-2 border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleOP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title"><i class="bi bi-search me-2"></i>Detalle OP</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="small text-muted mb-2">Código</div>
                <div id="detalleCodigo" class="fw-bold mb-3">-</div>
                <div class="small text-muted mb-2">Producto</div>
                <div id="detalleProducto" class="fw-bold mb-3">-</div>
                <div class="row">
                    <div class="col-6">
                        <div class="small text-muted">Planificado</div>
                        <div id="detallePlan" class="fw-bold">0.0000</div>
                    </div>
                    <div class="col-6">
                        <div class="small text-muted">Producido</div>
                        <div id="detalleReal" class="fw-bold">0.0000</div>
                    </div>
                </div>
                <div class="mt-3">
                    <span id="detalleEstado" class="badge bg-secondary">-</span>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEjecutarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-play-fill me-2"></i>Ejecutar Producción <span id="lblExecCodigo" class="badge bg-light text-dark ms-2"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <form method="post" id="formEjecutarOrden">
                <input type="hidden" name="accion" value="ejecutar_orden">
                <input type="hidden" name="id_orden" id="execIdOrden">
                
                <input type="hidden" id="execReqLote" value="0">
                <input type="hidden" id="execReqVenc" value="0">
                <input type="hidden" id="execUnidad" value="UND">
                
                <div class="modal-body p-0">
                    
                    <div class="px-4 pt-3 pb-2 bg-white border-bottom">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-bold mb-1"><i class="bi bi-clock-history me-1"></i>Inicio del Trabajo <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="fecha_inicio" id="execFechaInicio" class="form-control form-control-sm border-primary" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-bold mb-1"><i class="bi bi-check2-all me-1"></i>Fin del Trabajo <span class="text-danger">*</span></label>
                                <input type="datetime-local" name="fecha_fin" id="execFechaFin" class="form-control form-control-sm border-primary" required>
                            </div>
                        </div>
                    </div>

                    <ul class="nav nav-tabs nav-fill bg-light pt-2 px-2 border-bottom-0" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold border-bottom-0" data-bs-toggle="tab" data-bs-target="#tabConsumos" type="button" role="tab">
                                <i class="bi bi-box-seam me-1 text-danger"></i> 1. Consumos (Materia Prima)
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold border-bottom-0" data-bs-toggle="tab" data-bs-target="#tabIngresos" type="button" role="tab">
                                <i class="bi bi-box-arrow-in-down me-1 text-success"></i> 2. Ingresos (Producto Final)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content p-4 bg-white">
                        
                        <div class="tab-pane fade show active" id="tabConsumos" role="tabpanel">
                            <div class="d-flex justify-content-between mb-2">
                                <h6 class="fw-bold text-muted">Registro de Insumos Utilizados</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarConsumo">
                                    <i class="bi bi-plus"></i> Fila Extra
                                </button>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tablaConsumosDynamic">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Insumo (ID/Nombre)</th>
                                            <th>Almacén Origen (De donde sale)</th>
                                            <th style="width: 140px;">Cantidad</th>
                                            <th>Lote (Opcional)</th>
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        </tbody>
                                </table>
                            </div>

                            <div id="boxJustificacionFaltante" class="alert alert-warning mt-3 mb-0" style="display: none;">
                                <div class="d-flex align-items-start">
                                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 mt-1"></i>
                                    <div class="w-100">
                                        <h6 class="fw-bold mb-1">¡Advertencia! Consumo Incompleto o Faltante de Stock</h6>
                                        <p class="small mb-2">Has indicado que usarás menos material del que la receta exige, o estás forzando el stock. Por favor, justifica el motivo para poder cerrar esta orden.</p>
                                        <input type="text" name="justificacion" id="inputJustificacionFaltante" class="form-control form-control-sm" placeholder="Ej. El camión descargó directo en planta y aún no ingresa al sistema...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tabIngresos" role="tabpanel">
                            <div class="d-flex justify-content-between mb-2">
                                <h6 class="fw-bold text-muted">Distribución de Producto Final</h6>
                                <button type="button" class="btn btn-sm btn-outline-success" id="btnAgregarIngreso">
                                    <i class="bi bi-plus"></i> Agregar Destino
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered table-sm" id="tablaIngresosDynamic">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Almacén Destino (A dónde entra)</th>
                                            <th style="width: 170px;">Cant. Ingresada</th>
                                            <th>Lote</th>
                                            <th style="width: 140px;">Fecha Venc.</th> 
                                            <th style="width: 50px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        </tbody>
                                </table>
                            </div>
                            <div class="alert alert-info mt-3 py-2 small">
                                <i class="bi bi-info-circle me-1"></i> La cantidad total de producción será la suma de las cantidades ingresadas aquí.
                            </div>
                        </div>

                    </div>
                </div>

                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary text-white" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold"><i class="bi bi-check2-circle me-2"></i>Guardar Ejecución</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPlanificadorProduccion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered"> 
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-range me-2"></i>Planificador de Operaciones</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                
                <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                    <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1">
                        <button type="button" class="btn btn-sm btn-light text-secondary border-0" id="btnPlanAnterior"><i class="bi bi-chevron-left"></i></button>
                        <span class="mx-3 fw-bold text-dark text-uppercase text-center" id="lblPlanActual" style="min-width: 150px;">Cargando...</span>
                        <button type="button" class="btn btn-sm btn-light text-secondary border-0" id="btnPlanSiguiente"><i class="bi bi-chevron-right"></i></button>
                    </div>
                    
                    <div class="btn-group shadow-sm" role="group">
                        <input type="radio" class="btn-check" name="vistaPlanificador" id="vistaMes" value="mes" checked autocomplete="off">
                        <label class="btn btn-outline-primary fw-semibold" for="vistaMes"><i class="bi bi-calendar-month me-1"></i> Mes</label>

                        <input type="radio" class="btn-check" name="vistaPlanificador" id="vistaSemana" value="semana" autocomplete="off">
                        <label class="btn btn-outline-primary fw-semibold" for="vistaSemana"><i class="bi bi-calendar-week me-1"></i> Semana</label>
                    </div>
                </div>

                <div class="bg-white p-3 rounded shadow-sm border text-center min-vh-50 position-relative overflow-hidden">
                    <div id="planLoader" class="position-absolute w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex justify-content-center align-items-center d-none" style="z-index: 10;">
                        <div class="spinner-border text-primary" role="status"></div>
                    </div>

                    <div class="d-grid mb-2" style="grid-template-columns: repeat(7, 1fr); gap: 10px;">
                        <div class="fw-bold text-muted small">Lun</div>
                        <div class="fw-bold text-muted small">Mar</div>
                        <div class="fw-bold text-muted small">Mié</div>
                        <div class="fw-bold text-muted small">Jue</div>
                        <div class="fw-bold text-muted small">Vie</div>
                        <div class="fw-bold text-muted small">Sáb</div>
                        <div class="fw-bold text-danger small">Dom</div>
                    </div>
                    
                    <div class="planificador-scroll-area">
                        <div id="planificadorGrid" class="d-grid" style="grid-template-columns: repeat(7, 1fr); gap: 10px; min-height: 400px;">
                            </div>
                    </div>
                </div>

                <div class="card mt-4 border-0 shadow-sm bg-white">
                    <div class="card-body p-3">
                        <h6 class="small fw-bold text-muted text-uppercase mb-2">Leyenda de Planificación</h6>
                        <div class="d-flex flex-wrap gap-3 small fw-medium">
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-white border border-secondary border-dashed me-2" style="width: 14px; height: 14px;"></span> Día Libre / Sin Actividad</div>
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-primary-subtle border border-primary me-2" style="width: 14px; height: 14px;"></span> Turno Normal</div>
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-warning-subtle border border-warning me-2" style="width: 14px; height: 14px;"></span> Turno Extendido (Excepción)</div>
                            <div class="d-flex align-items-center"><span class="badge bg-dark ms-2 me-1 border px-1">OP</span> Órdenes Asignadas al día</div>
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



<script src="<?php echo base_url(); ?>/assets/js/produccion.js?v=2.7"></script>