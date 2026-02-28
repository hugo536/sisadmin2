<div class="modal fade" id="modalGestionItems" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-sliders me-2"></i>Configuración de ítems</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item"><button class="nav-link active" id="sabores-tab" data-bs-toggle="tab" data-bs-target="#tabSabores" type="button">Sabores</button></li>
                    <li class="nav-item"><button class="nav-link" id="presentaciones-tab" data-bs-toggle="tab" data-bs-target="#tabPresentaciones" type="button">Presentaciones</button></li>
                    <li class="nav-item"><button class="nav-link" id="marcas-tab" data-bs-toggle="tab" data-bs-target="#tabMarcas" type="button">Marcas</button></li>
                </ul>
                <div class="tab-content pt-3">
                    <div class="tab-pane fade show active" id="tabSabores" role="tabpanel">
                        <form method="post" id="formAgregarSabor" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_sabor">
                            <div class="col-md-7 form-floating"><input type="text" class="form-control" id="nuevoSaborNombre" name="nombre" required><label>Nombre del sabor</label></div>
                            <div class="col-md-3 form-check form-switch pt-4"><input class="form-check-input" type="checkbox" id="nuevoSaborEstado" name="estado" value="1" checked><label class="ms-2">Activo</label></div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Agregar</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 table-pro" id="tablaSaboresGestion">
                                <thead><tr><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($saboresGestion as $sabor): ?>
                                        <?php $nombreSabor = (string)($sabor['nombre'] ?? ''); $esSistema = ($nombreSabor === 'Ninguno'); ?>
                                        <tr class="<?php echo $esSistema ? 'bg-light' : ''; ?>">
                                            <td class="fw-semibold"><?php echo e($nombreSabor); ?><?php if($esSistema): ?><i class="bi bi-shield-lock-fill text-muted ms-2"></i><?php endif; ?></td>
                                            <td><div class="form-check form-switch m-0"><input class="form-check-input <?php echo $esSistema?'':'js-toggle-atributo'; ?>" type="checkbox" <?php if(!$esSistema): ?>data-accion="editar_sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" data-nombre="<?php echo e($nombreSabor); ?>"<?php endif; ?> <?php echo (int)($sabor['estado']??0)===1?'checked':''; ?> <?php echo $esSistema?'disabled':''; ?>></div></td>
                                            <td class="text-end">
                                                <?php if($esSistema): ?><span class="badge bg-secondary">Protegido</span><?php else: ?>
                                                <button class="btn btn-sm btn-outline-primary js-editar-atributo" data-target="sabor" data-id="<?php echo (int)($sabor['id']??0); ?>" data-nombre="<?php echo e($nombreSabor); ?>" data-estado="<?php echo (int)($sabor['estado']??1); ?>">Editar</button>
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-atributo" data-accion="eliminar_sabor" data-id="<?php echo (int)($sabor['id']??0); ?>">Eliminar</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabPresentaciones" role="tabpanel">
                        <form method="post" id="formAgregarPresentacion" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_presentacion">
                            <div class="col-md-7 form-floating"><input type="text" class="form-control" id="nuevaPresentacionNombre" name="nombre" required><label>Nombre</label></div>
                            <div class="col-md-3 form-check form-switch pt-4"><input class="form-check-input" type="checkbox" id="nuevaPresentacionEstado" name="estado" value="1" checked><label class="ms-2">Activo</label></div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Agregar</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 table-pro" id="tablaPresentacionesGestion">
                                <thead><tr><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($presentacionesGestion as $presentacion): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e((string)($presentacion['nombre'] ?? '')); ?></td>
                                            <td><div class="form-check form-switch m-0"><input class="form-check-input js-toggle-atributo" type="checkbox" data-accion="editar_presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" data-nombre="<?php echo e((string)($presentacion['nombre']??'')); ?>" <?php echo (int)($presentacion['estado']??0)===1?'checked':''; ?>></div></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary js-editar-atributo" data-target="presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>" data-nombre="<?php echo e((string)($presentacion['nombre']??'')); ?>" data-estado="<?php echo (int)($presentacion['estado']??1); ?>">Editar</button>
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-atributo" data-accion="eliminar_presentacion" data-id="<?php echo (int)($presentacion['id']??0); ?>">Eliminar</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tabMarcas" role="tabpanel">
                        <form method="post" id="formAgregarMarca" class="row g-2 align-items-end mb-3 border rounded-3 p-3 bg-light">
                            <input type="hidden" name="accion" value="crear_marca">
                            <div class="col-md-7 form-floating"><input type="text" class="form-control" id="nuevaMarcaNombre" name="nombre" required><label>Nombre</label></div>
                            <div class="col-md-3 form-check form-switch pt-4"><input class="form-check-input" type="checkbox" id="nuevaMarcaEstado" name="estado" value="1" checked><label class="ms-2">Activo</label></div>
                            <div class="col-md-2 d-grid"><button type="submit" class="btn btn-primary">Agregar</button></div>
                        </form>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 table-pro" id="tablaMarcasGestion">
                                <thead><tr><th>Nombre</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                                <tbody>
                                    <?php foreach ($marcasGestion as $marca): ?>
                                        <tr>
                                            <td class="fw-semibold"><?php echo e((string)($marca['nombre'] ?? '')); ?></td>
                                            <td><div class="form-check form-switch m-0"><input class="form-check-input js-toggle-atributo" type="checkbox" data-accion="editar_marca" data-id="<?php echo (int)($marca['id']??0); ?>" data-nombre="<?php echo e((string)($marca['nombre']??'')); ?>" <?php echo (int)($marca['estado']??0)===1?'checked':''; ?>></div></td>
                                            <td class="text-end">
                                                <button class="btn btn-sm btn-outline-primary js-editar-atributo" data-target="marca" data-id="<?php echo (int)($marca['id']??0); ?>" data-nombre="<?php echo e((string)($marca['nombre']??'')); ?>" data-estado="<?php echo (int)($marca['estado']??1); ?>">Editar</button>
                                                <button class="btn btn-sm btn-outline-danger js-eliminar-atributo" data-accion="eliminar_marca" data-id="<?php echo (int)($marca['id']??0); ?>">Eliminar</button>
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
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-secondary text-white py-2">
                <h6 class="modal-title mb-0"><i class="bi bi-diagram-3 me-2"></i>Administrar rubros</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form method="post" id="formGestionRubro" class="row g-2 mb-3 border rounded-3 p-3 bg-light">
                    <input type="hidden" name="accion" id="rubroAccion" value="crear_rubro">
                    <input type="hidden" name="id" id="rubroId" value="">
                    <div class="col-md-4 form-floating"><input type="text" class="form-control" id="rubroNombre" name="nombre" required><label>Nombre</label></div>
                    <div class="col-md-5 form-floating"><input type="text" class="form-control" id="rubroDescripcion" name="descripcion"><label>Descripción</label></div>
                    <div class="col-md-3 form-floating"><select class="form-select" id="rubroEstado" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select><label>Estado</label></div>
                    <div class="col-12 d-flex justify-content-end gap-2"><button type="button" class="btn btn-light" id="btnResetRubro">Limpiar</button><button type="submit" class="btn btn-primary" id="btnGuardarRubro">Guardar</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-pro">
                        <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($rubrosGestion as $rubro): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e((string)$rubro['nombre']); ?></td>
                                    <td><?php echo e((string)($rubro['descripcion'] ?? '')); ?></td>
                                    <td><?php if((int)($rubro['estado']??0)===1): ?><span class="badge bg-success-subtle text-success">Activo</span><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Inactivo</span><?php endif; ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary btn-editar-rubro" data-id="<?php echo (int)$rubro['id']; ?>" data-nombre="<?php echo e((string)$rubro['nombre']); ?>" data-descripcion="<?php echo e((string)($rubro['descripcion']??'')); ?>" data-estado="<?php echo (int)($rubro['estado']??1); ?>">Editar</button>
                                        <form method="post" class="d-inline js-swal-confirm"><input type="hidden" name="accion" value="eliminar_rubro"><input type="hidden" name="id" value="<?php echo (int)$rubro['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button></form>
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

<div class="modal fade" id="modalGestionCategorias" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
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
                    <div class="col-md-4 form-floating"><input type="text" class="form-control" id="categoriaNombre" name="nombre" required><label>Nombre</label></div>
                    <div class="col-md-5 form-floating"><input type="text" class="form-control" id="categoriaDescripcion" name="descripcion"><label>Descripción</label></div>
                    <div class="col-md-3 form-floating"><select class="form-select" id="categoriaEstado" name="estado"><option value="1">Activo</option><option value="0">Inactivo</option></select><label>Estado</label></div>
                    <div class="col-12 d-flex justify-content-end gap-2"><button type="button" class="btn btn-light" id="btnResetCategoria">Limpiar</button><button type="submit" class="btn btn-primary" id="btnGuardarCategoria">Guardar</button></div>
                </form>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0 table-pro">
                        <thead><tr><th>Nombre</th><th>Descripción</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
                        <tbody>
                            <?php foreach ($categoriasGestion as $categoria): ?>
                                <tr>
                                    <td class="fw-semibold"><?php echo e((string)$categoria['nombre']); ?></td>
                                    <td><?php echo e((string)($categoria['descripcion'] ?? '')); ?></td>
                                    <td><?php if((int)($categoria['estado']??0)===1): ?><span class="badge bg-success-subtle text-success">Activo</span><?php else: ?><span class="badge bg-secondary-subtle text-secondary">Inactivo</span><?php endif; ?></td>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-primary btn-editar-categoria" data-id="<?php echo (int)$categoria['id']; ?>" data-nombre="<?php echo e((string)$categoria['nombre']); ?>" data-descripcion="<?php echo e((string)($categoria['descripcion']??'')); ?>" data-estado="<?php echo (int)($categoria['estado']??1); ?>">Editar</button>
                                        <form method="post" class="d-inline js-swal-confirm"><input type="hidden" name="accion" value="eliminar_categoria"><input type="hidden" name="id" value="<?php echo (int)$categoria['id']; ?>"><button type="submit" class="btn btn-sm btn-outline-danger">Eliminar</button></form>
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

<div class="modal fade" id="modalEditarAtributo" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h6 class="modal-title mb-0" id="tituloEditarAtributo">Editar</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="formEditarAtributo" class="modal-body">
                <input type="hidden" name="accion" id="editarAtributoAccion" value="">
                <input type="hidden" name="id" id="editarAtributoId" value="">
                <div class="mb-3"><label class="form-label">Nombre</label><input type="text" class="form-control" id="editarAtributoNombre" name="nombre" required></div>
                <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" id="editarAtributoEstado" name="estado" value="1" checked><label class="form-check-label">Activo</label></div>
                <div class="d-flex justify-content-end gap-2"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary">Guardar cambios</button></div>
            </form>
        </div>
    </div>
</div>
