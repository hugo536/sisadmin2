<?php
$logs = $logs ?? [];
?>

<div class="container-fluid py-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h4 mb-0">Importación de Logs Biométricos</h1>
            <small class="text-muted">Bloque 2 y 3 · Carga cruda, procesamiento y seguimiento.</small>
        </div>
        <div class="d-flex gap-2">
            <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-outline-primary">
                <i class="bi bi-bar-chart-line me-1"></i> Dashboard RRHH
            </a>
            <a href="<?php echo e(route_url('asistencia/incidencias')); ?>" class="btn btn-outline-secondary">
                <i class="bi bi-clipboard2-pulse me-1"></i> Incidencias
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body">
            <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
                <input type="hidden" name="accion" value="subir_txt">
                <div class="col-md-7">
                    <label for="archivoTxtBiometrico" class="form-label">Archivo TXT del biométrico</label>
                    <input id="archivoTxtBiometrico" type="file" name="archivo_txt" class="form-control" accept=".txt,text/plain" required>
                </div>
                <div class="col-md-3 d-grid">
                    <button class="btn btn-primary" type="submit">
                        <i class="bi bi-upload me-1"></i> Subir Log de Biométrico
                    </button>
                </div>
            </form>
            <form method="post" action="<?php echo e(route_url('asistencia/importar')); ?>" class="mt-2" onsubmit="return confirm('¿Deseas procesar todos los logs pendientes?');">
                <input type="hidden" name="accion" value="procesar_asistencia">
                <button class="btn btn-success" type="submit">
                    <i class="bi bi-play-circle me-1"></i> Procesar Asistencia Pendiente
                </button>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white">
            <h2 class="h6 mb-0">Logs cargados</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle mb-0" id="tablaAsistenciaLogs">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Código Biométrico</th>
                            <th>Fecha/Hora</th>
                            <th>Tipo Marca</th>
                            <th>Dispositivo</th>
                            <th>Procesado</th>
                            <th>Creado</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo (int) ($log['id'] ?? 0); ?></td>
                                <td><?php echo e((string) ($log['codigo_biometrico'] ?? '')); ?></td>
                                <td><?php echo e((string) ($log['fecha_hora_marca'] ?? '')); ?></td>
                                <td><?php echo e((string) ($log['tipo_marca'] ?? '')); ?></td>
                                <td><?php echo e((string) ($log['nombre_dispositivo'] ?? '')); ?></td>
                                <td><?php echo ((int) ($log['procesado'] ?? 0) === 1) ? '<span class="badge text-bg-success">Sí</span>' : '<span class="badge text-bg-warning">No</span>'; ?></td>
                                <td><?php echo e((string) ($log['created_at'] ?? '')); ?></td>
                                <td><?php echo (int) ($log['created_by'] ?? 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
