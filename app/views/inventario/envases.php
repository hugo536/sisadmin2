<div class="container-fluid p-4" id="envasesApp">
    
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-start align-items-lg-center gap-3 mb-4 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-recycle me-2 text-primary"></i> <?= htmlspecialchars($titulo ?? 'Control de Envases Retornables') ?>
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión de saldos y cuenta corriente de envases físicos con clientes.</p>
        </div>

        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="button" class="btn btn-primary shadow-sm fw-semibold px-3" data-bs-toggle="modal" data-bs-target="#modalMovimientoEnvase">
                <i class="bi bi-plus-circle-fill me-2"></i>Registrar Movimiento
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-4">
                    <label for="envasesSearch" class="visually-hidden">Buscar cliente o envase</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="envasesSearch" name="busqueda_envases" placeholder="Buscar cliente o envase...">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive inventario-table-wrapper">
                <table class="table align-middle mb-0 table-pro table-hover" id="tablaEnvases"
                       data-erp-table="true"
                       data-search-input="#envasesSearch"
                       data-pagination-controls="#envasesPaginationControls"
                       data-pagination-info="#envasesPaginationInfo"
                       data-rows-per-page="25"
                       data-empty-text="No hay registros de envases para mostrar.">
                    
                    <thead class="inventario-sticky-thead border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Cliente / Distribuidor</th>
                            <th class="text-secondary fw-semibold">Tipo de Envase</th>
                            <th class="text-center text-secondary fw-semibold">Saldo en Planta</th>
                            <th class="text-center text-secondary fw-semibold">Situación</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($saldos)): ?>
                            <?php foreach ($saldos as $saldo): 
                                $searchString = strtolower($saldo['cliente_nombre'] . ' ' . $saldo['envase_nombre']);
                            ?>
                                <tr class="border-bottom" data-search="<?= e($searchString) ?>">
                                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($saldo['cliente_nombre']) ?></td>
                                    <td class="text-muted"><?= htmlspecialchars($saldo['envase_nombre']) ?></td>
                                    
                                    <td class="text-center">
                                        <span class="fw-bold fs-5 text-dark"><?= (int)$saldo['saldo_en_planta'] ?></span>
                                    </td>
                                    
                                    <td class="text-center">
                                        <?php if ($saldo['saldo_en_planta'] > 0): ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-success-subtle text-success border border-success-subtle">
                                                A favor del cliente
                                            </span>
                                        <?php elseif ($saldo['saldo_en_planta'] < 0): ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-danger-subtle text-danger border border-danger-subtle">
                                                Nos debe envases
                                            </span>
                                        <?php else: ?>
                                            <span class="badge px-3 py-2 rounded-pill bg-secondary-subtle text-secondary border border-secondary-subtle">
                                                A la par
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end pe-4">
                                        <div class="d-inline-flex align-items-center gap-1">
                                            <button class="btn btn-sm btn-light text-info border-0 rounded-circle btn-ver-historial" 
                                                    data-bs-toggle="tooltip" title="Ver Historial"
                                                    data-tercero="<?= $saldo['id_tercero'] ?>"
                                                    data-item="<?= $saldo['id_item_envase'] ?>"
                                                    data-cliente-nombre="<?= htmlspecialchars($saldo['cliente_nombre']) ?>">
                                                <i class="bi bi-clock-history fs-5"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-msg-row">
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No hay registros de envases para mostrar.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center px-4">
                <small class="text-muted fw-semibold" id="envasesPaginationInfo">Cargando...</small>
                <nav aria-label="Paginación de envases">
                    <ul class="pagination mb-0 justify-content-end" id="envasesPaginationControls"></ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalMovimientoEnvase" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-left-right me-2"></i>Registrar Movimiento de Envases</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form id="formMovimientoEnvase" action="<?= e(route_url('inventario/envases/guardar')) ?>" method="POST" autocomplete="off">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Datos de la Operación</h6>
                            
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label for="id_tercero" class="form-label text-muted small fw-bold mb-1">Cliente / Distribuidor <span class="text-danger">*</span></label>
                                    <select class="form-select bg-light" id="id_tercero" name="id_tercero" required>
                                        <option value="">Seleccione un cliente...</option>
                                        <?php if(!empty($clientes)): ?>
                                            <?php foreach ($clientes as $c): ?>
                                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nombre_completo']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="tipo_operacion" class="form-label text-muted small fw-bold mb-1">Tipo de Movimiento <span class="text-danger">*</span></label>
                                    <select class="form-select bg-light" id="tipo_operacion" name="tipo_operacion" required>
                                        <option value="">Seleccione...</option>
                                        <option value="RECEPCION_VACIO">📥 Recepción de envases vacíos (del cliente)</option>
                                        <option value="ENTREGA_LLENO">📤 Préstamo / Entrega manual al cliente</option>
                                        <option value="AJUSTE_CLIENTE">⚠️ Ajuste (Roto/Perdido por el cliente)</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="id_item_envase" class="form-label text-muted small fw-bold mb-1">Tipo de Envase <span class="text-danger">*</span></label>
                                    <select class="form-select bg-light" id="id_item_envase" name="id_item_envase" required>
                                        <option value="">Seleccione envase...</option>
                                        <?php if(!empty($items)): ?>
                                            <?php foreach ($items as $i): ?>
                                                <option value="<?= $i['id'] ?>"><?= htmlspecialchars($i['nombre']) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label for="cantidad" class="form-label text-muted small fw-bold mb-1">Cantidad Física <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" id="cantidad" name="cantidad" min="1" required placeholder="Ej. 50">
                                </div>

                                <div class="col-md-6">
                                    <label for="fecha_movimiento" class="form-label text-muted small fw-bold mb-1">Fecha y hora de movimiento <span class="text-danger">*</span></label>
                                    <input type="datetime-local" class="form-control" id="fecha_movimiento" name="fecha_movimiento" required>
                                </div>

                                <div class="col-md-6" id="grupo_almacen_envases">
                                    <label for="id_almacen" class="form-label text-muted small fw-bold mb-1">Almacén físico (Kardex)</label>
                                    <select class="form-select bg-light" id="id_almacen" name="id_almacen">
                                        <option value="">Seleccione almacén...</option>
                                        <?php if(!empty($almacenes)): ?>
                                            <?php foreach ($almacenes as $a): ?>
                                                <option value="<?= (int)($a['id'] ?? 0) ?>"><?= htmlspecialchars((string)($a['nombre'] ?? '')) ?></option>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </select>
                                    <small class="text-muted">Obligatorio para recepción y préstamo; opcional para ajustes.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-floating shadow-sm rounded">
                        <textarea class="form-control border-0" id="observaciones" name="observaciones" style="height: 80px" maxlength="255" placeholder="Observaciones"></textarea>
                        <label for="observaciones" class="fw-semibold text-muted">Referencia / Observaciones <small class="text-muted">(opcional)</small></label>
                    </div>

                </form>
            </div>
            
            <div class="modal-footer bg-white border-top-0 py-3">
                <button type="button" class="btn btn-light text-secondary me-2 fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="formMovimientoEnvase" class="btn btn-primary px-4 fw-bold shadow-sm">
                    <i class="bi bi-save me-2"></i>Guardar Movimiento
                </button>
            </div>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-clock-history me-2"></i>Historial de Movimientos</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <h6 class="text-primary fw-bold mb-3" id="historialClienteNombre">Cliente</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle bg-white shadow-sm rounded">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Operación</th>
                                <th>Fecha</th>
                                <th class="text-center">Cantidad</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody id="tablaHistorialBody">
                            </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/inventario/envases.js')); ?>?v=<?php echo time(); ?>"></script>
