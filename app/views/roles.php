<?php
// roles/index.php (o app/views/roles.php)

// -------------------------------
// Data defensiva
// -------------------------------
$roles = $roles ?? [];
$permisos = $permisos ?? [];

// Permisos por módulo (para asignación por rol)
$permisosPorModulo = [];
foreach ($permisos as $permiso) {
    $modulo = (string)($permiso['modulo'] ?? 'General');
    $permisosPorModulo[$modulo][] = $permiso;
}

// Permisos agrupados (para TAB “Permisos”)
$permisosAgrupados = [];
foreach ($permisos as $permiso) {
    $modulo = (string)($permiso['modulo'] ?? 'General');
    $permisosAgrupados[$modulo][] = $permiso;
}
?>

<div class="container-fluid p-4">

    <!-- HEADER (estilo Usuarios) -->
    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-shield-lock me-2 text-primary fs-5"></i>
                <span>Roles y Permisos</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">
                Administración de roles, asignación granular y catálogo de permisos del sistema.
            </p>
        </div>

        <!-- Acción principal: crear rol (solo aplica a Roles) -->
        <button class="btn btn-primary shadow-sm btn-new-user flex-shrink-0"
                type="button"
                data-bs-toggle="modal"
                data-bs-target="#modalCrearRol">
            <i class="bi bi-plus-circle me-0 me-sm-2"></i>
            <span class="d-none d-sm-inline">Nuevo Rol</span>
        </button>
    </div>

    <!-- TABS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-2 p-sm-3">
            <ul class="nav nav-pills gap-2" id="rolesPermisosTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold"
                            id="tab-roles"
                            data-bs-toggle="pill"
                            data-bs-target="#pane-roles"
                            type="button"
                            role="tab"
                            aria-controls="pane-roles"
                            aria-selected="true">
                        <i class="bi bi-shield-check me-1"></i> Roles
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold"
                            id="tab-permisos"
                            data-bs-toggle="pill"
                            data-bs-target="#pane-permisos"
                            type="button"
                            role="tab"
                            aria-controls="pane-permisos"
                            aria-selected="false">
                        <i class="bi bi-key me-1"></i> Permisos
                    </button>
                </li>
            </ul>
        </div>
    </div>

    <div class="tab-content">

        <!-- =========================================================
             TAB 1: ROLES
        ========================================================== -->
        <div class="tab-pane fade show active" id="pane-roles" role="tabpanel" aria-labelledby="tab-roles">

            <!-- FILTROS (estilo Usuarios) -->
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

            <!-- TABLA ROLES -->
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
                                <?php
                                $rolId = (int)($rol['id'] ?? 0);
                                $rolNombre = (string)($rol['nombre'] ?? '');
                                $rolEstado = (int)($rol['estado'] ?? 0);
                                $rolCreated = (string)($rol['created_at'] ?? '-');

                                // Para filtros JS
                                $dataSearch = strtolower($rolNombre);
                                ?>

                                <!-- ROW PRINCIPAL -->
                                <tr class="role-row-main"
                                    data-role-id="<?php echo $rolId; ?>"
                                    data-search="<?php echo e($dataSearch); ?>"
                                    data-estado="<?php echo $rolEstado; ?>">
                                    <td class="ps-4">
                                        <div class="fw-semibold text-dark d-flex align-items-center gap-2">
                                            <span><?php echo e($rolNombre); ?></span>
                                            <span class="badge bg-light text-dark border"><?php echo $rolId; ?></span>
                                        </div>
                                        <div class="small text-muted">Gestión de permisos por rol</div>
                                    </td>

                                    <td class="text-center">
                                        <?php if ($rolEstado === 1): ?>
                                            <span class="badge-status status-active" id="badge_rol_<?php echo $rolId; ?>">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-status status-inactive" id="badge_rol_<?php echo $rolId; ?>">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-muted small">
                                        <i class="bi bi-clock me-1"></i><?php echo e($rolCreated); ?>
                                    </td>

                                    <td class="text-end pe-4">
                                        <div class="d-flex align-items-center justify-content-end gap-2">

                                            <!-- Editar -->
                                            <button class="btn btn-sm btn-light text-primary border-0 bg-transparent btn-editar-rol"
                                                    data-id="<?php echo $rolId; ?>"
                                                    data-nombre="<?php echo e($rolNombre); ?>"
                                                    data-estado="<?php echo $rolEstado; ?>"
                                                    data-bs-toggle="tooltip"
                                                    title="Editar">
                                                <i class="bi bi-pencil-square fs-5"></i>
                                            </button>

                                            <div class="vr bg-secondary opacity-25" style="height: 20px;"></div>

                                            <!-- Toggle -->
                                            <form method="post" class="toggle-form d-inline m-0">
                                                <input type="hidden" name="accion" value="toggle">
                                                <input type="hidden" name="id" value="<?php echo $rolId; ?>">
                                                <input type="hidden" name="estado" value="<?php echo $rolEstado === 1 ? 0 : 1; ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-light text-secondary border-0 bg-transparent"
                                                        data-bs-toggle="tooltip"
                                                        title="Activar/Desactivar">
                                                    <i class="bi <?php echo $rolEstado === 1 ? 'bi-toggle-on' : 'bi-toggle-off'; ?> fs-5"></i>
                                                </button>
                                            </form>

                                            <!-- Eliminar -->
                                            <form method="post" class="delete-form d-inline m-0">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="id" value="<?php echo $rolId; ?>">
                                                <button type="submit"
                                                        class="btn btn-sm btn-light text-danger border-0 bg-transparent"
                                                        data-bs-toggle="tooltip"
                                                        title="Eliminar">
                                                    <i class="bi bi-trash fs-5"></i>
                                                </button>
                                            </form>

                                        </div>
                                    </td>
                                </tr>

                                <!-- ROW DETALLE (permisos por módulo) -->
                                <tr class="role-row-detail" data-detail-for="<?php echo $rolId; ?>">
                                    <td colspan="4" class="bg-light-subtle p-0 border-0">
                                        <div class="px-3 py-2">

                                            <form method="post" class="permiso-form">
                                                <input type="hidden" name="accion" value="permisos">
                                                <input type="hidden" name="id_rol" value="<?php echo $rolId; ?>">

                                                <div class="accordion" id="acordeonRol<?php echo $rolId; ?>">
                                                    <?php $idx = 0; foreach ($permisosPorModulo as $modulo => $items): $idx++; ?>
                                                        <div class="accordion-item border-0 mb-1 rounded-3 overflow-hidden shadow-sm">
                                                            <h2 class="accordion-header" id="heading<?php echo $rolId . $idx; ?>">
                                                                <button class="accordion-button collapsed py-2 bg-white"
                                                                        type="button"
                                                                        data-bs-toggle="collapse"
                                                                        data-bs-target="#collapse<?php echo $rolId . $idx; ?>">
                                                                    <span class="fw-semibold small text-uppercase">Módulo: <?php echo e((string)$modulo); ?></span>
                                                                </button>
                                                            </h2>

                                                            <div id="collapse<?php echo $rolId . $idx; ?>"
                                                                 class="accordion-collapse collapse"
                                                                 data-bs-parent="#acordeonRol<?php echo $rolId; ?>">
                                                                <div class="accordion-body py-2 bg-white">
                                                                    <div class="row g-2">
                                                                        <?php foreach ($items as $permiso): ?>
                                                                            <?php
                                                                            $permId   = (int)($permiso['id'] ?? 0);
                                                                            $permNom  = (string)($permiso['nombre'] ?? '');
                                                                            $permSlug = (string)($permiso['slug'] ?? '');

                                                                            // En tu controlador normalmente ya llega: $rol['permisos_ids']
                                                                            $checked = in_array($permId, ($rol['permisos_ids'] ?? []), true);
                                                                            ?>
                                                                            <div class="col-12 col-md-6 col-lg-4">
                                                                                <label class="form-check border rounded-2 px-2 py-2 bg-light d-block h-100">
                                                                                    <input class="form-check-input me-2"
                                                                                           type="checkbox"
                                                                                           name="permisos[]"
                                                                                           value="<?php echo $permId; ?>"
                                                                                           <?php echo $checked ? 'checked' : ''; ?>>
                                                                                    <span class="small fw-medium"><?php echo e($permNom); ?></span>
                                                                                    <div class="text-muted x-small ps-4"><?php echo e($permSlug); ?></div>
                                                                                </label>
                                                                            </div>
                                                                        <?php endforeach; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>

                                                <div class="text-end mt-2 mb-2">
                                                    <button class="btn btn-primary btn-sm px-3 shadow-sm">
                                                        <i class="bi bi-save me-1"></i> Guardar permisos
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

                    <!-- Footer (si luego activas paginación con ERPTable) -->
                    <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="rolesPaginationInfo"> </small>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0 justify-content-end" id="rolesPaginationControls"></ul>
                        </nav>
                    </div>
                </div>
            </div>

        </div>

        <!-- =========================================================
             TAB 2: PERMISOS
        ========================================================== -->
        <div class="tab-pane fade" id="pane-permisos" role="tabpanel" aria-labelledby="tab-permisos">

            <!-- Buscador permisos -->
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-body p-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="search"
                               id="permisoSearch"
                               class="form-control bg-light border-start-0 ps-0"
                               placeholder="Buscar por slug, nombre o módulo...">
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
                                <th>Slug</th>
                                <th>Nombre</th>
                                <th class="text-center">Estado</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($permisosAgrupados as $modulo => $plist): ?>
                                <?php foreach ($plist as $permiso): ?>
                                    <?php
                                    $mod = (string)$modulo;
                                    $slug = (string)($permiso['slug'] ?? '');
                                    $nom  = (string)($permiso['nombre'] ?? '');
                                    $est  = (int)($permiso['estado'] ?? 0);

                                    $search = strtolower($mod . ' ' . $slug . ' ' . $nom);
                                    ?>
                                    <tr data-search="<?php echo e($search); ?>">
                                        <td class="ps-4">
                                            <span class="badge rounded-pill text-bg-light border">
                                                <?php echo e($mod); ?>
                                            </span>
                                        </td>
                                        <td><code><?php echo e($slug); ?></code></td>
                                        <td><?php echo e($nom); ?></td>
                                        <td class="text-center">
                                            <span class="badge-status <?php echo $est === 1 ? 'status-active' : 'status-inactive'; ?>">
                                                <?php echo $est === 1 ? 'Activo' : 'Inactivo'; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- (Opcional) footer permisos si luego paginaras -->
                    <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="permisosPaginationInfo"></small>
                        <nav aria-label="Page navigation">
                            <ul class="pagination pagination-sm mb-0 justify-content-end" id="permisosPaginationControls"></ul>
                        </nav>
                    </div>

                </div>
            </div>

        </div>

    </div>
</div>

<!-- MODAL CREAR ROL -->
<div class="modal fade" id="modalCrearRol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-shield-plus me-2"></i>Registrar nuevo rol
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
                    <div class="col-12 d-flex justify-content-end pt-2">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">
                            <i class="bi bi-save me-2"></i>Guardar Rol
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MODAL EDITAR ROL -->
<div class="modal fade" id="modalEditarRol" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-light">
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

                    <div class="col-12 d-flex justify-content-end pt-3">
                        <button type="button" class="btn btn-light text-secondary me-2" data-bs-dismiss="modal">Cancelar</button>
                        <button class="btn btn-primary px-4 fw-bold" type="submit">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- JS específico (mantén tu archivo) -->
<script src="<?php echo asset_url('js/rol.js'); ?>"></script>
