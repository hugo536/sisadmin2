<?php
$logs = $logs ?? [];
$filtros = $filtros ?? [];
$fechaInicio = (string) ($filtros['fecha_inicio'] ?? date('Y-m-d', strtotime('-1 month')));
$fechaFin = (string) ($filtros['fecha_fin'] ?? date('Y-m-d'));
$estadoFiltro = (string) ($filtros['estado'] ?? ''); // '' = Todos, '1' = Sincronizados, '0' = Pendientes

$pendientesCount = (int) ($pendientes_count ?? 0);
$logsFiltrados = $logs;
?>

<div class="container-fluid p-4" id="importarLogsApp">
    
    <!-- CABECERA -->
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-fingerprint me-2 text-primary"></i> Importación Biométrico
            </h1>
            <p class="text-muted small mb-0 ms-1">Carga de marcas crudas y sincronización con el panel semanal.</p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <!-- BOTÓN: Descartar Huérfanos (Color entero) -->
            <form id="formDescartar" method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" class="m-0 p-0">
                <input type="hidden" name="accion" value="descartar_huerfanos">
                <button class="btn btn-danger btn-sm shadow-sm fw-bold px-3 py-2 transition-hover d-flex align-items-center" type="submit" title="Descartar marcas sin empleado asignado">
                    <i class="bi bi-trash fs-6 me-2"></i>Limpiar Huérfanos
                </button>
            </form>

            <!-- BOTÓN: Sincronizar -->
            <form id="formSincronizar" method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" class="m-0 p-0" data-pendientes="<?php echo $pendientesCount; ?>">
                <input type="hidden" name="accion" value="procesar_asistencia">
                <button class="btn btn-success btn-sm shadow-sm fw-bold px-3 py-2 transition-hover d-flex align-items-center" type="submit" <?php echo $pendientesCount === 0 ? 'disabled' : ''; ?>>
                    <i class="bi bi-arrow-repeat fs-6 me-2"></i>Sincronizar (<?php echo $pendientesCount; ?>)
                </button>
            </form>
        </div>
    </div>

    <!-- FORMULARIO GET INVISIBLE (Une Filtro Estado y Fechas) -->
    <form method="get" action="<?php echo e(route_url('asistencia/importar')); ?>" id="formFiltros" class="d-none">
        <input type="hidden" name="ruta" value="asistencia/importar">
    </form>

    <!-- BARRA DE HERRAMIENTAS: ESTADO | SUBIDA | FECHAS -->
    <div class="card border-0 shadow-sm mb-4 fade-in">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center">
                
                <!-- 1. Filtro de Estado (Izquierda) -->
                <div class="col-12 col-md-3">
                    <select name="estado" form="formFiltros" class="form-select bg-white border-secondary-subtle shadow-sm text-secondary fw-medium" aria-label="Filtrar por estado" onchange="document.getElementById('formFiltros').requestSubmit();">
                        <option value="" <?php echo $estadoFiltro === '' ? 'selected' : ''; ?>>Todas las marcas</option>
                        <option value="1" <?php echo $estadoFiltro === '1' ? 'selected' : ''; ?>>Ya sincronizadas</option>
                        <option value="0" <?php echo $estadoFiltro === '0' ? 'selected' : ''; ?>>Faltan sincronizar</option>
                    </select>
                </div>

                <!-- 2. Subir Archivo (Centro - Formulario POST independiente) -->
                <div class="col-12 col-md-4">
                    <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" enctype="multipart/form-data" class="m-0">
                        <input type="hidden" name="accion" value="subir_txt">
                        <div class="input-group shadow-sm">
                            <input id="archivoTxtBiometrico" type="file" name="archivo_txt" class="form-control bg-light border-secondary-subtle" accept=".txt,text/plain" required>
                            <button type="submit" class="btn btn-primary fw-bold px-3 transition-hover" onclick="if(document.getElementById('archivoTxtBiometrico').files.length > 0) { this.innerHTML='<span class=\'spinner-border spinner-border-sm\'></span>'; this.classList.add('disabled'); }">
                                <i class="bi bi-cloud-upload"></i> Subir
                            </button>
                        </div>
                    </form>
                </div>

                <!-- 3. Filtro de Fechas Agrupado (Derecha) -->
                <div class="col-12 col-md-5">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0 border-secondary-subtle">Desde</span>
                        <input type="date" name="fecha_inicio" form="formFiltros" class="form-control bg-light border-start-0 border-end-0 border-secondary-subtle text-secondary fw-medium" value="<?php echo e($fechaInicio); ?>" required>
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0 border-secondary-subtle">Hasta</span>
                        <input type="date" name="fecha_fin" form="formFiltros" class="form-control bg-light border-start-0 border-secondary-subtle text-secondary fw-medium" value="<?php echo e($fechaFin); ?>" required>
                        
                        <!-- Botones aligerados -->
                        <button class="btn btn-light border border-secondary-subtle text-primary px-3 transition-hover" type="submit" form="formFiltros" title="Aplicar filtros" style="z-index: 0;">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        <a href="<?php echo e(route_url('asistencia/importar')); ?>" class="btn btn-light border border-secondary-subtle text-danger px-3 transition-hover" title="Limpiar filtros" style="z-index: 0;">
                            <i class="bi bi-eraser-fill"></i>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- TABLA DE LOGS -->
    <div class="card border-0 shadow-sm fade-in">
        
        <!-- CABECERA DE TABLA CON BUSCADOR -->
        <div class="card-header bg-white border-bottom pt-3 pb-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h6 class="mb-0 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-list-task me-2 text-primary fs-5"></i> Historial de Marcas
            </h6>
            
            <div class="input-group shadow-sm" style="max-width: 350px;">
                <span class="input-group-text bg-white border-end-0 border-secondary-subtle"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-white border-start-0 ps-0 border-secondary-subtle shadow-none" id="searchLogs" placeholder="Buscar empleado o ID...">
            </div>
        </div>

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
                            <th class="ps-4 text-secondary fw-semibold" style="width: 10%;">ID</th>
                            <th class="text-secondary fw-semibold" style="width: 45%;">Empleado / Cód. Biométrico</th>
                            <th class="text-secondary fw-semibold" style="width: 25%;">Fecha y Hora (Marca)</th>
                            <th class="text-center text-secondary fw-semibold" style="width: 20%;">Estado</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php if (empty($logsFiltrados)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="4" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No hay marcas registradas para los filtros seleccionados.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logsFiltrados as $log): ?>
                                <?php
                                    $procesado = (int) ($log['procesado'] ?? 0) === 1;
                                    $badgeColor = $procesado ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
                                    $badgeTexto = $procesado ? 'Sincronizado' : 'Pendiente';
                                    $searchStr = strtolower(($log['id'] ?? '') . ' ' . ($log['nombre_completo'] ?? '') . ' ' . ($log['codigo_biometrico'] ?? ''));
                                    
                                    // Formato de Fecha: dd/mm/yyyy hh:mm:ss
                                    $fechaHoraFormateada = '--/--/---- --:--:--';
                                    if (!empty($log['fecha_hora_marca'])) {
                                        $fechaHoraFormateada = date('d/m/Y H:i:s', strtotime((string)$log['fecha_hora_marca']));
                                    }
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
                                                Sin Empleado Asignado
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="fw-bold text-dark align-top pt-3">
                                        <i class="bi bi-clock small text-muted me-1"></i>
                                        <?php echo $fechaHoraFormateada; ?>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo $badgeColor; ?>" style="font-size: 0.75rem;">
                                            <?php echo $badgeTexto; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (!empty($logsFiltrados)): ?>
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

<script src="<?php echo e(asset_url('js/rrhh/importar_asistencia.js')); ?>?v=<?php echo time(); ?>"></script>
