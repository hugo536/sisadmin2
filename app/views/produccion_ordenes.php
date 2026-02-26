<?php
$ordenes = $ordenes ?? [];
$recetasActivas = $recetas_activas ?? [];
$almacenes = $almacenes ?? [];
$flash = $flash ?? ['tipo' => '', 'texto' => ''];
?>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Órdenes de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Planificación, ejecución y control de fabricación.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalPlanificarOP">
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
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="opSearch" placeholder="Buscar OP, producto...">
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
                <table id="tablaOrdenes" class="table align-middle mb-0 table-pro">
                    <thead>
                        <tr>
                            <th class="ps-4">Código OP</th>
                            <th>Producto / Receta</th>
                            <th>Planificado / Real</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $orden): ?>
                            <?php $estado = (int) ($orden['estado'] ?? 0); ?>
                            <tr data-search="<?php echo mb_strtolower($orden['codigo'] . ' ' . $orden['producto_nombre']); ?>" 
                                data-estado="<?php echo $estado; ?>">
                                
                                <td class="ps-4 fw-bold text-primary"><?php echo e((string) $orden['codigo']); ?></td>
                                
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e((string) $orden['producto_nombre']); ?></div>
                                    <div class="small text-muted"><i class="bi bi-receipt me-1"></i><?php echo e((string) $orden['receta_codigo']); ?></div>
                                    <?php if (!empty($orden['justificacion_ajuste'])): ?>
                                        <div class="small text-warning mt-1"><i class="bi bi-exclamation-triangle"></i> Con ajuste de stock</div>
                                    <?php endif; ?>
                                </td>
                                
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-light text-dark border mb-1">Plan: <?php echo number_format((float) $orden['cantidad_planificada'], 4); ?></span>
                                        <?php if (!empty($orden['fecha_programada'])): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle mb-1">Fecha: <?php echo e((string) $orden['fecha_programada']); ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($orden['turno_programado'])): ?>
                                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle mb-1">Turno: <?php echo e((string) $orden['turno_programado']); ?></span>
                                        <?php endif; ?>
                                        <?php if ((float) $orden['cantidad_producida'] > 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle">Real: <?php echo number_format((float) $orden['cantidad_producida'], 4); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                
                                <td class="text-center">
                                    <?php if ($estado === 0): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Borrador</span>
                                    <?php elseif ($estado === 1): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 rounded-pill">En proceso</span>
                                    <?php elseif ($estado === 2): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 rounded-pill">Ejecutada</span>
                                    <?php elseif ($estado === 9): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 rounded-pill">Anulada</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-end pe-4">
                                    <?php if (in_array($estado, [0, 1], true)): ?>
                                        <button class="btn btn-sm btn-outline-success js-abrir-ejecucion"
                                                data-id="<?php echo (int) $orden['id']; ?>"
                                                data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                                data-receta="<?php echo (int) $orden['id_receta']; ?>"
                                                data-planificada="<?php echo (float) $orden['cantidad_planificada']; ?>"
                                                title="Ejecutar Producción">
                                            <i class="bi bi-play-fill"></i> Ejecutar
                                        </button>
                                        
                                        <form method="post" class="d-inline js-swal-confirm" data-confirm-title="¿Anular orden?" data-confirm-text="El estado cambiará a Anulado y no se podrá revertir.">
                                            <input type="hidden" name="accion" value="anular_orden">
                                            <input type="hidden" name="id_orden" value="<?php echo (int) $orden['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger ms-1" title="Anular">
                                                <i class="bi bi-x-circle"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="btn btn-sm btn-light text-secondary border-0" disabled><i class="bi bi-lock"></i></button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3">
                <small class="text-muted">Mostrando <?php echo count($ordenes); ?> órdenes</small>
            </div>
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
                                <label for="newCodigoOP" class="form-label small text-muted fw-bold mb-1">
                                    Código OP <span class="text-danger fs-6">*</span>
                                </label>
                                <input type="text" required name="codigo" id="newCodigoOP" class="form-control bg-light fw-bold text-primary" placeholder="Generando..." readonly>
                            </div>
                            
                            <div class="col-md-8">
                                <label for="newRecetaOP" class="form-label small text-muted fw-bold mb-1">
                                    Receta / Producto Terminado <span class="text-danger fs-6">*</span>
                                </label>
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
                                <label for="newCantPlan" class="form-label small text-muted fw-bold mb-1">
                                    Cantidad Planificada <span class="text-danger fs-6">*</span>
                                </label>
                                <input name="cantidad_planificada" id="newCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control border-primary" placeholder="Ej: 100">
                            </div>
                            
                            <div class="col-md-4">
                                <label for="newFechaProgramada" class="form-label small text-muted fw-bold mb-1">
                                    Fecha Programada <span class="text-danger fs-6">*</span>
                                </label>
                                <input type="date" name="fecha_programada" id="newFechaProgramada" class="form-control" required>
                            </div>

                            <div class="col-md-4">
                                <label for="newTurnoProgramado" class="form-label small text-muted fw-bold mb-1">
                                    Turno Programado <span class="text-danger fs-6">*</span>
                                </label>
                                <select name="turno_programado" id="newTurnoProgramado" class="form-select" required>
                                    <option value="">Seleccione...</option>
                                    <option value="Mañana">Mañana</option>
                                    <option value="Tarde">Tarde</option>
                                    <option value="Noche">Noche</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="newObsOP" class="form-label small text-muted fw-bold mb-1">
                                    Observaciones / Lote Estimado
                                </label>
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
                
                <div class="modal-body p-0">
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
                                            <th style="width: 150px;">Cant. Ingresada</th>
                                            <th>Lote (Opcional)</th>
                                            <th style="width: 140px;">Fecha Venc.</th> <th style="width: 50px;"></th>
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

<template id="tplSelectAlmacenes">
    <?php foreach ($almacenes as $a): ?>
        <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
    <?php endforeach; ?>
</template>

<script src="<?php echo base_url(); ?>/assets/js/produccion.js?v=2.3"></script>