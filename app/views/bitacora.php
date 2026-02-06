<?php $logs = $logs ?? []; $usuariosFiltro = $usuariosFiltro ?? []; $filtros = $filtros ?? []; ?>
<div class="container-fluid p-4">
    <div class="mb-4 fade-in">
        <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
            <i class="bi bi-journal-text me-2 text-primary"></i> Bitácora de seguridad
        </h1>
        <p class="text-muted small mb-0 ms-1">Consulta de eventos críticos, accesos y actividad del sistema.</p>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form class="row g-2 align-items-center" method="get">
                <input type="hidden" name="ruta" value="bitacora/index">
                <div class="col-12 col-md-3">
                    <select name="usuario" class="form-select bg-light">
                        <option value="">Todos los usuarios</option>
                        <?php foreach ($usuariosFiltro as $usuario): ?>
                            <option value="<?php echo (int) $usuario['id']; ?>" <?php echo ((string) ($filtros['usuario'] ?? '') === (string) $usuario['id']) ? 'selected' : ''; ?>><?php echo e((string) $usuario['usuario']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input name="evento" value="<?php echo e((string) ($filtros['evento'] ?? '')); ?>" class="form-control bg-light border-start-0 ps-0" placeholder="Evento">
                    </div>
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_inicio" value="<?php echo e((string) ($filtros['fecha_inicio'] ?? '')); ?>" class="form-control bg-light" title="Fecha inicio">
                </div>
                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_fin" value="<?php echo e((string) ($filtros['fecha_fin'] ?? '')); ?>" class="form-control bg-light" title="Fecha fin">
                </div>
                <div class="col-12 col-md-1 d-grid">
                    <button class="btn btn-primary">Filtrar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="bitacoraTable">
                    <thead>
                    <tr>
                        <th class="ps-4">Fecha</th>
                        <th>Evento</th>
                        <th>Usuario</th>
                        <th>Descripción</th>
                        <th class="pe-4">IP</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if ($logs === []): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No hay resultados para los filtros seleccionados.</td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 text-muted small"><?php echo e((string) $log['created_at']); ?></td>
                                <td class="fw-semibold"><?php echo e((string) $log['evento']); ?></td>
                                <td><?php echo e((string) $log['usuario']); ?></td>
                                <td><?php echo e((string) $log['descripcion']); ?></td>
                                <td class="pe-4"><code><?php echo e((string) $log['ip_address']); ?></code></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
