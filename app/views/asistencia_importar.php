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
        <div class="card-header bg-white border-bottom-0 pt-4 pb-0 ps-4">
            <h2 class="h6 fw-bold text-dark mb-0">Registro Histórico de Marcas (Crudo)</h2>
        </div>
        <div class="card-body p-0 mt-3">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro table-hover" id="tablaAsistenciaLogs">
                    <thead class="table-light border-bottom border-top">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">ID</th>
                            <th class="text-secondary fw-semibold">Cód. Biométrico</th>
                            <th class="text-secondary fw-semibold">Fecha y Hora (Marca)</th>
                            <th class="text-secondary fw-semibold">Modo/Tipo</th>
                            <th class="text-secondary fw-semibold text-center">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Registro Sistema</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <?php
                                $procesado = (int) ($log['procesado'] ?? 0) === 1;
                                $badgeColor = $procesado ? 'bg-success' : 'bg-warning text-dark';
                                $badgeTexto = $procesado ? 'Procesado' : 'Pendiente';
                            ?>
                            <tr class="border-bottom">
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
                                    <i class="bi bi-clock small text-muted me-1"></i>
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
