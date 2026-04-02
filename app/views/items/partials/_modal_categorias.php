<div class="modal fade" id="modalGestionItems" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title mb-0"><i class="bi bi-sliders me-2"></i>Configuración de ítems</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light">
                <ul class="nav nav-tabs border-bottom-0 mb-3" role="tablist">
                    <li class="nav-item"><button class="nav-link active fw-semibold" id="sabores-tab" data-bs-toggle="tab" data-bs-target="#tabSabores" type="button">Sabores</button></li>
                    <li class="nav-item"><button class="nav-link fw-semibold" id="presentaciones-tab" data-bs-toggle="tab" data-bs-target="#tabPresentaciones" type="button">Presentaciones</button></li>
                    <li class="nav-item"><button class="nav-link fw-semibold" id="marcas-tab" data-bs-toggle="tab" data-bs-target="#tabMarcas" type="button">Marcas</button></li>
                </ul>
                <div class="tab-content">
                    
                    <div class="tab-pane fade show active" id="tabSabores" role="tabpanel">
                        <div class="modal-pastel-card mb-3 bg-white p-3">
                            <form method="post" id="formAgregarSabor" class="row g-2 align-items-end m-0">
                                <input type="hidden" name="accion" value="crear_sabor">
                                <div class="col-md-7 form-floating">
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="nuevoSaborNombre" name="nombre" required placeholder="Nombre">
                                    <label>Nombre del sabor</label>
                                </div>
                                <div class="col-md-3 form-check form-switch pt-4">
                                    <input class="form-check-input" type="checkbox" id="nuevoSaborEstado" name="estado" value="1" checked>
                                    <label class="ms-2 fw-medium text-muted">Activo</label>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm">Agregar</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="modal-pastel-card bg-white overflow-hidden">
                            <table class="table table-sm table-pro table-pastel align-middle mb-0" id="tablaSaboresGestion">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3">Nombre</th>
                                        <th class="text-center py-3">Estado</th>
                                        <th class="text-end pe-4 py-3 col-w-100">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($saboresGestion as $sabor): ?>
                                        <?php $nombreSabor = (string)($sabor['nombre'] ?? ''); $esSistema = ($nombreSabor === 'Ninguno'); ?>
                                        <tr class="<?php echo $esSistema ? 'table-protected' : ''; ?>">
                                            <td class="ps-4 py-2 fw-medium <?php echo $esSistema ? 'text-secondary' : 'text-dark'; ?>" style="font-size: 0.9rem;">
                                                <?php echo e($nombreSabor); ?><?php if($esSistema): ?><i class="bi bi-shield-lock text-muted ms-2" title="Protegido por el sistema"></i><?php endif; ?>
                                            </td>
                                            <td class="text-center py-2">
                                                <div class="form-check form-switch d-inline-block m-0">
                                                    <input class="form-check-input <?php echo $esSistema?'':'js-toggle-atributo'; ?>" type="checkbox" <?php if(!$esSistema): ?>data-accion="editar_sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" data-nombre="<?php echo e($nombreSabor); ?>"<?php endif; ?> <?php echo (int)($sabor['estado']??0)===1?'checked':''; ?> <?php echo $esSistema?'disabled':''; ?>>
                                                </div>
                                            </td>
                                            <td class="text-end pe-4 py-2">
                                                <?php if($esSistema): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle rounded-pill" style="font-size: 0.7rem;">Protegido</span>
                                                <?php else: ?>
                                                    <div class="d-inline-flex gap-1">
                                                        <button type="button" class="btn-icon btn-icon-primary js-editar-atributo" data-target="sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" data-nombre="<?php echo e($nombreSabor); ?>" data-estado="<?php echo (int)($sabor['estado']??1); ?>" title="Editar">
                                                            <i class="bi bi-pencil-square"></i>
                                                        </button>
                                                        <button type="button" class="btn-icon btn-icon-danger js-eliminar-atributo" data-accion="eliminar_sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" title="Eliminar">
                                                            <i class="bi bi-trash3"></i>
                                                        </button>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabPresentaciones" role="tabpanel">
                        <div class="modal-pastel-card mb-3 bg-white p-3">
                            <form method="post" id="formAgregarPresentacion" class="row g-2 align-items-end m-0">
                                <input type="hidden" name="accion" value="crear_presentacion">
                                <div class="col-md-7 form-floating">
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="nuevaPresentacionNombre" name="nombre" required placeholder="Nombre">
                                    <label>Nombre de la presentación</label>
                                </div>
                                <div class="col-md-3 form-check form-switch pt-4">
                                    <input class="form-check-input" type="checkbox" id="nuevaPresentacionEstado" name="estado" value="1" checked>
                                    <label class="ms-2 fw-medium text-muted">Activo</label>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm">Agregar</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="modal-pastel-card bg-white overflow-hidden">
                            <table class="table table-sm table-pro table-pastel align-middle mb-0" id="tablaPresentacionesGestion">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3">Nombre</th>
                                        <th class="text-center py-3">Estado</th>
                                        <th class="text-end pe-4 py-3 col-w-100">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($presentacionesGestion as $presentacion): ?>
                                        <tr>
                                            <td class="ps-4 py-2 fw-medium text-dark" style="font-size: 0.9rem;"><?php echo e((string)($presentacion['nombre'] ?? '')); ?></td>
                                            <td class="text-center py-2">
                                                <div class="form-check form-switch d-inline-block m-0">
                                                    <input class="form-check-input js-toggle-atributo" type="checkbox" data-accion="editar_presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" data-nombre="<?php echo e((string)($presentacion['nombre']??'')); ?>" <?php echo (int)($presentacion['estado']??0)===1?'checked':''; ?>>
                                                </div>
                                            </td>
                                            <td class="text-end pe-4 py-2">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="btn-icon btn-icon-primary js-editar-atributo" data-target="presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" data-nombre="<?php echo e((string)($presentacion['nombre']??'')); ?>" data-estado="<?php echo (int)($presentacion['estado']??1); ?>" title="Editar">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon btn-icon-danger js-eliminar-atributo" data-accion="eliminar_presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" title="Eliminar">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tabMarcas" role="tabpanel">
                        <div class="modal-pastel-card mb-3 bg-white p-3">
                            <form method="post" id="formAgregarMarca" class="row g-2 align-items-end m-0">
                                <input type="hidden" name="accion" value="crear_marca">
                                <div class="col-md-7 form-floating">
                                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="nuevaMarcaNombre" name="nombre" required placeholder="Nombre">
                                    <label>Nombre de la marca</label>
                                </div>
                                <div class="col-md-3 form-check form-switch pt-4">
                                    <input class="form-check-input" type="checkbox" id="nuevaMarcaEstado" name="estado" value="1" checked>
                                    <label class="ms-2 fw-medium text-muted">Activo</label>
                                </div>
                                <div class="col-md-2 d-grid">
                                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm">Agregar</button>
                                </div>
                            </form>
                        </div>
                        
                        <div class="modal-pastel-card bg-white overflow-hidden">
                            <table class="table table-sm table-pro table-pastel align-middle mb-0" id="tablaMarcasGestion">
                                <thead>
                                    <tr>
                                        <th class="ps-4 py-3">Nombre</th>
                                        <th class="text-center py-3">Estado</th>
                                        <th class="text-end pe-4 py-3 col-w-100">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($marcasGestion as $marca): ?>
                                        <tr>
                                            <td class="ps-4 py-2 fw-medium text-dark" style="font-size: 0.9rem;"><?php echo e((string)($marca['nombre'] ?? '')); ?></td>
                                            <td class="text-center py-2">
                                                <div class="form-check form-switch d-inline-block m-0">
                                                    <input class="form-check-input js-toggle-atributo" type="checkbox" data-accion="editar_marca" data-id="<?php echo (int)($marca['id']??0); ?>" data-nombre="<?php echo e((string)($marca['nombre']??'')); ?>" <?php echo (int)($marca['estado']??0)===1?'checked':''; ?>>
                                                </div>
                                            </td>
                                            <td class="text-end pe-4 py-2">
                                                <div class="d-inline-flex gap-1">
                                                    <button type="button" class="btn-icon btn-icon-primary js-editar-atributo" data-target="marca" data-id="<?php echo (int)($marca['id']??0); ?>" data-nombre="<?php echo e((string)($marca['nombre']??'')); ?>" data-estado="<?php echo (int)($marca['estado']??1); ?>" title="Editar">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                    <button type="button" class="btn-icon btn-icon-danger js-eliminar-atributo" data-accion="eliminar_marca" data-id="<?php echo (int)($marca['id']??0); ?>" title="Eliminar">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
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

<div class="modal fade" id="modalGestionRubros" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title mb-0"><i class="bi bi-diagram-3 me-2"></i>Administrar rubros</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light">
                <div class="modal-pastel-card mb-3 bg-white p-3">
                    <form method="post" id="formGestionRubro" class="row g-2 align-items-end m-0">
                        <input type="hidden" name="accion" id="rubroAccion" value="crear_rubro">
                        <input type="hidden" name="id" id="rubroId" value="">
                        <div class="col-md-4 form-floating">
                            <input type="text" class="form-control shadow-none border-secondary-subtle" id="rubroNombre" name="nombre" required placeholder="Nombre">
                            <label>Nombre del rubro</label>
                        </div>
                        <div class="col-md-5 form-floating">
                            <input type="text" class="form-control shadow-none border-secondary-subtle" id="rubroDescripcion" name="descripcion" placeholder="Descripción">
                            <label>Descripción (Opcional)</label>
                        </div>
                        <div class="col-md-3 form-floating">
                            <select class="form-select shadow-none border-secondary-subtle" id="rubroEstado" name="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                            <label>Estado</label>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light border fw-medium text-secondary" id="btnResetRubro">Limpiar</button>
                            <button type="submit" class="btn btn-primary fw-semibold shadow-sm" id="btnGuardarRubro">Guardar Rubro</button>
                        </div>
                    </form>
                </div>
                
                <div class="modal-pastel-card bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-sm table-pro table-pastel align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Nombre</th>
                                    <th class="py-3">Descripción</th>
                                    <th class="text-center py-3">Estado</th>
                                    <th class="text-end pe-4 py-3 col-w-100">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rubrosGestion as $rubro): ?>
                                    <tr>
                                        <td class="ps-4 py-2 fw-medium text-dark" style="font-size: 0.9rem;"><?php echo e((string)$rubro['nombre']); ?></td>
                                        <td class="py-2 text-muted small"><?php echo e((string)($rubro['descripcion'] ?? '')); ?></td>
                                        <td class="text-center py-2">
                                            <?php if((int)($rubro['estado']??0)===1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 py-2">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn-icon btn-icon-primary btn-editar-rubro" data-id="<?php echo (int)$rubro['id']; ?>" data-nombre="<?php echo e((string)$rubro['nombre']); ?>" data-descripcion="<?php echo e((string)($rubro['descripcion']??'')); ?>" data-estado="<?php echo (int)($rubro['estado']??1); ?>" title="Editar">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <form method="post" class="d-inline js-swal-confirm m-0 p-0">
                                                    <input type="hidden" name="accion" value="eliminar_rubro">
                                                    <input type="hidden" name="id" value="<?php echo (int)$rubro['id']; ?>">
                                                    <button type="submit" class="btn-icon btn-icon-danger" title="Eliminar">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </div>
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

<div class="modal fade" id="modalGestionCategorias" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-3">
                <h6 class="modal-title mb-0"><i class="bi bi-tags me-2"></i>Administrar categorías</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-3 p-md-4 bg-light">
                <div class="modal-pastel-card mb-3 bg-white p-3">
                    <form method="post" id="formGestionCategoria" class="row g-2 align-items-end m-0">
                        <input type="hidden" name="accion" id="categoriaAccion" value="crear_categoria">
                        <input type="hidden" name="id" id="categoriaId" value="">
                        <div class="col-md-4 form-floating">
                            <input type="text" class="form-control shadow-none border-secondary-subtle" id="categoriaNombre" name="nombre" required placeholder="Nombre">
                            <label>Nombre de la categoría</label>
                        </div>
                        <div class="col-md-5 form-floating">
                            <input type="text" class="form-control shadow-none border-secondary-subtle" id="categoriaDescripcion" name="descripcion" placeholder="Descripción">
                            <label>Descripción (Opcional)</label>
                        </div>
                        <div class="col-md-3 form-floating">
                            <select class="form-select shadow-none border-secondary-subtle" id="categoriaEstado" name="estado">
                                <option value="1">Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                            <label>Estado</label>
                        </div>
                        <div class="col-12 d-flex justify-content-end gap-2 mt-3">
                            <button type="button" class="btn btn-light border fw-medium text-secondary" id="btnResetCategoria">Limpiar</button>
                            <button type="submit" class="btn btn-primary fw-semibold shadow-sm" id="btnGuardarCategoria">Guardar Categoría</button>
                        </div>
                    </form>
                </div>

                <div class="modal-pastel-card bg-white overflow-hidden">
                    <div class="table-responsive">
                        <table class="table table-sm table-pro table-pastel align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 py-3">Nombre</th>
                                    <th class="py-3">Descripción</th>
                                    <th class="text-center py-3">Estado</th>
                                    <th class="text-end pe-4 py-3 col-w-100">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categoriasGestion as $categoria): ?>
                                    <tr>
                                        <td class="ps-4 py-2 fw-medium text-dark" style="font-size: 0.9rem;"><?php echo e((string)$categoria['nombre']); ?></td>
                                        <td class="py-2 text-muted small"><?php echo e((string)($categoria['descripcion'] ?? '')); ?></td>
                                        <td class="text-center py-2">
                                            <?php if((int)($categoria['estado']??0)===1): ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Activo</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end pe-4 py-2">
                                            <div class="d-inline-flex gap-1">
                                                <button type="button" class="btn-icon btn-icon-primary btn-editar-categoria" data-id="<?php echo (int)$categoria['id']; ?>" data-nombre="<?php echo e((string)$categoria['nombre']); ?>" data-descripcion="<?php echo e((string)($categoria['descripcion']??'')); ?>" data-estado="<?php echo (int)($categoria['estado']??1); ?>" title="Editar">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <form method="post" class="d-inline js-swal-confirm m-0 p-0">
                                                    <input type="hidden" name="accion" value="eliminar_categoria">
                                                    <input type="hidden" name="id" value="<?php echo (int)$categoria['id']; ?>">
                                                    <button type="submit" class="btn-icon btn-icon-danger" title="Eliminar">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </form>
                                            </div>
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

<div class="modal fade" id="modalEditarAtributo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-light py-3 border-bottom">
                <h6 class="modal-title mb-0 fw-bold text-dark" id="tituloEditarAtributo"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Atributo</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" id="formEditarAtributo" class="modal-body p-4">
                <input type="hidden" name="accion" id="editarAtributoAccion" value="">
                <input type="hidden" name="id" id="editarAtributoId" value="">
                
                <div class="form-floating mb-3">
                    <input type="text" class="form-control shadow-none border-secondary-subtle" id="editarAtributoNombre" name="nombre" required placeholder="Nombre">
                    <label>Nombre</label>
                </div>
                
                <div class="form-check form-switch mb-4 px-3 py-2 bg-light border border-secondary-subtle rounded-3 d-flex justify-content-between align-items-center">
                    <label class="form-check-label fw-medium text-dark m-0" for="editarAtributoEstado">Estado del atributo</label>
                    <input class="form-check-input m-0 fs-5" type="checkbox" id="editarAtributoEstado" name="estado" value="1" checked>
                </div>
                
                <div class="d-flex justify-content-end gap-2 mt-2">
                    <button type="button" class="btn btn-light border fw-medium text-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-semibold shadow-sm">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>