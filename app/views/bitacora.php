<?php $logs = $logs ?? []; $usuariosFiltro = $usuariosFiltro ?? []; $filtros = $filtros ?? []; ?>
<div class="container-fluid">
    <h1 class="h3 mb-3">Bitácora de seguridad</h1>

    <div class="card mb-3"><div class="card-body">
        <form class="row g-2" method="get">
            <input type="hidden" name="ruta" value="bitacora/index">
            <div class="col-md-4">
                <select name="usuario" class="form-select">
                    <option value="">Todos los usuarios</option>
                    <?php foreach ($usuariosFiltro as $usuario): ?>
                        <option value="<?php echo (int) $usuario['id']; ?>" <?php echo ((string)$filtros['usuario'] === (string)$usuario['id'])?'selected':''; ?>><?php echo e((string) $usuario['usuario']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4"><input name="evento" value="<?php echo e((string) ($filtros['evento'] ?? '')); ?>" class="form-control" placeholder="Evento"></div>
            <div class="col-md-4"><button class="btn btn-primary">Filtrar</button></div>
        </form>
    </div></div>

    <div class="card"><div class="card-body table-responsive">
        <?php if ($logs === []): ?><div class="alert alert-light border">No hay resultados.</div><?php else: ?>
        <table class="table table-sm">
            <thead><tr><th>Fecha</th><th>Evento</th><th>Usuario</th><th>Descripción</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?php echo e((string) $log['created_at']); ?></td>
                    <td><?php echo e((string) $log['evento']); ?></td>
                    <td><?php echo e((string) $log['usuario']); ?></td>
                    <td><?php echo e((string) $log['descripcion']); ?></td>
                    <td><?php echo e((string) $log['ip_address']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div></div>
</div>
