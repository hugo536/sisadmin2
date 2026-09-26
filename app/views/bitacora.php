<?php 
$logs = $logs ?? []; 
$usuariosFiltro = $usuariosFiltro ?? []; 
$filtros = $filtros ?? []; 

// Helper para dar color al tipo de evento
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
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center mb-4 fade-in gap-3">
        <div>
            <h1 class="h4 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary fs-5"></i> Bitácora de Seguridad
            </h1>
            <p class="text-muted small mb-0 ms-1">Auditoría de eventos, accesos y actividad crítica del sistema.</p>
        </div>
        
        <button class="btn btn-light border shadow-sm text-secondary fw-semibold flex-shrink-0">
            <i class="bi bi-download me-2 text-info"></i>Exportar CSV
        </button>
    </div>

    <!-- PANEL DE FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center" id="filtrosBitacoraForm">
                <input type="hidden" name="ruta" value="bitacora/index">
                
                <div class="col-12 col-md-3 col-xl-2">
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

                <div class="col-12 col-md-5 col-xl-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input id="bitacoraSearch" name="evento" value="<?php echo e((string) ($filtros['evento'] ?? '')); ?>" class="form-control bg-light border-start-0 ps-0" placeholder="Buscar evento, detalle o IP..." data-auto-submit="input">
                    </div>
                </div>

                <div class="col-6 col-md-2 col-xl-2">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" title="Desde"><i class="bi bi-calendar-minus text-muted"></i></span>
                        <input type="date" name="fecha_inicio" value="<?php echo e((string) ($filtros['fecha_inicio'] ?? '')); ?>" class="form-control bg-light border-start-0 ps-0 text-muted" data-auto-submit="change">
                    </div>
                </div>

                <div class="col-6 col-md-2 col-xl-2">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0" title="Hasta"><i class="bi bi-calendar-plus text-muted"></i></span>
                        <input type="date" name="fecha_fin" value="<?php echo e((string) ($filtros['fecha_fin'] ?? '')); ?>" class="form-control bg-light border-start-0 ps-0 text-muted" data-auto-submit="change">
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA DE RESULTADOS -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <!-- Se agregó table-hover para mejorar la lectura de filas -->
                <table class="table table-hover align-middle mb-0 table-pro" id="bitacoraTable"
                       data-erp-table="true"
                       data-search-input="#bitacoraSearch"
                       data-pagination-controls="#bitacoraPaginationControls"
                       data-pagination-info="#bitacoraPaginationInfo">
                    <thead class="table-light">
                        <tr>
                            <!-- Usamos min-width en lugar de width:1% para evitar el parpadeo al cargar -->
                            <th class="ps-4 text-nowrap" style="min-width: 170px;">Fecha / Hora</th>
                            <th class="text-nowrap" style="min-width: 140px;">Evento</th>
                            <th class="text-nowrap" style="min-width: 160px;">Usuario</th>
                            <th style="min-width: 300px;">Descripción del Evento</th>
                            <th class="text-end pe-4 text-nowrap" style="min-width: 120px;">IP Origen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-5 text-muted">
                                    <i class="bi bi-inbox fs-1 d-block mb-3 text-light"></i>
                                    <h6 class="fw-semibold text-secondary">No hay eventos registrados</h6>
                                    <p class="small mb-0">Intenta cambiar los filtros de búsqueda o fechas.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                                <?php 
                                    // Formateo de fecha profesional
                                    $fechaRaw = (string)$log['created_at'];
                                    $fechaFormateada = $fechaRaw !== '' ? date('d/m/Y h:i A', strtotime($fechaRaw)) : '-';
                                    $searchString = mb_strtolower((string)$log['evento'] . ' ' . (string)$log['descripcion'] . ' ' . (string)$log['usuario'] . ' ' . (string)$log['ip_address']);
                                ?>
                                <tr data-search="<?php echo e($searchString); ?>">
                                    
                                    <td class="ps-4 text-nowrap">
                                        <div class="text-muted small fw-medium">
                                            <i class="bi bi-clock me-1 opacity-75"></i><?php echo $fechaFormateada; ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-nowrap">
                                        <span class="badge <?php echo $getEventoBadge((string)$log['evento']); ?> rounded-pill px-3 py-1 fw-bold" style="letter-spacing: 0.5px;">
                                            <?php echo e((string) $log['evento']); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-nowrap">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2 bg-secondary bg-opacity-10 text-secondary border border-secondary-subtle small fw-bold d-flex align-items-center justify-content-center" style="width:28px; height:28px; border-radius:50%;">
                                                <?php echo strtoupper(substr((string)$log['usuario'], 0, 1)); ?>
                                            </div>
                                            <span class="fw-semibold text-dark"><?php echo e((string) $log['usuario']); ?></span>
                                        </div>
                                    </td>
                                    
                                    <td>
                                        <!-- Al quitar el max-width, la columna absorberá todo el espacio libre fluidamente -->
                                        <div class="text-secondary small text-wrap text-break lh-sm">
                                            <?php echo e((string) $log['descripcion']); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-end pe-4 text-nowrap">
                                        <code class="text-secondary bg-light border px-2 py-1 rounded-2 small fw-bold">
                                            <?php echo e((string) $log['ip_address']); ?>
                                        </code>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
                <small class="text-muted fw-medium" id="bitacoraPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación bitácora">
                    <ul class="pagination pagination-sm mb-0 justify-content-end" id="bitacoraPaginationControls"></ul>
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