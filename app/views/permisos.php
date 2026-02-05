<?php $permisosAgrupados = $permisosAgrupados ?? []; ?>
<div class="container-fluid">
    <h1 class="h3 mb-3">Permisos</h1>
    <?php if ($permisosAgrupados === []): ?>
        <div class="alert alert-light border">No hay permisos registrados.</div>
    <?php endif; ?>

    <?php foreach ($permisosAgrupados as $modulo => $permisos): ?>
        <div class="card mb-3">
            <div class="card-header">Módulo: <?php echo e((string) $modulo); ?></div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Slug</th><th>Nombre</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($permisos as $permiso): ?>
                        <tr>
                            <td><?php echo e((string) $permiso['slug']); ?></td>
                            <td><?php echo e((string) $permiso['nombre']); ?></td>
                            <td><?php echo (int) $permiso['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <small class="text-muted">Los permisos base no se eliminan físicamente.</small>
            </div>
        </div>
    <?php endforeach; ?>
</div>
