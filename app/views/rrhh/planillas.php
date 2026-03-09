<?php
// Inicialización segura de variables
$loteActual = $lote_actual ?? null; 
$lotesRecientes = $lotes_recientes ?? [];
$detallesNomina = $detalles_nomina ?? []; 
$cuentas = $cuentas ?? []; 
$metodos = $metodos ?? [];

$estadoLote = $loteActual['estado'] ?? 'BORRADOR';

// Extraemos totales para los KPIs sumando dinámicamente los detalles
$nominaTotal = 0.0;
$deducciones = 0.0;
$netoPagar = 0.0;

if (!empty($detallesNomina)) {
    foreach ($detallesNomina as $det) {
        $nominaTotal += (float) ($det['total_percepciones'] ?? 0);
        $deducciones += (float) ($det['total_deducciones'] ?? 0);
        $netoPagar += (float) ($det['neto_a_pagar'] ?? 0);
    }
}
?>

<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-wallet-fill me-2 text-primary"></i> Procesamiento de Nómina
            </h1>
            <p class="text-muted small mb-0 ms-1">Generación de lotes, consolidación financiera, bonos y dispersión de pagos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <div class="dropdown me-2">
                <button class="btn btn-white border shadow-sm text-secondary fw-semibold dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-folder2-open me-2 text-warning"></i>
                    <?php echo $loteActual ? htmlspecialchars($loteActual['referencia']) : 'Seleccionar Lote...'; ?>
                </button>
                <ul class="dropdown-menu shadow-sm">
                    <?php foreach ($lotesRecientes as $l): ?>
                        <li><a class="dropdown-item fw-medium" href="?ruta=planillas&id_lote=<?php echo (int)$l['id']; ?>"><?php echo htmlspecialchars($l['referencia'] . ' - ' . $l['nombre']); ?></a></li>
                    <?php endforeach; ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-primary fw-bold" href="?ruta=nominas/historial"><i class="bi bi-search me-2"></i>Ver todos los lotes</a></li>
                </ul>
            </div>

            <?php if ($loteActual && $estadoLote === 'BORRADOR'): ?>
                <?php if ($netoPagar > 0): ?>
                    <form method="post" action="<?php echo e(route_url('planillas/aprobar')); ?>" class="m-0" onsubmit="return confirm('¿Aprobar este lote? Ya no se podrán agregar bonos ni modificar montos. Las asistencias quedarán selladas.');">
                        <input type="hidden" name="id_lote" value="<?php echo (int)$loteActual['id']; ?>">
                        <button type="submit" class="btn btn-primary shadow-sm fw-semibold">
                            <i class="bi bi-check-circle me-2"></i>Aprobar Lote
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary shadow-sm fw-semibold opacity-50" disabled title="No hay datos para aprobar">
                        <i class="bi bi-check-circle me-2"></i>Lote Vacío
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            
            <button class="btn btn-success shadow-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalGenerarLote">
                <i class="bi bi-plus-circle me-2"></i>Nuevo Lote
            </button>
        </div>
    </div>

    <?php if (!$loteActual): ?>
        <div class="card border-0 shadow-sm">
            <div class="card-body p-5 text-center">
                <i class="bi bi-folder-plus fs-1 text-muted opacity-50 d-block mb-3"></i>
                <h5 class="fw-bold text-dark">No hay un lote de nómina seleccionado</h5>
                <p class="text-muted mb-4">Seleccione un lote en el menú superior o genere uno nuevo para consolidar los pagos del periodo.</p>
                <button class="btn btn-primary shadow-sm fw-semibold px-4" type="button" data-bs-toggle="modal" data-bs-target="#modalGenerarLote">
                    Generar Nuevo Lote
                </button>
            </div>
        </div>
    <?php else: ?>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100 bg-light border-start border-secondary border-4">
                    <div class="card-body p-4">
                        <p class="small text-muted text-uppercase fw-bold mb-1">Percepciones (Bruto + Bonos)</p>
                        <h3 class="fw-bold text-dark mb-0">S/ <?php echo number_format($nominaTotal, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100 bg-light border-start border-danger border-4">
                    <div class="card-body p-4">
                        <p class="small text-muted text-uppercase fw-bold mb-1">Deducciones Total</p>
                        <h3 class="fw-bold text-danger mb-0">- S/ <?php echo number_format($deducciones, 2); ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="card border-0 shadow-sm h-100 bg-primary text-white border-start border-info border-4">
                    <div class="card-body p-4">
                        <p class="small text-white-50 text-uppercase fw-bold mb-1">Neto a Desembolsar</p>
                        <h3 class="fw-bold mb-0">S/ <?php echo number_format($netoPagar, 2); ?></h3>
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
                    
                    <div class="progress w-100" style="height: 3px;">
                        <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $progreso; ?>%;"></div>
                    </div>
                    
                    <button class="position-absolute top-50 start-0 translate-middle btn btn-sm rounded-pill <?php echo $progreso >= 0 ? 'btn-success' : 'btn-secondary'; ?>" style="width: 2.5rem; height:2.5rem;" title="Borrador"><i class="bi bi-calculator fs-5"></i></button>
                    <button class="position-absolute top-50 start-50 translate-middle btn btn-sm rounded-pill <?php echo $progreso >= 50 ? 'btn-success' : 'btn-secondary'; ?>" style="width: 2.5rem; height:2.5rem;" title="Aprobado"><i class="bi bi-check2-all fs-5"></i></button>
                    <button class="position-absolute top-50 start-100 translate-middle btn btn-sm rounded-pill <?php echo $progreso >= 100 ? 'btn-success' : 'btn-secondary'; ?>" style="width: 2.5rem; height:2.5rem;" title="Pagado"><i class="bi bi-bank fs-5"></i></button>
                    
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
                        <button class="nav-link active text-primary border-0 border-bottom border-primary border-3 bg-transparent pb-3" id="recibos-tab" data-bs-toggle="tab" data-bs-target="#recibos-pane" type="button" role="tab"><i class="bi bi-people me-2"></i>Recibos (Empleados)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-muted border-0 bg-transparent pb-3" id="tesoreria-tab" data-bs-toggle="tab" data-bs-target="#tesoreria-pane" type="button" role="tab"><i class="bi bi-bank me-2"></i>Tesorería (Dispersión)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link text-muted border-0 bg-transparent pb-3" id="contabilidad-tab" data-bs-toggle="tab" data-bs-target="#contabilidad-pane" type="button" role="tab"><i class="bi bi-journal-text me-2"></i>Póliza Contable</button>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-0">
                <div class="tab-content" id="nominaTabContent">
                    
                    <div class="tab-pane fade show active" id="recibos-pane" role="tabpanel" tabindex="0">
                        <div class="p-3 border-bottom bg-light">
                            <div class="row g-2 align-items-center">
                                <div class="col-12 col-md-4">
                                    <div class="input-group input-group-sm shadow-sm">
                                        <span class="input-group-text bg-white border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                                        <input type="search" class="form-control bg-white border-secondary-subtle border-start-0 ps-0" id="searchDetalles" placeholder="Buscar empleado...">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table align-middle mb-0 table-pro" id="tablaDetallesNomina"
                                   data-erp-table="true"
                                   data-search-input="#searchDetalles"
                                   data-rows-selector="#detallesTableBody tr:not(.empty-msg-row)"
                                   data-empty-text="No hay recibos generados en este lote">
                                <thead class="bg-light border-bottom">
                                    <tr>
                                        <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                                        <th class="text-center text-secondary fw-semibold">Días / Horas</th>
                                        <th class="text-end text-secondary fw-semibold">Percepciones</th>
                                        <th class="text-end text-secondary fw-semibold">Deducciones</th>
                                        <th class="text-end text-dark fw-bold">Neto a Pagar</th>
                                        <th class="text-center text-secondary fw-semibold pe-4">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="detallesTableBody">
                                    <?php if (empty($detallesNomina)): ?>
                                        <tr class="empty-msg-row border-bottom-0">
                                            <td colspan="6" class="text-center text-muted py-5">
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
                                                
                                                // No mostramos empleados que salieron en 0 completamente (sin actividad alguna)
                                                $tieneDeduccion = ((float)($row['total_deducciones'] ?? 0) > 0);
                                                if ($row['neto_a_pagar'] <= 0 && $row['dias_pagados'] == 0 && !$tieneBono && !$tieneDeduccion) continue;
                                            ?>
                                            <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                                <td class="ps-4 fw-semibold text-dark align-top pt-3">
                                                    <?php echo htmlspecialchars((string) ($row['nombre_completo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>
                                                    <div class="small text-muted fw-normal">
                                                        <?php echo htmlspecialchars((string) ($row['cargo'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> 
                                                        | <span class="badge bg-light text-secondary border"><?php echo htmlspecialchars((string) ($row['frecuencia'] ?? 'MENSUAL'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-center">
                                                    <?php if (isset($row['tiene_conflicto']) && $row['tiene_conflicto']): ?>
                                                        <span class="badge bg-warning text-dark fw-bold" title="Marcaciones incompletas en asistencia">
                                                            <i class="bi bi-exclamation-triangle-fill"></i> Conflicto
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="fw-bold fs-6">
                                                            <?= (float)($row['dias_pagados'] ?? 0) ?>D
                                                        </span>
                                                        <br>
                                                        <span class="text-muted small">
                                                            <?= (float)($row['horas_acumuladas'] ?? 0) ?>h acumuladas
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end align-top pt-3">
                                                    <div class="fw-medium text-success">S/ <?php echo number_format((float)($row['total_percepciones'] ?? 0), 2); ?></div>
                                                    
                                                    <?php if ((float)($row['pago_por_hora'] ?? 0) > 0): ?>
                                                        <div class="small text-muted fw-normal mb-1" style="font-size: 0.7rem;">
                                                            Tarifa: S/ <?php echo number_format((float)($row['pago_por_hora']), 2); ?>/hr
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if($tieneBono): ?>
                                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;"><i class="bi bi-star-fill me-1"></i>Incluye Bono</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end align-top pt-3">
                                                    <div class="fw-medium text-danger">- S/ <?php echo number_format((float)($row['total_deducciones'] ?? 0), 2); ?></div>
                                                    
                                                    <?php if($descuentoAdelanto > 0): ?>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.65rem;" title="Se cobró S/ <?php echo $descuentoAdelanto; ?> por adelantos previos">
                                                            <i class="bi bi-wallet2 me-1"></i>Cobro de Adelanto
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-end fw-bold text-primary align-top pt-3">S/ <?php echo number_format((float)($row['neto_a_pagar'] ?? 0), 2); ?></td>
                                                <td class="text-center pe-4 align-top pt-3">
                                                    <div class="d-flex justify-content-center gap-1">
                                                        
                                                        <?php if ($estadoLote === 'BORRADOR'): ?>
                                                            <button type="button" class="btn btn-sm btn-light text-warning border-0 rounded-circle shadow-sm btn-ajustar-empleado" 
                                                                    data-bs-toggle="modal" data-bs-target="#modalAjustarNomina"
                                                                    data-id="<?php echo (int)($row['id'] ?? 0); ?>"
                                                                    data-nombre="<?php echo htmlspecialchars($row['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                                    data-bs-toggle="tooltip" title="Ajustar Conceptos / Añadir Bono">
                                                                <i class="bi bi-pencil-square fs-5"></i>
                                                            </button>
                                                        <?php endif; ?>

                                                        <a href="?ruta=planillas/imprimir_boleta&id=<?php echo (int)($row['id'] ?? 0); ?>" target="_blank" class="btn btn-sm btn-light text-secondary border-0 rounded-circle shadow-sm" data-bs-toggle="tooltip" title="Ver Boleta">
                                                            <i class="bi bi-file-earmark-pdf fs-5"></i>
                                                        </a>
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
                        <div class="row g-4">
                            <div class="col-md-6 border-end pe-md-4">
                                <h5 class="fw-bold mb-3"><i class="bi bi-file-earmark-arrow-down text-primary me-2"></i>Layouts Bancarios</h5>
                                <p class="text-muted small mb-4">Descarga el archivo estructurado para subirlo al portal de tu banco y pagar a todos los empleados en una sola operación.</p>
                                
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-secondary fw-semibold d-flex justify-content-between align-items-center" <?php echo $estadoLote === 'BORRADOR' ? 'disabled' : ''; ?>>
                                        <span><i class="bi bi-bank me-2"></i>Formato BCP (TXT)</span>
                                        <i class="bi bi-download"></i>
                                    </button>
                                    <button class="btn btn-outline-secondary fw-semibold d-flex justify-content-between align-items-center" <?php echo $estadoLote === 'BORRADOR' ? 'disabled' : ''; ?>>
                                        <span><i class="bi bi-bank me-2"></i>Formato BBVA (CSV)</span>
                                        <i class="bi bi-download"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-6 ps-md-4">
                                <h5 class="fw-bold mb-3"><i class="bi bi-cash-coin text-success me-2"></i>Salida de Dinero</h5>
                                
                                <?php if ($estadoLote === 'BORRADOR'): ?>
                                    <div class="alert alert-secondary border-0 shadow-sm small">
                                        <i class="bi bi-info-circle me-1"></i> Debes <strong>Aprobar el Lote</strong> (botón superior) antes de poder registrar el pago.
                                    </div>
                                <?php elseif ($estadoLote === 'APROBADO'): ?>
                                    <div class="alert alert-warning border-0 shadow-sm small">
                                        <i class="bi bi-exclamation-triangle me-1"></i> El lote está aprobado. Falta registrar la salida del dinero.
                                    </div>
                                    <button class="btn btn-success fw-bold shadow-sm w-100" type="button" data-bs-toggle="modal" data-bs-target="#modalPagarLote">
                                        <i class="bi bi-check2-all me-2"></i>Registrar Dispersión
                                    </button>
                                <?php else: ?>
                                    <div class="alert alert-success border-0 shadow-sm small">
                                        <h6 class="fw-bold mb-1"><i class="bi bi-check-circle-fill me-1"></i>Pago Registrado</h6>
                                        Pagado el <?php echo htmlspecialchars($loteActual['fecha_pago'] ?? '-'); ?> desde la cuenta bancaria. 
                                        Ref: <?php echo htmlspecialchars($loteActual['referencia_pago'] ?? '-'); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade p-5 text-center text-muted" id="contabilidad-pane" role="tabpanel" tabindex="0">
                        <i class="bi bi-journal-check opacity-50 d-block mb-3" style="font-size: 3rem;"></i>
                        <h6 class="fw-bold text-dark">Póliza de Nómina</h6>
                        <p class="small">El asiento contable se generará automáticamente en el módulo de Contabilidad una vez que el lote sea aprobado y pagado.</p>
                        <button class="btn btn-light border text-secondary fw-semibold shadow-sm mt-2" <?php echo $estadoLote !== 'PAGADO' ? 'disabled' : ''; ?>>
                            Ver Asiento Contable
                        </button>
                    </div>

                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="modalGenerarLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-folder-plus me-2"></i>Generar Nuevo Lote
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('planillas/generar')); ?>" id="formGenerarLote">
                <div class="modal-body p-4 bg-light">
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold mb-1">Nombre / Referencia del Lote</label>
                        <input type="text" name="nombre_lote" id="nombreGeneradoLote" class="form-control bg-secondary-subtle border-secondary-subtle fw-semibold" placeholder="Se generará automáticamente..." readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold mb-1">Frecuencia de Pago <span class="text-danger">*</span></label>
                        <select name="frecuencia" id="frecuenciaLote" class="form-select bg-white border-secondary-subtle shadow-sm" required>
                            <option value="TODOS" selected>Todos (sin filtro)</option>
                            <option value="SEMANAL">Semanal</option>
                            <option value="QUINCENAL">Quincenal</option>
                            <option value="MENSUAL">Mensual</option>
                        </select>
                    </div>
                    <div class="row g-3 mb-2">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha Inicio <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_inicio" id="fechaInicioLote" class="form-control bg-white border-secondary-subtle shadow-sm" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha Fin <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_fin" id="fechaFinLote" class="form-control bg-white border-secondary-subtle shadow-sm" required>
                        </div>
                    </div>
                    <div class="form-text small mt-1 mb-3" id="ayudaFrecuenciaLote"><i class="bi bi-info-circle text-primary me-1"></i> El sistema leerá las asistencias entre estas dos fechas.</div>
                    
                    <div class="mb-1">
                        <label class="form-label small text-muted fw-bold mb-1">Observaciones Internas <span class="text-muted fw-normal">(Opcional)</span></label>
                        <textarea name="observaciones" class="form-control bg-white border-secondary-subtle shadow-sm" rows="2" placeholder="Ej. Lote correspondiente a liquidación de destajo..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                        <i class="bi bi-gear-fill me-2"></i>Calcular Nómina
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAjustarNomina" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-warning text-dark border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-sliders me-2"></i>Ajustar Conceptos de Nómina
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('planillas/agregar_concepto')); ?>">
                <input type="hidden" name="id_detalle_nomina" id="ajusteIdDetalle">
                
                <div class="modal-body p-4 bg-light">
                    <div class="d-flex align-items-center mb-4 p-3 bg-white border border-secondary-subtle rounded-3 shadow-sm">
                        <i class="bi bi-person-badge fs-2 text-warning me-3"></i>
                        <div>
                            <span class="small text-muted d-block mb-1">Empleado a ajustar:</span>
                            <strong class="d-block text-dark fs-5" id="ajusteNombreEmpleado">Cargando...</strong>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold mb-1">Tipo de Movimiento <span class="text-danger">*</span></label>
                        <select name="tipo_concepto" class="form-select bg-white border-secondary-subtle shadow-sm" required>
                            <option value="PERCEPCION">Percepción (Bono, Incentivo) [+ Aumenta el pago]</option>
                            <option value="DEDUCCION">Deducción (Adelanto, Multa) [- Disminuye el pago]</option>
                        </select>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold mb-1">Monto <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-light border-secondary-subtle">S/</span>
                                <input type="number" step="0.01" min="0.1" name="monto" class="form-control bg-white border-secondary-subtle" required>
                            </div>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold mb-1">Categoría <span class="text-danger">*</span></label>
                            <select name="categoria_concepto" class="form-select bg-white border-secondary-subtle shadow-sm" required>
                                <option value="Bono Productividad">Bono Productividad</option>
                                <option value="Bono Especial">Bono Especial</option>
                                <option value="Incentivo Empresa">Incentivo Empresa</option>
                                <option value="Adelanto de Sueldo">Adelanto de Sueldo</option>
                                <option value="Otros">Otros</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-0">
                        <label class="form-label small text-muted fw-bold mb-1">Descripción para la Boleta <span class="text-danger">*</span></label>
                        <input type="text" name="descripcion" class="form-control bg-white border-secondary-subtle shadow-sm" placeholder="Ej. Bono por meta de ventas alcanzada" required>
                        <div class="form-text small mt-1"><i class="bi bi-receipt me-1"></i> Este texto es el que leerá el empleado en su recibo de pago impreso.</div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning px-4 fw-bold shadow-sm text-dark">
                        <i class="bi bi-save me-2"></i>Guardar (Recalculará tabla)
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPagarLote" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-3">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-bank me-2"></i>Registrar Dispersión de Nómina
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('planillas/pagar_lote')); ?>">
                <input type="hidden" name="id_lote" value="<?php echo (int)($loteActual['id'] ?? 0); ?>">
                
                <div class="modal-body p-4 bg-light">
                    <div class="alert alert-success border-0 shadow-sm mb-4">
                        <h6 class="fw-bold mb-1">Monto Total a Desembolsar:</h6>
                        <h3 class="mb-0 fw-bold">S/ <?php echo number_format($netoPagar ?? 0, 2); ?></h3>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small text-muted fw-bold mb-1">Cuenta de Origen (Caja/Banco) <span class="text-danger">*</span></label>
                        <select name="id_cuenta" class="form-select shadow-sm" required>
                            <option value="">Seleccione una cuenta...</option>
                            <?php foreach ($cuentas as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nombre'] . ' - Saldo: S/' . number_format((float)$c['saldo_actual'], 2)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Pago <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_pago" class="form-control shadow-sm" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small text-muted fw-bold mb-1">Referencia / Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm" placeholder="Ej. OP-901234">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top-0">
                    <button type="button" class="btn btn-light text-secondary border fw-semibold shadow-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                        <i class="bi bi-check2-all me-2"></i>Confirmar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/rrhh/planillas.js"></script>