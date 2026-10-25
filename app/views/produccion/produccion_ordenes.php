<?php
/** @var array $ordenes */
$ordenes = $ordenes ?? [];
$recetasActivas = $recetas_activas ?? [];
$almacenes = $almacenes ?? [];
$almacenesPlanta = $almacenes_planta ?? [];
$centros = $centros ?? [];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>

<div class="container-fluid p-2 p-md-4" id="ordenesProduccionApp">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h4 h3-md fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Órdenes de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Planificación, ejecución y control de fabricación.</p>
        </div>
        <div class="d-flex flex-column flex-sm-row gap-2 mt-2 mt-md-0">
            <button class="btn btn-dark shadow-sm fw-bold px-4 transition-hover" data-bs-toggle="modal" data-bs-target="#modalPlanificadorProduccion">
                <i class="bi bi-calendar3-week me-1 me-md-2"></i>Ver Planificador
            </button>
            <button class="btn btn-primary shadow-sm fw-bold px-4 transition-hover" data-bs-toggle="modal" data-bs-target="#modalPlanificarOP">
                <i class="bi bi-plus-circle me-1 me-md-2"></i>Nueva OP
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 mb-md-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-6 col-lg-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="opSearch" placeholder="Buscar OP, producto, receta...">
                    </div>
                </div>
                <div class="col-12 col-md-4 col-lg-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="opFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="0">Borrador</option>
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
                <table id="tablaOrdenes" class="table align-middle mb-0 table-pro table-hover op-mobile-cards"
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
                    <thead class="table-light border-bottom text-nowrap">
                        <tr>
                            <th class="ps-3 ps-md-4 text-secondary fw-semibold">Código OP</th>
                            <th class="text-secondary fw-semibold">Producto / Receta</th>
                            <th class="text-secondary fw-semibold">Planificado / Real</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-3 pe-md-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="opTableBody">
                        <?php if (empty($ordenes)) { ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-gear fs-1 d-block mb-2 text-light"></i>
                                    No hay órdenes de producción registradas.
                                </td>
                            </tr>
                        <?php } else { ?>
                            <?php foreach ($ordenes as $orden): ?>
                                <?php 
                                    $estado = (int) ($orden['estado'] ?? 0); 
                                    $precheckOk = (int) ($orden['precheck_ok'] ?? 0) === 1;
                                    $precheckResumen = (string) ($orden['precheck_resumen'] ?? 'Sin información de faltantes');
                                    
                                    $permiteDecimales = isset($orden['permite_decimales']) ? (int) $orden['permite_decimales'] : 1; 
                                    $decimales = $permiteDecimales === 1 ? 4 : 0;
                                    $fechaProgStr = !empty($orden['fecha_programada']) ? date('d-m-Y', strtotime($orden['fecha_programada'])) : '';
                                    
                                    $searchStr = strtolower($orden['codigo'] . ' ' . $orden['producto_nombre'] . ' ' . $orden['receta_codigo'] . ' ' . (string) ($orden['almacen_planta_nombre'] ?? ''));
                                ?>
                                
                                <tr class="border-bottom op-main-row" 
                                    data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>" 
                                    data-estado="<?php echo $estado; ?>">
                                    
                                    <!-- 1. CÓDIGO OP + RECETA -->
                                    <td class="ps-3 ps-md-4 align-top pt-3">
                                        <div class="fw-bold text-primary <?php echo $estado === 9 ? "text-decoration-line-through text-muted" : ""; ?>">
                                            <?php echo e((string) $orden['codigo']); ?>
                                        </div>
                                        <div class="small text-muted mt-1" style="font-size: 0.75rem;">
                                            <i class="bi bi-receipt me-1"></i><?php echo e((string) $orden['receta_codigo']); ?>
                                        </div>
                                    </td>
                                    
                                    <!-- 2. PRODUCTO + PLANTA -->
                                    <td class="align-top pt-3 min-vw-50">
                                        <div class="fw-bold text-dark <?php echo $estado === 9 ? "text-decoration-line-through text-muted" : ""; ?>">
                                            <?php echo e((string) $orden['producto_nombre']); ?>
                                        </div>
                                        
                                        <?php if (!empty($orden['almacen_planta_nombre'])): ?>
                                            <div class="mt-1">
                                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2" style="font-size: 0.7rem;">
                                                    <i class="bi bi-building me-1"></i><?php echo e((string) $orden['almacen_planta_nombre']); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($orden['justificacion_ajuste'])): ?>
                                            <div class="small text-warning mt-1 fw-medium"><i class="bi bi-exclamation-triangle-fill me-1"></i>Con ajuste de stock</div>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- 3. PLANIFICADO / REAL (Limpio) -->
                                    <td class="align-top pt-3">
                                        <div class="d-flex flex-column align-items-start gap-1">
                                            <?php 
                                                $unidadBase = (string) ($orden['unidad_base'] ?? '');
                                                $espacioUnidad = $unidadBase !== '' ? ' ' . $unidadBase : '';
                                            ?>
                                            <span class="badge bg-light text-secondary border border-secondary-subtle shadow-sm px-2">
                                                Plan: <?php echo number_format((float) $orden['cantidad_planificada'], $decimales) . $espacioUnidad; ?>
                                            </span>
                                            
                                            <?php if ($fechaProgStr !== ''): ?>
                                                <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle px-2"><i class="bi bi-calendar3 me-1"></i><?php echo $fechaProgStr; ?></span>
                                            <?php endif; ?>
                                            
                                            <?php if ((float) $orden['cantidad_producida'] > 0): ?>
                                                <span class="badge bg-success text-white px-2 shadow-sm">
                                                    <i class="bi bi-check2-all me-1"></i>Real: <?php echo number_format((float) $orden['cantidad_producida'], $decimales) . $espacioUnidad; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>

                                    <!-- 4. ESTADO -->
                                    <td class="text-center align-top pt-3">
                                        <?php if (in_array($estado, [0, 1], true)): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 px-md-3 py-1 py-md-2 rounded-pill">Borrador</span>
                                        <?php elseif ($estado === 2): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-2 px-md-3 py-1 py-md-2 rounded-pill">Ejecutada</span>
                                        <?php elseif ($estado === 9): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 px-md-3 py-1 py-md-2 rounded-pill">Anulada</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <!-- 5. ACCIONES -->
                                    <td class="text-end pe-3 pe-md-4 align-top pt-3 text-nowrap">
                                        <?php if (in_array($estado, [0, 1], true)): ?>
                                            <div class="d-inline-flex align-items-center gap-1 gap-md-2 acciones-op-wrap">
                                                
                                                <button type="button" 
                                                        class="btn btn-sm shadow-sm js-ver-diagnostico <?php echo $precheckOk ? 'btn-outline-success' : 'btn-warning text-dark'; ?>"
                                                        data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                        data-destino="<?php echo e((string) ($orden['almacen_planta_nombre'] ?? 'Planta no asignada')); ?>"
                                                        data-resumen="<?php echo htmlspecialchars($precheckResumen, ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-ok="<?php echo $precheckOk ? '1' : '0'; ?>"
                                                        title="<?php echo $precheckOk ? 'Insumos Completos' : 'Faltan Insumos'; ?>">
                                                    <i class="bi <?php echo $precheckOk ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
                                                </button>

                                                <button class="btn btn-sm btn-success fw-bold px-2 px-lg-3 shadow-sm js-abrir-ejecucion"
                                                        data-id="<?php echo (int) $orden['id']; ?>"
                                                        data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                        data-receta="<?php echo (int) $orden['id_receta']; ?>"
                                                        data-planificada="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                        data-precheck-ok="<?php echo $precheckOk ? '1' : '0'; ?>"
                                                        data-req-lote="<?php echo (int) ($orden['requiere_lote'] ?? 0); ?>"
                                                        data-req-venc="<?php echo (int) ($orden['requiere_vencimiento'] ?? 0); ?>"
                                                        data-unidad="<?php echo e((string) ($orden['unidad_base'] ?? 'UND')); ?>"
                                                        data-id-almacen-planta="<?php echo (int) ($orden['id_almacen_planta'] ?? 0); ?>"
                                                        data-receta-centro-costo="<?php echo (int) ($orden['receta_id_centro_costo'] ?? 0); ?>"
                                                        data-producto-nombre="<?php echo e((string) ($orden['producto_nombre'] ?? '')); ?>"
                                                        data-receta-version="<?php echo (int) ($orden['receta_version_snapshot'] ?? 1); ?>"
                                                        data-receta-rendimiento="<?php echo (float) ($orden['receta_rendimiento_base'] ?? 0); ?>"
                                                        data-receta-tiempo="<?php echo (float) ($orden['receta_tiempo_horas'] ?? 0); ?>"
                                                        title="Ejecutar Producción">
                                                    <i class="bi bi-play-fill"></i>
                                                    <span class="d-none d-lg-inline ms-1">Ejecutar</span>
                                                </button>

                                                <button type="button"
                                                        class="btn btn-sm btn-light text-dark shadow-sm border js-editar-op"
                                                        data-id="<?php echo (int) $orden['id']; ?>"
                                                        data-cantidad="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                        data-fecha="<?php echo e((string) ($orden['fecha_programada'] ?? '')); ?>"
                                                        data-id-almacen="<?php echo (int) ($orden['id_almacen_planta'] ?? 0); ?>"
                                                        data-observaciones="<?php echo e((string) ($orden['observaciones'] ?? '')); ?>"
                                                        title="Editar borrador">
                                                    <i class="bi bi-pencil"></i>
                                                </button>

                                                <form method="post" class="d-inline js-swal-confirm m-0 p-0"
                                                    data-confirm-title="¿Eliminar borrador?"
                                                    data-confirm-text="Se ocultará la orden.">
                                                    <input type="hidden" name="accion" value="eliminar_borrador">
                                                    <input type="hidden" name="id_orden" value="<?php echo (int) $orden['id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm"
                                                            title="Eliminar borrador">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif (in_array($estado, [2, 9], true)): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary shadow-sm js-ver-detalle"
                                                    data-id="<?php echo (int) $orden['id']; ?>"
                                                    data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                    data-estado="<?php echo $estado === 2 ? 'Ejecutada' : 'Anulada'; ?>"
                                                    data-producto="<?php echo e((string) $orden['producto_nombre']); ?>"
                                                    data-plan="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                    data-real="<?php echo (float) $orden['cantidad_producida']; ?>"
                                                    data-md-real="<?php echo (float) ($orden['costo_md_real'] ?? 0); ?>"
                                                    data-mod-real="<?php echo (float) ($orden['costo_mod_real'] ?? 0); ?>"
                                                    data-cif-real="<?php echo (float) ($orden['costo_cif_real'] ?? 0); ?>"
                                                    data-total-real="<?php echo (float) ($orden['costo_real_total'] ?? 0); ?>"
                                                    title="Ver detalle de costos">
                                                <i class="bi bi-search"></i> <span class="d-none d-md-inline ms-1">Detalle</span>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($ordenes)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 px-3 px-md-4">
                <small class="text-muted fw-semibold" id="opPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de OP" class="overflow-auto w-100 w-md-auto text-center text-md-end">
                    <ul class="pagination mb-0 shadow-sm d-inline-flex" id="opPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<!-- Modal Nueva OP -->
<div class="modal fade" id="modalPlanificarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-3 pb-md-4">
                <h5 class="modal-title fw-bold fs-5 fs-md-4"><i class="bi bi-plus-circle me-2"></i>Planificar Nueva Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" id="formCrearOP">
                    <input type="hidden" name="accion" value="crear_orden">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="newCodigoOP" class="form-label small text-muted fw-bold mb-1">Código OP <span class="text-danger">*</span></label>
                                    <input type="text" required name="codigo" id="newCodigoOP" class="form-control shadow-none border-secondary-subtle bg-light fw-bold text-primary" placeholder="Generando auto..." readonly>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label for="newRecetaOP" class="form-label small text-muted fw-bold mb-1">Receta / Producto Terminado <span class="text-danger">*</span></label>
                                    <select name="id_receta" id="newRecetaOP" required class="form-select shadow-none border-secondary-subtle">
                                        <option value="">Seleccione fórmula...</option>
                                        <?php foreach ($recetasActivas as $r): ?>
                                            <?php $codigoReceta = (string) ($r['codigo'] ?? ''); ?>
                                            <?php $productoReceta = (string) ($r['producto_nombre'] ?? ''); ?>
                                            <option value="<?php echo (int) $r['id']; ?>"
                                                    data-rendimiento="<?php echo (float) ($r['rendimiento_base'] ?? 1); ?>" 
                                                    data-tiempo="<?php echo (float) ($r['tiempo_estimado_horas'] ?? 1); ?>"
                                                    data-id-almacen-planta="<?php echo (int) ($r['id_almacen_planta'] ?? 0); ?>"
                                                    data-search-tokens="<?php echo e(strtolower(trim($codigoReceta . ' ' . $productoReceta))); ?>">
                                                <?php echo e($productoReceta); ?> (v<?php echo (int) $r['version']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 mb-1 mt-3 border-top pt-3">
                                    <label class="form-label small text-muted fw-bold mb-2 d-block">Estrategia de Planificación <span class="text-danger">*</span></label>
                                    <div class="d-flex flex-column flex-sm-row gap-2 gap-sm-4">
                                        <div class="form-check form-check-inline m-0">
                                            <input class="form-check-input" type="radio" name="modo_planificacion" id="modoCant" value="cantidad" checked disabled>
                                            <label class="form-check-label small fw-bold text-dark" for="modoCant">
                                                <i class="bi bi-box-seam me-1 text-primary"></i> Por Cantidad a Producir
                                            </label>
                                        </div>
                                        <div class="form-check form-check-inline m-0">
                                            <input class="form-check-input" type="radio" name="modo_planificacion" id="modoHoras" value="horas" disabled>
                                            <label class="form-check-label small fw-bold text-dark" for="modoHoras">
                                                <i class="bi bi-clock-history me-1 text-warning"></i> Por Horas de Máquina
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="newCantPlan" class="form-label small text-muted fw-bold mb-1">Cantidad a Producir <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input name="cantidad_planificada" id="newCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control shadow-none border-primary fw-bold bg-light text-muted" placeholder="Seleccione receta primero" readonly>
                                        <span class="input-group-text bg-light text-muted">Und/Kg</span>
                                    </div>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="newHorasPlan" class="form-label small text-muted fw-bold mb-1">Horas de Trabajo <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input id="newHorasPlan" min="0.0001" step="0.0001" type="number" class="form-control shadow-none bg-light text-muted" placeholder="Seleccione receta primero" readonly>
                                        <span class="input-group-text bg-light text-muted">Hrs</span>
                                    </div>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label for="newFechaProgramada" class="form-label small text-muted fw-bold mb-1">Fecha Programada <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_programada" id="newFechaProgramada" class="form-control shadow-none border-secondary-subtle" required>
                                </div>
                                <div class="col-12 col-md-6">
                                    <label for="newAlmacenPlanta" class="form-label small text-muted fw-bold mb-1">Planta de Trabajo <span class="text-danger">*</span></label>
                                    <select name="id_almacen_planta" id="newAlmacenPlanta" class="form-select shadow-none border-secondary-subtle" required>
                                        <option value="">Seleccione planta...</option>
                                        <?php foreach ($almacenesPlanta as $a): ?>
                                            <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo e((string) ($a['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="newObsOP" class="form-label small text-muted fw-bold mb-1">Observaciones / Lote Estimado</label>
                                    <input name="observaciones" id="newObsOP" class="form-control shadow-none border-secondary-subtle" placeholder="Notas adicionales o pre-asignación de lote...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 pt-2 mt-2">
                        <button type="button" class="btn btn-light text-secondary border px-4 fw-semibold shadow-sm w-100 w-sm-auto" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm w-100 w-sm-auto"><i class="bi bi-save me-2"></i>Guardar Borrador OP</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Editar OP -->
<div class="modal fade" id="modalEditarOP" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-3 pb-md-4">
                <h5 class="modal-title fw-bold fs-5 fs-md-4"><i class="bi bi-pencil-square me-2"></i>Editar Borrador de Producción</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" id="formEditarOP">
                    <input type="hidden" name="accion" value="editar_orden">
                    <input type="hidden" name="id_orden" id="editIdOrden">

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-3 p-md-4">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label for="editCantPlan" class="form-label small text-muted fw-bold mb-1">Cantidad a Producir <span class="text-danger">*</span></label>
                                    <input name="cantidad_planificada" id="editCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control shadow-none border-warning fw-bold">
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="editFechaProgramada" class="form-label small text-muted fw-bold mb-1">Fecha Programada <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_programada" id="editFechaProgramada" class="form-control shadow-none border-secondary-subtle" required>
                                </div>
                                <div class="col-12 col-md-4">
                                    <label for="editAlmacenPlanta" class="form-label small text-muted fw-bold mb-1">Planta de Trabajo <span class="text-danger">*</span></label>
                                    <select name="id_almacen_planta" id="editAlmacenPlanta" class="form-select shadow-none border-secondary-subtle" required>
                                        <option value="">Seleccione planta...</option>
                                        <?php foreach ($almacenesPlanta as $a): ?>
                                            <option value="<?php echo (int) ($a['id'] ?? 0); ?>"><?php echo e((string) ($a['nombre'] ?? '')); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label for="editObsOP" class="form-label small text-muted fw-bold mb-1">Observaciones / Lote Estimado</label>
                                    <input name="observaciones" id="editObsOP" class="form-control shadow-none border-secondary-subtle" placeholder="Opcional">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-column flex-sm-row justify-content-end gap-2 pt-2 mt-2">
                        <button type="button" class="btn btn-light text-secondary border px-4 fw-semibold shadow-sm w-100 w-sm-auto" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm w-100 w-sm-auto"><i class="bi bi-save me-2"></i>Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalle OP -->
<div class="modal fade" id="modalDetalleOP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-dark text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold fs-5 fs-md-4"><i class="bi bi-clipboard2-data me-2 text-info"></i>Análisis Financiero de OP</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-2 p-md-4 bg-light">
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-2 p-md-3">
                        <div class="row g-2 align-items-center text-center text-md-start">
                            <div class="col-6 col-md-3 border-end">
                                <div class="small text-muted fw-bold text-uppercase">Código OP</div>
                                <div id="detalleCodigo" class="fw-bold fs-6 fs-md-5 text-primary text-truncate">-</div>
                            </div>
                            <div class="col-6 col-md-5 border-end-md">
                                <div class="small text-muted fw-bold text-uppercase">Producto Final</div>
                                <div id="detalleProducto" class="fw-bold fs-6 text-dark text-truncate">-</div>
                            </div>
                            <div class="col-6 col-md-2 border-top border-top-md-0 border-end pt-2 pt-md-0">
                                <div class="small text-muted fw-bold text-uppercase">Planificado</div>
                                <div id="detallePlan" class="fw-bold fs-6 fs-md-5 text-secondary">0.00</div>
                            </div>
                            <div class="col-6 col-md-2 border-top border-top-md-0 pt-2 pt-md-0">
                                <div class="small text-muted fw-bold text-uppercase">Producido</div>
                                <div id="detalleReal" class="fw-bold fs-6 fs-md-5 text-success">0.00</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body p-0">
                        <ul class="nav nav-tabs nav-tabs-custom px-2 px-md-3 pt-2 bg-white rounded-top flex-nowrap overflow-auto" role="tablist">
                            <li class="nav-item text-nowrap"><button class="nav-link active fw-bold text-primary" data-bs-toggle="tab" data-bs-target="#tabDetalleMd"><i class="bi bi-box-seam me-1"></i>Materiales (MD)</button></li>
                            <li class="nav-item text-nowrap"><button class="nav-link fw-bold text-secondary" data-bs-toggle="tab" data-bs-target="#tabDetalleMod"><i class="bi bi-people me-1"></i>Mano Obra (MOD)</button></li>
                            <li class="nav-item text-nowrap"><button class="nav-link fw-bold text-secondary" data-bs-toggle="tab" data-bs-target="#tabDetalleCif"><i class="bi bi-lightning-charge me-1"></i>Indirectos (CIF)</button></li>
                        </ul>
                        <div class="tab-content p-0" style="min-height: 220px;">
                            <div class="tab-pane fade show active" id="tabDetalleMd">
                                <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light text-muted small text-uppercase"><tr><th class="ps-2 ps-md-3 text-nowrap">Insumo Utilizado</th><th class="text-end text-nowrap">Cant. Teórica / Real</th><th class="text-end text-nowrap">Costo Unit.</th><th class="text-end pe-2 pe-md-3 text-nowrap">Total</th></tr></thead><tbody id="detalleTablaMd"></tbody></table></div>
                            </div>
                            <div class="tab-pane fade" id="tabDetalleMod">
                                <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light text-muted small text-uppercase"><tr><th class="ps-2 ps-md-3 text-nowrap">Operario / Perfil</th><th class="text-end text-nowrap">Horas Registradas</th><th class="text-end text-nowrap">Costo Hora</th><th class="text-end pe-2 pe-md-3 text-nowrap">Total</th></tr></thead><tbody id="detalleTablaMod"></tbody></table></div>
                            </div>
                            <div class="tab-pane fade" id="tabDetalleCif">
                                <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead class="table-light text-muted small text-uppercase"><tr><th class="ps-2 ps-md-3 text-nowrap">Concepto / Activo</th><th class="text-nowrap">Base (Horas)</th><th class="text-end pe-2 pe-md-3 text-nowrap">Costo Aplicado</th></tr></thead><tbody id="detalleTablaCif"></tbody></table></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-primary-subtle bg-opacity-10 border-top d-flex flex-column flex-md-row justify-content-between align-items-center p-3 gap-3">
                <div class="d-flex align-items-center gap-2 w-100 w-md-auto justify-content-center justify-content-md-start">
                    <span class="text-muted fw-bold text-uppercase small d-none d-md-inline">Estado:</span>
                    <span id="detalleEstado" class="badge bg-secondary px-3 py-2 fs-6 rounded-pill">-</span>
                </div>
                
                <div class="d-flex flex-wrap justify-content-center justify-content-md-end gap-3 gap-md-4 text-center text-md-end w-100 w-md-auto">
                    <div class="d-none d-lg-block">
                        <div class="small text-muted fw-bold">MD + MOD + CIF</div>
                        <div class="fw-semibold text-dark" style="font-size: 0.9rem;">S/ <span id="detalleMdReal">0.00</span> + <span id="detalleModReal">0.00</span> + <span id="detalleCifReal">0.00</span></div>
                    </div>
                    <div class="border-start-md ps-0 ps-md-4">
                        <div class="small text-primary fw-bold text-uppercase mb-1">Costo Total</div>
                        <div class="fw-bold text-primary fs-5 fs-md-4 lh-1">S/ <span id="detalleTotalReal">0.00</span></div>
                    </div>
                    <div class="border-start ps-3 ps-md-4">
                        <div class="small text-success fw-bold text-uppercase mb-1">Costo Unitario</div>
                        <div class="fw-bold text-success fs-5 fs-md-4 lh-1">S/ <span id="detalleUnitarioReal">0.00</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
#modalEjecutarOP .op-modal-icon { font-size: 1rem; line-height: 1; }
/* Asegurar que los inputs en tablas no colapsen a 0 width en móviles */
#modalEjecutarOP table input[type="number"] { min-width: 80px; }
</style>

<!-- Modal Ejecutar OP -->
<div class="modal fade" id="modalEjecutarOP" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <form method="post" id="formEjecutarOrden" class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-success text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold fs-5 fs-md-4 d-flex flex-column flex-sm-row align-items-sm-center">
                    <div>
                        <i class="bi bi-play-fill me-1 op-modal-icon"></i>Ejecutar Producción 
                        <span class="badge bg-white text-success ms-2" id="lblExecCodigo">OP-000000</span>
                    </div>
                    <span class="small fw-semibold ms-0 ms-sm-2 mt-1 mt-sm-0 opacity-90 text-truncate" style="max-width: 100%;" id="lblExecRecetaInfo"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white align-self-start" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <input type="hidden" name="accion" value="ejecutar_orden">
            <input type="hidden" name="id_orden" id="execIdOrden">
            <input type="hidden" id="execReqLote">
            <input type="hidden" id="execReqVenc">
            <input type="hidden" id="execUnidad">

            <div class="modal-body p-2 p-md-4 bg-light">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white border-bottom-0 pt-2 pb-0 d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2">
                            <h6 class="fw-bold text-dark mb-0 fs-6"><i class="bi bi-clock-history text-primary me-1 op-modal-icon"></i>Tiempos</h6>
                            <button type="button" class="btn btn-sm btn-outline-secondary shadow-sm rounded-pill fw-bold px-3 py-1 transition-hover align-self-end align-self-sm-auto" id="btnMagicoEjecucion" title="Cargar cantidades teóricas de la receta">
                                <i class="bi bi-calculator me-1 op-modal-icon"></i><span class="d-none d-sm-inline">Proponer Consumos</span><span class="d-inline d-sm-none">Proponer</span>
                            </button>
                        </div>
                        <div class="card-body p-2 p-sm-3">
                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-play-circle text-success me-1 op-modal-icon"></i>Inicio <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <input type="date" id="execFechaInicioDate" class="form-control border-secondary-subtle fw-medium" required>
                                        <input type="time" id="execFechaInicioTime" class="form-control border-secondary-subtle fw-medium text-center" required>
                                    </div>
                                    <input type="hidden" name="fecha_inicio" id="execFechaInicio"> 
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-bold text-secondary mb-1"><i class="bi bi-stop-circle text-danger me-1 op-modal-icon"></i>Fin <span class="text-danger">*</span></label>
                                    <div class="input-group input-group-sm">
                                        <input type="date" id="execFechaFinDate" class="form-control border-secondary-subtle fw-medium" required>
                                        <input type="time" id="execFechaFinTime" class="form-control border-secondary-subtle fw-medium text-center" required>
                                    </div>
                                    <input type="hidden" name="fecha_fin" id="execFechaFin">
                                </div>

                                <div class="col-12 col-md-4">
                                    <label class="form-label small fw-bold text-secondary mb-1">
                                        <i class="bi bi-pause-circle text-warning me-1 op-modal-icon"></i>Parada 
                                        <span class="d-inline d-md-none text-muted fw-normal ms-1" style="font-size: 0.65rem;">(Descansos/Fallas)</span>
                                    </label>
                                    <div class="row g-2">
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="1" min="0" name="parada_horas" id="execParadaHoras" class="form-control border-secondary-subtle bg-light" placeholder="Horas" disabled>
                                                <span class="input-group-text bg-light text-muted px-2">h</span>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="input-group input-group-sm">
                                                <input type="number" step="1" min="0" max="59" name="parada_minutos" id="execParadaMinutos" class="form-control border-secondary-subtle bg-light" placeholder="Minutos" disabled>
                                                <span class="input-group-text bg-light text-muted px-2">m</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="horas_parada" id="execHorasParada" value="0">
                                </div>
                            </div>
                            
                            <div class="mt-3 pt-2 border-top d-flex justify-content-between align-items-center bg-light rounded p-2">
                                <span class="text-muted fw-bold" style="font-size: 0.8rem;"><i class="bi bi-calculator me-1 op-modal-icon"></i>Hrs a Costear:</span>
                                <span class="badge bg-dark fs-6 shadow-sm px-3" id="lblTiempoNeto">0.00 <small class="text-light fw-normal opacity-75">Hrs</small></span>
                            </div>
                        </div>
                    </div>

                    <div id="boxJustificacionFaltante" style="display: none;"></div>

                    <ul class="nav nav-tabs nav-tabs-custom mb-3 flex-nowrap overflow-auto" role="tablist">
                        <li class="nav-item text-nowrap" role="presentation">
                            <button class="nav-link active text-danger fw-bold" data-bs-toggle="tab" data-bs-target="#tabConsumos" type="button" role="tab">
                                1. <i class="bi bi-box-arrow-up-right me-1 op-modal-icon"></i>Consumos (Salidas)
                            </button>
                        </li>
                        <li class="nav-item text-nowrap" role="presentation">
                            <button class="nav-link text-success fw-bold" data-bs-toggle="tab" data-bs-target="#tabIngresos" type="button" role="tab">
                                <i class="bi bi-box-arrow-in-down-left me-1 op-modal-icon"></i>2. Ingresos (Producto Final)
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content bg-white p-2 p-md-3 rounded shadow-sm border mb-4">
                        <!-- Pestaña Consumos -->
                        <div class="tab-pane fade show active" id="tabConsumos" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Insumos Utilizados</h6>
                                    <p class="small text-muted mb-0 d-none d-sm-block">Material que se descontará del almacén.</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle table-bordered mb-0 mobile-card-dynamic" id="tablaConsumosDynamic">
                                    <thead class="table-light text-muted small text-uppercase text-nowrap">
                                        <tr>
                                            <th>Insumo (ID / Nombre)</th>
                                            <th style="width: 25%; min-width: 150px;">Almacén Origen</th>
                                            <th style="width: 15%; text-align: center;">Cantidad</th>
                                            <th style="width: 20%;" class="d-none">Lote (Opcional)</th>
                                            <th style="width: 5%; text-align: center;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Pestaña Ingresos -->
                        <div class="tab-pane fade" id="tabIngresos" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">Productos a Ingresar</h6>
                                    <p class="small text-muted mb-0 d-none d-sm-block">Unidades terminadas que entrarán al almacén.</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle table-bordered mb-0 mobile-card-dynamic" id="tablaIngresosDynamic">
                                    <thead class="table-light text-muted small text-uppercase text-nowrap">
                                        <tr>
                                            <th style="width: 55%; min-width: 150px;">Almacén Destino</th>
                                            <th style="width: 35%; text-align: center;">Cantidad Producida</th>
                                            <th class="d-none">Lote Asignado</th>
                                            <th class="d-none">F. Vencimiento</th>
                                            <th style="width: 10%; text-align: center;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Pie del Modal: Centro de Costos -->
                    <div class="row g-3 mb-2">
                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Centro de Costos <span class="text-danger">*</span></label>
                            <select name="id_centro_costo" id="execCentroCosto" class="form-select shadow-none border-secondary-subtle" required>
                                <option value="">Seleccione a dónde irá el gasto...</option>
                                <?php if (!empty($centros)): ?>
                                    <?php foreach ($centros as $c): ?>
                                        <?php if ((int)$c['estado'] === 1): ?>
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

                        <div class="col-12 col-md-6">
                            <label class="form-label small fw-bold text-secondary">Nota o Justificación de Cierre (Opcional)</label>
                            <input type="text" name="justificacion" class="form-control shadow-none border-secondary-subtle" placeholder="Ej: Se utilizó material alternativo...">
                        </div>
                    </div>

            </div>

            <div class="modal-footer bg-white border-top shadow-sm d-flex flex-column flex-sm-row gap-2">
                <button type="button" class="btn btn-light border fw-semibold w-100 w-sm-auto px-4" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-success fw-bold w-100 w-sm-auto px-5"><i class="bi bi-check-circle-fill me-2 op-modal-icon"></i>Guardar y Ejecutar</button>
            </div>
        </form> 
    </div>
</div>

<!-- Modal Planificador de Operaciones -->
<div class="modal fade" id="modalPlanificadorProduccion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-lg-down"> 
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0 pb-3 pb-md-4">
                <h5 class="modal-title fw-bold fs-5 fs-md-4"><i class="bi bi-calendar-range me-2 text-info"></i>Planificador de Operaciones</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <div class="card border-0 shadow-sm mb-3 mb-md-4">
                    <div class="card-body p-2 p-md-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center bg-white border rounded shadow-sm p-1 w-100 w-md-auto justify-content-between justify-content-md-start">
                            <button type="button" class="btn btn-sm btn-light text-secondary border-0 px-3 transition-hover" id="btnPlanAnterior"><i class="bi bi-chevron-left"></i></button>
                            <span class="mx-2 mx-md-3 fw-bold text-primary fs-6 fs-md-5 text-uppercase text-center" id="lblPlanActual" style="min-width: 140px;">Cargando...</span>
                            <button type="button" class="btn btn-sm btn-light text-secondary border-0 px-3 transition-hover" id="btnPlanSiguiente"><i class="bi bi-chevron-right"></i></button>
                        </div>
                        
                        <div class="btn-group shadow-sm w-100 w-md-auto" role="group">
                            <input type="radio" class="btn-check" name="vistaPlanificador" id="vistaMes" value="mes" checked autocomplete="off">
                            <label class="btn btn-outline-primary fw-semibold px-3 px-md-4" for="vistaMes"><i class="bi bi-calendar-month me-1"></i> Mes</label>

                            <input type="radio" class="btn-check" name="vistaPlanificador" id="vistaSemana" value="semana" autocomplete="off">
                            <label class="btn btn-outline-primary fw-semibold px-3 px-md-4" for="vistaSemana"><i class="bi bi-calendar-week me-1"></i> Semana</label>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-2 p-md-3 rounded shadow-sm border text-center min-vh-50 position-relative overflow-hidden">
                    <div id="planLoader" class="position-absolute w-100 h-100 top-0 start-0 bg-white bg-opacity-75 d-flex justify-content-center align-items-center d-none" style="z-index: 10;">
                        <div class="spinner-border text-primary shadow-sm" role="status" style="width: 3rem; height: 3rem;"></div>
                    </div>

                    <!-- Envoltorio para permitir scroll horizontal del calendario en móvil -->
                    <div class="overflow-auto w-100 pb-2">
                        <div style="min-width: 700px;"> <!-- Fuerza a que el calendario no se apriete -->
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
                    </div>
                </div>

                <div class="card mt-3 mt-md-4 border-0 shadow-sm bg-white">
                    <div class="card-body p-2 p-md-3">
                        <h6 class="small fw-bold text-muted text-uppercase mb-2 mb-md-3"><i class="bi bi-info-circle-fill me-2 text-info"></i>Leyenda de Planificación</h6>
                        <div class="d-flex flex-wrap gap-2 gap-md-4 small fw-semibold text-dark">
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-light border border-secondary border-dashed me-2 shadow-sm" style="width: 16px; height: 16px;"></span> Día Libre / Sin Actividad</div>
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-primary-subtle border border-primary me-2 shadow-sm" style="width: 16px; height: 16px;"></span> Turno Normal</div>
                            <div class="d-flex align-items-center"><span class="d-inline-block rounded bg-warning-subtle border border-warning me-2 shadow-sm" style="width: 16px; height: 16px;"></span> Turno Extendido</div>
                            <div class="d-flex align-items-center"><span class="badge bg-dark ms-1 ms-md-2 me-2 border px-2 py-1 shadow-sm">OP</span> Órdenes Asignadas</div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Modal Diagnóstico -->
<div class="modal fade" id="modalDiagnosticoOP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold text-dark fs-5"><i class="bi bi-clipboard-data text-primary me-2"></i>Diagnóstico de Insumos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4 pt-0">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center mb-3 pb-2 border-bottom gap-2">
                    <span class="badge bg-dark px-3 py-2 fs-6" id="diagCodigoOP">-</span>
                    <span class="badge bg-secondary-subtle text-secondary-emphasis border"><i class="bi bi-building me-1"></i> <span id="diagDestino">-</span></span>
                </div>
                
                <div id="diagContenido"></div>
            </div>
            <div class="modal-footer bg-light border-top-0 d-flex flex-column flex-sm-row justify-content-between gap-2">
                <button type="button" class="btn btn-outline-secondary fw-semibold w-100 w-sm-auto" data-bs-dismiss="modal">Cerrar</button>
                <a href="<?php echo e((string) route_url('inventario')); ?>" target="_blank" class="btn btn-primary fw-bold shadow-sm w-100 w-sm-auto">
                    <i class="bi bi-box-seam me-2"></i>Ir a Inventario
                </a>
            </div>
        </div>
    </div>
</div>

<template id="tplSelectAlmacenes">
    <?php foreach ($almacenes as $a): ?>
        <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
    <?php endforeach; ?>
</template>