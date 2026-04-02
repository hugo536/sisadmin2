<?php
$logs = $logs ?? [];
// Aseguramos que la lista de empleados esté disponible por si se necesita más adelante
$empleados = $empleados ?? []; 
?>

<div class="container-fluid p-4" id="importarLogsApp">
    
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-fingerprint me-2 text-primary"></i> Importación de Logs Biométricos
            </h1>
            <p class="text-muted small mb-0 ms-1">Carga de marcas crudas, procesamiento y seguimiento de asistencia.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover">
                <i class="bi bi-bar-chart-line me-2 text-info"></i>Dashboard RRHH
            </a>
            <a href="<?php echo e(route_url('asistencia/incidencias')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold transition-hover">
                <i class="bi bi-clipboard2-pulse me-2 text-info"></i>Incidencias
            </a>
            <div class="vr mx-1 d-none d-sm-block"></div>
            
            <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" onsubmit="return confirm('¿Deseas procesar todos los logs pendientes y calcular las tardanzas?');" class="m-0 p-0">
                <input type="hidden" name="accion" value="procesar_asistencia">
                <button class="btn btn-success shadow-sm fw-bold px-3 transition-hover" type="submit">
                    <i class="bi bi-play-circle-fill me-2"></i>Procesar Pendientes
                </button>
            </form>

            <button type="button" class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" data-bs-toggle="modal" data-bs-target="#modalCargarLogs">
                <i class="bi bi-cloud-upload me-2"></i>Cargar Archivo
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" class="form-control bg-light border-start-0 ps-0 border-secondary-subtle shadow-none" id="searchLogs" placeholder="Buscar empleado, código o ID...">
                    </div>
                </div>
                <div class="col-12 col-md-7 text-md-end mt-2 mt-md-0">
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill shadow-sm">
                        <i class="bi bi-list-columns-reverse me-1"></i> <?php echo count($logs); ?> Marcas registradas
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro table-hover" id="tablaAsistenciaLogs"
                       data-erp-table="true"
                       data-search-input="#searchLogs"
                       data-rows-selector="#logsTableBody tr:not(.empty-msg-row)"
                       data-empty-text="No se encontraron logs biométricos"
                       data-info-text-template="Mostrando {start} a {end} de {total} logs"
                       data-pagination-controls="#logsPaginationControls"
                       data-pagination-info="#logsPaginationInfo"
                       data-rows-per-page="15">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold col-w-5p">ID</th>
                            <th class="text-secondary fw-semibold col-w-25p">Empleado / Cód. Biométrico</th>
                            <th class="text-secondary fw-semibold col-w-20p">Fecha y Hora (Marca)</th>
                            <th class="text-secondary fw-semibold col-w-20p">Modo/Tipo</th>
                            <th class="text-center text-secondary fw-semibold col-w-10p">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold col-w-20p">Registro Sistema</th>
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
                                    $searchStr = strtolower(($log['id'] ?? '') . ' ' . ($log['nombre_completo'] ?? '') . ' ' . ($log['codigo_biometrico'] ?? '') . ' ' . ($log['tipo_marca'] ?? ''));
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 text-muted small align-top pt-3 fw-bold">
                                        #<?php echo str_pad((string)(int)($log['id'] ?? 0), 5, '0', STR_PAD_LEFT); ?>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <?php if (!empty($log['nombre_completo'])): ?>
                                            <div class="fw-bold text-dark fs-6">
                                                <?php echo e((string)$log['nombre_completo']); ?>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-upc-scan me-1"></i>Cód: <span class="fw-semibold text-primary"><?php echo e((string) ($log['codigo_biometrico'] ?? '')); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="fw-bold text-primary d-block fs-6 mb-1">
                                                <?php echo e((string) ($log['codigo_biometrico'] ?? '')); ?>
                                            </span>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2">
                                                Sin Empleado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="fw-bold text-dark align-top pt-3">
                                        <i class="bi bi-clock small text-muted me-1"></i>
                                        <?php echo e((string) ($log['fecha_hora_marca'] ?? '')); ?>
                                    </td>
                                    
                                    <td class="align-top pt-3">
                                        <span class="badge bg-light text-dark border shadow-sm px-2 py-1 mb-1">
                                            <?php echo e((string) ($log['tipo_marca'] ?? 'Desconocido')); ?>
                                        </span>
                                        <div class="text-muted small">
                                            Disp: <?php echo e((string) ($log['nombre_dispositivo'] ?? 'N/A')); ?>
                                        </div>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo $badgeColor; ?>">
                                            <?php echo $badgeTexto; ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-end pe-4 align-top pt-3">
                                        <span class="d-block text-secondary small fw-medium"><?php echo e((string) ($log['created_at'] ?? '')); ?></span>
                                        <small class="text-muted" style="font-size: 0.7rem;">Por ID Usuario: <?php echo (int) ($log['created_by'] ?? 0); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($logs)): ?>
            <div class="card-footer bg-white border-top-0 py-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 px-4">
                <small class="text-muted fw-semibold" id="logsPaginationInfo">Procesando...</small>
                <nav aria-label="Paginación de logs">
                    <ul class="pagination mb-0 shadow-sm" id="logsPaginationControls"></ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<div class="modal fade" id="modalCargarLogs" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-cloud-upload me-2"></i>Cargar Archivo del Reloj
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-start mb-4">
                    <i class="bi bi-info-circle-fill fs-4 me-3 mt-1 text-info"></i> 
                    <div class="small">
                        Asegúrese de que el archivo <strong>TXT</strong> esté delimitado por tabulaciones y provenga del software oficial del reloj biométrico (ZK Teco, etc).
                    </div>
                </div>

                <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="subir_txt">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4 text-center">
                            <i class="bi bi-file-earmark-text text-secondary opacity-50 mb-3 d-block" style="font-size: 3rem;"></i>
                            <label for="archivoTxtBiometrico" class="form-label text-dark fw-bold mb-2">Seleccione el archivo de marcas <span class="text-danger">*</span></label>
                            <input id="archivoTxtBiometrico" type="file" name="archivo_txt" class="form-control shadow-none border-primary border-2 text-primary fw-medium" accept=".txt,text/plain" required>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2 mt-3 pt-2 border-top">
                        <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm" onclick="if(document.getElementById('archivoTxtBiometrico').files.length > 0) { this.innerHTML='<span class=\'spinner-border spinner-border-sm me-1\'></span> Subiendo...'; this.classList.add('disabled'); }">
                            <i class="bi bi-upload me-1"></i> Subir y Leer Archivo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="/assets/js/rrhh/importar_asistencia.js"></script>