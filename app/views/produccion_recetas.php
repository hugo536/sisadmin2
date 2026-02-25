<?php
$recetas = $recetas ?? [];
$itemsStockeables = $items_stockeables ?? [];
$parametrosCatalogo = $parametros_catalogo ?? []; // RECIBIMOS EL CATÁLOGO DESDE EL CONTROLLER
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-check me-2 text-primary"></i> Recetas (BOM)
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo de fórmulas y semielaborados.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionParametrosCatalogo">
                <i class="bi bi-sliders me-2 text-info"></i>Parámetros
            </button>
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearReceta">
                <i class="bi bi-plus-circle me-2"></i>Nueva receta
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="recetaSearch" placeholder="Buscar por código, producto...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="recetaFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activas</option>
                        <option value="0">Inactivas</option>
                        <option value="2">Sin receta</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="tablaRecetas" class="table align-middle mb-0 table-pro">
                    <thead>
                        <tr>
                            <th class="ps-4">Código</th>
                            <th>Producto Terminado</th>
                            <th>Versión</th>
                            <th>N° Insumos</th>
                            <th>Costo Teórico</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recetas as $receta): ?>
                            <tr data-search="<?php echo mb_strtolower($receta['codigo'] . ' ' . $receta['producto_nombre']); ?>"
                                data-estado="<?php echo (int) (($receta['sin_receta'] ?? 0) === 1 ? 2 : ($receta['estado'] ?? 0)); ?>">
                                <td class="ps-4 fw-semibold text-primary"><?php echo e((string) $receta['codigo']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e((string) $receta['producto_nombre']); ?></div>
                                    <div class="small text-muted"><?php echo e((string) ($receta['descripcion'] ?? '')); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border">v.<?php echo (int) $receta['version']; ?></span></td>
                                <td><?php echo (int) $receta['total_insumos']; ?> ítems</td>
                                <td>S/ <?php echo number_format((float) ($receta['costo_teorico'] ?? 0), 4); ?></td>
                                <td class="text-center">
                                    <?php if ((int) ($receta['sin_receta'] ?? 0) === 1): ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Sin receta</span>
                                    <?php elseif ((int) ($receta['estado'] ?? 0) === 1): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 rounded-pill">Activa</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 rounded-pill">Inactiva</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <?php if ((int) ($receta['sin_receta'] ?? 0) === 1): ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-warning js-agregar-receta"
                                                data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                data-producto="<?php echo e((string) ($receta['producto_nombre'] ?? '')); ?>"
                                                data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                data-version="<?php echo (int) ($receta['version'] ?? 1); ?>"
                                                title="Agregar receta inicial">
                                            <i class="bi bi-journal-plus me-1"></i>Agregar receta
                                        </button>
                                    <?php else: ?>
                                        <button type="button"
                                                class="btn btn-sm btn-outline-primary js-nueva-version"
                                                data-id-receta="<?php echo (int) $receta['id']; ?>"
                                                data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                title="Editar y crear nueva versión">
                                            <i class="bi bi-files me-1"></i>Nueva Versión
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3">
                <small class="text-muted">Mostrando <?php echo count($recetas); ?> recetas</small>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearReceta" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalCrearRecetaTitle"><i class="bi bi-plus-circle me-2"></i>Nueva receta</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form method="post" id="formCrearReceta">
                    <input type="hidden" name="accion" value="crear_receta">
                    <input type="hidden" name="id_receta_base" id="newIdRecetaBase" value="0">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-3">Información General</h6>
                            <div class="row g-2">
                                <div class="col-md-3 form-floating">
                                    <input type="text" class="form-control" id="newCodigo" name="codigo" placeholder="Código" required>
                                    <label for="newCodigo">Código</label>
                                </div>
                                <div class="col-md-2 form-floating">
                                    <input type="number" class="form-control" id="newVersion" name="version" value="1" min="1">
                                    <label for="newVersion">Versión</label>
                                </div>
                                <div class="col-md-4 form-floating">
                                    <select class="form-select" id="newProducto" name="id_producto" required>
                                        <option value="" selected>Seleccionar...</option>
                                        <?php foreach ($itemsStockeables as $item): ?>
                                            <option value="<?php echo (int) $item['id']; ?>" 
                                                    data-tipo="<?php echo (int) ($item['tipo_item'] ?? 0); ?>"
                                                    data-costo="<?php echo (float) ($item['costo_calculado'] ?? $item['costo_referencial'] ?? 0); ?>">
                                                <?php echo e((string) $item['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <label for="newProducto">Producto Destino</label>
                                </div>
                                <div class="col-md-3 form-floating">
                                    <input type="number" step="0.0001" min="0" class="form-control" id="newRendimientoBase" name="rendimiento_base" placeholder="Rendimiento base">
                                    <label for="newRendimientoBase">Rendimiento base</label>
                                </div>
                                <div class="col-md-3 form-floating">
                                    <input type="text" class="form-control" id="newUnidadRendimiento" name="unidad_rendimiento" placeholder="Unidad">
                                    <label for="newUnidadRendimiento">Unidad rendimiento</label>
                                </div>
                                <div class="col-md-9 form-floating">
                                    <input type="text" class="form-control" id="newDescripcion" name="descripcion" placeholder="Descripción">
                                    <label for="newDescripcion">Descripción / Observaciones</label>
                                </div>
                                <div class="col-md-6" id="contenedorVersionesPrevias" style="display:none;">
                                    <label for="newVersionBase" class="form-label small text-muted fw-bold mb-1">Versiones anteriores</label>
                                    <select class="form-select" id="newVersionBase">
                                        <option value="">Seleccione versión...</option>
                                    </select>
                                    <small class="text-muted">Seleccione una versión para cargar sus datos en el formulario.</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-muted mb-0"><i class="bi bi-sliders me-2"></i>Parámetros de Control (IPC)</h6>
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnAgregarParametro">
                                    <i class="bi bi-plus-lg me-1"></i>Añadir parámetro
                                </button>
                            </div>
                            
                            <div id="contenedorParametros"></div>
                            
                            <div id="emptyParametros" class="text-muted small fst-italic py-2 text-center">
                                No se han definido parámetros de control para esta receta.
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-muted mb-0"><i class="bi bi-diagram-3 me-2"></i>Composición (BOM)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarInsumo">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar insumo
                                </button>
                            </div>
                            <div id="listaInsumosReceta" class="lista-insumos-etapa"></div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm bg-primary-subtle bg-opacity-10">
                        <div class="card-body d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="fw-bold text-dark mb-1">Resumen de la Receta</h6>
                                <div id="bomResumen" class="small text-muted">0 insumos agregados.</div>
                            </div>
                            <div class="text-end">
                                <h5 class="fw-bold text-primary mb-0" id="costoTotalCalculado">S/ 0.0000</h5>
                                <small class="text-muted fw-semibold">Costo teórico total</small>
                            </div>
                        </div>
                    </div>

                    <template id="parametroTemplate">
                        <div class="row g-2 mb-2 parametro-row align-items-center bg-white p-2 border rounded-2 shadow-sm animate__animated animate__fadeIn">
                            <div class="col-md-6">
                                <label class="form-label small text-muted mb-0 fw-bold">Parámetro</label>
                                <select class="form-select form-select-sm" name="parametro_id[]" required>
                                    <option value="">Seleccione parámetro del catálogo...</option>
                                    <?php foreach ($parametrosCatalogo as $param): ?>
                                        <option value="<?php echo (int) $param['id']; ?>">
                                            <?php echo e((string) $param['nombre']); ?> 
                                            <?php echo !empty($param['unidad_medida']) ? '(' . e((string) $param['unidad_medida']) . ')' : ''; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small text-muted mb-0 fw-bold">Valor Objetivo</label>
                                <input step="0.0001" type="number" required name="parametro_valor[]" class="form-control form-control-sm" placeholder="Ej: 7.5">
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm text-danger border-0 bg-transparent mt-3 js-remove-param" title="Quitar parámetro">
                                    <i class="bi bi-trash fs-5"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template id="detalleRecetaTemplate">
                        <div class="row g-2 mb-2 detalle-row align-items-center bg-white p-2 border rounded-2 shadow-sm animate__animated animate__fadeIn">
                            <input type="hidden" name="detalle_etapa[]" class="input-etapa-hidden" value="General">
                            
                            <div class="col-md-3">
                                <label class="form-label small text-muted mb-0 fw-bold">Insumo / Semielaborado</label>
                                <select class="form-select form-select-sm select-insumo" name="detalle_id_insumo[]" required>
                                    <option value="">Seleccione...</option>
                                    <?php foreach ($itemsStockeables as $item): ?>
                                        <option value="<?php echo (int) $item['id']; ?>"
                                                data-tipo="<?php echo (int) ($item['tipo_item'] ?? 0); ?>"
                                                data-costo="<?php echo (float) ($item['costo_calculado'] ?? $item['costo_referencial'] ?? 0); ?>">
                                            <?php echo e((string) $item['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0 fw-bold">Cantidad Base</label>
                                <input step="0.0001" min="0.0001" type="number" required name="detalle_cantidad_por_unidad[]" class="form-control form-control-sm input-cantidad" placeholder="0.0000">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0 fw-bold">Merma %</label>
                                <input step="0.01" min="0" type="number" name="detalle_merma_porcentaje[]" value="0.00" class="form-control form-control-sm input-merma">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0 fw-bold">Costo unitario</label>
                                <input type="number" step="0.0001" min="0" class="form-control form-control-sm bg-light input-costo-unitario" name="detalle_costo_unitario[]" value="0.0000" readonly>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small text-muted mb-0 fw-bold">Costo total ítem</label>
                                <input type="text" class="form-control form-control-sm bg-light input-costo-item" value="0.0000" readonly>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm text-danger border-0 bg-transparent mt-3 js-remove-row" title="Quitar línea">
                                    <i class="bi bi-trash fs-5"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <div class="d-flex justify-content-end pt-3 pb-2">
                        <button type="button" class="btn btn-light text-secondary me-2 border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-save me-2"></i>Guardar Receta</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<div class="modal fade" id="modalGestionParametrosCatalogo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-sliders me-2 text-info"></i>Gestión de Parámetros</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="formGestionParametroCatalogo" class="row g-2 mb-3 border rounded-3 p-3 bg-light">
                    <input type="hidden" name="accion" id="accionParametroCatalogo" value="crear_parametro_catalogo">
                    <input type="hidden" name="id_parametro_catalogo" id="idParametroCatalogo" value="">
                    <div class="col-12 col-md-5">
                        <label class="form-label small text-muted fw-bold mb-1">Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="nombreParametroCatalogo" name="nombre" maxlength="50" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label small text-muted fw-bold mb-1">Unidad</label>
                        <input type="text" class="form-control" id="unidadParametroCatalogo" name="unidad_medida" maxlength="20" placeholder="Ej: °Bx">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Descripción</label>
                        <input type="text" class="form-control" id="descripcionParametroCatalogo" name="descripcion" placeholder="Opcional">
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-light" id="btnResetParametroCatalogo">Limpiar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarParametroCatalogo">Guardar</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table align-middle table-sm mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Unidad</th>
                                <th>Descripción</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($parametrosCatalogo)): ?>
                                <?php foreach ($parametrosCatalogo as $param): ?>
                                    <tr>
                                        <td class="fw-semibold"><?php echo e((string) ($param['nombre'] ?? '')); ?></td>
                                        <td><?php echo e((string) ($param['unidad_medida'] ?? '-')); ?></td>
                                        <td><?php echo e((string) ($param['descripcion'] ?? '-')); ?></td>
                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary js-editar-param-catalogo"
                                                    data-id="<?php echo (int) ($param['id'] ?? 0); ?>"
                                                    data-nombre="<?php echo e((string) ($param['nombre'] ?? '')); ?>"
                                                    data-unidad="<?php echo e((string) ($param['unidad_medida'] ?? '')); ?>"
                                                    data-descripcion="<?php echo e((string) ($param['descripcion'] ?? '')); ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este parámetro?');">
                                                <input type="hidden" name="accion" value="eliminar_parametro_catalogo">
                                                <input type="hidden" name="id_parametro_catalogo" value="<?php echo (int) ($param['id'] ?? 0); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-3">No hay parámetros registrados.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo base_url(); ?>/assets/js/produccion_recetas.js?v=2.2"></script>
