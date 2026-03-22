<?php
$recetas = $recetas ?? [];
$parametrosCatalogo = $parametros_catalogo ?? [];
// Recibimos la nueva variable del controlador
$conceptosOperativos = $conceptos_operativos ?? [];
?>
<style>
    #tabsRecetaCostos .nav-link {
        border-radius: 0.65rem 0.65rem 0 0;
        transition: all .2s ease-in-out;
    }

    #tabsRecetaCostos .nav-link.receta-tab-active {
        color: #0d6efd !important;
        background: rgba(13, 110, 253, 0.10);
        border-color: rgba(13, 110, 253, 0.35) rgba(13, 110, 253, 0.35) #fff;
    }

    @media (max-width: 767.98px) {
        #listaInsumosReceta .detalle-row,
        #contenedorMod .mod-row,
        #contenedorCif .cif-row {
            border: 1px solid #dbe7ff !important;
            border-radius: .85rem !important;
            background: #fff !important;
            box-shadow: 0 .125rem .5rem rgba(10, 37, 64, 0.08) !important;
            padding: .8rem !important;
            margin-bottom: .75rem !important;
        }

        #listaInsumosReceta .detalle-row > [class*="col-"],
        #contenedorMod .mod-row > [class*="col-"],
        #contenedorCif .cif-row > [class*="col-"] {
            width: 100%;
            margin-bottom: .45rem;
        }

        #listaInsumosReceta .detalle-row > [class*="col-"]:last-child,
        #contenedorMod .mod-row > [class*="col-"]:last-child,
        #contenedorCif .cif-row > [class*="col-"]:last-child {
            margin-bottom: 0;
            text-align: right !important;
        }
    }

    #tablaRecetas tbody tr.receta-row-disabled {
        background-color: #f8f9fa;
    }

    #tablaRecetas tbody tr.receta-row-disabled td,
    #tablaRecetas tbody tr.receta-row-disabled .fw-bold,
    #tablaRecetas tbody tr.receta-row-disabled .fw-medium {
        color: #6c757d !important;
    }
</style>
<div class="container-fluid p-4" id="recetasApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-check me-2 text-primary"></i> Recetas (BOM)
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo de fórmulas y semielaborados.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionParametrosCatalogo">
                <i class="bi bi-sliders me-2 text-info"></i>Parámetros
            </button>
            <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearReceta" id="btnNuevaReceta">
                <i class="bi bi-plus-circle me-2"></i>Nueva receta
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="recetaSearch" placeholder="Buscar por código, producto...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-none" id="recetaFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                        <option value="2">Sin receta</option>
                        <option value="3">BOM desactivada</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaRecetas" class="table align-middle mb-0 table-pro table-hover"
                       data-erp-table="true"
                       data-rows-selector="#recetasTableBody tr:not(.empty-msg-row)"
                       data-search-input="#recetaSearch"
                       data-empty-text="No se encontraron recetas"
                       data-info-text-template="Mostrando {start} a {end} de {total} recetas"
                       data-erp-filters='[{"el":"#recetaFiltroEstado","attr":"data-estado","match":"equals"}]'
                       data-pagination-controls="#recetasPaginationControls"
                       data-pagination-info="#recetasPaginationInfo"
                       data-rows-per-page="15">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Producto Terminado</th>
                            <th class="text-center text-secondary fw-semibold">Versión</th>
                            <th class="text-center text-secondary fw-semibold">N° Insumos</th>
                            <th class="text-end text-secondary fw-semibold">Costo Teórico</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="recetasTableBody">
                        <?php if (empty($recetas)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-light"></i>
                                    No hay recetas registradas en el catálogo.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recetas as $receta): ?>
                                <?php
                                    $bomDesactivada = (int) ($receta['bom_desactivada'] ?? 0) === 1;
                                    $sinReceta = (int) ($receta['sin_receta'] ?? 0) === 1;
                                    $dataEstado = $bomDesactivada ? 3 : ($sinReceta ? 2 : (int) ($receta['estado'] ?? 0));
                                ?>
                                <tr class="border-bottom<?php echo $bomDesactivada ? ' receta-row-disabled' : ''; ?>" 
                                    data-search="<?php echo htmlspecialchars(mb_strtolower($receta['codigo'] . ' ' . $receta['producto_nombre']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-estado="<?php echo $dataEstado; ?>">
                                    
                                    <td class="ps-4 fw-bold text-primary align-top pt-3" data-label="Código">
                                        <?php echo e((string) $receta['codigo']); ?>
                                    </td>
                                    <td class="align-top pt-3" data-label="Producto Terminado">
                                        <div class="fw-bold text-dark"><?php echo e((string) $receta['producto_nombre']); ?></div>
                                        <?php if (!empty($receta['descripcion'])): ?>
                                            <div class="small text-muted text-truncate" style="max-width: 250px;" title="<?php echo e((string) $receta['descripcion']); ?>">
                                                <?php echo e((string) $receta['descripcion']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center align-top pt-3" data-label="Versión">
                                        <span class="badge bg-light text-secondary border shadow-sm">v.<?php echo (int) $receta['version']; ?></span>
                                    </td>
                                    <td class="text-center align-top pt-3" data-label="N° Insumos">
                                        <span class="fw-medium text-dark"><?php echo (int) $receta['total_insumos']; ?> ítems</span>
                                    </td>
                                    <td class="text-end align-top pt-3 fw-bold text-success" data-label="Costo Teórico">
                                        S/ <?php echo number_format((float) ($receta['costo_teorico'] ?? 0), 4); ?>
                                    </td>
                                    <td class="text-center align-top pt-3" data-label="Estado">
                                        <?php if ($bomDesactivada): ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill">BOM desactivada</span>
                                        <?php elseif ($sinReceta): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 rounded-pill">Sin receta</span>
                                        <?php elseif ((int) ($receta['estado'] ?? 0) === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 align-top pt-3" data-label="Acciones">
                                        <?php if ($bomDesactivada): ?>
                                            <span class="text-muted small">Habilita “Fórmula (BOM)” en Ítems para editar.</span>
                                        <?php elseif ($sinReceta): ?>
                                            <div class="d-inline-flex gap-1">
                                                <button type="button"
                                                        class="btn-icon btn-icon-warning js-agregar-receta"
                                                        data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                        data-producto="<?php echo e((string) ($receta['producto_nombre'] ?? '')); ?>"
                                                        data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                        data-version="<?php echo (int) ($receta['version'] ?? 1); ?>"
                                                        data-unidad="<?php echo e((string) ($receta['unidad_base'] ?? 'UND')); ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Crear Receta">
                                                    <i class="bi bi-journal-plus"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <div class="d-inline-flex gap-1">
                                                <button type="button"
                                                        class="btn-icon btn-icon-primary js-nueva-version"
                                                        data-id-receta="<?php echo (int) $receta['id']; ?>"
                                                        data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                        data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                        data-bs-toggle="tooltip"
                                                        title="Nueva Versión">
                                                    <i class="bi bi-files"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($recetas)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="recetasPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de Recetas">
                    <ul class="pagination mb-0 shadow-sm" id="recetasPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearReceta" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="modalCrearRecetaTitle">
                    <i class="bi bi-plus-circle me-2"></i><span id="modalTitleText">Nueva receta</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-3 p-md-4">
                <form method="post" id="formCrearReceta">
                    <input type="hidden" name="accion" value="crear_receta">
                    <input type="hidden" name="id_receta_base" id="newIdRecetaBase" value="0">

                    <div class="card modal-pastel-card mb-4">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información General</h6>
                            <div class="row g-3 align-items-end">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newCodigo">Código</label>
                                    <input type="text" class="form-control fw-bold shadow-none border-secondary-subtle text-primary" id="newCodigo" name="codigo" required>
                                </div>

                                <div class="col-md-1">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newVersion">Versión</label>
                                    <input type="number" class="form-control bg-light fw-bold text-center border-secondary-subtle shadow-none text-secondary" id="newVersion" name="version" value="1" readonly>
                                </div>

                                <div class="col-md-4" id="productoSelectContainer">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newProducto">Producto Terminado</label>
                                    <select class="form-select shadow-none border-secondary-subtle" id="newProducto" name="id_producto" required>
                                        <option value="" selected>Seleccionar producto...</option>
                                    </select>
                                </div>

                                <div class="col-md-4" id="productoDisplayContainer" style="display: none;">
                                    <label class="form-label small text-muted fw-bold mb-1">Producto Terminado</label>
                                    <div id="newProductoNombreDisplay" class="form-control bg-light fw-bold border-secondary-subtle text-dark"></div>
                                    <input type="hidden" id="newIdProductoHidden" name="id_producto">
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newRendimientoBase">Cantidad diseñada (lote)</label>
                                    <input type="number" class="form-control shadow-none border-secondary-subtle fw-semibold text-end" id="newRendimientoBase" name="rendimiento_base" step="0.0001" value="1" min="0.0001" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1">Unidad rendimiento</label>
                                    <input type="text" class="form-control bg-light fw-bold text-center border-secondary-subtle shadow-none" id="newUnidadRendimiento" name="unidad_rendimiento" value="UND" readonly>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newTiempoProduccionHoras">Tiempo producción (horas)</label>
                                    <input type="number" class="form-control shadow-none border-secondary-subtle fw-semibold text-end" id="newTiempoProduccionHoras" name="tiempo_produccion_horas" step="0.0001" value="1" min="0.0001" required>
                                </div>

                                <div class="col-md-4" id="contenedorVersionesPrevias" style="display:none;">
                                    <label for="newVersionBase" class="form-label small text-muted fw-bold mb-1"></i>Precargar versión</label>
                                    <select class="form-select form-select shadow-none border-secondary-subtle" id="newVersionBase">
                                        <option value="">Seleccione versión...</option>
                                    </select>
                                </div>

                                <div class="col-3">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newDescripcion">Descripción / Observaciones</label>
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="newDescripcion" name="descripcion" placeholder="Ej: Fórmula inicial de producción...">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card modal-pastel-card mb-4">
                        <div class="card-body p-0">
                            <ul class="nav nav-tabs px-3 pt-3 bg-white rounded-top" id="tabsRecetaCostos" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active fw-bold text-primary px-4 py-3" data-bs-toggle="tab" data-bs-target="#tabRecetaBom" type="button" role="tab"><i class="bi bi-diagram-3 me-2"></i>BOM / Insumos</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link fw-bold text-secondary px-4 py-3" data-bs-toggle="tab" data-bs-target="#tabRecetaMod" type="button" role="tab"><i class="bi bi-people me-2"></i>Mano de Obra (MOD)</button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link fw-bold text-secondary px-4 py-3" data-bs-toggle="tab" data-bs-target="#tabRecetaCif" type="button" role="tab"><i class="bi bi-lightning-charge me-2"></i>Costos Indirectos (CIF)</button>
                                </li>
                            </ul>

                            <div class="tab-content p-4">
                                <div class="tab-pane fade show active" id="tabRecetaBom" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0">Composición de Materiales</h6>
                                            <small class="text-muted">Insumos requeridos para la cantidad diseñada de esta receta.</small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" id="btnAgregarInsumo">
                                            <i class="bi bi-plus-lg me-1"></i>Añadir insumo
                                        </button>
                                    </div>
                                    <div id="listaInsumosReceta" class="lista-insumos-etapa d-flex flex-column gap-2"></div>
                                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                                        <div class="badge text-bg-secondary fs-6 px-3 py-2">
                                          Total BOM (Receta): <span id="totalBomCalculado">S/ 0.0000</span>
                                        </div>
                                        <div class="badge text-bg-info fs-6 px-3 py-2">
                                          Total BOM (Unidad): <span id="totalBomUnitCalculado">S/ 0.0000</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="tabRecetaMod" role="tabpanel">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <div>
                                            <h6 class="fw-bold text-dark mb-0">Mano de Obra Directa</h6>
                                            <small class="text-muted">Personal directo dedicado a la producción del lote.</small>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3 shadow-sm" id="btnAgregarMod">
                                            <i class="bi bi-person-plus me-1"></i>Añadir operario
                                        </button>
                                    </div>
                                    <div class="row g-2 mb-2 px-1 d-none d-md-flex text-muted small fw-bold text-uppercase border-bottom pb-2">
                                        <div class="col-md-5">Perfil / Puesto</div>
                                        <div class="col-md-6">Costo base (S/)</div>
                                        <div class="col-md-1 text-center">Acción</div>
                                    </div>
                                    <div id="contenedorMod" class="d-flex flex-column gap-2"></div>
                                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                                        <div class="badge text-bg-secondary fs-6 px-3 py-2">
                                            Total MOD (Receta): <span id="totalModCalculado">S/ 0.0000</span>
                                        </div>
                                        <div class="badge text-bg-info fs-6 px-3 py-2">
                                            Total MOD (Unidad): <span id="totalModUnitCalculado">S/ 0.0000</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="tabRecetaCif" role="tabpanel">
                                    <div class="alert alert-info d-flex align-items-center border-0 shadow-sm rounded-3 mb-4">
                                        <i class="bi bi-info-circle-fill fs-4 me-3 text-info"></i>
                                        <div>Ingrese el costo estimado por lote para cada concepto operativo (Servicios, Desgaste, Insumos Menores).</div>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h6 class="fw-bold text-dark mb-0">Costos Indirectos de Fabricación</h6>
                                        <button type="button" class="btn btn-sm btn-outline-secondary fw-bold px-3 shadow-sm" id="btnAgregarCif">
                                            <i class="bi bi-plus-lg me-1"></i>Añadir Costo Operativo
                                        </button>
                                    </div>
                                    <div class="row g-2 mb-2 px-1 d-none d-md-flex text-muted small fw-bold text-uppercase border-bottom pb-2">
                                        <div class="col-md-7">Concepto Operativo (MOD / CIF)</div>
                                        <div class="col-md-3">Costo Estimado (S/)</div>
                                        <div class="col-md-2 text-center">Acción</div>
                                    </div>
                                    <div id="contenedorCif" class="d-flex flex-column gap-2"></div>
                                    <div class="d-flex justify-content-end gap-2 flex-wrap mt-3">
                                        <div class="badge text-bg-secondary fs-6 px-3 py-2">
                                            Total CIF (Receta): <span id="totalCifCalculado">S/ 0.0000</span>
                                        </div>
                                        <div class="badge text-bg-info fs-6 px-3 py-2">
                                            Total CIF (Unidad): <span id="totalCifUnitCalculado">S/ 0.0000</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card modal-pastel-card mb-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-sliders me-2 text-info"></i>Parámetros de Calidad (IPC)</h6>
                                    <small class="text-muted">Métricas a medir durante la producción (Ej. Brix, pH, Temperatura).</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-info fw-bold px-3 shadow-sm" id="btnAgregarParametro">
                                    <i class="bi bi-plus-lg me-1"></i>Añadir Parámetro
                                </button>
                            </div>
                            <div id="contenedorParametros" class="d-flex flex-column gap-2 mt-3"></div>
                        </div>
                    </div>

                    <div class="card border border-primary-subtle shadow-sm bg-primary-subtle bg-opacity-10 mb-2">
                        <div class="card-body d-flex justify-content-between align-items-center p-4">
                            <div>
                                <h5 class="fw-bold text-dark mb-1">Costo teórico</h5>
                                <div id="bomResumen" class="small text-muted fw-semibold">0 insumos agregados.</div>
                            </div>
                            <div class="text-end">
                                <div class="mb-2">
                                    <h3 class="fw-bold text-primary mb-0" id="costoTotalCalculado">S/ 0.0000</h3>
                                    <span class="badge bg-secondary text-white mt-1">Costo Total Receta</span>
                                </div>
                                <div>
                                    <h4 class="fw-bold text-primary mb-0" id="costoTotalUnitarioCalculado">S/ 0.0000</h4>
                                    <span class="badge bg-primary text-white mt-1">Costo Total por Unidad</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <template id="parametroTemplate">
                        <div class="row g-2 align-items-center parametro-row bg-white p-2 border rounded shadow-sm">
                            <div class="col-md-5">
                                <select class="form-select form-select-sm shadow-none border-secondary-subtle" name="parametro_id[]" required>
                                    <option value="">Seleccione parámetro del catálogo...</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="number" class="form-control form-control-sm shadow-none border-secondary-subtle" name="parametro_valor[]" step="0.0001" placeholder="Valor objetivo (Numérico)" required>
                            </div>
                            <div class="col-md-2 text-center">
                                <button type="button" class="btn-icon btn-icon-danger js-remove-param" title="Eliminar parámetro">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template id="detalleRecetaTemplate">
                        <div class="row g-2 align-items-center detalle-row bg-white p-2 border rounded shadow-sm mb-2">
                            <div class="col-md-3">
                                <select class="form-select form-select-sm select-insumo shadow-none border-secondary-subtle" name="insumo_id[]" required></select>
                                <input type="hidden" class="input-etapa-hidden" name="insumo_etapa[]" value="General">
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted fw-bold border-secondary-subtle" title="Cantidad requerida">Q</span>
                                    <input type="number" class="form-control input-cantidad shadow-none border-secondary-subtle fw-semibold" name="insumo_cantidad[]" step="0.0001" value="1" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted fw-bold border-secondary-subtle" title="% de Merma">% M.</span>
                                    <input type="number" class="form-control input-merma shadow-none border-secondary-subtle" name="insumo_merma[]" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted border-secondary-subtle" title="Costo Unitario">S/</span>
                                    <input type="number" class="form-control bg-light input-costo-unitario border-secondary-subtle text-muted" name="insumo_costo[]" step="0.0001" value="0" readonly>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <input type="text" class="form-control form-control-sm bg-light input-costo-item text-primary fw-bold px-1 text-center border-secondary-subtle" value="0.0000" readonly>
                            </div>
                            <div class="col-md-1 text-center">
                                <button type="button" class="btn-icon btn-icon-danger js-remove-row" title="Eliminar insumo">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template id="modTemplate">
                        <div class="row g-2 align-items-center mod-row bg-white p-2 border rounded shadow-sm mb-2">
                            <div class="col-md-5"><input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle fw-semibold" name="mod_perfil_puesto[]" placeholder="Ej: Operador de Máquina A"></div>
                            <div class="col-md-6">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted border-secondary-subtle">S/</span>
                                    <input type="number" class="form-control form-control-sm mod-costo shadow-none border-secondary-subtle" name="mod_costo_hora_estimado[]" step="0.0001" placeholder="Costo Base">
                                </div>
                            </div>
                            <div class="col-md-1 text-center">
                                <button type="button" class="btn-icon btn-icon-danger js-remove-mod" title="Eliminar operario">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template id="cifTemplate">
                        <div class="row g-2 align-items-center cif-row bg-white p-2 border rounded shadow-sm mb-2">
                            <div class="col-md-7">
                                <select class="form-select form-select-sm cif-concepto-select shadow-none border-secondary-subtle" name="cif_id_activo[]" required>
                                    <option value="" selected disabled>Seleccione Costo Indirecto (CIF)...</option>
                                    
                                    <?php foreach ($conceptosOperativos as $c): if ($c['tipo'] === 'CIF'): ?>
                                        <option value="<?php echo $c['id']; ?>" data-nombre="<?php echo htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endif; endforeach; ?>
                                    
                                </select>
                                <input type="hidden" name="cif_concepto[]" class="cif-concepto-nombre">
                            </div>
                            <div class="col-md-3">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted border-secondary-subtle">S/</span>
                                    <input type="number" class="form-control form-control-sm cif-costo border-secondary-subtle fw-semibold text-primary" name="cif_costo_estimado[]" step="0.0001" placeholder="0.00" required>
                                </div>
                            </div>
                            <div class="col-md-2 text-center">
                                <button type="button" class="btn-icon btn-icon-danger js-remove-cif" title="Eliminar Costo Operativo">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="d-flex justify-content-end pt-4 mt-2">
                        <button type="button" class="btn btn-light text-secondary me-2 border px-4 fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Receta BOM</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionParametrosCatalogo" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-sliders me-2 text-info"></i>Catálogo de Parámetros (IPC)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body bg-light pt-0">
                <div class="card modal-pastel-card mb-4">
                    <div class="card-body">
                        <form method="post" id="formGestionParametroCatalogo" class="row g-2">
                            <input type="hidden" name="accion" id="accionParametroCatalogo" value="crear_parametro_catalogo">
                            <input type="hidden" name="id_parametro_catalogo" id="idParametroCatalogo" value="">
                            
                            <div class="col-12 col-md-5">
                                <label class="form-label small text-muted fw-bold mb-1">Nombre de la Métrica <span class="text-danger">*</span></label>
                                <input type="text" class="form-control shadow-none border-secondary-subtle fw-semibold" id="nombreParametroCatalogo" name="nombre" maxlength="50" required placeholder="Ej: Temperatura Horneado">
                            </div>
                            <div class="col-12 col-md-3">
                                <label class="form-label small text-muted fw-bold mb-1">Unidad de Medida</label>
                                <input type="text" class="form-control shadow-none border-secondary-subtle" id="unidadParametroCatalogo" name="unidad_medida" maxlength="20" placeholder="Ej: °C, °Bx, pH">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label small text-muted fw-bold mb-1">Descripción Breve</label>
                                <input type="text" class="form-control shadow-none border-secondary-subtle" id="descripcionParametroCatalogo" name="descripcion" placeholder="Opcional">
                            </div>
                            <div class="col-12 d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                                <button type="button" class="btn btn-sm btn-light border px-3" id="btnResetParametroCatalogo">Limpiar campos</button>
                                <button type="submit" class="btn btn-sm btn-info text-white fw-bold px-4" id="btnGuardarParametroCatalogo">Registrar Parámetro</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card modal-pastel-card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table align-middle table-sm mb-0 table-pastel">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-3 text-secondary fw-semibold">Nombre de Métrica</th>
                                        <th class="text-secondary fw-semibold">Unidad</th>
                                        <th class="text-secondary fw-semibold">Descripción</th>
                                        <th class="text-end pe-3 text-secondary fw-semibold">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($parametrosCatalogo)): ?>
                                        <?php foreach ($parametrosCatalogo as $param): ?>
                                            <tr class="border-bottom">
                                                <td class="ps-3 fw-bold text-dark"><?php echo e((string) ($param['nombre'] ?? '')); ?></td>
                                                <td><span class="badge bg-secondary-subtle text-secondary border px-2"><?php echo e((string) ($param['unidad_medida'] ?? '-')); ?></span></td>
                                                <td class="text-muted small"><?php echo e((string) ($param['descripcion'] ?? '-')); ?></td>
                                                <td class="text-end pe-3">
                                                    <div class="d-inline-flex gap-1">
                                                        <button type="button"
                                                                class="btn-icon btn-icon-info js-editar-param-catalogo"
                                                                data-bs-toggle="tooltip" title="Editar este parámetro"
                                                                data-id="<?php echo (int) ($param['id'] ?? 0); ?>"
                                                                data-nombre="<?php echo e((string) ($param['nombre'] ?? '')); ?>"
                                                                data-unidad="<?php echo e((string) ($param['unidad_medida'] ?? '')); ?>"
                                                                data-descripcion="<?php echo e((string) ($param['descripcion'] ?? '')); ?>">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <form method="post" class="d-inline m-0" onsubmit="return confirm('¿Está seguro de eliminar este parámetro permanentemente?');">
                                                            <input type="hidden" name="accion" value="eliminar_parametro_catalogo">
                                                            <input type="hidden" name="id_parametro_catalogo" value="<?php echo (int) ($param['id'] ?? 0); ?>">
                                                            <button type="submit" class="btn-icon btn-icon-danger" data-bs-toggle="tooltip" title="Eliminar definitivamente">
                                                                <i class="bi bi-trash3"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4"><i class="bi bi-inbox d-block fs-3 text-light mb-1"></i>No hay parámetros registrados.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>window.ACTIVOS_FIJOS_CIF = [];</script>
<script src="<?php echo base_url(); ?>/assets/js/produccion/produccion_recetas.js?v=2.6"></script>
