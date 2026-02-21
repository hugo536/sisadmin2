<?php
$acuerdos = $acuerdos ?? [];
$acuerdoSeleccionado = $acuerdo_seleccionado ?? null;
$preciosMatriz = $precios_matriz ?? [];
?>
<div class="container-fluid p-4" id="acuerdosComercialesApp"
     data-url-clientes-disponibles="<?php echo e(route_url('comercial/clientesDisponiblesAjax')); ?>"
     data-url-crear-acuerdo="<?php echo e(route_url('comercial/crearLista')); ?>"
     data-url-obtener-matriz="<?php echo e(route_url('comercial/obtenerMatrizAcuerdoAjax')); ?>"
     data-url-presentaciones-disponibles="<?php echo e(route_url('comercial/presentacionesDisponiblesAjax')); ?>"
     data-url-agregar-producto="<?php echo e(route_url('comercial/agregarProductoAcuerdoAjax')); ?>"
     data-url-actualizar-precio="<?php echo e(route_url('comercial/actualizarPrecioPactadoAjax')); ?>"
     data-url-toggle-precio="<?php echo e(route_url('comercial/toggleEstadoPrecioAcuerdoAjax')); ?>"
     data-url-eliminar-producto="<?php echo e(route_url('comercial/eliminarProductoAcuerdoAjax')); ?>"
     data-url-suspender-acuerdo="<?php echo e(route_url('comercial/suspenderAcuerdoAjax')); ?>"
     data-url-activar-acuerdo="<?php echo e(route_url('comercial/activarAcuerdoAjax')); ?>"
     data-url-eliminar-acuerdo="<?php echo e(route_url('comercial/eliminarAcuerdoAjax')); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 d-flex align-items-center text-dark">
                <i class="bi bi-diagram-3-fill me-2 text-primary"></i> Acuerdos Comerciales
            </h1>
            <p class="text-muted small mb-0">Matriz de tarifas personalizadas por cliente distribuidor.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body border-bottom">
                    <h6 class="fw-bold mb-3">Clientes Vinculados</h6>
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                        <input type="search" class="form-control" id="filtroClientesAcuerdo" placeholder="Filtrar clientes...">
                    </div>
                    <button class="btn btn-primary w-100" type="button" data-bs-toggle="modal" data-bs-target="#modalVincularCliente">
                        <i class="bi bi-person-plus-fill me-2"></i>Vincular Cliente
                    </button>
                </div>
                <div class="list-group list-group-flush overflow-auto" style="max-height: 70vh;" id="acuerdosSidebarList">
                    <?php if (empty($acuerdos)): ?>
                        <div class="px-3 py-4 text-center text-muted small" id="sidebarNoResults">
                            No hay clientes vinculados.
                        </div>
                    <?php else: ?>
                        <?php foreach ($acuerdos as $acuerdo): ?>
                            <?php
                            $isActive = (int)$acuerdo['estado'] === 1;
                            $sinTarifas = (int)($acuerdo['sin_tarifas'] ?? 0) === 1;
                            $isSelected = $acuerdoSeleccionado && (int)$acuerdoSeleccionado['id'] === (int)$acuerdo['id'];
                            ?>
                            <button type="button"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-start acuerdo-sidebar-item <?php echo $isSelected ? 'active' : ''; ?>"
                                    data-id-acuerdo="<?php echo (int)$acuerdo['id']; ?>"
                                    data-search="<?php echo e(mb_strtolower($acuerdo['cliente_nombre'])); ?>">
                                <div class="me-2">
                                    <div class="fw-semibold"><?php echo e($acuerdo['cliente_nombre']); ?></div>
                                    <?php if ($sinTarifas): ?>
                                        <small class="text-warning d-flex align-items-center gap-1 mt-1">
                                            <i class="bi bi-exclamation-triangle-fill"></i> Sin Tarifas
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted"><?php echo (int)$acuerdo['total_productos']; ?> productos</small>
                                    <?php endif; ?>
                                </div>
                                <span class="rounded-circle mt-1 flex-shrink-0" style="width:10px;height:10px;background:<?php echo $isActive ? '#22c55e' : '#9ca3af'; ?>;"></span>
                            </button>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-bottom py-3">
                    <?php if ($acuerdoSeleccionado): ?>
                        <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                            <div>
                                <h5 class="mb-1 fw-bold text-primary" id="acuerdoTituloCliente"><?php echo e($acuerdoSeleccionado['cliente_nombre']); ?></h5>
                                <small class="text-muted" id="acuerdoResumenTarifas"><?php echo count($preciosMatriz); ?> tarifas configuradas</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary btn-sm" id="btnAgregarProducto" type="button">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar Producto
                                </button>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots"></i> Opciones
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item" type="button" id="btnSuspenderAcuerdo">
                                                <i class="bi bi-pause-circle me-2"></i>Suspender Acuerdo
                                            </button>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" type="button" id="btnActivarAcuerdo">
                                                <i class="bi bi-play-circle me-2"></i>Activar Acuerdo
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" type="button" id="btnEliminarAcuerdo">
                                                <i class="bi bi-x-octagon me-2"></i>Romper Acuerdo / Eliminar
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <p class="text-muted mb-0">Selecciona o vincula un cliente para iniciar su matriz de tarifas.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="card-body p-0">
                    <?php if ($acuerdoSeleccionado): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="tablaMatrizAcuerdo" data-id-acuerdo="<?php echo (int)$acuerdoSeleccionado['id']; ?>">
                                <thead class="table-light">
                                <tr>
                                    <th class="ps-4" style="width: 120px;">Código</th>
                                    <th>Producto</th>
                                    <th style="width: 220px;">Precio Pactado</th>
                                    <th style="width: 130px;">Estado</th>
                                    <th class="text-end pe-4" style="width: 90px;">Acciones</th>
                                </tr>
                                </thead>
                                <tbody id="matrizBodyRows">
                                <?php if (empty($preciosMatriz)): ?>
                                    <tr id="emptyMatrizRow">
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="bi bi-exclamation-circle text-warning me-1"></i>
                                            Este acuerdo aún no tiene productos tarifados.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($preciosMatriz as $row): ?>
                                        <tr data-id-detalle="<?php echo (int)$row['id']; ?>">
                                            <td class="ps-4"><span class="badge bg-light text-dark border"><?php echo e($row['codigo_presentacion'] ?: 'N/A'); ?></span></td>
                                            <td><?php echo e($row['producto_nombre']); ?></td>
                                            <td>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">S/</span>
                                                    <input type="number" min="0" step="0.0001" class="form-control text-end js-precio-pactado"
                                                           value="<?php echo e((string)$row['precio_pactado']); ?>">
                                                </div>
                                            </td>
                                            <td>
                                                <div class="form-check form-switch m-0">
                                                    <input class="form-check-input js-estado-precio" type="checkbox" <?php echo (int)$row['estado'] === 1 ? 'checked' : ''; ?>>
                                                </div>
                                            </td>
                                            <td class="text-end pe-4">
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-producto" type="button" title="Eliminar producto">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="p-5 text-center text-muted">
                            <i class="bi bi-layout-sidebar-inset-reverse display-6 d-block mb-2"></i>
                            Vincula un cliente desde el panel izquierdo para crear su acuerdo comercial.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVincularCliente" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-link-45deg me-2"></i>Vincular Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formVincularCliente">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Cliente</label>
                        <select class="form-select" id="selectClienteVincular" required></select>
                    </div>
                    <div>
                        <label class="form-label">Observaciones</label>
                        <textarea class="form-control" id="inputObservacionesAcuerdo" rows="2" placeholder="Opcional"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Guardar Acuerdo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgregarProducto" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-seam me-2"></i>Agregar Producto al Acuerdo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAgregarProductoAcuerdo">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Presentación</label>
                        <select class="form-select" id="selectPresentacionAcuerdo" required></select>
                    </div>
                    <label class="form-label">Precio Inicial</label>
                    <div class="input-group">
                        <span class="input-group-text">S/</span>
                        <input type="number" min="0" step="0.0001" class="form-control" id="inputPrecioInicialAcuerdo" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary" type="submit">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>
