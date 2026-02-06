<?php
$roles = $roles ?? [];
$permisos = $permisos ?? [];
$permisosPorModulo = [];
foreach ($permisos as $permiso) {
    $modulo = (string) ($permiso['modulo'] ?? 'General');
    $permisosPorModulo[$modulo][] = $permiso;
}
?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-shield-lock me-2 text-primary"></i> Roles
            </h1>
            <p class="text-muted small mb-0 ms-1">Administración de roles y asignación granular de permisos.</p>
        </div>
        <button class="btn btn-primary shadow-sm" type="button" data-bs-toggle="collapse" data-bs-target="#crearRolCollapse">
            <i class="bi bi-plus-circle me-2"></i>Nuevo Rol
        </button>
    </div>

    <div class="collapse mb-4" id="crearRolCollapse">
        <div class="card border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-white py-3">
                <h6 class="mb-0 fw-bold text-primary"><i class="bi bi-person-badge me-2"></i>Registrar nuevo rol</h6>
            </div>
            <div class="card-body p-4 bg-light">
                <form method="post" class="row g-3" id="formCrearRol">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-md-8 form-floating">
                        <input name="nombre" id="rolNombre" class="form-control" placeholder="Nombre del rol" required>
                        <label for="rolNombre">Nombre del rol</label>
                    </div>
                    <div class="col-md-4 d-flex align-items-center">
                        <button class="btn btn-primary w-100 h-100 py-3 fw-bold">Guardar rol</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0" id="rolesSearch" placeholder="Buscar rol...">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <select class="form-select bg-light" id="filtroEstadoRol">
                        <option value="">Todos los estados</option>
                        <option value="1">Activos</option>
                        <option value="0">Inactivos</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="rolesTable">
                    <thead>
                    <tr>
                        <th class="ps-4">Rol</th>
                        <th class="text-center">Estado</th>
                        <th>Creado</th>
                        <th class="text-end pe-4">Acciones</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $rol): ?>
                        <tr data-search="<?php echo e(strtolower((string) $rol['nombre'])); ?>" data-estado="<?php echo (int) $rol['estado']; ?>">
                            <td class="ps-4 fw-semibold"><?php echo e((string) $rol['nombre']); ?></td>
                            <td class="text-center">
                                <span class="badge-status <?php echo (int) $rol['estado'] === 1 ? 'status-active' : 'status-inactive'; ?>" id="badge_rol_<?php echo (int) $rol['id']; ?>">
                                    <?php echo (int) $rol['estado'] === 1 ? 'Activo' : 'Inactivo'; ?>
                                </span>
                            </td>
                            <td class="text-muted small"><?php echo e((string) ($rol['created_at'] ?? '-')); ?></td>
                            <td class="text-end pe-4">
                                <div class="d-flex align-items-center justify-content-end gap-2">
                                    <button class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar-rol"
                                            data-id="<?php echo (int) $rol['id']; ?>"
                                            data-nombre="<?php echo e((string) $rol['nombre']); ?>"
                                            data-estado="<?php echo (int) $rol['estado']; ?>"
                                            data-bs-toggle="tooltip" title="Editar">
                                        <i class="bi bi-pencil-square fs-5"></i>
                                    </button>

                                    <form method="post" class="toggle-form d-inline m-0">
                                        <input type="hidden" name="accion" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo (int) $rol['id']; ?>">
                                        <input type="hidden" name="estado" value="<?php echo (int) $rol['estado'] === 1 ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-secondary border-0 bg-transparent" data-bs-toggle="tooltip" title="Activar/Desactivar">
                                            <i class="bi <?php echo (int) $rol['estado'] === 1 ? 'bi-toggle-on' : 'bi-toggle-off'; ?> fs-5"></i>
                                        </button>
                                    </form>

                                    <form method="post" class="delete-form d-inline m-0">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?php echo (int) $rol['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-light text-danger border-0 bg-transparent" data-bs-toggle="tooltip" title="Eliminar">
                                            <i class="bi bi-trash fs-5"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="4" class="bg-light-subtle">
                                <form method="post" class="permiso-form">
                                    <input type="hidden" name="accion" value="permisos">
                                    <input type="hidden" name="id_rol" value="<?php echo (int) $rol['id']; ?>">
                                    <div class="accordion" id="acordeonRol<?php echo (int) $rol['id']; ?>">
                                        <?php $idx = 0; foreach ($permisosPorModulo as $modulo => $items): $idx++; ?>
                                            <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden">
                                                <h2 class="accordion-header" id="heading<?php echo (int) $rol['id'] . $idx; ?>">
                                                    <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo (int) $rol['id'] . $idx; ?>">
                                                        <span class="fw-semibold small">Módulo: <?php echo e((string) $modulo); ?></span>
                                                    </button>
                                                </h2>
                                                <div id="collapse<?php echo (int) $rol['id'] . $idx; ?>" class="accordion-collapse collapse" data-bs-parent="#acordeonRol<?php echo (int) $rol['id']; ?>">
                                                    <div class="accordion-body py-2">
                                                        <div class="row g-2">
                                                            <?php foreach ($items as $permiso): ?>
                                                                <div class="col-md-4">
                                                                    <label class="form-check border rounded-3 px-2 py-1 bg-white">
                                                                        <input class="form-check-input me-2" type="checkbox" name="permisos[]" value="<?php echo (int) $permiso['id']; ?>" <?php echo in_array((int) $permiso['id'], $rol['permisos_ids'], true) ? 'checked' : ''; ?>>
                                                                        <span class="small"><?php echo e((string) $permiso['nombre']); ?> <span class="text-muted">(<?php echo e((string) $permiso['slug']); ?>)</span></span>
                                                                    </label>
                                                                </div>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-end mt-2">
                                        <button class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>Guardar permisos</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="rolesPaginationInfo">Cargando...</small>
                <nav><ul class="pagination pagination-sm mb-0" id="rolesPaginationControls"></ul></nav>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarRol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">Editar rol</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" id="formEditarRol" class="row g-3">
                    <input type="hidden" name="accion" value="editar">
                    <input type="hidden" name="id" id="editRolId">
                    <div class="col-12 form-floating">
                        <input class="form-control" id="editRolNombre" name="nombre" placeholder="Nombre" required>
                        <label for="editRolNombre">Nombre del rol</label>
                    </div>
                    <div class="col-12 form-floating">
                        <select class="form-select" id="editRolEstado" name="estado">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                        <label for="editRolEstado">Estado</label>
                    </div>
                    <div class="col-12 mt-3">
                        <button class="btn btn-primary w-100 py-2 fw-bold" type="submit">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
