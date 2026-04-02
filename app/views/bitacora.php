<?php 
$logs = $logs ?? []; 
$usuariosFiltro = $usuariosFiltro ?? []; 
$filtros = $filtros ?? []; 

// Helper simple para dar color al tipo de evento
$getEventoBadge = function(string $evento): string {
    $e = strtolower($evento);
    if (str_contains($e, 'login') || str_contains($e, 'sesión')) return 'bg-info-subtle text-info border-info-subtle';
    if (str_contains($e, 'error') || str_contains($e, 'fallido') || str_contains($e, 'anul')) return 'bg-danger-subtle text-danger border-danger-subtle';
    if (str_contains($e, 'crear') || str_contains($e, 'insert')) return 'bg-success-subtle text-success border-success-subtle';
    if (str_contains($e, 'editar') || str_contains($e, 'update')) return 'bg-warning-subtle text-warning-emphasis border-warning-subtle';
    if (str_contains($e, 'eliminar') || str_contains($e, 'delete')) return 'bg-danger-subtle text-danger border-danger-subtle';
    return 'bg-light text-secondary border';
};
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Bitácora de Seguridad
            </h1>
            <p class="text-muted small mb-0 ms-1">Auditoría de eventos, accesos y actividad crítica del sistema.</p>
        </div>
        
        <button class="btn btn-white border shadow-sm text-secondary fw-semibold">
            <i class="bi bi-download me-2 text-info"></i>Exportar
        </button>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center" id="filtrosBitacoraForm">
                <input type="hidden" name="ruta" value="bitacora/index">
                
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-person text-muted"></i></span>
                        <select name="usuario" class="form-select bg-light border-start-0 ps-0" data-auto-submit="change">
                            <option value="">Todos los usuarios</option>
                            <?php foreach ($usuariosFiltro as $usuario): ?>
                                <option value="<?php echo (int) $usuario['id']; ?>" <?php echo ((string) ($filtros['usuario'] ?? '') === (string) $usuario['id']) ? 'selected' : ''; ?>>
                                    <?php echo e((string) $usuario['usuario']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input id="bitacoraSearch" name="evento" value="<?php echo e((string) ($filtros['evento'] ?? '')); ?>" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar evento o descripción..." data-auto-submit="input">
                    </div>
                </div>

                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_inicio" value="<?php echo e((string) ($filtros['fecha_inicio'] ?? '')); ?>" class="form-control bg-light text-muted" title="Fecha inicio" data-auto-submit="change">
                </div>

                <div class="col-6 col-md-2">
                    <input type="date" name="fecha_fin" value="<?php echo e((string) ($filtros['fecha_fin'] ?? '')); ?>" class="form-control bg-light text-muted" title="Fecha fin" data-auto-submit="change">
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="bitacoraTable"
                       data-erp-table="true"
                       data-search-input="#bitacoraSearch"
                       data-pagination-controls="#bitacoraPaginationControls"
                       data-pagination-info="#bitacoraPaginationInfo">
                    <thead>
                        <tr class="empty-msg-row">
                            <th class="ps-4 col-w-180">Fecha / Hora</th>
                            <th class="col-w-150">Evento</th>
                            <th class="col-w-200">Usuario</th>
                            <th>Descripción</th>
                            <th class="text-end pe-4 col-w-150">IP Origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted"><i class="bi bi-search fs-1 d-block mb-2"></i>No hay eventos registrados con estos filtros.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <tr data-search="<?php echo e(mb_strtolower((string) $log['evento'] . ' ' . (string) $log['descripcion'] . ' ' . (string) $log['usuario'] . ' ' . (string) $log['ip_address'])); ?>">
                                    <td class="ps-4">
                                        <div class="small text-muted">
                                            <i class="bi bi-calendar3 me-1"></i><?php echo e((string) $log['created_at']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $getEventoBadge((string)$log['evento']); ?> border rounded-pill px-3">
                                            <?php echo e((string) $log['evento']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2 bg-light text-secondary border small fw-bold d-flex align-items-center justify-content-center" style="width:24px; height:24px; border-radius:50%;">
                                                <?php echo strtoupper(substr((string)$log['usuario'], 0, 1)); ?>
                                            </div>
                                            <span class="fw-semibold text-dark"><?php echo e((string) $log['usuario']); ?></span>
                                        </div>
                                    </td>
                                    <td class="text-secondary">
                                        <?php echo e((string) $log['descripcion']); ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <code class="text-muted bg-light border px-2 py-1 rounded small"><?php echo e((string) $log['ip_address']); ?></code>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3">
                <small class="text-muted" id="bitacoraPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación bitácora">
                    <ul class="pagination mb-0 justify-content-end" id="bitacoraPaginationControls"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('filtrosBitacoraForm');
    if (!form) return;

    let timer = null;
    const debounceSubmit = () => {
        clearTimeout(timer);
        timer = setTimeout(() => form.submit(), 350);
    };

    form.querySelectorAll('[data-auto-submit="change"]').forEach((field) => {
        field.addEventListener('change', () => form.submit());
    });

    form.querySelectorAll('[data-auto-submit="input"]').forEach((field) => {
        field.addEventListener('input', debounceSubmit);
    });
});
</script>
