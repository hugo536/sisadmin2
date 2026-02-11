<?php
$items = $items ?? [];
$categorias = $categorias ?? [];
$categoriasGestion = $categorias_gestion ?? [];
$marcas = $marcas ?? [];
$marcasGestion = $marcas_gestion ?? [];
$sabores = $sabores ?? [];
$saboresGestion = $sabores_gestion ?? [];
$presentaciones = $presentaciones ?? [];
$presentacionesGestion = $presentaciones_gestion ?? [];
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-box-seam me-2 text-primary"></i> Ítems y Servicios
            </h1>
            <p class="text-muted small mb-0 ms-1">Administra el catálogo maestro de ítems.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGestionCategorias">
                <i class="bi bi-tags me-2 text-info"></i>Categorías
            </button>
            <button class="btn btn-white border shadow-sm text-secondary fw-semibold js-open-gestion-items" type="button" id="btnGestionItemsHeader" data-tab="sabores" aria-label="Abrir configuración de ítems">
                <i class="bi bi-sliders me-2 text-info"></i>Configuración de ítems
            </button>
            <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="modal" data-bs-target="#modalCrearItem">
                <i class="bi bi-plus-circle me-2"></i>Nuevo ítem
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="itemSearch" placeholder="Buscar ítem...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="itemFiltroTipo">
                        <option value="">Todos los tipos</option>
                        <option value="producto">Producto</option>
                        <option value="materia_prima">Materia prima</option>
                        <option value="material_empaque">Material de empaque</option>
                        <option value="servicio">Servicio</option>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="itemFiltroEstado">
                        <option value="">Todos los estados</option>
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="itemsTable">
                    <thead>
                        <tr>
                            <th class="ps-4">SKU</th>
                            <th>Nombre</th>
                            <th>Tipo</th>
                            <th>Precio</th>
                            <th>Moneda</th>
                            <th>Stock mín.</th>
                            <th class="text-center">Estado</th>
                            <th class="text-end pe-4">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr data-estado="<?php echo (int) $item['estado']; ?>"
                                data-tipo="<?php echo e($item['tipo_item']); ?>"
                                data-search="<?php echo e(mb_strtolower($item['sku'].' '.$item['nombre'].' '.($item['descripcion'] ?? '').' '.($item['marca'] ?? ''))); ?>">
                                <td class="ps-4 fw-semibold"><?php echo e($item['sku']); ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?php echo e($item['nombre']); ?></div>
                                    <div class="small text-muted"><?php echo e($item['descripcion'] ?? ''); ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo e($item['tipo_item']); ?></span></td>
                                <td><?php echo e(number_format((float) $item['precio_venta'], 4)); ?></td>
                                <td><?php echo e($item['moneda'] ?? ''); ?></td>
                                <td><?php echo e(number_format((float) $item['stock_minimo'], 4)); ?></td>
                                <td class="text-center">
                                    <?php if ((int) $item['estado'] === 1): ?>
                                        <span class="badge-status status-active" id="badge_status_item_<?php echo (int) $item['id']; ?>">Activo</span>
                                    <?php else: ?>
                                        <span class="badge-status status-inactive" id="badge_status_item_<?php echo (int) $item['id']; ?>">Inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-2">
                                        <div class="form-check form-switch pt-1" title="Cambiar estado">
                                            <input class="form-check-input switch-estado-item" type="checkbox" role="switch"
                                                   style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                   data-id="<?php echo (int) $item['id']; ?>"
                                                   <?php echo ((int) $item['estado'] === 1) ? 'checked' : ''; ?>>
                                        </div>

                                        <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                        <a href="?ruta=items/perfil&id=<?php echo (int) $item['id']; ?>" class="btn btn-sm btn-light text-info border-0 bg-transparent" title="Ver Perfil y Documentos">
                                            <i class="bi bi-person-badge fs-5"></i>
                                        </a>
                                        <button class="btn btn-sm btn-light text-primary border-0 bg-transparent" data-bs-toggle="modal" data-bs-target="#modalEditarItem"
                                            data-id="<?php echo (int) $item['id']; ?>"
                                            data-sku="<?php echo e($item['sku']); ?>"
                                            data-nombre="<?php echo e($item['nombre']); ?>"
                                            data-descripcion="<?php echo e($item['descripcion'] ?? ''); ?>"
                                            data-tipo="<?php echo e($item['tipo_item']); ?>"
                                            data-marca="<?php echo e($item['marca'] ?? ''); ?>"
                                            data-unidad="<?php echo e($item['unidad_base'] ?? ''); ?>"
                                            data-moneda="<?php echo e($item['moneda'] ?? ''); ?>"
                                            data-impuesto="<?php echo e((string) ($item['impuesto'] ?? '18.00')); ?>"
                                            data-precio="<?php echo e((string) $item['precio_venta']); ?>"
                                            data-stock-minimo="<?php echo e((string) $item['stock_minimo']); ?>"
                                            data-costo="<?php echo e((string) $item['costo_referencial']); ?>"
                                            data-controla-stock="<?php echo (int) $item['controla_stock']; ?>"
                                            data-permite-decimales="<?php echo (int) ($item['permite_decimales'] ?? 0); ?>"
                                            data-requiere-lote="<?php echo (int) ($item['requiere_lote'] ?? 0); ?>"
                                            data-requiere-vencimiento="<?php echo (int) ($item['requiere_vencimiento'] ?? 0); ?>"
                                            data-dias-alerta-vencimiento="<?php echo e((string) ($item['dias_alerta_vencimiento'] ?? '')); ?>"
                                            data-categoria="<?php echo e((string) ($item['id_categoria'] ?? '')); ?>"
                                            data-sabor="<?php echo e((string) ($item['id_sabor'] ?? '')); ?>"
                                            data-presentacion="<?php echo e((string) ($item['id_presentacion'] ?? '')); ?>"
                                            data-estado="<?php echo (int) $item['estado']; ?>">
                                            <i class="bi bi-pencil-square fs-5"></i>
                                        </button>
                                        <form method="post" class="d-inline m-0" onsubmit="return confirm('¿Eliminar este ítem?');">
                                            <input type="hidden" name="accion" value="eliminar">
                                            <input type="hidden" name="id" value="<?php echo (int) $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="itemsPaginationInfo">Cargando...</small>
                <nav><ul class="pagination pagination-sm mb-0 justify-content-end" id="itemsPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionItems" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-sliders me-2"></i>Configuración de ítems</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="sabores-tab" data-bs-toggle="tab" data-bs-target="#tabSabores" type="button" role="tab" aria-controls="tabSabores" aria-selected="true">Sabores</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="presentaciones-tab" data-bs-toggle="tab" data-bs-target="#tabPresentaciones" type="button" role="tab" aria-controls="tabPresentaciones" aria-selected="false">Presentaciones</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="marcas-tab" data-bs-toggle="tab" data-bs-target="#tabMarcas" type="button" role="tab" aria-controls="tabMarcas" aria-selected="false">Marcas</button>
                    </li>
                </ul>

                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tabSabores" role="tabpanel" aria-labelledby="sabores-tab">
                        <form method="post" id="formAgregarSabor" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_sabor">
                            <div class="col-md-7 form-floating">
                                <input type="text" class="form-control" id="nuevoSaborNombre" name="nombre" placeholder="Nombre del sabor" required>
                                <label for="nuevoSaborNombre">Nombre del sabor</label>
                            </div>
                            <div class="col-md-3 form-check form-switch d-flex align-items-center justify-content-center h-100 pt-4">
                                <input class="form-check-input" type="checkbox" id="nuevoSaborEstado" name="estado" value="1" checked>
                                <label class="form-check-label ms-2" for="nuevoSaborEstado">Activo</label>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary">Agregar</button>
                            </div>
                        </form>

                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="buscarSabores" placeholder="Buscar sabor...">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaSaboresGestion">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($saboresGestion as $sabor): ?>
                                        <tr data-search="<?php echo e(mb_strtolower((string) ($sabor['nombre'] ?? ''))); ?>">
                                            <td class="fw-semibold"><?php echo e((string) ($sabor['nombre'] ?? '')); ?></td>
                                            <td>
                                                <div class="form-check form-switch m-0">
                                                    <input
                                                        class="form-check-input js-toggle-atributo"
                                                        type="checkbox"
                                                        data-accion="editar_sabor"
                                                        data-id="<?php echo (int) ($sabor['id'] ?? 0); ?>"
                                                        data-nombre="<?php echo e((string) ($sabor['nombre'] ?? '')); ?>"
                                                        <?php echo (int) ($sabor['estado'] ?? 0) === 1 ? 'checked' : ''; ?>
                                                    >
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary js-editar-atributo"
                                                    data-target="sabor"
                                                    data-id="<?php echo (int) ($sabor['id'] ?? 0); ?>"
                                                    data-nombre="<?php echo e((string) ($sabor['nombre'] ?? '')); ?>"
                                                    data-estado="<?php echo (int) ($sabor['estado'] ?? 1); ?>">
                                                    Editar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger js-eliminar-atributo"
                                                    data-accion="eliminar_sabor"
                                                    data-id="<?php echo (int) ($sabor['id'] ?? 0); ?>">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabPresentaciones" role="tabpanel" aria-labelledby="presentaciones-tab">
                        <form method="post" id="formAgregarPresentacion" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_presentacion">
                            <div class="col-md-7 form-floating">
                                <input type="text" class="form-control" id="nuevaPresentacionNombre" name="nombre" placeholder="Nombre de la presentación" required>
                                <label for="nuevaPresentacionNombre">Nombre de la presentación</label>
                            </div>
                            <div class="col-md-3 form-check form-switch d-flex align-items-center justify-content-center h-100 pt-4">
                                <input class="form-check-input" type="checkbox" id="nuevaPresentacionEstado" name="estado" value="1" checked>
                                <label class="form-check-label ms-2" for="nuevaPresentacionEstado">Activo</label>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary">Agregar</button>
                            </div>
                        </form>

                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="buscarPresentaciones" placeholder="Buscar presentación...">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaPresentacionesGestion">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($presentacionesGestion as $presentacion): ?>
                                        <tr data-search="<?php echo e(mb_strtolower((string) ($presentacion['nombre'] ?? ''))); ?>">
                                            <td class="fw-semibold"><?php echo e((string) ($presentacion['nombre'] ?? '')); ?></td>
                                            <td>
                                                <div class="form-check form-switch m-0">
                                                    <input
                                                        class="form-check-input js-toggle-atributo"
                                                        type="checkbox"
                                                        data-accion="editar_presentacion"
                                                        data-id="<?php echo (int) ($presentacion['id'] ?? 0); ?>"
                                                        data-nombre="<?php echo e((string) ($presentacion['nombre'] ?? '')); ?>"
                                                        <?php echo (int) ($presentacion['estado'] ?? 0) === 1 ? 'checked' : ''; ?>
                                                    >
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary js-editar-atributo"
                                                    data-target="presentacion"
                                                    data-id="<?php echo (int) ($presentacion['id'] ?? 0); ?>"
                                                    data-nombre="<?php echo e((string) ($presentacion['nombre'] ?? '')); ?>"
                                                    data-estado="<?php echo (int) ($presentacion['estado'] ?? 1); ?>">
                                                    Editar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger js-eliminar-atributo"
                                                    data-accion="eliminar_presentacion"
                                                    data-id="<?php echo (int) ($presentacion['id'] ?? 0); ?>">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabMarcas" role="tabpanel" aria-labelledby="marcas-tab">
                        <form method="post" id="formAgregarMarca" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_marca">
                            <div class="col-md-7 form-floating">
                                <input type="text" class="form-control" id="nuevaMarcaNombre" name="nombre" placeholder="Nombre de la marca" required>
                                <label for="nuevaMarcaNombre">Nombre de la marca</label>
                            </div>
                            <div class="col-md-3 form-check form-switch d-flex align-items-center justify-content-center h-100 pt-4">
                                <input class="form-check-input" type="checkbox" id="nuevaMarcaEstado" name="estado" value="1" checked>
                                <label class="form-check-label ms-2" for="nuevaMarcaEstado">Activo</label>
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="submit" class="btn btn-primary">Agregar</button>
                            </div>
                        </form>

                        <div class="input-group input-group-sm mb-2">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="search" class="form-control" id="buscarMarcas" placeholder="Buscar marca...">
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaMarcasGestion">
                                <thead>
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marcasGestion as $marca): ?>
                                        <tr data-search="<?php echo e(mb_strtolower((string) ($marca['nombre'] ?? ''))); ?>">
                                            <td class="fw-semibold"><?php echo e((string) ($marca['nombre'] ?? '')); ?></td>
                                            <td>
                                                <div class="form-check form-switch m-0">
                                                    <input
                                                        class="form-check-input js-toggle-atributo"
                                                        type="checkbox"
                                                        data-accion="editar_marca"
                                                        data-id="<?php echo (int) ($marca['id'] ?? 0); ?>"
                                                        data-nombre="<?php echo e((string) ($marca['nombre'] ?? '')); ?>"
                                                        <?php echo (int) ($marca['estado'] ?? 0) === 1 ? 'checked' : ''; ?>
                                                    >
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <button type="button" class="btn btn-sm btn-outline-primary js-editar-atributo"
                                                    data-target="marca"
                                                    data-id="<?php echo (int) ($marca['id'] ?? 0); ?>"
                                                    data-nombre="<?php echo e((string) ($marca['nombre'] ?? '')); ?>"
                                                    data-estado="<?php echo (int) ($marca['estado'] ?? 1); ?>">
                                                    Editar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-danger js-eliminar-atributo"
                                                    data-accion="eliminar_marca"
                                                    data-id="<?php echo (int) ($marca['id'] ?? 0); ?>">
                                                    Eliminar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalGestionCategorias" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-tags me-2"></i>Administrar categorías</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="formGestionCategoria" class="row g-2 mb-3 border rounded-3 p-3 bg-light">
                    <input type="hidden" name="accion" id="categoriaAccion" value="crear_categoria">
                    <input type="hidden" name="id" id="categoriaId" value="">
                    <div class="col-md-4 form-floating">
                        <input type="text" class="form-control" id="categoriaNombre" name="nombre" placeholder="Nombre" required>
                        <label for="categoriaNombre">Nombre</label>
                    </div>
                    <div class="col-md-5 form-floating">
                        <input type="text" class="form-control" id="categoriaDescripcion" name="descripcion" placeholder="Descripción">
                        <label for="categoriaDescripcion">Descripción</label>
                    </div>
                    <div class="col-md-3 form-floating">
                        <select class="form-select" id="categoriaEstado" name="estado">
                            <option value="1" selected>Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <label for="categoriaEstado">Estado</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-light" id="btnResetCategoria">Limpiar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardarCategoria">Guardar categoría</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Estado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoriasGestion as $categoria): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e((string) $categoria['nombre']); ?></td>
                                    <td><?php echo e((string) ($categoria['descripcion'] ?? '')); ?></td>
                                    <td>
                                        <?php if ((int) ($categoria['estado'] ?? 0) === 1): ?>
                                            <span class="badge bg-success-subtle text-success">Activo</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-editar-categoria"
                                            data-id="<?php echo (int) $categoria['id']; ?>"
                                            data-nombre="<?php echo e((string) $categoria['nombre']); ?>"
                                            data-descripcion="<?php echo e((string) ($categoria['descripcion'] ?? '')); ?>"
                                            data-estado="<?php echo (int) ($categoria['estado'] ?? 1); ?>">
                                            Editar
                                        </button>
                                        <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta categoría?');">
                                            <input type="hidden" name="accion" value="eliminar_categoria">
                                            <input type="hidden" name="id" value="<?php echo (int) $categoria['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarAtributo" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title mb-0" id="tituloEditarAtributo">Editar</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="formEditarAtributo" class="modal-body">
                <input type="hidden" name="accion" id="editarAtributoAccion" value="">
                <input type="hidden" name="id" id="editarAtributoId" value="">
                <div class="mb-3">
                    <label for="editarAtributoNombre" class="form-label">Nombre</label>
                    <input type="text" class="form-control" id="editarAtributoNombre" name="nombre" required>
                </div>
                <div class="form-check form-switch mb-3">
                    <input class="form-check-input" type="checkbox" id="editarAtributoEstado" name="estado" value="1" checked>
                    <label class="form-check-label" for="editarAtributoEstado">Activo</label>
                </div>
                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalCrearItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar ítem</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" class="row g-3" id="formCrearItem">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-3"><div class="form-floating"><input type="text" class="form-control" id="newSku" name="sku" placeholder="SKU"><label for="newSku">SKU (opcional)</label></div></div>
                    <div class="col-md-5"><div class="form-floating"><input type="text" class="form-control" id="newNombre" name="nombre" placeholder="Nombre" required><label for="newNombre">Nombre</label></div></div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="newTipo" name="tipo_item" required>
                            <option value="" selected disabled>Seleccionar...</option>
                            <option value="producto">Producto Terminado</option>
                            <option value="materia_prima">Materia Prima</option>
                            <option value="material_empaque">Material de Empaque</option>
                            <option value="servicio">Servicios / Otros</option>
                        </select>
                        <label for="newTipo">Tipo de ítem</label>
                    </div>
                    <div class="col-md-6 form-floating" id="newMarcaContainer">
                        <select class="form-select" id="newMarca" name="id_marca">
                            <option value="" selected>Seleccionar marca...</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?php echo e((string) $marca['nombre']); ?>"><?php echo e((string) $marca['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="newMarca">Marca</label>
                    </div>
                    <div class="col-md-3 form-floating">
                        <select class="form-select" id="newUnidad" name="unidad_base">
                            <option value="UND" selected>UND</option>
                            <option value="KG">KG</option>
                            <option value="LT">LT</option>
                            <option value="M">M</option>
                            <option value="CAJA">CAJA</option>
                            <option value="PAQ">PAQ (Paquete)</option>
                        </select>
                        <label for="newUnidad">Unidad base</label>
                    </div>
                    <div class="col-md-3 form-floating">
                        <select class="form-select" id="newMoneda" name="moneda">
                            <option value="PEN" selected>PEN (Soles)</option>
                            <option value="USD">USD (Dólares)</option>
                        </select>
                        <label for="newMoneda">Moneda</label>
                    </div>
                    <div class="col-md-6"><div class="form-floating"><input type="number" step="0.0001" class="form-control" id="newPrecio" name="precio_venta" value="0.0000"><label for="newPrecio">Precio venta</label></div></div>
                    <div class="col-md-6"><div class="form-floating"><input type="number" step="0.0001" class="form-control" id="newCosto" name="costo_referencial" value="0.0000"><label for="newCosto">Costo referencial</label></div></div>
                    <div class="col-md-6"><div class="form-floating"><input type="number" step="0.0001" class="form-control" id="newImpuesto" name="impuesto" value="18.00"><label for="newImpuesto">Impuesto (%)</label></div></div>
                    <div class="col-md-12"><div class="form-floating"><input type="text" class="form-control" id="newDescripcion" name="descripcion" placeholder="Descripción"><label for="newDescripcion">Descripción</label></div></div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="newCategoria" name="id_categoria">
                            <option value="" selected>Seleccionar categoría...</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="newCategoria">Categoría</label>
                    </div>
                    <div class="col-md-4 form-floating" id="newSaborContainer">
                        <select class="form-select" id="newSabor" name="id_sabor">
                            <option value="" selected>Seleccionar sabor...</option>
                            <?php foreach ($sabores as $sabor): ?>
                                <option value="<?php echo (int) $sabor['id']; ?>"><?php echo e((string) $sabor['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="newSabor">Sabor</label>
                    </div>
                    <div class="col-md-4 form-floating" id="newPresentacionContainer">
                        <select class="form-select" id="newPresentacion" name="id_presentacion">
                            <option value="" selected>Seleccionar presentación...</option>
                            <?php foreach ($presentaciones as $presentacion): ?>
                                <option value="<?php echo (int) $presentacion['id']; ?>"><?php echo e((string) $presentacion['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="newPresentacion">Presentación</label>
                    </div>
                    <div class="col-md-4 d-flex align-items-center"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="newControlaStock" name="controla_stock" value="1"><label class="form-check-label ms-2" for="newControlaStock">Controla stock</label></div></div>
                    <div class="col-md-4 form-floating d-none" id="newStockMinContainer"><input type="number" step="0.0001" class="form-control" id="newStockMin" name="stock_minimo" value="0.0000" disabled><label for="newStockMin">Stock mín.</label></div>
                    <div class="col-md-4 d-flex align-items-center" id="newPermiteDecimalesContainer"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="newPermiteDecimales" name="permite_decimales" value="1"><label class="form-check-label ms-2" for="newPermiteDecimales">Permite decimales</label></div></div>
                    <div class="col-md-4 d-flex align-items-center" id="newRequiereLoteContainer"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="newRequiereLote" name="requiere_lote" value="1"><label class="form-check-label ms-2" for="newRequiereLote">Requiere lote</label></div></div>
                    <div class="col-md-4 d-flex align-items-center" id="newRequiereVencimientoContainer"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="newRequiereVencimiento" name="requiere_vencimiento" value="1"><label class="form-check-label ms-2" for="newRequiereVencimiento">Requiere vencimiento</label></div></div>
                    <div class="col-md-4 form-floating d-none" id="newDiasAlertaContainer">
                        <input type="number" min="0" class="form-control" id="newDiasAlerta" name="dias_alerta_vencimiento" value="30">
                        <label for="newDiasAlerta">Días de alerta</label>
                    </div>
                    <input type="hidden" id="newEstado" name="estado" value="1">
                    <div class="col-12 d-flex justify-content-end pt-3">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4" type="submit"><i class="bi bi-save me-2"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarItem" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Editar ítem</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" id="formEditarItem" class="row g-3">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editId">
                    <div class="col-md-4 form-floating"><input class="form-control" id="editSku" name="sku" readonly><label for="editSku">SKU (inmutable)</label></div>
                    <div class="col-md-8 form-floating"><input class="form-control" id="editNombre" name="nombre" required><label for="editNombre">Nombre</label></div>
                    <div class="col-md-12 form-floating"><input class="form-control" id="editDescripcion" name="descripcion"><label for="editDescripcion">Descripción</label></div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editTipo" name="tipo_item" required>
                            <option value="producto">Producto</option>
                            <option value="materia_prima">Materia prima</option>
                            <option value="material_empaque">Material de empaque</option>
                            <option value="servicio">Servicio</option>
                        </select>
                        <label for="editTipo">Tipo</label>
                    </div>
                    <div class="col-md-4 form-floating" id="editMarcaContainer">
                        <select class="form-select" id="editMarca" name="id_marca">
                            <option value="">Seleccionar marca...</option>
                            <?php foreach ($marcas as $marca): ?>
                                <option value="<?php echo e((string) $marca['nombre']); ?>"><?php echo e((string) $marca['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="editMarca">Marca</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editUnidad" name="unidad_base">
                            <option value="UND">UND</option>
                            <option value="KG">KG</option>
                            <option value="LT">LT</option>
                            <option value="M">M</option>
                            <option value="CAJA">CAJA</option>
                            <option value="PAQ">PAQ (Paquete)</option>
                        </select>
                        <label for="editUnidad">Unidad base</label>
                    </div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editMoneda" name="moneda">
                            <option value="PEN">PEN (Soles)</option>
                            <option value="USD">USD (Dólares)</option>
                        </select>
                        <label for="editMoneda">Moneda</label>
                    </div>
                    <div class="col-md-4 form-floating"><input class="form-control" id="editImpuesto" name="impuesto" type="number" step="0.0001" value="18.00"><label for="editImpuesto">Impuesto (%)</label></div>
                    <div class="col-md-4 form-floating"><input class="form-control" id="editPrecio" name="precio_venta" type="number" step="0.0001"><label for="editPrecio">Precio</label></div>
                    <div class="col-md-4 form-floating"><input class="form-control" id="editCosto" name="costo_referencial" type="number" step="0.0001"><label for="editCosto">Costo referencial</label></div>
                    <div class="col-md-4 form-floating">
                        <select class="form-select" id="editCategoria" name="id_categoria">
                            <option value="">Seleccionar categoría...</option>
                            <?php foreach ($categorias as $categoria): ?>
                                <option value="<?php echo (int) $categoria['id']; ?>"><?php echo e((string) $categoria['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="editCategoria">Categoría</label>
                    </div>
                    <div class="col-md-4 form-floating" id="editSaborContainer">
                        <select class="form-select" id="editSabor" name="id_sabor">
                            <option value="">Seleccionar sabor...</option>
                            <?php foreach ($sabores as $sabor): ?>
                                <option value="<?php echo (int) $sabor['id']; ?>"><?php echo e((string) $sabor['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="editSabor">Sabor</label>
                    </div>
                    <div class="col-md-4 form-floating" id="editPresentacionContainer">
                        <select class="form-select" id="editPresentacion" name="id_presentacion">
                            <option value="">Seleccionar presentación...</option>
                            <?php foreach ($presentaciones as $presentacion): ?>
                                <option value="<?php echo (int) $presentacion['id']; ?>"><?php echo e((string) $presentacion['nombre']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label for="editPresentacion">Presentación</label>
                    </div>

                    <div class="col-md-4 d-flex align-items-center"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="editControlaStock" name="controla_stock" value="1"><label class="form-check-label ms-2" for="editControlaStock">Controla stock</label></div></div>
                    <div class="col-md-4 form-floating d-none" id="editStockMinimoContainer"><input class="form-control" id="editStockMinimo" name="stock_minimo" type="number" step="0.0001" disabled><label for="editStockMinimo">Stock mín.</label></div>
                    <div class="col-md-4 d-flex align-items-center" id="editPermiteDecimalesContainer"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="editPermiteDecimales" name="permite_decimales" value="1"><label class="form-check-label ms-2" for="editPermiteDecimales">Permite decimales</label></div></div>
                    <div class="col-md-4 d-flex align-items-center" id="editRequiereLoteContainer"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="editRequiereLote" name="requiere_lote" value="1"><label class="form-check-label ms-2" for="editRequiereLote">Requiere lote</label></div></div>
                    <div class="col-md-4 d-flex align-items-center" id="editRequiereVencimientoContainer"><div class="form-check form-switch ps-5"><input class="form-check-input" type="checkbox" id="editRequiereVencimiento" name="requiere_vencimiento" value="1"><label class="form-check-label ms-2" for="editRequiereVencimiento">Requiere vencimiento</label></div></div>
                    <div class="col-md-4 form-floating d-none" id="editDiasAlertaContainer">
                        <input type="number" min="0" class="form-control" id="editDiasAlerta" name="dias_alerta_vencimiento" value="30">
                        <label for="editDiasAlerta">Días de alerta</label>
                    </div>

                    <div class="col-md-4 form-floating"><select class="form-select" id="editEstado" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select><label for="editEstado">Estado</label></div>
                    <div class="col-12 d-flex justify-content-end pt-3"><button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary px-4" type="submit">Actualizar</button></div>
                </form>
            </div>
        </div>
    </div>
</div>
