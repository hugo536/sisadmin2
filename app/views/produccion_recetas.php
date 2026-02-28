<?php
$recetas = $recetas ?? [];
$parametrosCatalogo = $parametros_catalogo ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-check me-2 text-primary"></i> Recetas (BOM)
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo de fórmulas y semielaborados.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionParametrosCatalogo">
                <i class="bi bi-sliders me-2 text-info"></i>Parámetros
            </button>
            <button class="btn btn-primary shadow-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearReceta" id="btnNuevaReceta">
                <i class="bi bi-plus-circle me-2"></i>Nueva receta
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="recetaSearch" placeholder="Buscar por código, producto...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light border-secondary-subtle shadow-sm" id="recetaFiltroEstado">
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
                <table id="tablaRecetas" class="table align-middle mb-0 table-pro"
                       data-erp-table="true"
                       data-search-input="#recetaSearch"
                       data-pagination-controls="#recetasPaginationControls"
                       data-pagination-info="#recetasPaginationInfo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold border-end">Código</th>
                            <th class="text-secondary fw-semibold border-end">Producto Terminado</th>
                            <th class="text-secondary fw-semibold border-end">Versión</th>
                            <th class="text-secondary fw-semibold border-end">N° Insumos</th>
                            <th class="text-secondary fw-semibold border-end">Costo Teórico</th>
                            <th class="text-center text-secondary fw-semibold border-end">Estado</th>
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
                                <tr class="border-bottom" 
                                    data-search="<?php echo htmlspecialchars(mb_strtolower($receta['codigo'] . ' ' . $receta['producto_nombre']), ENT_QUOTES, 'UTF-8'); ?>"
                                    data-estado="<?php echo (int) (($receta['sin_receta'] ?? 0) === 1 ? 2 : ($receta['estado'] ?? 0)); ?>">
                                    
                                    <td class="ps-4 fw-semibold text-primary align-top pt-3 border-end" style="background-color: #fcfcfc;">
                                        <?php echo e((string) $receta['codigo']); ?>
                                    </td>
                                    <td class="align-top pt-3 border-end">
                                        <div class="fw-bold text-dark"><?php echo e((string) $receta['producto_nombre']); ?></div>
                                        <div class="small text-muted"><?php echo e((string) ($receta['descripcion'] ?? '')); ?></div>
                                    </td>
                                    <td class="align-top pt-3 border-end">
                                        <span class="badge bg-light text-dark border shadow-sm">v.<?php echo (int) $receta['version']; ?></span>
                                    </td>
                                    <td class="align-top pt-3 border-end">
                                        <span class="fw-medium text-secondary"><?php echo (int) $receta['total_insumos']; ?> ítems</span>
                                    </td>
                                    <td class="align-top pt-3 border-end text-success fw-medium">
                                        S/ <?php echo number_format((float) ($receta['costo_teorico'] ?? 0), 4); ?>
                                    </td>
                                    <td class="text-center align-top pt-3 border-end">
                                        <?php if ((int) ($receta['sin_receta'] ?? 0) === 1): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-1 rounded-pill">Sin receta</span>
                                        <?php elseif ((int) ($receta['estado'] ?? 0) === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Activa</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-3 py-1 rounded-pill">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4 align-top pt-3">
                                        <?php if ((int) ($receta['sin_receta'] ?? 0) === 1): ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-warning fw-semibold js-agregar-receta"
                                                    data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                    data-producto="<?php echo e((string) ($receta['producto_nombre'] ?? '')); ?>"
                                                    data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                    data-version="<?php echo (int) ($receta['version'] ?? 1); ?>"
                                                    data-unidad="<?php echo e((string) ($receta['unidad_base'] ?? 'UND')); ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Agregar receta inicial">
                                                <i class="bi bi-journal-plus me-1"></i>Agregar receta
                                            </button>
                                        <?php else: ?>
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary fw-semibold js-nueva-version"
                                                    data-id-receta="<?php echo (int) $receta['id']; ?>"
                                                    data-id-producto="<?php echo (int) ($receta['id_producto'] ?? 0); ?>"
                                                    data-codigo="<?php echo e((string) ($receta['codigo'] ?? '')); ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Editar y crear nueva versión">
                                                <i class="bi bi-files me-1"></i>Nueva Versión
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($recetas)): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="recetasPaginationInfo">Procesando...</div>
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
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="modalCrearRecetaTitle">
                    <i class="bi bi-plus-circle me-2"></i><span id="modalTitleText">Nueva receta</span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <form method="post" id="formCrearReceta">
                    <input type="hidden" name="accion" value="crear_receta">
                    <input type="hidden" name="id_receta_base" id="newIdRecetaBase" value="0">

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-muted mb-3">Información General</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newCodigo">Código</label>
                                    <input type="text" class="form-control fw-semibold" id="newCodigo" name="codigo" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newVersion">Versión</label>
                                    <input type="number" class="form-control bg-light fw-bold text-center border" id="newVersion" name="version" value="1" readonly>
                                </div>

                                <div class="col-md-4" id="productoSelectContainer">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newProducto">Producto Terminado</label>
                                    <select class="form-select" id="newProducto" name="id_producto" required>
                                        <option value="" selected>Seleccionar producto...</option>
                                    </select>
                                </div>

                                <div class="col-md-4" id="productoDisplayContainer" style="display: none;">
                                    <label class="form-label small text-muted fw-bold mb-1">Producto Terminado</label>
                                    <div id="newProductoNombreDisplay" class="form-control bg-light fw-bold"></div>
                                    <input type="hidden" id="newIdProductoHidden" name="id_producto">
                                </div>

                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold mb-1">Unidad rendimiento</label>
                                    <input type="text" class="form-control bg-light fw-bold" id="newUnidadRendimiento" name="unidad_rendimiento" value="UND" readonly>
                                </div>

                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold mb-1" for="newDescripcion">Descripción / Observaciones</label>
                                    <input type="text" class="form-control" id="newDescripcion" name="descripcion" placeholder="Ej: Fórmula inicial...">
                                </div>

                                <div class="col-12" id="contenedorVersionesPrevias" style="display:none;">
                                    <label for="newVersionBase" class="form-label small text-muted fw-bold mb-1">Versiones anteriores</label>
                                    <select class="form-select" id="newVersionBase">
                                        <option value="">Seleccione versión para cargar...</option>
                                    </select>
                                    <small class="text-muted">Seleccione una versión para precargar sus datos.</small>
                                </div>
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

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-muted mb-0"><i class="bi bi-sliders me-2"></i>Parámetros de Control (IPC)</h6>
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnAgregarParametro">
                                    <i class="bi bi-plus-lg me-1"></i>Añadir parámetro
                                </button>
                            </div>
                            <div id="contenedorParametros"></div>
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
                        <div class="row g-2 align-items-center mb-2 parametro-row">
                            <div class="col-md-5">
                                <select class="form-select form-select-sm" name="parametro_id[]" required>
                                    <option value="">Seleccione parámetro...</option>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <input type="number" class="form-control form-control-sm" name="parametro_valor[]" step="0.0001" placeholder="Valor objetivo" required>
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-param" title="Eliminar parámetro">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    </template>

                    <template id="detalleRecetaTemplate">
                        <div class="row g-2 align-items-center mb-2 detalle-row pb-2 border-bottom">
                            <div class="col-md-4">
                                <select class="form-select form-select-sm select-insumo" name="insumo_id[]" required></select>
                                <input type="hidden" class="input-etapa-hidden" name="insumo_etapa[]" value="General">
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted" title="Cantidad">Cant.</span>
                                    <input type="number" class="form-control input-cantidad" name="insumo_cantidad[]" step="0.0001" value="1" required>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted" title="% de Merma">% M.</span>
                                    <input type="number" class="form-control input-merma" name="insumo_merma[]" step="0.01" value="0">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light text-muted" title="Costo Unitario">C.U. S/</span>
                                    <input type="number" class="form-control bg-light input-costo-unitario" name="insumo_costo[]" step="0.0001" value="0" readonly>
                                </div>
                            </div>
                            <div class="col-md-1">
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control bg-light input-costo-item text-primary fw-bold px-1 text-center" value="0.0000" readonly>
                                </div>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="button" class="btn btn-sm btn-outline-danger js-remove-row" title="Eliminar insumo">
                                    <i class="bi bi-trash"></i>
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
                        <thead class="bg-light">
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
                                        <td class="fw-semibold text-dark"><?php echo e((string) ($param['nombre'] ?? '')); ?></td>
                                        <td><span class="badge bg-secondary-subtle text-secondary border"><?php echo e((string) ($param['unidad_medida'] ?? '-')); ?></span></td>
                                        <td class="text-muted small"><?php echo e((string) ($param['descripcion'] ?? '-')); ?></td>
                                        <td class="text-end">
                                            <button type="button"
                                                    class="btn btn-sm btn-light text-primary rounded-circle border-0 js-editar-param-catalogo"
                                                    data-bs-toggle="tooltip" title="Editar"
                                                    data-id="<?php echo (int) ($param['id'] ?? 0); ?>"
                                                    data-nombre="<?php echo e((string) ($param['nombre'] ?? '')); ?>"
                                                    data-unidad="<?php echo e((string) ($param['unidad_medida'] ?? '')); ?>"
                                                    data-descripcion="<?php echo e((string) ($param['descripcion'] ?? '')); ?>">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar este parámetro?');">
                                                <input type="hidden" name="accion" value="eliminar_parametro_catalogo">
                                                <input type="hidden" name="id_parametro_catalogo" value="<?php echo (int) ($param['id'] ?? 0); ?>">
                                                <button type="submit" class="btn btn-sm btn-light text-danger rounded-circle border-0" data-bs-toggle="tooltip" title="Eliminar">
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

<script src="<?php echo base_url(); ?>/assets/js/produccion_recetas.js?v=2.3"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.ERPTable !== 'undefined') {
        
        ERPTable.initTooltips();
        
        ERPTable.createTableManager({
            tableSelector: '#tablaRecetas',
            rowsSelector: '#recetasTableBody tr:not(.empty-msg-row)', // Excluye la fila de mensaje vacío
            searchInput: '#recetaSearch',
            searchAttr: 'data-search',
            rowsPerPage: 25, 
            paginationControls: '#recetasPaginationControls',
            paginationInfo: '#recetasPaginationInfo',
            emptyText: 'No se encontraron recetas',
            infoText: ({ start, end, total }) => `Mostrando ${start} a ${end} de ${total} recetas`,
            
            // Filtro personalizado de estado
            filters: [
                { el: '#recetaFiltroEstado', attr: 'data-estado', match: 'equals' }
            ]
        }).init();
        
    }
});
</script>