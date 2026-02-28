<?php
$logs = $logs ?? [];
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-fingerprint me-2 text-primary"></i> Importación de Logs Biométricos
            </h1>
            <p class="text-muted small mb-0 ms-1">Carga de marcas crudas, procesamiento y seguimiento de asistencia.</p>
        </div>

        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard RRHH
            </a>
            <a href="<?php echo e(route_url('asistencia/incidencias')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-clipboard2-pulse me-2 text-info"></i>Incidencias
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-4">
            <div class="row g-4 align-items-center">
                
                <div class="col-md-8 border-end-md pe-md-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-cloud-upload me-2 text-primary"></i>Cargar Archivo del Reloj
                    </h6>
                    <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                        <input type="hidden" name="accion" value="subir_txt">
                        <div class="col-md-8">
                            <label for="archivoTxtBiometrico" class="form-label small text-muted fw-bold">Archivo TXT (Delimitado por tabulaciones) <span class="text-danger">*</span></label>
                            <input id="archivoTxtBiometrico" type="file" name="archivo_txt" class="form-control bg-light border-secondary-subtle" accept=".txt,text/plain" required>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-primary shadow-sm w-100 fw-semibold" type="submit">
                                <i class="bi bi-upload me-2"></i>Subir Logs
                            </button>
                        </div>
                    </form>
                </div>

                <div class="col-md-4 ps-md-4">
                    <h6 class="fw-bold text-dark mb-3">
                        <i class="bi bi-cpu me-2 text-success"></i>Motor de Cálculo
                    </h6>
                    <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" onsubmit="return confirm('¿Deseas procesar todos los logs pendientes y calcular las tardanzas?');">
                        <input type="hidden" name="accion" value="procesar_asistencia">
                        <button class="btn btn-success shadow-sm w-100 fw-semibold py-2" type="submit">
                            <i class="bi bi-play-circle-fill me-2 fs-5 align-middle"></i>Procesar Pendientes
                        </button>
                    </form>
                    <p class="text-muted small mt-2 mb-0 text-center" style="font-size: 0.75rem;">
                        Cruza las marcas con los horarios para generar el Dashboard.
                    </p>
                </div>

            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
    <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <h2 class="h6 fw-bold text-dark mb-0">Registro Histórico de Marcas (Crudo)</h2>
        <div class="input-group shadow-sm" style="max-width: 300px;">
            <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
            <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchLogs" placeholder="Buscar empleado o código...">
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table align-middle mb-0 table-pro" id="tablaAsistenciaLogs"
                   data-erp-table="true"
                   data-search-input="#searchLogs"
                   data-pagination-controls="#logsPaginationControls"
                   data-pagination-info="#logsPaginationInfo">
                <thead>
                    <tr>
                        <th class="ps-4 text-secondary fw-semibold">ID</th>
                        <th class="text-secondary fw-semibold">Cód. Biométrico</th>
                        <th class="text-secondary fw-semibold">Fecha y Hora (Marca)</th>
                        <th class="text-secondary fw-semibold">Modo/Tipo</th>
                        <th class="text-secondary fw-semibold text-center">Estado</th>
                        <th class="text-end pe-4 text-secondary fw-semibold">Registro Sistema</th>
                    </tr>
                </thead>
                <tbody id="logsTableBody">
                    <?php if (empty($logs)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                Aún no se han importado logs biométricos.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $procesado = (int) ($log['procesado'] ?? 0) === 1;
                                $badgeColor = $procesado ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                $badgeTexto = $procesado ? 'Procesado' : 'Pendiente';
                                
                                // Construimos el texto para el buscador
                                $searchStr = strtolower(($log['id'] ?? '') . ' ' . ($log['nombre_completo'] ?? '') . ' ' . ($log['codigo_biometrico'] ?? '') . ' ' . ($log['tipo_marca'] ?? ''));
                            ?>
                            <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="ps-4 text-muted small align-top pt-3">
                                    #<?php echo str_pad((string)(int)($log['id'] ?? 0), 5, '0', STR_PAD_LEFT); ?>
                                </td>
                                <td class="align-top pt-3">
                                    <?php if (!empty($log['nombre_completo'])): ?>
                                        <div class="fw-bold text-dark" style="font-size: 0.95rem;">
                                            <?php echo e((string)$log['nombre_completo']); ?>
                                        </div>
                                        <div class="text-muted" style="font-size: 0.8rem;">
                                            <i class="bi bi-upc-scan me-1"></i>Cód: <span class="fw-semibold text-primary"><?php echo e((string) ($log['codigo_biometrico'] ?? '')); ?></span>
                                        </div>
                                    <?php else: ?>
                                        <span class="fw-semibold text-primary d-block">
                                            <?php echo e((string) ($log['codigo_biometrico'] ?? '')); ?>
                                        </span>
                                        <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;">
                                            Sin Empleado
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-medium text-dark align-top pt-3">
                                    <i class="bi bi-clock small text-muted me-1 opacity-50"></i>
                                    <?php echo e((string) ($log['fecha_hora_marca'] ?? '')); ?>
                                </td>
                                <td class="align-top pt-3">
                                    <span class="d-block text-dark"><?php echo e((string) ($log['tipo_marca'] ?? 'Desconocido')); ?></span>
                                    <small class="text-muted" style="font-size: 0.7rem;">Disp: <?php echo e((string) ($log['nombre_dispositivo'] ?? '')); ?></small>
                                </td>
                                <td class="text-center align-top pt-3">
                                    <span class="badge px-3 py-1 rounded-pill <?php echo $badgeColor; ?>" style="font-size: 0.75rem;">
                                        <?php echo $badgeTexto; ?>
                                    </span>
                                </td>
                                <td class="text-end pe-4 align-top pt-3">
                                    <span class="d-block text-secondary small"><?php echo e((string) ($log['created_at'] ?? '')); ?></span>
                                    <small class="text-muted" style="font-size: 0.7rem;">Por ID: <?php echo (int) ($log['created_by'] ?? 0); ?></small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if (!empty($logs)): ?>
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
            <div class="small text-muted fw-medium" id="logsPaginationInfo">Procesando...</div>
            <nav aria-label="Paginación">
                <ul class="pagination mb-0 shadow-sm" id="logsPaginationControls"></ul>
            </nav>
        </div>
        <?php endif; ?>
        
    </div>
</div>
</div>
