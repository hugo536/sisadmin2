<?php
// app/views/permisos.php

// Data defensiva
$permisosAgrupados = $permisosAgrupados ?? [];
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-start align-items-sm-center mb-4 fade-in gap-2">
        <div class="flex-grow-1">
            <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-key-fill me-2 text-primary fs-5"></i>
                <span>Catálogo de Permisos</span>
            </h1>
            <p class="text-muted small mb-0 ms-1">
                Diccionario de acciones autorizables del sistema agrupadas por módulo.
            </p>
        </div>
        
        </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0">
                    <i class="bi bi-search text-muted"></i>
                </span>
                <input type="search"
                       id="permisoSearch"
                       class="form-control bg-light border-start-0 ps-0"
                       placeholder="Buscar por slug, nombre o módulo..."
                       aria-label="Buscar permisos">
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
                            <th>Nombre Descriptivo</th>
                            <th>Auditoría</th>
                            <th class="text-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($permisosAgrupados)): ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                No hay permisos registrados en el sistema.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($permisosAgrupados as $modulo => $permisos): ?>
                            <?php foreach ($permisos as $permiso): ?>
                                <?php
                                $mod  = (string)$modulo;
                                $slug = (string)($permiso['slug'] ?? '');
                                $nom  = (string)($permiso['nombre'] ?? '');
                                $desc = (string)($permiso['descripcion'] ?? '');
                                $est  = (int)($permiso['estado'] ?? 0);
                                $updatedAt = (string)($permiso['updated_at'] ?? $permiso['created_at'] ?? '-');
                                $updatedBy = (string)($permiso['updated_by_nombre'] ?? $permiso['created_by_nombre'] ?? 'Sistema');
                                
                                // String de búsqueda para filtrado JS
                                $search = mb_strtolower(trim($mod . ' ' . $slug . ' ' . $nom . ' ' . $desc));
                                ?>
                                <tr data-search="<?php echo e($search); ?>">
                                    
                                    <td class="ps-4" data-label="Módulo">
                                        <div class="d-flex align-items-center">
                                            <span class="badge rounded-pill bg-light text-dark border border-secondary-subtle">
                                                <i class="bi bi-box-seam me-1 opacity-50"></i><?php echo e($mod); ?>
                                            </span>
                                        </div>
                                    </td>

                                    <td data-label="Slug">
                                        <code class="text-primary bg-primary bg-opacity-10 px-2 py-1 rounded-2 small fw-bold">
                                            <?php echo e($slug); ?>
                                        </code>
                                    </td>

                                    <td data-label="Descripción">
                                        <span class="fw-medium text-dark"><?php echo e($nom); ?></span>
                                        <?php if ($desc !== ''): ?>
                                            <div class="small text-muted mt-1"><?php echo e($desc); ?></div>
                                        <?php endif; ?>
                                    </td>


                                    <td data-label="Auditoría">
                                        <div class="small text-muted"><i class="bi bi-clock me-1"></i><?php echo e($updatedAt); ?></div>
                                        <div class="small text-secondary">Por: <?php echo e($updatedBy); ?></div>
                                    </td>

                                    <td class="text-center" data-label="Estado">
                                        <?php if ($est === 1): ?>
                                            <span class="badge-status status-active">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-status status-inactive">Inactivo</span>
                                        <?php endif; ?>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted" id="permisosPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación de permisos">
                    <ul class="pagination pagination-sm mb-0 justify-content-end" id="permisosPaginationControls">
                        </ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<script src="<?php echo asset_url('js/permisos.js'); ?>"></script>