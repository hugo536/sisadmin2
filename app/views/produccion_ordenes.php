<?php
$ordenes = $ordenes ?? [];
$recetasActivas = $recetasActivas ?? [];
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
            <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearOrden">
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
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="opSearch" placeholder="Buscar OP, producto, almacén...">
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
                            <th>Ruta (Almacenes)</th>
                            <th>Planificado / Real</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ordenes as $orden): ?>
                            <?php $estado = (int) ($orden['estado'] ?? 0); ?>
                            <tr data-search="<?php echo mb_strtolower($orden['codigo'] . ' ' . $orden['producto_nombre'] . ' ' . $orden['almacen_origen_nombre']); ?>" 
                                data-estado="<?php echo $estado; ?>">
                                
                                <td class="ps-4 fw-bold text-primary"><?php echo e((string) $orden['codigo']); ?></td>
                                
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e((string) $orden['producto_nombre']); ?></div>
                                    <div class="small text-muted"><i class="bi bi-receipt me-1"></i><?php echo e((string) $orden['receta_codigo']); ?></div>
                                </td>
                                
                                <td>
                                    <div class="small">
                                        <div class="text-muted"><i class="bi bi-box-arrow-right text-danger me-1"></i><?php echo e((string) $orden['almacen_origen_nombre']); ?></div>
                                        <div class="text-muted"><i class="bi bi-box-arrow-in-down text-success me-1"></i><?php echo e((string) $orden['almacen_destino_nombre']); ?></div>
                                    </div>
                                </td>
                                
                                <td>
                                    <div class="d-flex flex-column">
                                        <span class="badge bg-light text-dark border mb-1">Plan: <?php echo number_format((float) $orden['cantidad_planificada'], 4); ?></span>
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

<div class="modal fade" id="modalCrearOrden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nueva Orden de Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" class="modal-body p-4 bg-light">
                <input type="hidden" name="accion" value="crear_orden">
                
                <div class="card border-0 shadow-sm mb-3">
                    <div class="card-body">
                        <div class="row g-2">
                            <div class="col-md-4 form-floating">
                                <input type="text" required name="codigo" id="newCodigoOP" class="form-control" placeholder="Código">
                                <label for="newCodigoOP">Código OP</label>
                            </div>
                            <div class="col-md-8 form-floating">
                                <select name="id_receta" id="newRecetaOP" required class="form-select">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($recetasActivas as $r): ?>
                                        <option value="<?php echo (int) $r['id']; ?>">
                                            <?php echo e((string) $r['codigo']); ?> - <?php echo e((string) $r['producto_nombre']); ?> (v<?php echo (int) $r['version']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="newRecetaOP">Receta / Producto</label>
                            </div>
                            
                            <div class="col-md-6 form-floating mt-3">
                                <select name="id_almacen_origen" id="newAlmacenOrigen" required class="form-select">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($almacenes as $a): ?>
                                        <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="newAlmacenOrigen">Almacén Origen (Insumos)</label>
                            </div>
                            <div class="col-md-6 form-floating mt-3">
                                <select name="id_almacen_destino" id="newAlmacenDestino" required class="form-select">
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($almacenes as $a): ?>
                                        <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label for="newAlmacenDestino">Almacén Destino (Producto)</label>
                            </div>
                            
                            <div class="col-md-4 form-floating mt-3">
                                <input name="cantidad_planificada" id="newCantPlan" min="0.0001" step="0.0001" required type="number" class="form-control" placeholder="Cantidad">
                                <label for="newCantPlan">Cantidad Planificada</label>
                            </div>
                            <div class="col-md-8 form-floating mt-3">
                                <input name="observaciones" id="newObsOP" class="form-control" placeholder="Obs">
                                <label for="newObsOP">Observaciones</label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end pt-2">
                    <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Guardar OP</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEjecutarOP" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-play-fill me-2"></i>Ejecutar Producción</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" class="modal-body p-4">
                <input type="hidden" name="accion" value="ejecutar_orden">
                <input type="hidden" name="id_orden" id="execIdOrden">
                
                <div class="alert alert-info small d-flex align-items-center mb-3">
                    <i class="bi bi-info-circle fs-4 me-2"></i>
                    <div>
                        Se generarán los consumos de insumos y el ingreso del producto terminado automáticamente.
                    </div>
                </div>

                <div class="mb-3 form-floating">
                    <input type="text" class="form-control bg-light" id="execCodigo" readonly>
                    <label>Código OP</label>
                </div>

                <div class="mb-3 form-floating">
                    <input type="number" step="0.0001" name="cantidad_producida" id="execCantidad" class="form-control border-success fw-bold" placeholder="Cant" required>
                    <label for="execCantidad">Cantidad Real Producida</label>
                </div>

                <div class="mb-3 form-floating">
                    <input type="text" name="lote_ingreso" id="execLote" class="form-control" placeholder="Lote" required>
                    <label for="execLote">Lote Asignado (Nuevo Lote)</label>
                </div>

                <div class="d-flex justify-content-end pt-2">
                    <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold">Confirmar Ejecución</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>/assets/js/produccion.js?v=1.0"></script>