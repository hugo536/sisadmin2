<?php
$acuerdos = $acuerdos ?? [];
$acuerdoSeleccionado = $acuerdo_seleccionado ?? null;
$preciosMatriz = $precios_matriz ?? [];
$presentacionesHabilitadas = $presentaciones_habilitadas ?? true;
$modoVista = ($acuerdoSeleccionado && ((int)($acuerdoSeleccionado['id'] ?? -1) === 0)) ? 'volumen' : 'acuerdo';
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
     data-url-eliminar-acuerdo="<?php echo e(route_url('comercial/eliminarAcuerdoAjax')); ?>"
     data-url-items-volumen="<?php echo e(route_url('comercial/itemsVolumenDisponiblesAjax')); ?>"
     data-url-agregar-volumen="<?php echo e(route_url('comercial/agregarPrecioVolumenAjax')); ?>"
     data-url-actualizar-volumen="<?php echo e(route_url('comercial/actualizarPrecioVolumenAjax')); ?>"
     data-url-eliminar-volumen="<?php echo e(route_url('comercial/eliminarPrecioVolumenAjax')); ?>">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold mb-1 d-flex align-items-center text-dark">
                <i class="bi bi-diagram-3-fill me-2 text-primary"></i> Acuerdos Comerciales
            </h1>
            <p class="text-muted small mb-0">Matriz de tarifas personalizadas por cliente distribuidor.</p>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4 col-xl-3 mb-3 mb-lg-0">
            <button class="btn btn-outline-primary w-100 d-lg-none mb-3 shadow-sm bg-white" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarClientesMenu">
                <i class="bi bi-people-fill me-2"></i> Cambiar Cliente / Acuerdo
            </button>

            <div class="offcanvas-lg offcanvas-end border-0 shadow-sm rounded-3" tabindex="-1" id="sidebarClientesMenu">
                <div class="offcanvas-header bg-light border-bottom d-lg-none">
                    <h6 class="offcanvas-title fw-bold mb-0 text-primary">
                        <i class="bi bi-people-fill me-2"></i>Seleccionar Cliente
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
                </div>

                <div class="offcanvas-body p-0 flex-column h-100">
                    <div class="card border-0 h-100 w-100 shadow-none d-flex flex-column">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0">Clientes Vinculados</h6>
                            <button class="btn btn-sm btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#modalVincularCliente" title="Vincular Cliente">
                                <i class="bi bi-person-plus-fill me-1"></i> Nuevo
                            </button>
                        </div>
                        
                        <div class="p-2 border-bottom bg-light flex-shrink-0">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="search" class="form-control border-start-0 ps-0 shadow-none" id="filtroClientesAcuerdo" placeholder="Buscar cliente...">
                            </div>
                        </div>

                        <div class="list-group list-group-flush overflow-auto flex-grow-1" id="acuerdosSidebarList">
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
                                    <div class="list-group-item list-group-item-action d-flex justify-content-between align-items-start acuerdo-sidebar-item <?php echo $isSelected ? 'active' : ''; ?>"
                                         role="button"
                                         data-id-acuerdo="<?php echo (int)$acuerdo['id']; ?>"
                                         data-search="<?php echo e(mb_strtolower($acuerdo['cliente_nombre'])); ?>">
                                        <div class="me-2 flex-grow-1">
                                            <div class="fw-semibold"><?php echo e($acuerdo['cliente_nombre']); ?></div>
                                            <?php if ((int)$acuerdo['id'] === 0): ?>
                                                <small class="text-muted"><?php echo (int)$acuerdo['total_productos']; ?> productos</small>
                                            <?php elseif ($sinTarifas): ?>
                                                <small class="text-warning d-flex align-items-center gap-1 mt-1">
                                                    <i class="bi bi-exclamation-triangle-fill"></i> Sin Tarifas
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted"><?php echo (int)$acuerdo['total_productos']; ?> productos</small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex align-items-start gap-2 flex-shrink-0">
                                            <?php if ((int)$acuerdo['id'] !== 0): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-link text-danger p-0 mt-1 js-eliminar-acuerdo-sidebar"
                                                        title="Eliminar acuerdo"
                                                        aria-label="Eliminar acuerdo">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                            <span class="rounded-circle mt-1" style="width:10px;height:10px;background:<?php echo $isActive ? '#22c55e' : '#9ca3af'; ?>;"></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                                <div class="px-3 py-4 text-center text-muted small d-none" id="sidebarNoResults">No hay coincidencias.</div>
                            <?php endif; ?>
                        </div>
                    </div>
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
                                <small class="text-muted" id="acuerdoResumenTarifas">
                                    <?php 
                                    if ($modoVista === 'volumen') {
                                        echo count(array_unique(array_column($preciosMatriz, 'id_item'))) . ' productos configurados';
                                    } else {
                                        echo count($preciosMatriz) . ' tarifas configuradas';
                                    }
                                    ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <?php if ($presentacionesHabilitadas): ?>
                                <button class="btn btn-primary btn-sm" id="btnAgregarProducto" type="button">
                                    <i class="bi bi-plus-lg me-1"></i><?php echo $modoVista === "volumen" ? "Agregar Escala" : "Agregar Producto"; ?>
                                </button>
                                <?php endif; ?>
                                <?php if ($modoVista !== "volumen"): ?>
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
                                <?php endif; ?>
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
                        <div class="p-2 border-bottom bg-light">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-search"></i></span>
                                <input type="search" class="form-control border-start-0 ps-0 shadow-none" id="filtroMatrizAcuerdo" placeholder="Buscar producto o código...">
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-pro" id="tablaMatrizAcuerdo" data-id-acuerdo="<?php echo (int)$acuerdoSeleccionado['id']; ?>" data-modo="<?php echo e($modoVista); ?>">
                                <thead>
                                <tr>
                                    <?php if ($modoVista === "volumen"): ?>
                                    <th class="ps-4 col-w-120">Código</th>
                                    <th>Producto</th>
                                    <th class="col-w-180">Cantidad Mínima</th>
                                    <th class="col-w-220">Precio Unitario</th>
                                    <th class="text-end pe-4 col-w-90">Acciones</th>
                                    <?php else: ?>
                                    <th class="ps-4 col-w-120">Código</th>
                                    <th>Producto</th>
                                    <th class="col-w-220">Precio Pactado</th>
                                    <th class="col-w-130">Estado</th>
                                    <th class="text-end pe-4 col-w-90">Acciones</th>
                                    <?php endif; ?>
                                </tr>
                                </thead>
                                <tbody id="matrizBodyRows">
                                    <?php if (empty($preciosMatriz)): ?>
                                        <tr id="emptyMatrizRow">
                                            <td colspan="5" class="text-center text-muted py-5">
                                                <i class="bi bi-exclamation-circle text-warning fs-1 d-block mb-2"></i>
                                                <?php echo $modoVista === "volumen" ? "Aún no hay escalas por volumen configuradas." : "Este acuerdo aún no tiene productos tarifados."; ?>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php if ($modoVista === 'volumen'): ?>
                                            <?php foreach ($preciosMatriz as $row): ?>
                                            <tr data-id-detalle="<?php echo (int)$row['id']; ?>" class="mobile-expandable-row">
                                                <td class="ps-4 col-mobile-hide">
                                                    <span class="badge bg-light text-dark border"><?php echo e($row['codigo_presentacion'] ?: 'N/A'); ?></span>
                                                </td>
                                                <td class="fw-semibold text-dark"><?php echo e($row['producto_nombre']); ?></td>
                                                <td>
                                                    <div class="input-group input-group-sm" style="max-width: 120px;">
                                                        <span class="input-group-text bg-light border-end-0">≥</span>
                                                        <input type="number" min="0.01" step="0.01" class="form-control border-start-0 px-1 js-cantidad-minima" value="<?php echo number_format((float)$row['cantidad_minima'], 2, '.', ''); ?>" data-original="<?php echo number_format((float)$row['cantidad_minima'], 2, '.', ''); ?>">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="input-group input-group-sm" style="max-width: 130px;">
                                                        <span class="input-group-text bg-light border-end-0">S/</span>
                                                        <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-volumen" value="<?php echo number_format((float)$row['precio_pactado'], 4, '.', ''); ?>" data-original="<?php echo number_format((float)$row['precio_pactado'], 4, '.', ''); ?>">
                                                    </div>
                                                </td>
                                                <td class="text-end pe-4 col-mobile-hide">
                                                    <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-volumen" type="button" title="Eliminar escala">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <?php foreach ($preciosMatriz as $row): ?>
                                            <tr data-id-detalle="<?php echo (int)$row['id']; ?>" class="mobile-expandable-row">
                                                <td class="ps-4 col-mobile-hide"><span class="badge bg-light text-dark border"><?php echo e($row['codigo_presentacion'] ?: 'N/A'); ?></span></td>
                                                <td class="fw-semibold text-dark"><?php echo e($row['producto_nombre']); ?></td>
                                                <td>
                                                    <div class="input-group input-group-sm" style="max-width: 130px;">
                                                        <span class="input-group-text bg-light border-end-0">S/</span>
                                                        <input type="number" min="0" step="0.0001" class="form-control text-primary fw-bold border-start-0 px-1 js-precio-pactado" value="<?php echo number_format((float)$row['precio_pactado'], 4, '.', ''); ?>" data-original="<?php echo number_format((float)$row['precio_pactado'], 4, '.', ''); ?>">
                                                    </div>
                                                </td>
                                                <td class="text-center col-mobile-hide">
                                                    <div class="form-check form-switch d-flex justify-content-center mb-0">
                                                        <input class="form-check-input js-estado-precio" type="checkbox" <?php echo (int)$row['estado'] === 1 ? 'checked' : ''; ?>>
                                                    </div>
                                                </td>
                                                <td class="text-end pe-4 col-mobile-hide">
                                                    <button class="btn btn-sm btn-outline-danger border-0 js-eliminar-producto" type="button" title="Eliminar producto">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>
</div>

<div class="modal fade" id="modalVincularCliente" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title text-white"><i class="bi bi-link-45deg me-2"></i>Vincular Cliente</h5>
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

<div class="modal fade" id="modalAgregarEscalaVolumen" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header modal-header-adaptative pt-4 px-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-bar-chart-steps me-2"></i>Agregar Escala por Volumen
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAgregarEscalaVolumen" class="m-0">
                <div class="modal-body px-4 pt-3 pb-4">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Presentación</label>
                            <select class="form-select form-select-lg" id="selectItemVolumen" required></select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Cantidad Mínima</label>
                            <input type="number" min="0.0001" step="0.0001" class="form-control form-control-lg" id="inputCantidadMinimaVolumen" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label fw-semibold small text-muted text-uppercase">Precio Unitario</label>
                            <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden border">
                                <span class="input-group-text bg-white border-0 text-muted fw-bold ps-3">S/</span>
                                <input type="number" min="0" step="0.0001" class="form-control border-0 px-2 fw-bold text-primary fs-5 shadow-none" id="inputPrecioUnitarioVolumen" placeholder="0.0000" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 py-3 px-4 d-flex justify-content-between">
                    <button class="btn btn-link text-muted text-decoration-none fw-semibold px-0" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary px-4 fw-bold shadow-sm rounded-pill" type="submit">Agregar Escala</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAgregarProducto" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow-lg border-0">
             <div class="modal-header modal-header-adaptative pt-4 px-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-box-seam me-2"></i>Agregar Producto
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAgregarProductoAcuerdo" class="m-0">
                <div class="modal-body px-4 pt-3 pb-4">
                    <div class="mb-4">
                        <label class="form-label fw-semibold small text-muted text-uppercase">Presentación</label>
                        <select class="form-select form-select-lg" id="selectPresentacionAcuerdo" required></select>
                    </div>
                    <div>
                        <label class="form-label fw-semibold small text-muted text-uppercase">Precio Inicial</label>
                        <div class="input-group input-group-lg shadow-sm rounded-3 overflow-hidden border">
                            <span class="input-group-text bg-white border-0 text-muted fw-bold ps-3">S/</span>
                            <input type="number" min="0" step="0.0001" class="form-control border-0 px-2 fw-bold text-primary fs-5 shadow-none" id="inputPrecioInicialAcuerdo" placeholder="0.0000" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 py-3 px-4 d-flex justify-content-between">
                    <button class="btn btn-link text-muted text-decoration-none fw-semibold px-0" type="button" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary px-4 fw-bold shadow-sm rounded-pill" type="submit">Agregar a la lista</button>
                </div>
            </form>
        </div>
    </div>
</div>
