<?php
$ordenes = $ordenes ?? [];
$recetasActivas = $recetas_activas ?? [];
$almacenes = $almacenes ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-gear-wide-connected me-2 text-primary"></i> Órdenes de Producción
            </h1>
            <p class="text-muted small mb-0 ms-1">Ejecución y seguimiento de fabricación.</p>
        </div>
        <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCrearOrden">
            <i class="bi bi-plus-circle me-2"></i>Nueva OP
        </button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table id="tablaOrdenes" class="table align-middle mb-0 table-pro">
                    <thead>
                    <tr>
                        <th>OP</th>
                        <th>Producto</th>
                        <th>Plan/Real</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($ordenes as $orden): ?>
                        <?php $estado = (int) ($orden['estado'] ?? 0); ?>
                        <tr>
                            <td class="fw-semibold"><?php echo e((string) $orden['codigo']); ?></td>
                            <td>
                                <div><?php echo e((string) $orden['producto_nombre']); ?></div>
                                <small class="text-muted"><?php echo e((string) $orden['almacen_origen_nombre']); ?> → <?php echo e((string) $orden['almacen_destino_nombre']); ?></small>
                            </td>
                            <td><?php echo number_format((float) $orden['cantidad_planificada'], 4); ?> / <?php echo number_format((float) $orden['cantidad_producida'], 4); ?></td>
                            <td>
                                <?php if ($estado === 0): ?><span class="badge bg-secondary">Borrador</span><?php endif; ?>
                                <?php if ($estado === 1): ?><span class="badge bg-warning text-dark">En proceso</span><?php endif; ?>
                                <?php if ($estado === 2): ?><span class="badge bg-success">Ejecutada</span><?php endif; ?>
                                <?php if ($estado === 9): ?><span class="badge bg-danger">Anulada</span><?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (in_array($estado, [0, 1], true)): ?>
                                    <button class="btn btn-sm btn-outline-success js-ejecutar-op"
                                            data-id="<?php echo (int) $orden['id']; ?>"
                                            data-codigo="<?php echo e((string) $orden['codigo']); ?>"
                                            data-planificada="<?php echo e((string) $orden['cantidad_planificada']); ?>">
                                        Ejecutar
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger js-anular-op"
                                            data-id="<?php echo (int) $orden['id']; ?>"
                                            data-codigo="<?php echo e((string) $orden['codigo']); ?>">
                                        Anular
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted small">Sin acciones</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<form id="formAccionOP" method="post" class="d-none">
    <input type="hidden" name="accion" id="accionOP">
    <input type="hidden" name="id_orden" id="idOrdenOP">
    <input type="hidden" name="cantidad_producida" id="cantidadProducidaOP">
    <input type="hidden" name="lote_ingreso" id="loteIngresoOP">
</form>

<div class="modal fade" id="modalCrearOrden" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-sm">
            <form method="post">
                <input type="hidden" name="accion" value="crear_orden">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva orden de producción</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label">Código OP</label><input required name="codigo" class="form-control" placeholder="OP-0001"></div>
                        <div class="col-md-8"><label class="form-label">Receta</label>
                            <select name="id_receta" required class="form-select">
                                <option value="">Seleccione</option>
                                <?php foreach ($recetasActivas as $r): ?>
                                    <option value="<?php echo (int) $r['id']; ?>"><?php echo e((string) $r['codigo']); ?> - <?php echo e((string) $r['producto_nombre']); ?> (v<?php echo (int) $r['version']; ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Almacén origen</label>
                            <select name="id_almacen_origen" required class="form-select">
                                <option value="">Seleccione</option>
                                <?php foreach ($almacenes as $a): ?>
                                    <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Almacén destino</label>
                            <select name="id_almacen_destino" required class="form-select">
                                <option value="">Seleccione</option>
                                <?php foreach ($almacenes as $a): ?>
                                    <option value="<?php echo (int) $a['id']; ?>"><?php echo e((string) $a['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4"><label class="form-label">Cantidad planificada</label><input name="cantidad_planificada" min="0.0001" step="0.0001" required type="number" class="form-control"></div>
                        <div class="col-md-8"><label class="form-label">Observaciones</label><input name="observaciones" class="form-control"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Guardar OP</button>
                </div>
            </form>
        </div>
    </div>
</div>
