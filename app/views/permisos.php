<?php $permisosAgrupados = $permisosAgrupados ?? []; ?>
<div class="container-fluid p-4">
    <div class="mb-4 fade-in">
        <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
            <i class="bi bi-key me-2 text-primary"></i> Permisos
        </h1>
        <p class="text-muted small mb-0 ms-1">Listado de permisos base del sistema agrupados por módulo.</p>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" id="permisoSearch" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar por slug, nombre o módulo...">
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
                    <?php foreach ($permisosAgrupados as $modulo => $permisos): ?>
                        <?php foreach ($permisos as $permiso): ?>
                            <tr data-search="<?php echo e(strtolower($modulo . ' ' . $permiso['slug'] . ' ' . $permiso['nombre'])); ?>">
                                <td class="ps-4"><span class="badge rounded-pill text-bg-light border"><?php echo e((string) $modulo); ?></span></td>
                                <td><code><?php echo e((string) $permiso['slug']); ?></code></td>
                                <td><?php echo e((string) $permiso['nombre']); ?></td>
                                <td class="text-center">
                                    <span class="badge-status <?php echo (int) ($permiso['estado'] ?? 0) === 1 ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo (int) ($permiso['estado'] ?? 0) === 1 ? 'Activo' : 'Inactivo'; ?>
                                    </span>
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
