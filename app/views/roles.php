<?php
// app/views/roles.php

$roles = $roles ?? [];
$permisos = $permisos ?? [];

// Permisos agrupados por módulo
$permisosPorModulo = [];
foreach ($permisos as $permiso) {
    $modulo = (string)($permiso['modulo'] ?? 'General');
    $permisosPorModulo[$modulo][] = $permiso;
}
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-shield-lock-fill me-2 text-primary fs-5"></i>
                <span>Roles y Permisos</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">
                Gestión de perfiles de acceso y matriz de seguridad (RBAC).
            </p>
        </div>

        <button class="btn btn-primary shadow-sm btn-new-user flex-shrink-0"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#modalCrearRol">
            <i class="bi bi-shield-plus me-0 me-sm-2"></i>
            <span class="d-none d-sm-inline">Nuevo Rol</span>
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-sm-3">
            <ul class="nav nav-pills gap-2" id="rolesPermisosTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold"
                            id="tab-roles"
                            data-bs-toggle="pill"
                            data-bs-target="#pane-roles"
                            type="button"
                            role="tab">
                        <i class="bi bi-person-badge me-1"></i> Roles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold"
                            id="tab-permisos"
                            data-bs-toggle="pill"
                            data-bs-target="#pane-permisos"
                            type="button"
                            role="tab">
                        <i class="bi bi-key me-1"></i> Catálogo de Permisos
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content">

        <div class="tab-pane fade show active" id="pane-roles" role="tabpanel">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-12 col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0">
                                    <i class="bi bi-search text-muted"></i>
                                </span>
                                <input type="search"
                                       class="form-control bg-light border-start-0 ps-0"
                                       id="rolesSearch"
                                       placeholder="Buscar rol...">
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
                                <th>Última Actualización</th>
                                <th class="text-end pe-4">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>

                            <?php foreach ($roles as $rol): ?>
                                <?php
                                $rolId = (int)($rol['id'] ?? 0);
                                $rolNombre = (string)($rol['nombre'] ?? '');
                                $rolSlug = (string)($rol['slug'] ?? '');
                                $rolEstado = (int)($rol['estado'] ?? 0);
                                $rolUpdated = (string)($rol['updated_at'] ?? $rol['created_at'] ?? '-');
                                $rolUpdatedBy = (string)($rol['updated_by_nombre'] ?? $rol['created_by_nombre'] ?? 'Sistema');
                                $dataSearch = mb_strtolower(trim($rolNombre . ' ' . $rolSlug));
                                ?>

                                <tr class="role-row-main"
                                    data-role-id="<?php echo $rolId; ?>"
                                    data-search="<?php echo e($dataSearch); ?>"
                                    data-estado="<?php echo $rolEstado; ?>">
                                    
                                    <td class="ps-4">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" 
                                                 style="width:40px; height:40px; border-radius:50%;">
                                                <i class="bi bi-shield-fill"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo e($rolNombre); ?></div>
                                                <div class="small text-muted">ID: <?php echo $rolId; ?></div>
                                                <?php if ($rolSlug !== ''): ?>
                                                    <code class="small text-primary bg-primary bg-opacity-10 px-2 py-1 rounded-2"><?php echo e($rolSlug); ?></code>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($rolEstado === 1): ?>
                                            <span class="badge-status status-active" id="badge_rol_<?php echo $rolId; ?>">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-status status-inactive" id="badge_rol_<?php echo $rolId; ?>">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-muted small">
                                        <div><i class="bi bi-clock me-1"></i><?php echo e($rolUpdated); ?></div>
                                        <div class="text-secondary">Por: <?php echo e($rolUpdatedBy); ?></div>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            
                                            <div class="form-check form-switch pt-1" data-bs-toggle="tooltip" title="Cambiar estado">
                                                <input class="form-check-input switch-estado-rol" 
                                                       type="checkbox" 
                                                       role="switch"
                                                       style="cursor: pointer; width: 2.5em; height: 1.25em;"
                                                       data-id="<?php echo $rolId; ?>"
                                                       <?php echo $rolEstado === 1 ? 'checked' : ''; ?>>
                                            </div>

                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                            <button class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar-rol"
                                                    data-id="<?php echo $rolId; ?>"
                                                    data-nombre="<?php echo e($rolNombre); ?>"
                                                    data-estado="<?php echo $rolEstado; ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Editar Nombre">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>

                                            <form method="post" class="delete-form d-inline m-0">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $rolId; ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-light text-danger border-0 bg-transparent"
                                                        data-bs-toggle="tooltip"
                                                        title="Eliminar Rol">
                                                    <i class="bi bi-trash fs-5"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                                <tr class="role-row-detail bg-light-subtle" data-detail-for="<?php echo $rolId; ?>">
                                    <td colspan="4" class="p-0 border-0">
                                        <div class="px-3 py-3">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <h6 class="fw-bold text-primary mb-0">
                                                    <i class="bi bi-sliders me-2"></i>Permisos asignados a: <span class="text-dark"><?php echo e($rolNombre); ?></span>
                                                </h6>
                                            </div>

                                            <form method="post" class="permiso-form">
                                                <input type="hidden" name="accion" value="permisos">
                                                <input type="hidden" name="id_rol" value="<?php echo $rolId; ?>">

                                                <div class="accordion shadow-sm rounded-3 overflow-hidden" id="acordeonRol<?php echo $rolId; ?>">
                                                    <?php $idx = 0; foreach ($permisosPorModulo as $modulo => $items): $idx++; ?>
                                                        <div class="accordion-item border-0 border-bottom">
                                                            <h2 class="accordion-header" id="heading<?php echo $rolId . $idx; ?>">
                                                                <button class="accordion-button collapsed py-2 bg-white fw-semibold"
                                                                        type="button"
                                                                        data-bs-toggle="collapse"
                                                                        data-bs-target="#collapse<?php echo $rolId . $idx; ?>">
                                                                    <span class="text-uppercase small ls-1"><?php echo e((string)$modulo); ?></span>
                                                                    <span class="badge bg-light text-secondary ms-2 border"><?php echo count($items); ?></span>
                                                                </button>
                                                            </h2>

                                                            <div id="collapse<?php echo $rolId . $idx; ?>"
                                                                 class="accordion-collapse collapse"
                                                                 data-bs-parent="#acordeonRol<?php echo $rolId; ?>">
                                                                <div class="accordion-body bg-light bg-opacity-50">
                                                                    <div class="row g-2">
                                                                        <?php foreach ($items as $permiso): ?>
                                                                            <?php
                                                                            $permId   = (int)($permiso['id'] ?? 0);
                                                                            $permNom  = (string)($permiso['nombre'] ?? '');
                                                                            $permSlug = (string)($permiso['slug'] ?? '');
                                                                            $checked  = in_array($permId, ($rol['permisos_ids'] ?? []), true);
                                                                            ?>
                                                                            <div class="col-12 col-md-6 col-lg-4">
                                                                                <div class="form-check form-switch bg-white border rounded-2 p-2 h-100 d-flex align-items-center shadow-sm">
                                                                                    <input class="form-check-input m-0 me-3 flex-shrink-0 permiso-check"
                                                                                        type="checkbox"
                                                                                        role="switch"
                                                                                        name="permisos[]"
                                                                                        value="<?php echo $permId; ?>"
                                                                                        data-slug="<?php echo e($permSlug); ?>"
                                                                                        style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                                                                        <?php echo $checked ? 'checked' : ''; ?>>
                                                                                    
                                                                                    <div class="lh-1">
                                                                                        <span class="d-block fw-medium text-dark mb-1"><?php echo e($permNom); ?></span>
                                                                                        <code class="text-muted small" style="font-size: 0.75em;"><?php echo e($permSlug); ?></code>
                                                                                    </div>
                                                                                </div>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="d-flex justify-content-end mt-3">
                                                    <button class="btn btn-primary px-4 fw-bold shadow-sm">
                                                        <i class="bi bi-check-circle-fill me-2"></i>Guardar Permisos
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="rolesPaginationInfo"></small>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0 justify-content-end" id="rolesPaginationControls"></ul>
                        </nav>
                    </div>
                </div>
            </div>

        </div>

        <div class="tab-pane fade" id="pane-permisos" role="tabpanel">

            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="search"
                               id="permisoSearch"
                               class="form-control bg-light border-start-0 ps-0"
                               placeholder="Filtrar catálogo...">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0 table-pro" id="permisosTable">
                            <thead>
                            <tr>
                                <th class="ps-4">Módulo</th>
                                <th>Slug Técnico</th>
                                <th>Descripción</th>
                                <th>Auditoría</th>
                                <th class="text-center">Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($permisosPorModulo as $modulo => $plist): ?>
                                <?php foreach ($plist as $permiso): ?>
                                    <?php
                                    $mod  = (string)$modulo;
                                    $slug = (string)($permiso['slug'] ?? '');
                                    $nom  = (string)($permiso['nombre'] ?? '');
                                    $desc = (string)($permiso['descripcion'] ?? '');
                                    $est  = (int)($permiso['estado'] ?? 0);
                                    $updatedAt = (string)($permiso['updated_at'] ?? $permiso['created_at'] ?? '-');
                                    $updatedBy = (string)($permiso['updated_by_nombre'] ?? $permiso['created_by_nombre'] ?? 'Sistema');
                                    $search = mb_strtolower(trim($mod . ' ' . $slug . ' ' . $nom . ' ' . $desc));
                                    ?>
                                    <tr data-search="<?php echo e($search); ?>">
                                        <td class="ps-4">
                                            <span class="badge bg-light text-dark border">
                                                <?php echo e($mod); ?>
                                            </span>
                                        </td>
                                        <td><code class="text-primary"><?php echo e($slug); ?></code></td>
                                        <td>
                                            <span class="fw-medium text-dark"><?php echo e($nom); ?></span>
                                            <?php if ($desc !== ''): ?>
                                                <div class="small text-muted mt-1"><?php echo e($desc); ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="small text-muted">
                                            <div><i class="bi bi-clock me-1"></i><?php echo e($updatedAt); ?></div>
                                            <div class="text-secondary">Por: <?php echo e($updatedBy); ?></div>
                                        </td>

                                        <td class="text-center">
                                            <?php if ($est === 1): ?>
                                                <span class="badge-status status-active">Activo</span>
                                            <?php else: ?>
                                                <span class="badge-status status-inactive">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<div class="modal fade" id="modalCrearRol" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-plus me-2"></i>Nuevo Rol
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <form method="post" class="row g-3" id="formCrearRol">
                    <input type="hidden" name="accion" value="crear">
                    <div class="col-12 form-floating">
                        <input name="nombre" id="rolNombre" class="form-control" placeholder="Nombre del rol" required>
                        <label for="rolNombre">Nombre del rol</label>
                    </div>
                    <div class="col-12 d-flex justify-content-end pt-3">
                        <button type="button" class="btn btn-link text-secondary text-decoration-none me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">
                            <i class="bi bi-save me-2"></i>Guardar Rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarRol" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light border-bottom-0">
                <h5 class="modal-title fw-bold">Editar Rol</h5>
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

                    <div class="col-12 mt-4">
                        <button class="btn btn-primary w-100 py-2 fw-bold" type="submit">Actualizar Datos</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
    // Variable global para que JS sepa quién soy yo
    window.MY_ROLE_ID = <?php echo (int)($_SESSION['id_rol'] ?? 0); ?>;
</script>
<script src="<?php echo asset_url('js/roles.js'); ?>"></script>