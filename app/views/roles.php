<?php
// app/views/roles.php

$roles = $roles ?? [];
$permisos = $permisos ?? [];

// Permisos agrupados por módulo
$permisosPorModulo = [];
foreach ($permisos as $permiso) {
    // Normalizamos a mayúsculas y quitamos espacios accidentales
    $modulo = trim(mb_strtoupper((string)($permiso['modulo'] ?? 'GENERAL'), 'UTF-8'));
    $permisosPorModulo[$modulo][] = $permiso;
}

ksort($permisosPorModulo, SORT_NATURAL | SORT_FLAG_CASE);
foreach ($permisosPorModulo as &$permisosModulo) {
    usort($permisosModulo, static function (array $a, array $b): int {
        return strcasecmp((string)($a['nombre'] ?? ''), (string)($b['nombre'] ?? ''));
    });
}
unset($permisosModulo);
?>

<style>
    /* Efecto hover y diseño para la tarjeta del permiso */
    .permiso-card {
        transition: all 0.2s ease-in-out;
        border: 1px solid #e9ecef;
        user-select: none;
    }
    
    .permiso-card:hover {
        border-color: #86b7fe;
        background-color: #f8fbff !important;
        transform: translateY(-2px);
        box-shadow: 0 0.25rem 0.75rem rgba(13, 110, 253, 0.08) !important;
    }

    .permiso-card:has(.form-check-input:checked) {
        border-color: #0d6efd;
        background-color: #f0f7ff !important;
    }

    .accordion-header-master {
        background-color: #f8f9fa;
        border-bottom: 1px solid #dee2e6;
    }

    .permiso-tecnico-wrapper {
        max-height: 0;
        opacity: 0;
        overflow: hidden;
        transition: all 0.3s ease;
    }

    .permiso-card:hover .permiso-tecnico-wrapper,
    .permiso-card:has(.form-check-input:checked) .permiso-tecnico-wrapper {
        max-height: 30px;
        opacity: 1;
        margin-top: 4px;
    }

    /* ===================================================
       NUEVO: ESTILOS PARA EL ACORDEÓN DEL ROL (MASTER)
       =================================================== */
    .role-row-main td:first-child:hover {
        background-color: #f8f9fa;
    }
    
    .role-chevron {
        transition: transform 0.3s ease;
    }
    
    /* Gira la flecha cuando el acordeón está abierto */
    td[aria-expanded="true"] .role-chevron {
        transform: rotate(180deg);
    }

    /* ===================================================
       CORRECCIONES PARA MÓVILES
       =================================================== */
    @media (max-width: 767px) {
        #rolesTable td {
            white-space: normal !important;
            word-wrap: break-word !important;
            overflow-wrap: break-word !important;
        }

        #rolesTable .role-row-main td .d-flex {
            align-items: flex-start !important;
        }
        
        #rolesTable .role-row-main td .d-flex > div:last-child {
            min-width: 0;
            flex: 1;
        }

        #rolesTable .role-row-main .text-end {
            display: flex;
            justify-content: flex-end;
            flex-wrap: wrap;
            gap: 5px;
        }
    }
</style>

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
                                $rolUpdatedRaw = (string)($rol['updated_at'] ?? $rol['created_at'] ?? '');
                                $rolUpdated = $rolUpdatedRaw !== '' ? date('d/m/Y h:i A', strtotime($rolUpdatedRaw)) : '-';
                                $rolUpdatedBy = (string)($rol['updated_by_nombre'] ?? $rol['created_by_nombre'] ?? 'Sistema');
                                $dataSearch = mb_strtolower(trim($rolNombre . ' ' . $rolSlug));
                                ?>

                                <tr class="role-row-main"
                                    data-role-id="<?php echo $rolId; ?>"
                                    data-search="<?php echo e($dataSearch); ?>"
                                    data-estado="<?php echo $rolEstado; ?>">
                                    
                                    <!-- COLUMNA 1: CONVERTIDA EN EL GATILLO DEL ACORDEÓN PRINCIPAL -->
                                    <td class="ps-4" 
                                        style="cursor: pointer; width: 45%;" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#collapseRol_<?php echo $rolId; ?>" 
                                        aria-expanded="false">
                                        
                                        <div class="d-flex align-items-center">
                                            <!-- Ícono Chevron animado -->
                                            <i class="bi bi-chevron-down text-primary me-3 fs-5 role-chevron"></i>

                                            <div class="avatar-circle me-3 bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center" 
                                                 style="width:40px; height:40px; border-radius:50%; flex-shrink: 0;">
                                                <i class="bi bi-shield-fill"></i>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark text-wrap text-break"><?php echo e($rolNombre); ?></div>
                                                <div class="small text-muted text-wrap text-break">ID: <?php echo $rolId; ?> <span class="ms-1 d-none d-sm-inline fw-normal text-primary-emphasis opacity-75">(Clic para configurar)</span></div>
                                                <?php if ($rolSlug !== ''): ?>
                                                    <code class="small text-primary bg-primary bg-opacity-10 px-2 py-1 rounded-2 d-inline-block mt-1"><?php echo e($rolSlug); ?></code>
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
                                        <div class="text-wrap text-break"><i class="bi bi-clock me-1"></i><?php echo e($rolUpdated); ?></div>
                                        <div class="text-secondary text-wrap text-break">Por: <?php echo e($rolUpdatedBy); ?></div>
                                    </td>

                                    <!-- ACCIONES (Fuera del gatillo para evitar conflictos de clic) -->
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
                                        
                                        <!-- NUEVO: EL CONTENEDOR COLLAPSE DEL ROL -->
                                        <div class="collapse" id="collapseRol_<?php echo $rolId; ?>">
                                            <div class="px-3 px-sm-4 py-3">
                                                
                                                <form method="post" class="permiso-form" id="formPermisos<?php echo $rolId; ?>">
                                                    <input type="hidden" name="accion" value="permisos">
                                                    <input type="hidden" name="id_rol" value="<?php echo $rolId; ?>">

                                                    <!-- CABECERA: Botón Guardar -->
                                                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between mb-3 pb-2 border-bottom border-secondary-subtle gap-2">
                                                        <h6 class="fw-bold text-primary mb-0 text-wrap">
                                                            <i class="bi bi-sliders me-2"></i>Configuración de permisos: <span class="text-dark"><?php echo e($rolNombre); ?></span>
                                                        </h6>
                                                        <button class="btn btn-sm btn-primary px-4 fw-bold shadow-sm" type="submit">
                                                            <i class="bi bi-save-fill me-2"></i>Guardar Permisos
                                                        </button>
                                                    </div>

                                                    <!-- ACORDEÓN FLUSH DE LOS MÓDULOS -->
                                                    <div class="accordion accordion-flush shadow-sm rounded-3 overflow-hidden border bg-white" id="acordeonRol<?php echo $rolId; ?>">
                                                        <?php $idx = 0; foreach ($permisosPorModulo as $modulo => $items): $idx++; ?>
                                                            
                                                            <?php
                                                                // Verificamos si TODOS los permisos de este módulo están asignados
                                                                $todosActivos = true;
                                                                foreach ($items as $p) {
                                                                    if (!in_array((int)($p['id'] ?? 0), ($rol['permisos_ids'] ?? []), true)) {
                                                                        $todosActivos = false;
                                                                        break;
                                                                    }
                                                                }
                                                            ?>

                                                            <div class="accordion-item <?php echo $idx < count($permisosPorModulo) ? 'border-bottom' : ''; ?>">
                                                                
                                                                <!-- HEADER MÓDULO CON MASTER SWITCH -->
                                                                <h2 class="accordion-header d-flex align-items-stretch accordion-header-master" id="heading<?php echo $rolId . $idx; ?>">
                                                                    
                                                                    <button class="accordion-button collapsed py-3 px-4 bg-transparent shadow-none fw-semibold border-0 flex-grow-1"
                                                                            type="button"
                                                                            data-bs-toggle="collapse"
                                                                            data-bs-target="#collapseModulo<?php echo $rolId . $idx; ?>">
                                                                        <div class="d-flex align-items-center">
                                                                            <span class="text-uppercase ls-1 text-dark fw-bold" style="letter-spacing: 0.5px;"><?php echo e((string)$modulo); ?></span>
                                                                            <span class="badge bg-primary text-white ms-3 rounded-pill"><?php echo count($items); ?></span>
                                                                        </div>
                                                                    </button>
                                                                    
                                                                    <!-- MASTER SWITCH -->
                                                                    <div class="d-flex align-items-center pe-4 ps-3 border-start fs-6 fw-normal">
                                                                        <div class="form-check form-switch m-0 d-flex align-items-center gap-2" data-bs-toggle="tooltip" title="Activar/Desactivar todo">
                                                                            <label class="form-check-label text-muted d-none d-sm-block mb-0" 
                                                                                   style="cursor: pointer; font-size: 0.85rem; font-weight: 600;" 
                                                                                   for="master_<?php echo $rolId . '_' . $idx; ?>">Todo</label>
                                                                            <input class="form-check-input m-0 switch-master-modulo" 
                                                                                   type="checkbox" 
                                                                                   role="switch" 
                                                                                   id="master_<?php echo $rolId . '_' . $idx; ?>"
                                                                                   data-target-class="child-perm-<?php echo $rolId . '-' . $idx; ?>"
                                                                                   style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                                                                   <?php echo $todosActivos ? 'checked' : ''; ?>>
                                                                        </div>
                                                                    </div>
                                                                </h2>

                                                                <div id="collapseModulo<?php echo $rolId . $idx; ?>"
                                                                     class="accordion-collapse collapse"
                                                                     data-bs-parent="#acordeonRol<?php echo $rolId; ?>">
                                                                    <div class="accordion-body bg-light px-4 py-4 border-top">
                                                                        
                                                                        <!-- GRID PERMISOS INDIVIDUALES -->
                                                                        <div class="row g-3">
                                                                            <?php foreach ($items as $permiso): ?>
                                                                                <?php
                                                                                $permId   = (int)($permiso['id'] ?? 0);
                                                                                $permNom  = (string)($permiso['nombre'] ?? '');
                                                                                $permSlug = (string)($permiso['slug'] ?? '');
                                                                                $checked  = in_array($permId, ($rol['permisos_ids'] ?? []), true);
                                                                                ?>
                                                                                
                                                                                <div class="col-12 col-md-6 col-xl-4">
                                                                                    <label class="form-check form-switch m-0 h-100 p-3 bg-white rounded-3 shadow-sm d-flex align-items-start gap-3 permiso-card" 
                                                                                           style="cursor: pointer;"
                                                                                           for="perm_<?php echo $rolId . '_' . $permId; ?>">
                                                                                        
                                                                                        <input class="form-check-input m-0 flex-shrink-0 mt-1 permiso-check child-perm-<?php echo $rolId . '-' . $idx; ?>"
                                                                                               type="checkbox"
                                                                                               role="switch"
                                                                                               id="perm_<?php echo $rolId . '_' . $permId; ?>" 
                                                                                               name="permisos[]"
                                                                                               value="<?php echo $permId; ?>"
                                                                                               data-slug="<?php echo e($permSlug); ?>"
                                                                                               style="width: 2.5em; height: 1.25em; cursor: pointer;"
                                                                                               <?php echo $checked ? 'checked' : ''; ?>>
                                                                                        
                                                                                        <div class="d-flex flex-column justify-content-center w-100">
                                                                                            <span class="fw-bold text-dark lh-sm" style="font-size: 0.9rem;"><?php echo e($permNom); ?></span>
                                                                                            <div class="permiso-tecnico-wrapper mt-1">
                                                                                                <code class="text-secondary bg-light border px-2 py-1 rounded-2" style="font-size: 0.75rem;">
                                                                                                    <i class="bi bi-code-slash me-1"></i><?php echo e($permSlug); ?>
                                                                                                </code>
                                                                                            </div>
                                                                                        </div>
                                                                                    </label>
                                                                                </div>
                                                                            <?php endforeach; ?>
                                                                        </div>

                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </form>
                                            </div>
                                        </div> <!-- CIERRE DEL COLLAPSE PRINCIPAL DEL ROL -->

                                    </td>
                                </tr>

                            <?php endforeach; ?>

                            </tbody>
                        </table>
                    </div>

                    <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                        <small class="text-muted text-center" id="rolesPaginationInfo"></small>
                        <nav aria-label="Page navigation">
                            <ul class="pagination mb-0 justify-content-center justify-content-sm-end" id="rolesPaginationControls"></ul>
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
                        <table class="table align-middle mb-0 table-pro" id="permisosTable"
                               data-erp-table="true"
                               data-rows-selector="tbody tr[data-search]"
                               data-search-input="#permisoSearch"
                               data-pagination-controls="#permisosPaginationControls"
                               data-pagination-info="#permisosPaginationInfo">
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
                                    $updatedAtRaw = (string)($permiso['updated_at'] ?? $permiso['created_at'] ?? '');
                                    $updatedAt = $updatedAtRaw !== '' ? date('d/m/Y h:i A', strtotime($updatedAtRaw)) : '-';
                                    $updatedBy = (string)($permiso['updated_by_nombre'] ?? $permiso['created_by_nombre'] ?? 'Sistema');
                                    $search = mb_strtolower(trim($mod . ' ' . $slug . ' ' . $nom . ' ' . $desc));
                                    ?>
                                    <tr data-search="<?php echo e($search); ?>">
                                        <td class="ps-4">
                                            <span class="badge bg-light text-dark border text-wrap">
                                                <?php echo e($mod); ?>
                                            </span>
                                        </td>
                                        <td><code class="text-primary text-break"><?php echo e($slug); ?></code></td>
                                        <td>
                                            <span class="fw-medium text-dark text-wrap text-break"><?php echo e($nom); ?></span>
                                            <?php if ($desc !== ''): ?>
                                                <div class="small text-muted mt-1 text-wrap text-break"><?php echo e($desc); ?></div>
                                            <?php endif; ?>
                                        </td>

                                        <td class="small text-muted">
                                            <div class="text-wrap text-break"><i class="bi bi-clock me-1"></i><?php echo e($updatedAt); ?></div>
                                            <div class="text-secondary text-wrap text-break">Por: <?php echo e($updatedBy); ?></div>
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
    window.MY_ROLE_ID = <?php echo (int)($_SESSION['id_rol'] ?? 0); ?>;
</script>