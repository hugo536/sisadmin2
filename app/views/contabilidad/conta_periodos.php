<?php
$periodos = $periodos ?? [];
$anio = (int)($anio ?? date('Y'));

// Helper rápido para los meses (para mejorar la UI)
$mesesNombres = [
    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
    5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
    9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
];

// ==========================================
// Capturador de alertas y errores
// ==========================================
$swalIcon = null;
$swalMessage = null;

if (!empty($_GET['error'])) {
    $swalIcon = 'error';
    $swalMessage = (string) $_GET['error'];
} elseif (!empty($_GET['ok'])) {
    $swalIcon = 'success';
    $swalMessage = 'Operación realizada correctamente.';
}
?>
<div class="container-fluid p-4">

    <?php if ($swalMessage !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal === 'undefined') {
                    return;
                }
                Swal.fire({
                    icon: <?php echo json_encode($swalIcon); ?>,
                    title: <?php echo json_encode($swalIcon === 'error' ? 'Error' : 'Éxito'); ?>,
                    text: <?php echo json_encode($swalMessage); ?>,
                    confirmButtonText: 'Entendido'
                });
            });
        </script>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-calendar3-range-fill me-2 text-primary"></i> Periodos Contables
            </h1>
            <p class="text-muted small mb-0 ms-1">Apertura y cierre de meses para el control de registros financieros.</p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoPeriodo">
                <i class="bi bi-calendar-plus-fill me-2"></i>Abrir Nuevo Periodo
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="contabilidad/periodos">
                
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Año a consultar</span>
                        <input type="number" class="form-control bg-light border-start-0 fw-bold text-primary" name="anio" value="<?php echo $anio; ?>" min="2000" max="2100">
                    </div>
                </div>
                
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-secondary w-100 shadow-sm">
                        <i class="bi bi-search me-2"></i> Ver Periodos
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center flex-wrap">
            <h6 class="fw-bold text-dark mb-2 mb-md-0"><i class="bi bi-list-columns-reverse text-primary me-2"></i>Listado de Periodos del Año <?php echo $anio; ?></h6>
            <div style="width: 250px;">
                <input type="text" id="periodosSearch" class="form-control form-control-sm bg-light" placeholder="Buscar por mes, estado...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro"
                       id="periodosTable" 
                       data-erp-table="true" 
                       data-search-input="#periodosSearch" 
                       data-rows-per-page="10">
                    <thead class="table-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold" style="width: 20%;">Periodo</th>
                            <th class="text-secondary fw-semibold">Fecha Inicio</th>
                            <th class="text-secondary fw-semibold">Fecha Fin</th>
                            <th class="text-center text-secondary fw-semibold" style="width: 15%;">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold" style="width: 20%;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($periodos)): ?>
                            <?php foreach ($periodos as $p): ?>
                                <?php 
                                    $estado = strtoupper(trim($p['estado']));
                                    $esAbierto = ($estado === 'ABIERTO');
                                    $mesNum = (int)$p['mes'];
                                    $nombreMes = $mesesNombres[$mesNum] ?? "Mes $mesNum";
                                    
                                    // Preparamos el texto para que el buscador del JS lo encuentre fácilmente
                                    $textoBusqueda = $p['anio'] . ' ' . $nombreMes . ' ' . $estado;
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars(strtolower($textoBusqueda)); ?>">
                                    <td class="ps-4 fw-bold text-dark pt-3">
                                        <?php echo (int)$p['anio']; ?> - <?php echo str_pad((string)$mesNum, 2, '0', STR_PAD_LEFT); ?>
                                        <span class="d-block text-muted fw-normal small"><?php echo $nombreMes; ?></span>
                                    </td>
                                    <td class="text-muted pt-3"><?php echo e($p['fecha_inicio']); ?></td>
                                    <td class="text-muted pt-3"><?php echo e($p['fecha_fin']); ?></td>
                                    <td class="text-center pt-3">
                                        <span class="badge px-3 py-2 rounded-pill <?php echo $esAbierto ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?>">
                                            <?php echo $esAbierto ? '<i class="bi bi-unlock-fill me-1"></i> Abierto' : '<i class="bi bi-lock-fill me-1"></i> Cerrado'; ?>
                                        </span>
                                    </td>
                                    <td class="text-end pe-4 pt-3">
                                        <?php if ($esAbierto): ?>
                                            <form method="post" action="<?php echo e(route_url('contabilidad/cerrar_periodo')); ?>" class="m-0 form-cerrar-periodo">
                                                <input type="hidden" name="id_periodo" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger px-3 rounded-pill fw-semibold" data-bs-toggle="tooltip" title="Cerrar Periodo">
                                                    <i class="bi bi-door-closed me-1"></i> Cerrar
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <form method="post" action="<?php echo e(route_url('contabilidad/abrir_periodo')); ?>" class="m-0 form-abrir-periodo">
                                                <input type="hidden" name="id_periodo" value="<?php echo (int)$p['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-success px-3 rounded-pill fw-semibold" data-bs-toggle="tooltip" title="Reabrir Periodo">
                                                    <i class="bi bi-door-open me-1"></i> Reabrir
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr class="empty-msg-row">
                                <td colspan="5" class="text-center text-muted py-5">
                                    <i class="bi bi-calendar-x fs-1 d-block mb-2 text-light"></i>
                                    No hay periodos registrados para el año <?php echo $anio; ?>.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="px-4 py-3 border-top">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
                    <span id="periodosPaginationInfo" class="text-muted small fw-medium"></span>
                    <nav aria-label="Navegación de tabla">
                        <ul id="periodosPaginationControls" class="pagination pagination-sm mb-0 shadow-sm"></ul>
                    </nav>
                </div>
            </div>

        </div>
    </div>

</div>

<div class="modal fade" id="modalNuevoPeriodo" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-sm modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i>Aperturar Periodo</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form method="post" action="<?php echo e(route_url('contabilidad/crear_periodo')); ?>">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold">Año <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control shadow-none text-center fw-bold fs-5 text-primary" name="anio" value="<?php echo $anio; ?>" required>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small text-muted fw-bold">Mes <span class="text-danger">*</span></label>
                                    
                                    <select class="form-select shadow-none" name="mes" required>
                                        <option value="" selected disabled>Seleccione el mes...</option>
                                        <?php foreach ($mesesNombres as $num => $nombre): ?>
                                            <option value="<?php echo $num; ?>"><?php echo str_pad((string)$num, 2, '0', STR_PAD_LEFT) . ' - ' . $nombre; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-2 mt-3">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border w-50" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold shadow-sm w-50">Abrir Mes</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script src="<?php echo e(asset_url('js/contabilidad/periodos.js')); ?>"></script>