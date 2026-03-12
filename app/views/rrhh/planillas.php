<?php
// Inicialización segura de variables
$loteActual = $lote_actual ?? null; 
$lotesRecientes = $lotes_recientes ?? [];
$detallesNomina = $detalles_nomina ?? []; 
$cuentas = $cuentas ?? []; 
$metodos = $metodos ?? [];

$estadoLote = strtoupper((string)($loteActual['estado'] ?? 'BORRADOR'));

// Extraemos totales para los KPIs sumando dinámicamente los detalles
$nominaTotal = 0.0;
$deducciones = 0.0;
$netoPagar = 0.0;
$hayConflictos = false;

if (!empty($detallesNomina)) {
    foreach ($detallesNomina as $det) {
        $nominaTotal += (float) ($det['total_percepciones'] ?? 0);
        $deducciones += (float) ($det['total_deducciones'] ?? 0);
        $netoPagar += (float) ($det['neto_a_pagar'] ?? 0);
        if (!empty($det['tiene_conflicto'])) {
            $hayConflictos = true;
        }
    }
}
?>

<div class="container-fluid p-4" id="procesamientoNominaApp">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-wallet-fill me-2 text-primary"></i> Procesamiento de Nómina
            </h1>
            <p class="text-muted small mb-0 ms-1">Generación de lotes, consolidación financiera, bonos y dispersión de pagos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <div class="dropdown me-2">
                <button class="btn btn-white border shadow-sm text-secondary fw-semibold dropdown-toggle transition-hover" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-folder2-open me-2 text-warning"></i>
                    <?php echo $loteActual ? htmlspecialchars($loteActual['referencia']) : 'Seleccionar Lote...'; ?>
                </button>
                <ul class="dropdown-menu border-0 shadow">
                    <?php foreach ($lotesRecientes as $l): ?>
                        <li><a class="dropdown-item fw-medium py-2" href="?ruta=planillas&id_lote=<?php echo (int)$l['id']; ?>"><?php echo htmlspecialchars($l['referencia'] . ' - ' . $l['nombre']); ?></a></li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-primary fw-bold py-2" href="?ruta=nominas/historial"><i class="bi bi-search me-2"></i>Ver todos los lotes</a></li>
                </ul>
            </div>

            <?php if ($loteActual && $estadoLote === 'BORRADOR'): ?>
                <?php if ($netoPagar > 0): ?>
                    <form method="post" action="<?php echo e(route_url('planillas/aprobar')); ?>" class="m-0" id="formAprobarLote">
                        <input type="hidden" name="id_lote" value="<?php echo (int)$loteActual['id']; ?>">
                        <button type="submit" 
                            class="btn btn-primary shadow-sm fw-bold px-3 transition-hover"
                            data-hay-conflictos="<?php echo $hayConflictos ? 'true' : 'false'; ?>">
                            <i class="bi bi-check-circle me-2"></i>Aprobar Lote
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary shadow-sm fw-bold px-3 opacity-50" disabled title="No hay datos para aprobar">
                        <i class="bi bi-check-circle me-2"></i>Lote Vacío
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <button class="btn btn-success shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalGenerarLote">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Lote
            </button>
        </div>
    </div>

    <?php if (!empty($_GET['error'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Error al pagar', text: '<?php echo addslashes($_GET['error']); ?>', confirmButtonText: 'Revisar' });
                } else {
                    alert('Error: <?php echo addslashes($_GET['error']); ?>');
                }
            });
        </script>
    <?php endif; ?>
    <?php if (!empty($_GET['ok'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: '¡Éxito!', text: '<?php echo addslashes($_GET['ok']); ?>', confirmButtonText: 'Genial' });
                }
            });
        </script>
    <?php endif; ?>

    <?php if (!$loteActual): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-body p-5 text-center">
                <i class="bi bi-folder-plus fs-1 text-muted opacity-25 d-block mb-3" style="font-size: 4rem !important;"></i>
                <h5 class="fw-bold text-dark">No hay un lote de nómina seleccionado</h5>
                <p class="text-muted mb-4">Seleccione un lote en el menú superior o genere uno nuevo para consolidar los pagos del periodo.</p>
                <button class="btn btn-primary shadow-sm fw-bold px-4 py-2 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalGenerarLote">
                    Generar Nuevo Lote
                </button>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100 bg-light border-start border-secondary border-4">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <p class="small text-muted text-uppercase fw-bold mb-1">Percepciones (Bruto + Bonos)</p>
                            <h3 class="fw-bold text-dark mb-0">S/ <?php echo number_format($nominaTotal, 2); ?></h3>
                        </div>
                        <div class="bg-white p-2 rounded-circle shadow-sm text-secondary"><i class="bi bi-graph-up-arrow fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100 bg-light border-start border-danger border-4">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <p class="small text-muted text-uppercase fw-bold mb-1">Deducciones Total</p>
                            <h3 class="fw-bold text-danger mb-0">- S/ <?php echo number_format($deducciones, 2); ?></h3>
                        </div>
                        <div class="bg-white p-2 rounded-circle shadow-sm text-danger"><i class="bi bi-graph-down-arrow fs-4"></i></div>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100 bg-primary text-white border-start border-info border-4">
                    <div class="card-body p-4 d-flex justify-content-between align-items-center">
                        <div>
                            <p class="small text-white-50 text-uppercase fw-bold mb-1">Neto a Desembolsar</p>
                            <h3 class="fw-bold mb-0">S/ <?php echo number_format($netoPagar, 2); ?></h3>
                        </div>
                        <div class="bg-white bg-opacity-25 p-2 rounded-circle text-white"><i class="bi bi-cash-stack fs-4"></i></div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body p-5 d-flex justify-content-center">
                <div class="d-flex align-items-center w-75 position-relative">
                    
                    <?php 
                        $progreso = ($estadoLote === 'BORRADOR') ? 0 : (($estadoLote === 'APROBADO') ? 50 : 100); 
                    ?>
                    
                    <div class="progress w-100 shadow-sm" style="height: 4px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progreso; ?>%;"></div>
                    </div>
                    
                    <button class="position-absolute top-50 start-0 translate-middle btn btn-sm rounded-pill shadow-sm <?php echo $progreso >= 0 ? 'btn-success' : 'btn-secondary'; ?>" style="width: 2.5rem; height:2.5rem;" title="Borrador"><i class="bi bi-calculator fs-5"></i></button>
                    <button class="position-absolute top-50 start-50 translate-middle btn btn-sm rounded-pill shadow-sm <?php echo $progreso >= 50 ? 'btn-success' : 'btn-secondary'; ?>" style="width: 2.5rem; height:2.5rem;" title="Aprobado"><i class="bi bi-check2-all fs-5"></i></button>
                    <button class="position-absolute top-50 start-100 translate-middle btn btn-sm rounded-pill shadow-sm <?php echo $progreso >= 100 ? 'btn-success' : 'btn-secondary'; ?>" style="width: 2.5rem; height:2.5rem;" title="Pagado"><i class="bi bi-bank fs-5"></i></button>
                    
                    <div class="position-absolute top-50 start-0 translate-middle-x mt-4 small fw-bold <?php echo $progreso >= 0 ? 'text-success' : 'text-muted'; ?>">Consolidado</div>
                    <div class="position-absolute top-50 start-50 translate-middle-x mt-4 small fw-bold <?php echo $progreso >= 50 ? 'text-success' : 'text-muted'; ?>">Aprobado</div>
                    <div class="position-absolute top-50 start-100 translate-middle-x mt-4 small fw-bold <?php echo $progreso >= 100 ? 'text-success' : 'text-muted'; ?>">Pagado</div>
                    
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-bottom pt-3 pb-0 px-4">
                <ul class="nav nav-tabs border-bottom-0 fw-semibold" id="nominaTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active text-primary border-0 border-bottom border-primary border-3 bg-transparent pb-3 px-4" id="recibos-tab" data-bs-toggle="tab" data-bs-target="#recibos-pane" type="button" role="tab"><i class="bi bi-people me-2"></i>Recibos (Empleados)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-muted border-0 bg-transparent pb-3 px-4" id="tesoreria-tab" data-bs-toggle="tab" data-bs-target="#tesoreria-pane" type="button" role="tab"><i class="bi bi-bank me-2"></i>Tesorería (Dispersión)</button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-0">
                <div class="tab-content" id="nominaTabContent">
                    
                    <div class="tab-pane fade show active" id="recibos-pane" role="tabpanel" tabindex="0">
                        <div class="p-3 border-bottom bg-light">
                            <div class="row g-2 align-items-center justify-content-between">
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="search" class="form-control bg-white border-secondary-subtle border-start-0 ps-0 shadow-none" id="searchDetalles" placeholder="Buscar empleado...">
                                    </div>
                                </div>
                                <div class="col-12 col-md-auto text-end">
                                    <?php if ($estadoLote !== 'BORRADOR' && !empty($detallesNomina)): ?>
                                        <a href="?ruta=planillas/imprimir_masivo&id_lote=<?php echo (int)($loteActual['id']); ?>" target="_blank" class="btn btn-sm btn-outline-danger fw-bold shadow-sm transition-hover">
                                            <i class="bi bi-printer-fill me-2"></i>Imprimir Tiras (Masivo)
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-pro table-hover" id="tablaDetallesNomina"
                                   data-erp-table="true"
                                   data-search-input="#searchDetalles"
                                   data-rows-selector="#detallesTableBody tr:not(.empty-msg-row)"
                                   data-empty-text="No hay recibos generados en este lote"
                                   data-rows-per-page="15">
                                <thead class="table-light border-bottom">
                                    <tr>
                                        <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                                        <th class="text-center text-secondary fw-semibold">Días / Horas</th>
                                        <th class="text-end text-secondary fw-semibold">Percepciones</th>
                                        <th class="text-end text-secondary fw-semibold">Deducciones</th>
                                        <th class="text-center text-secondary fw-semibold">Movimientos</th>
                                        <th class="text-end text-dark fw-bold">Neto a Pagar</th>
                                        <th class="text-center text-secondary fw-semibold pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="detallesTableBody">
                                    <?php if (empty($detallesNomina)): ?>
                                        <tr class="empty-msg-row border-bottom-0">
                                            <td colspan="7" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                                No hay recibos generados.<br>
                                                <small>Es posible que los empleados ya hayan sido pagados en estas fechas.</small>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($detallesNomina as $row): ?>
                                            <?php 
                                                $searchStr = strtolower($row['nombre_completo'] ?? ''); 
                                                $tieneBono = ((float)($row['monto_bonos'] ?? 0) > 0); 
                                                $descuentoAdelanto = (float)($row['descuento_adelanto'] ?? 0);
                                                
                                                // NUNCA oculta a los que tienen problemas (tiene_conflicto)
                                                $tieneDeduccion = ((float)($row['total_deducciones'] ?? 0) > 0);
                                                if ($row['neto_a_pagar'] <= 0 && $row['dias_pagados'] == 0 && !$tieneBono && !$tieneDeduccion && empty($row['tiene_conflicto'])) {
                                                    continue;
                                                }
                                            ?>
                                            <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                                
                                                <td class="ps-4 fw-semibold text-dark align-top pt-3">
                                                    <?php echo htmlspecialchars((string) ($row['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                    <div class="small text-muted fw-normal mt-1">
                                                        <?php echo htmlspecialchars((string) ($row['cargo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> 
                                                        | <span class="badge bg-light text-secondary border border-secondary-subtle"><?php echo htmlspecialchars((string) ($row['frecuencia'] ?? 'MENSUAL'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                </td>

                                                <td class="text-center align-top pt-3">
                                                    <?php if (!empty($row['tiene_conflicto'])): ?>
                                                        <div class="d-flex flex-column align-items-center">
                                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle mb-1 px-2 py-1 shadow-sm" title="Marcaciones incompletas" style="font-size: 0.65rem;">
                                                                <i class="bi bi-exclamation-triangle-fill me-1"></i>Incompleto
                                                            </span>
                                                            <a href="?ruta=asistencia/dashboard&periodo=rango&fecha_inicio=<?php echo $loteActual['fecha_inicio']; ?>&fecha_fin=<?php echo $loteActual['fecha_fin']; ?>&id_tercero=<?php echo $row['id_tercero']; ?>" 
                                                            class="text-danger small fw-bold text-decoration-none hover-underline" style="font-size: 0.75rem;">
                                                                <i class="bi bi-pencil-square me-1"></i>Corregir
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="fw-bold fs-6 text-dark">
                                                            <?= (float)($row['dias_pagados'] ?? 0) ?>D
                                                        </span>
                                                        <br>
                                                        <span class="text-muted fw-medium" style="font-size: 0.8rem;">
                                                            <?php 
                                                                $hAcum = (float)($row['horas_acumuladas'] ?? 0);
                                                                $horasCompletas = floor($hAcum);
                                                                $minutosRestantes = round(($hAcum - $horasCompletas) * 60);
                                                                if ($minutosRestantes >= 60) {
                                                                    $horasCompletas += intdiv((int)$minutosRestantes, 60);
                                                                    $minutosRestantes = $minutosRestantes % 60;
                                                                }
                                                                echo "{$horasCompletas}h " . ($minutosRestantes > 0 ? "{$minutosRestantes}m" : "00m");
                                                            ?>
                                                        </span>
                                                    <?php endif; ?>

                                                <td class="text-end align-top pt-3">
                                                    <div class="fw-bold <?php echo !empty($row['tiene_conflicto']) ? 'text-muted opacity-50' : 'text-success'; ?>">
                                                        S/ <?php echo number_format((float)($row['total_percepciones'] ?? 0), 2); ?>
                                                    </div>
                                                    
                                                    <?php if (empty($row['tiene_conflicto']) && (float)($row['pago_por_hora'] ?? 0) > 0): ?>
                                                        <div class="small text-muted fw-medium mb-1 mt-1" style="font-size: 0.7rem;">
                                                            Tarifa: S/ <?php echo number_format((float)($row['pago_por_hora']), 2); ?>/hr
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if(($row['pago_horas_extras'] ?? 0) > 0): ?>
                                                        <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle shadow-sm" style="font-size: 0.65rem;" title="<?= (float)($row['horas_extras'] ?? 0) ?>h extras a S/ <?= number_format((float)($row['pago_por_hora'] ?? 0), 2) ?>/hr">
                                                            <i class="bi bi-clock-fill me-1"></i>+S/ <?= number_format((float)$row['pago_horas_extras'], 2) ?> HE
                                                        </span>
                                                    <?php endif; ?>

                                                </td>

                                                <td class="text-end align-top pt-3">
                                                    <div class="fw-bold <?php echo !empty($row['tiene_conflicto']) ? 'text-muted opacity-50' : 'text-danger'; ?>">
                                                        - S/ <?php echo number_format((float)($row['total_deducciones'] ?? 0), 2); ?>
                                                    </div>
                                                    
                                                    <?php if($descuentoAdelanto > 0): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-2 shadow-sm" style="font-size: 0.65rem;" title="Se cobró S/ <?php echo $descuentoAdelanto; ?> por adelantos previos">
                                                            <i class="bi bi-wallet2 me-1"></i>Cobro Adelanto
                                                        </span>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="align-top pt-3 text-center">
                                                    <?php
                                                        $movimientos = $row['movimientos_manuales'] ?? [];
                                                        if (is_string($movimientos)) {
                                                            $movimientos = array_filter(explode('||', $movimientos));
                                                        }
                                                    ?>
                                                    <?php if (empty($movimientos)): ?>
                                                        <span class="text-muted small opacity-50">-</span>
                                                    <?php else: ?>
                                                        <div class="d-flex flex-column align-items-center gap-1">
                                                            <?php foreach ($movimientos as $mov): ?>
                                                                <?php
                                                                    $tipo = '';
                                                                    $categoria = '';
                                                                    $montoMov = 0.0;
                                                                    if (is_array($mov)) {
                                                                        $tipo = (string)($mov['tipo'] ?? 'Movimiento');
                                                                        $categoria = (string)($mov['categoria'] ?? 'Sin categoría');
                                                                        $montoMov = (float)($mov['monto'] ?? 0);
                                                                    } else {
                                                                        $partes = explode('::', (string)$mov);
                                                                        $tipoRaw = strtoupper((string)($partes[0] ?? ''));
                                                                        $tipo = $tipoRaw === 'PERCEPCION' ? 'Percepción' : ($tipoRaw === 'DEDUCCION' ? 'Deducción' : 'Movimiento');
                                                                        $categoria = (string)($partes[1] ?? 'Sin categoría');
                                                                        $montoMov = (float)str_replace(',', '', (string)($partes[3] ?? 0));
                                                                    }
                                                                    $badgeClass = stripos($tipo, 'Deducción') !== false
                                                                        ? 'bg-danger-subtle text-danger border border-danger-subtle'
                                                                        : 'bg-success-subtle text-success border border-success-subtle';
                                                                    $textoBadge = $tipo . ': ' . $categoria . ' S/ ' . number_format($montoMov, 2);
                                                                ?>
                                                                <span class="badge shadow-sm <?php echo $badgeClass; ?>" style="font-size:0.65rem;" title="<?php echo htmlspecialchars($textoBadge, ENT_QUOTES, 'UTF-8'); ?>">
                                                                    <?php echo htmlspecialchars($textoBadge, ENT_QUOTES, 'UTF-8'); ?>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>

                                                <td class="text-end fw-bold align-top pt-3">
                                                    <span class="<?php echo !empty($row['tiene_conflicto']) ? 'text-muted opacity-50' : 'text-primary'; ?> fs-6">
                                                        S/ <?php echo number_format((float)($row['neto_a_pagar'] ?? 0), 2); ?>
                                                    </span>
                                                </td>

                                                <td class="text-center pe-4 align-top pt-3">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <?php if ($estadoLote === 'BORRADOR'): ?>
                                                            <?php if (!empty($row['tiene_conflicto'])): ?>
                                                                <button type="button" class="btn btn-sm btn-light text-muted border-0 rounded-circle shadow-sm" disabled title="Corrija la asistencia para desbloquear opciones">
                                                                    <i class="bi bi-lock-fill fs-5"></i>
                                                                </button>
                                                            <?php else: ?>
                                                                <button type="button" class="btn btn-sm btn-light text-warning border-0 rounded-circle shadow-sm transition-hover btn-ajustar-empleado" 
                                                                        data-bs-toggle="modal" data-bs-target="#modalAjustarNomina"
                                                                        data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                                                                        data-nombre="<?php echo htmlspecialchars($row['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                        data-bs-toggle="tooltip" title="Editar movimientos adicionales">
                                                                    <i class="bi bi-sliders fs-5"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        <?php endif; ?>

                                                        <?php if (empty($row['tiene_conflicto']) && $estadoLote !== 'BORRADOR'): ?>
                                                            <a href="?ruta=planillas/imprimir_boleta&id=<?php echo (int)($row['id'] ?? 0); ?>" target="_blank" class="btn btn-sm btn-light text-secondary border-0 rounded-circle shadow-sm transition-hover" data-bs-toggle="tooltip" title="Ver Boleta Individual">
                                                                <i class="bi bi-file-earmark-pdf fs-5"></i>
                                                            </a>
                                                        <?php endif; ?>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade p-4" id="tesoreria-pane" role="tabpanel" tabindex="0">
                        <?php if ($estadoLote === 'BORRADOR'): ?>
                            <div class="alert alert-secondary border-0 shadow-sm small text-center p-5 rounded-3">
                                <i class="bi bi-info-circle fs-1 d-block mb-3 text-muted opacity-50"></i>
                                <span class="fs-6">Debes <strong>Aprobar el Lote</strong> en la parte superior antes de poder configurar y registrar la dispersión de pagos.</span>
                            </div>
                        <?php elseif ($estadoLote === 'APROBADO'): ?>
                            <form method="post" action="<?php echo e(route_url('planillas/pagar_lote_mixto')); ?>" id="formDispersionMasiva">
                                <input type="hidden" name="id_lote" value="<?php echo (int)($loteActual['id'] ?? 0); ?>">
                                
                                <div class="card bg-light border border-secondary-subtle shadow-sm mb-4">
                                    <div class="card-body p-4">
                                        <div class="row align-items-end g-3">
                                            <div class="col-md-5">
                                                <label class="form-label small fw-bold text-muted mb-1">Cuenta de la empresa por defecto</label>
                                                <select id="cuentaGlobal" class="form-select shadow-none border-secondary-subtle fw-semibold">
                                                    <option value="" selected>Seleccione una cuenta general (Origen)...</option>
                                                    <?php foreach ($cuentas as $c): ?>
                                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre'] . ' (' . $c['moneda'] . ')'); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            <div class="col-md-3">
                                                <button type="button" class="btn btn-secondary shadow-sm w-100 fw-bold transition-hover" id="btnAplicarCuentaGlobal">
                                                    <i class="bi bi-arrow-down me-2"></i>Aplicar a todos
                                                </button>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="submit" class="btn btn-success shadow-sm w-100 fw-bold fs-6 transition-hover">
                                                    <i class="bi bi-check2-all me-2"></i>Ejecutar Pagos
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive border border-secondary-subtle rounded-3 shadow-sm">
                                    <table class="table align-middle table-hover mb-0 bg-white">
                                        <thead class="table-light border-bottom">
                                            <tr>
                                                <th class="ps-4 text-secondary fw-semibold py-3">Empleado</th>
                                                <th class="text-end text-secondary fw-semibold py-3">Neto a Pagar</th>
                                                <th class="text-secondary fw-semibold ps-4 py-3" style="width: 30%">Método de Pago (Destino)</th>
                                                <th class="text-secondary fw-semibold pe-4 py-3" style="width: 30%">Cuenta Empresa (Origen)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($detallesNomina as $det): 
                                                if ($det['neto_a_pagar'] <= 0) continue;
                                                $cuentasBanco = $det['cuentas_bancarias'] ?? [];
                                            ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-dark py-3">
                                                        <?php echo htmlspecialchars($det['nombre_completo']); ?>
                                                        <input type="hidden" name="pagos[<?php echo $det['id']; ?>][monto]" value="<?php echo $det['neto_a_pagar']; ?>">
                                                    </td>
                                                    <td class="text-end fw-bold text-primary fs-6 py-3">
                                                        S/ <?php echo number_format($det['neto_a_pagar'], 2); ?>
                                                    </td>
                                                    <td class="ps-4 py-3">
                                                        <select name="pagos[<?php echo $det['id']; ?>][metodo]" class="form-select form-select-sm shadow-none border-secondary-subtle fw-semibold" required>
                                                            <option value="EFECTIVO">💵 Efectivo (Caja Fuerte)</option>
                                                            <?php if (!empty($cuentasBanco)): ?>
                                                                <optgroup label="Cuentas Bancarias Registradas">
                                                                    <?php foreach($cuentasBanco as $cb): ?>
                                                                        <option value="BANCO_<?php echo $cb['id']; ?>">
                                                                            🏦 <?php echo htmlspecialchars($cb['entidad'] . ' - ' . ($cb['numero_mostrar'] ?? $cb['numero_cuenta'])); ?>
                                                                        </option>
                                                                    <?php endforeach; ?>
                                                                </optgroup>
                                                            <?php endif; ?>
                                                        </select>
                                                    </td>
                                                    <td class="pe-4 py-3">
                                                        <select name="pagos[<?php echo $det['id']; ?>][id_cuenta_origen]" class="form-select form-select-sm shadow-none border-secondary-subtle fw-semibold select-origen-row" required>
                                                            <option value="" selected disabled>Seleccione origen...</option>
                                                            <?php foreach ($cuentas as $c): ?>
                                                                <option value="<?php echo (int)$c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-success border-success-subtle bg-success-subtle shadow-sm mb-4 p-4 d-flex align-items-center rounded-3">
                                <i class="bi bi-check-circle-fill text-success fs-1 me-4"></i>
                                <div>
                                    <h5 class="fw-bold mb-1 text-success-emphasis">¡Lote Pagado y Dispersado!</h5>
                                    <p class="text-success-emphasis mb-0 small fw-medium">Los saldos se han descontado correctamente en Tesorería. A continuación, el detalle histórico de la operación:</p>
                                </div>
                            </div>

                            <div class="card border-0 shadow-sm">
                                <div class="card-header bg-white border-bottom pt-3 pb-2 ps-4 pe-4">
                                    <h6 class="fw-bold text-dark mb-0"><i class="bi bi-receipt text-secondary me-2"></i>Comprobante de Dispersión</h6>
                                </div>
                                <div class="table-responsive">
                                    <table class="table align-middle table-hover mb-0 bg-white">
                                        <thead class="table-light border-bottom">
                                            <tr>
                                                <th class="ps-4 text-secondary fw-semibold py-3">Empleado</th>
                                                <th class="text-end text-secondary fw-semibold py-3">Monto Pagado</th>
                                                <th class="text-secondary fw-semibold ps-4 py-3">Método (Destino)</th>
                                                <th class="text-secondary fw-semibold pe-4 py-3">Cuenta Empresa (Origen)</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            // Mapa para buscar el nombre de la cuenta rápidamente
                                            $mapaCuentasNombres = [];
                                            foreach ($cuentas as $c) {
                                                $mapaCuentasNombres[$c['id']] = $c['nombre'] . ' (' . $c['moneda'] . ')';
                                            }

                                            foreach ($detallesNomina as $det): 
                                                if ($det['neto_a_pagar'] <= 0) continue;
                                                
                                                // Decodificamos el JSON histórico que guardamos
                                                $pagoInfo = json_decode($det['metodos_pago_json'] ?? '{}', true);
                                                $metodo = $pagoInfo['metodo'] ?? 'EFECTIVO';
                                                $idCuentaOrigen = $pagoInfo['id_cuenta_origen'] ?? 0;
                                                $monto = $pagoInfo['monto'] ?? $det['neto_a_pagar'];
                                                
                                                $nombreCuentaOrigen = $mapaCuentasNombres[$idCuentaOrigen] ?? 'Cuenta no encontrada';
                                                
                                                // Diseño del Badge
                                                if ($metodo === 'EFECTIVO') {
                                                    $badgeMetodo = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="bi bi-cash me-1"></i> Efectivo (Caja)</span>';
                                                } else {
                                                    $badgeMetodo = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1"><i class="bi bi-bank me-1"></i> Transf. Bancaria</span>';
                                                }
                                            ?>
                                                <tr>
                                                    <td class="ps-4 fw-bold text-dark py-3"><?php echo htmlspecialchars($det['nombre_completo']); ?></td>
                                                    <td class="text-end fw-bold text-success fs-6 py-3">S/ <?php echo number_format((float)$monto, 2); ?></td>
                                                    <td class="ps-4 py-3"><?php echo $badgeMetodo; ?></td>
                                                    <td class="pe-4 text-muted fw-medium py-3"><?php echo htmlspecialchars($nombreCuentaOrigen); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalGenerarLote" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-folder-plus me-2"></i>Generar Nuevo Lote de Nómina
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('planillas/generar')); ?>" id="formGenerarLote">
                <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="mb-3">
                                <label class="form-label small text-muted fw-bold mb-1">Nombre / Referencia Automática</label>
                                <input type="text" name="nombre_lote" id="nombreGeneradoLote" class="form-control shadow-none bg-light border-secondary-subtle fw-bold text-secondary" placeholder="Se generará automáticamente..." readonly>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small text-muted fw-bold mb-1">Frecuencia de Pago a Filtrar <span class="text-danger">*</span></label>
                                <select name="frecuencia" id="frecuenciaLote" class="form-select shadow-none border-secondary-subtle fw-semibold text-dark" required>
                                    <option value="TODOS" selected>Todos los empleados (Sin filtro)</option>
                                    <option value="SEMANAL">Pago Semanal</option>
                                    <option value="QUINCENAL">Pago Quincenal</option>
                                    <option value="MENSUAL">Pago Mensual</option>
                                </select>
                            </div>
                            <div class="row g-3 mb-2">
                                <div class="col-6">
                                    <label class="form-label small text-muted fw-bold mb-1">Fecha Inicio <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_inicio" id="fechaInicioLote" class="form-control shadow-none border-secondary-subtle fw-medium" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label small text-muted fw-bold mb-1">Fecha Fin <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha_fin" id="fechaFinLote" class="form-control shadow-none border-secondary-subtle fw-medium" required>
                                </div>
                            </div>
                            <div class="form-text small mt-2 mb-3" id="ayudaFrecuenciaLote">
                                <i class="bi bi-info-circle-fill text-primary me-1"></i> El sistema consolidará las asistencias e incidencias dentro de estas fechas.
                            </div>
                            
                            <div class="mb-1">
                                <label class="form-label small text-muted fw-bold mb-1">Observaciones Internas <span class="text-muted fw-normal">(Opcional)</span></label>
                                <textarea name="observaciones" class="form-control shadow-none border-secondary-subtle" rows="2" placeholder="Ej. Lote correspondiente a liquidación de destajo y bonos extra..."></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-success px-5 fw-bold shadow-sm transition-hover">
                            <i class="bi bi-gear-fill me-2"></i>Calcular Nómina
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAjustarNomina" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-sliders me-2"></i>Ajustar Conceptos Adicionales
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('planillas/agregar_concepto')); ?>">
                <input type="hidden" name="id_detalle_nomina" id="ajusteIdDetalle">
                
                <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    
                    <div class="d-flex align-items-center mb-4 p-3 bg-white border border-warning-subtle rounded-3 shadow-sm">
                        <div class="bg-warning-subtle p-2 rounded-circle me-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-person-badge fs-3 text-warning-emphasis"></i>
                        </div>
                        <div>
                            <span class="small text-muted fw-bold text-uppercase d-block mb-1">Empleado a ajustar:</span>
                            <strong class="d-block text-dark fs-5 lh-1" id="ajusteNombreEmpleado">Cargando...</strong>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-3">
                                <div>
                                    <h6 class="fw-bold text-dark mb-0">Movimientos Manuales</h6>
                                    <small class="text-muted">Bonos, comisiones, adelantos o descuentos.</small>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary fw-bold shadow-sm px-3" id="btnAgregarMovimientoNomina">
                                    <i class="bi bi-plus-circle me-1"></i>Añadir Fila
                                </button>
                            </div>

                            <div id="contenedorMovimientosNomina" class="d-flex flex-column gap-3"></div>
                            
                            <div class="form-text small mt-3 text-secondary">
                                <i class="bi bi-shield-check me-1"></i> El sistema bloqueará movimientos repetidos (Mismo tipo + Categoría + Descripción) por seguridad.
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-2">
                        <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm text-dark transition-hover">
                            <i class="bi bi-arrow-repeat me-2"></i>Guardar y Recalcular
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="tplMovimientoNomina">
    <div class="card border border-secondary-subtle shadow-sm movimiento-nomina-item bg-white">
        <div class="card-body p-3">
            <div class="d-flex justify-content-between align-items-center mb-3 border-bottom pb-2">
                <strong class="small text-primary text-uppercase fw-bold"><i class="bi bi-tag-fill me-1"></i> Movimiento <span class="js-mov-index">#1</span></strong>
                <button type="button" class="btn btn-sm btn-light text-danger js-remove-movimiento border-0" title="Quitar este movimiento">
                    <i class="bi bi-trash fs-6"></i>
                </button>
            </div>
            
            <div class="row g-3">
                <div class="col-md-12">
                    <label class="form-label small text-muted fw-bold mb-1">Naturaleza <span class="text-danger">*</span></label>
                    <select data-name="tipo_concepto" class="form-select shadow-none border-secondary-subtle fw-semibold" required>
                        <option value="PERCEPCION">Ingreso / Percepción (+ Aumenta el pago)</option>
                        <option value="DEDUCCION">Descuento / Deducción (- Disminuye el pago)</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small text-muted fw-bold mb-1">Categoría <span class="text-danger">*</span></label>
                    <select data-name="categoria_concepto" class="form-select shadow-none border-secondary-subtle" required>
                        <option value="Bono Productividad">Bono Productividad</option>
                        <option value="Bono Especial">Bono Especial</option>
                        <option value="Incentivo Empresa">Incentivo Empresa</option>
                        <option value="Adelanto de Sueldo">Adelanto de Sueldo</option>
                        <option value="Multa / Sanción">Multa / Sanción</option>
                        <option value="Otros">Otros</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label small text-muted fw-bold mb-1">Monto <span class="text-danger">*</span></label>
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle text-muted">S/</span>
                        <input type="number" step="0.01" min="0.1" data-name="monto" class="form-control shadow-none border-secondary-subtle fw-bold" placeholder="0.00" required>
                    </div>
                </div>
                
                <div class="col-md-12">
                    <label class="form-label small text-muted fw-bold mb-1">Descripción que verá el empleado en su Boleta <span class="text-danger">*</span></label>
                    <input type="text" data-name="descripcion" class="form-control shadow-none border-secondary-subtle" placeholder="Ej. Bono por meta de ventas de Enero..." required>
                </div>
            </div>
        </div>
    </div>
</template>