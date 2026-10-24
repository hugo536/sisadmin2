<?php
$lotes_recientes = $lotes_recientes ?? [];
$lote_actual = $lote_actual ?? null;
$detalles_nomina = $detalles_nomina ?? [];
$csrf_token = $csrf_token ?? '';

// 1. CAPTURAR ALERTAS DE LA URL
$swalMsg = '';
$swalIcon = '';
if (!empty($_GET['error'])) {
    $swalMsg = $_GET['error'];
    $swalIcon = 'error';
} elseif (!empty($_GET['ok'])) {
    $swalMsg = $_GET['ok'];
    $swalIcon = 'success';
}
?>

<!-- Turbo Frame principal -->
<turbo-frame id="planillasApp" class="d-block">

    <!-- 2. DISPARAR SWEETALERT SI HAY MENSAJE -->
    <?php if ($swalMsg !== ''): ?>
    <script>
        setTimeout(() => {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: '<?php echo $swalIcon; ?>',
                    title: '<?php echo $swalIcon === "error" ? "Atención" : "Éxito"; ?>',
                    text: '<?php echo htmlspecialchars($swalMsg, ENT_QUOTES); ?>',
                    confirmButtonColor: '<?php echo $swalIcon === "error" ? "#dc3545" : "#198754"; ?>'
                });
            } else {
                alert('<?php echo htmlspecialchars($swalMsg, ENT_QUOTES); ?>');
            }
            
            // MAGIA: Limpiar la URL para evitar que la alerta se repita al presionar F5
            if (window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.delete('ok');
                url.searchParams.delete('error');
                window.history.replaceState({ path: url.href }, '', url.href);
            }
        }, 150);
    </script>
    <?php endif; ?>

    <div class="container-fluid p-4 fade-in">
        
        <!-- ========================================== -->
        <!-- CABECERA PRINCIPAL -->
        <!-- ========================================== -->
        <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
            <div>
                <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                    <i class="bi bi-wallet2 me-2 text-primary"></i> Planillas y Pagos
                </h1>
                <p class="text-muted small mb-0 ms-1">Cálculo de nómina, bonos, deducciones y generación de recibos.</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <!-- Menú Desplegable: Historial de Lotes -->
                <div class="dropdown">
                    <button class="btn btn-light bg-white border-secondary-subtle fw-bold dropdown-toggle shadow-sm px-4 py-2 d-flex align-items-center transition-hover" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-clock-history me-2 text-muted"></i>Lotes Recientes
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow shadow-lg border-0" style="max-height: 400px; overflow-y: auto; width: 320px; z-index: 1050;">
                        <li class="dropdown-header fw-bold text-uppercase small text-muted bg-light border-bottom">Historial de Planillas</li>
                        <?php if (empty($lotes_recientes)): ?>
                            <li><span class="dropdown-item text-muted small py-3 text-center">No hay lotes generados.</span></li>
                        <?php else: ?>
                            <?php foreach ($lotes_recientes as $lote): ?>
                                <?php $activo = ($lote_actual && $lote_actual['id'] === $lote['id']) ? 'bg-primary-subtle' : ''; ?>
                                <li>
                                    <a class="dropdown-item py-2 border-bottom transition-hover <?php echo $activo; ?>" href="<?php echo e(route_url('planillas')); ?>&id_lote=<?php echo $lote['id']; ?>" data-turbo-frame="planillasApp">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <strong class="small text-dark text-truncate me-2"><?php echo htmlspecialchars($lote['referencia']); ?></strong>
                                            <?php if ($lote['estado'] === 'BORRADOR'): ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">BORRADOR</span>
                                            <?php elseif ($lote['estado'] === 'APROBADO'): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle" style="font-size: 0.65rem;">POR PAGAR</span>
                                            <?php else: ?>
                                                <span class="badge bg-success" style="font-size: 0.65rem;">PAGADO</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div class="small text-muted" style="font-size: 0.75rem;">
                                                <?php echo date('d/m/y', strtotime($lote['fecha_inicio'])) . ' - ' . date('d/m/y', strtotime($lote['fecha_fin'])); ?>
                                            </div>
                                            <div class="fw-bold text-dark" style="font-size: 0.8rem;">S/ <?php echo number_format((float)$lote['total_neto'], 2); ?></div>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- Botón Generar -->
                <button type="button" class="btn btn-primary fw-bold shadow-sm px-4 py-2 d-flex align-items-center transition-hover" data-bs-toggle="modal" data-bs-target="#modalGenerarLote">
                    <i class="bi bi-plus-lg me-2"></i>Generar Nuevo Lote
                </button>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- ÁREA DE TRABAJO -->
        <!-- ========================================== -->
        <div class="row">
            <div class="col-12">
                <?php if (!$lote_actual): ?>
                    <!-- Estado Vacío -->
                    <div class="card border-0 shadow-sm h-100 d-flex flex-column justify-content-center align-items-center py-5 bg-light rounded-4">
                        <i class="bi bi-file-earmark-spreadsheet text-muted opacity-25 mb-3" style="font-size: 5rem;"></i>
                        <h5 class="text-dark fw-bold">Selecciona o genera un lote</h5>
                        <p class="text-muted small">Utiliza los botones superiores para cargar una planilla y visualizar sus detalles financieros.</p>
                    </div>
                <?php else: ?>
                    <!-- Detalle del Lote -->
                    <div class="card border-0 shadow-sm rounded-4 overflow-hidden">
                        
                        <!-- CARD HEADER: REESTRUCTURADO MOBILE FIRST -->
                        <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                            
                            <!-- BLOQUE 1: TÍTULO Y ESTADO -->
                            <div class="d-flex flex-column align-items-start gap-1">
                                <h5 class="fw-bold text-dark mb-0 d-flex flex-wrap align-items-center gap-2">
                                    <?php echo htmlspecialchars($lote_actual['nombre']); ?>
                                    
                                    <!-- BADGES DE ESTADO JUNTO AL TÍTULO -->
                                    <?php if ($lote_actual['estado'] === 'BORRADOR'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2 py-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">EDICIÓN / BORRADOR</span>
                                    <?php elseif ($lote_actual['estado'] === 'APROBADO'): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1" style="font-size: 0.7rem; letter-spacing: 0.5px;">FALTA PAGAR</span>
                                    <?php elseif ($lote_actual['estado'] === 'PAGADO'): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1" style="font-size: 0.7rem; letter-spacing: 0.5px;"><i class="bi bi-check-circle-fill me-1"></i>PAGADA</span>
                                    <?php endif; ?>
                                </h5>
                                <span class="text-muted small fw-medium">Ref: <?php echo htmlspecialchars($lote_actual['referencia']); ?> | Frecuencia: <?php echo htmlspecialchars($lote_actual['frecuencia']); ?></span>
                            </div>

                            <!-- BLOQUE 2: CONTROLES DE ACCIÓN PRINCIPALES -->
                            <div class="d-flex align-items-center gap-2 w-100 w-md-auto mt-2 mt-md-0">
                                <?php if ($lote_actual['estado'] === 'BORRADOR'): ?>
                                    <!-- Botón de Eliminar Lote -->
                                    <form action="<?php echo e(route_url('planillas/eliminar')); ?>" method="POST" id="formEliminarLote" class="m-0 flex-grow-1 flex-md-grow-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                        <input type="hidden" name="id_lote" value="<?php echo $lote_actual['id']; ?>">
                                        <button type="submit" class="btn btn-outline-danger bg-white fw-bold shadow-sm px-4 py-2 d-flex align-items-center justify-content-center transition-hover w-100">
                                            <i class="bi bi-trash-fill me-2"></i> Eliminar
                                        </button>
                                    </form>
                                    
                                    <!-- Botón de Cerrar Planilla -->
                                    <form action="<?php echo e(route_url('planillas/cerrar')); ?>" method="POST" id="formCerrarLote" class="m-0 flex-grow-1 flex-md-grow-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                        <input type="hidden" name="id_lote" value="<?php echo $lote_actual['id']; ?>">
                                        <button type="submit" class="btn btn-success fw-bold shadow-sm px-4 py-2 d-flex align-items-center justify-content-center transition-hover w-100">
                                            <i class="bi bi-lock-fill me-2"></i> Cerrar
                                        </button>
                                    </form>

                                <?php elseif ($lote_actual['estado'] === 'APROBADO'): ?>
                                    
                                    <!-- BOTÓN SECUNDARIO: Reabrir Borrador Directo -->
                                    <form action="<?php echo e(route_url('planillas/reabrir')); ?>" method="POST" id="formReabrirLote" class="m-0 flex-grow-1 flex-md-grow-0">
                                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                        <input type="hidden" name="id_lote" value="<?php echo $lote_actual['id']; ?>">
                                        <button type="submit" class="btn btn-outline-warning fw-bold shadow-sm px-4 py-2 w-100 d-flex justify-content-center align-items-center transition-hover">
                                            <i class="bi bi-arrow-counterclockwise me-2"></i> Reabrir Borrador
                                        </button>
                                    </form>

                                    <!-- BOTÓN PRINCIPAL: Pagar Lote -->
                                    <button type="button" class="btn btn-primary fw-bold shadow-sm px-4 py-2 flex-grow-1 flex-md-grow-0 text-nowrap d-flex justify-content-center align-items-center" data-bs-toggle="modal" data-bs-target="#modalPagarPlanilla">
                                        <i class="bi bi-cash-stack me-2"></i> Pagar Lote
                                    </button>

                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- BARRA SECUNDARIA (Buscador y Exportar) -->
                        <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center px-4">
                            <!-- Buscador -->
                            <div class="input-group input-group-sm bg-white shadow-sm rounded-2" style="max-width: 350px;">
                                <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 shadow-none py-2" id="searchDetalles" placeholder="Buscar empleado o cargo...">
                            </div>
                            
                            <!-- Controles de la derecha (Botón Exportar) -->
                            <div class="d-flex align-items-center gap-3">
                                <?php if ($lote_actual): ?>
                                    <?php 
                                        $tienePagos = array_reduce($detalles_nomina, function($carry, $item) {
                                            return $carry || ((float)($item['neto_a_pagar'] ?? 0) > 0);
                                        }, false);
                                        $esBorrador = ($lote_actual['estado'] === 'BORRADOR');
                                    ?>
                                    
                                    <!-- BOTÓN EXPORTAR -->
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light bg-white border border-secondary-subtle shadow-sm px-3 rounded-pill fw-bold text-secondary dropdown-toggle transition-hover d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="bi bi-cloud-download me-2"></i> Exportar
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 mt-2" style="font-size: 0.85rem; min-width: 200px; z-index: 1060;">
                                            <li>
                                                <a class="dropdown-item py-2 fw-medium" href="#" onclick="event.preventDefault(); exportarSiEsValido('excel', <?php echo $lote_actual['id']; ?>, <?php echo $esBorrador ? 'true' : 'false'; ?>, <?php echo $tienePagos ? 'true' : 'false'; ?>)">
                                                    <i class="bi bi-file-earmark-excel-fill text-success me-2 fs-6"></i>Formato Excel (.xlsx)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item py-2 fw-medium" href="#" onclick="event.preventDefault(); exportarSiEsValido('csv', <?php echo $lote_actual['id']; ?>, <?php echo $esBorrador ? 'true' : 'false'; ?>, <?php echo $tienePagos ? 'true' : 'false'; ?>)">
                                                    <i class="bi bi-filetype-csv text-secondary me-2 fs-6"></i>Datos Crudos (.csv)
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item py-2 fw-medium" href="#" onclick="event.preventDefault(); exportarSiEsValido('pdf', <?php echo $lote_actual['id']; ?>, <?php echo $esBorrador ? 'true' : 'false'; ?>, <?php echo $tienePagos ? 'true' : 'false'; ?>)">
                                                    <i class="bi bi-file-earmark-pdf-fill text-danger me-2 fs-6"></i>Formato PDF (.pdf)
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- TABLA DE DETALLES -->
                        <div class="table-responsive" style="max-height: calc(100vh - 300px);">
                            <table class="table table-hover align-middle mb-0 text-center" id="tablaDetallesNomina">
                                <thead class="table-light sticky-top shadow-sm" style="font-size: 0.8rem; text-transform: uppercase;">
                                    <tr>
                                        <th class="text-start ps-4 py-3">Empleado</th>
                                        <th class="py-3">Asistencia</th>
                                        <th class="py-3 text-center">Préstamos</th>
                                        <th class="py-3">Ingresos (S/)</th>
                                        <th class="py-3">Deducciones (S/)</th>
                                        <th class="bg-success-subtle text-success py-3">Neto a Pagar</th>
                                        <th class="pe-4 py-3 text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($detalles_nomina)): ?>
                                        <tr class="empty-msg-row">
                                            <td colspan="7" class="py-5 text-muted">No se encontraron empleados para este lote.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($detalles_nomina as $det): ?>
                                            <tr data-search="<?php echo strtolower(htmlspecialchars($det['nombre_completo'] . ' ' . ($det['numero_documento'] ?? '') . ' ' . $det['cargo'])); ?>">
                                                
                                                <!-- EMPLEADO -->
                                                <td class="text-start ps-4">
                                                    <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($det['nombre_completo']); ?></div>
                                                    <div class="text-muted" style="font-size: 0.75rem;">
                                                        <?php echo htmlspecialchars($det['cargo']); ?> | DNI: <?php echo htmlspecialchars((string)($det['numero_documento'] ?? 'No registrado')); ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($det['dias_ya_pagados']) && $det['dias_ya_pagados'] > 0): ?>
                                                        <div class="mt-1">
                                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;" title="Días omitidos porque ya fueron pagados en otra planilla.">
                                                                <i class="bi bi-info-circle-fill me-1"></i> 
                                                                <?php echo $det['dias_ya_pagados']; ?> día(s) omitido(s)
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- ASISTENCIA -->
                                                <td style="font-size: 0.85rem;">
                                                    <?php if ($det['tiene_conflicto']): ?>
                                                        <span class="badge bg-danger mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Incompleta</span>
                                                    <?php else: ?>
                                                        <span class="d-block text-success fw-bold"><?php echo $det['dias_pagados']; ?> días</span>
                                                        <span class="text-muted"><?php echo $det['horas_acumuladas']; ?>h (<?php echo $det['horas_extras']; ?>h ext)</span>
                                                    <?php endif; ?>
                                                </td>
                                                
                                                <!-- PRÉSTAMOS -->
                                                <td class="text-center align-middle" style="font-size: 0.85rem;">
                                                    <?php 
                                                        $monto_deuda = isset($det['monto_adeudado']) ? (float)$det['monto_adeudado'] : 0;
                                                    ?>
                                                    <?php if ($monto_deuda > 0): ?>
                                                        <div class="fw-bold text-danger">S/ <?php echo number_format($monto_deuda, 2); ?></div>
                                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle mt-1" style="font-size: 0.65rem;">Deuda Activa</span>
                                                    <?php else: ?>
                                                        <div class="text-muted fw-medium">S/ 0.00</div>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- INGRESOS -->
                                                <td style="font-size: 0.9rem;">
                                                    <?php if ($det['tiene_conflicto']): ?>
                                                        <span class="text-muted">--</span>
                                                    <?php else: ?>
                                                        <div class="fw-bold text-dark"><?php echo number_format((float)$det['total_percepciones'], 2); ?></div>
                                                        <?php if ($det['monto_bonos'] > 0): ?>
                                                            <div class="text-success small fw-medium" style="font-size: 0.75rem;">+ <?php echo number_format((float)$det['monto_bonos'], 2); ?> Bonos</div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- DEDUCCIONES -->
                                                <td style="font-size: 0.9rem;">
                                                    <?php if ($det['tiene_conflicto']): ?>
                                                        <span class="text-muted">--</span>
                                                    <?php else: ?>
                                                        <div class="fw-bold text-danger"><?php echo number_format((float)$det['total_deducciones'], 2); ?></div>
                                                        <?php if (($det['descuento_tardanzas'] ?? 0) > 0): ?>
                                                            <div class="text-danger small fw-medium" style="font-size: 0.75rem;">- <?php echo number_format((float)$det['descuento_tardanzas'], 2); ?> Tardanzas</div>
                                                        <?php endif; ?>
                                                        <?php if (($det['descuento_adelanto'] ?? 0) > 0): ?>
                                                            <div class="text-danger small fw-medium" style="font-size: 0.75rem;">- <?php echo number_format((float)$det['descuento_adelanto'], 2); ?> Préstamos</div>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- NETO -->
                                                <td class="bg-success-subtle fw-bold text-success fs-6">
                                                    <?php if ($det['tiene_conflicto']): ?>
                                                        <i class="bi bi-lock-fill text-danger opacity-50"></i>
                                                    <?php else: ?>
                                                        S/ <?php echo number_format((float)$det['neto_a_pagar'], 2); ?>
                                                    <?php endif; ?>
                                                </td>

                                                <!-- ACCIONES -->
                                                <td class="pe-4 align-middle text-center">
                                                    <?php if ($lote_actual['estado'] === 'BORRADOR'): ?>
                                                        <?php if ($det['tiene_conflicto']): ?>
                                                            <!-- BOTÓN CORREGIR ACTUALIZADO -->
                                                            <a href="<?php echo e(route_url('asistencia/gestion_asistencia')); ?>&id_empleado=<?php echo $det['id_tercero']; ?>&fecha_inicio=<?php echo $lote_actual['fecha_inicio']; ?>" 
                                                            class="btn btn-sm btn-outline-danger shadow-sm px-3 rounded-pill fw-bold" 
                                                            title="Ir a corregir asistencia en esta fecha">
                                                                <i class="bi bi-calendar-x"></i> Corregir
                                                            </a>
                                                        <?php else: ?>
                                                            <button type="button" class="btn btn-sm btn-light border border-secondary-subtle shadow-sm px-3 rounded-pill text-primary fw-bold transition-hover" 
                                                                    data-bs-toggle="modal" data-bs-target="#modalAjustarNomina" 
                                                                    data-id="<?php echo $det['id']; ?>" 
                                                                    data-nombre="<?php echo htmlspecialchars($det['nombre_completo']); ?>">
                                                                <i class="bi bi-plus-dash me-1"></i> Ajustar
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- BOTONES CUANDO LA PLANILLA YA ESTÁ APROBADA O PAGADA -->
                                                        <div class="d-flex justify-content-center gap-2">
                                                            <button type="button" class="btn btn-sm btn-light border border-secondary-subtle shadow-sm px-3 rounded-pill text-info fw-bold transition-hover js-ver-info-empleado"
                                                                    data-info='<?php echo htmlspecialchars(json_encode([
                                                                        "id_detalle" => $det['id'],
                                                                        "nombre" => $det['nombre_completo'],
                                                                        "cargo" => $det['cargo'],
                                                                        "frecuencia" => $det['frecuencia'] ?? 'MENSUAL',
                                                                        "dias" => $det['dias_pagados'],
                                                                        "horas_normales" => $det['horas_acumuladas'],
                                                                        "horas_extras" => $det['horas_extras'],
                                                                        "sueldo_base" => (float)($det['sueldo_base_calculado'] ?? 0),
                                                                        "pago_extras" => (float)($det['pago_horas_extras'] ?? 0),
                                                                        "percepciones" => (float)($det['total_percepciones'] ?? 0),
                                                                        "deducciones" => (float)($det['total_deducciones'] ?? 0),
                                                                        "neto" => (float)($det['neto_a_pagar'] ?? 0)
                                                                    ]), ENT_QUOTES, 'UTF-8'); ?>'
                                                                    data-bs-toggle="modal" data-bs-target="#modalInfoEmpleado">
                                                                <i class="bi bi-info-circle-fill me-1"></i> Ver Desglose
                                                            </button>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- ========================================== -->
        <!-- MODALES DENTRO DEL TURBO-FRAME -->
        <!-- ========================================== -->

        <!-- MODAL: GENERAR LOTE -->
        <div class="modal fade" id="modalGenerarLote" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header bg-primary text-white border-bottom-0 pb-3 rounded-top-4">
                        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Generar Nuevo Lote</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form action="<?php echo e(route_url('planillas/generar')); ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                        
                        <div class="modal-body p-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Frecuencia de Pago</label>
                                <select class="form-select bg-white shadow-none border-secondary-subtle fw-medium" name="frecuencia" id="frecuenciaLote" required>
                                    <option value="TODOS">Todos (Global)</option>
                                    <option value="SEMANAL">Semanal</option>
                                    <option value="QUINCENAL">Quincenal</option>
                                    <option value="MENSUAL">Mensual</option>
                                </select>
                                <div id="ayudaFrecuenciaLote" class="form-text small mt-2"></div>
                            </div>

                            <div class="row g-3 mb-3" id="rangoFechasNormal">
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-muted">Fecha Inicio</label>
                                    <input type="date" class="form-control bg-white shadow-none text-primary fw-bold border-secondary-subtle" name="fecha_inicio" id="fechaInicioLote" required>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold small text-muted">Fecha Fin</label>
                                    <input type="date" class="form-control bg-white shadow-none text-primary fw-bold border-secondary-subtle" name="fecha_fin" id="fechaFinLote" required>
                                </div>
                            </div>

                            <div class="row g-3 mb-3 d-none" id="rangoFechasSemanal">
                                <div class="col-12">
                                    <label class="form-label fw-bold small text-muted">Seleccione la Semana</label>
                                    <input type="week" class="form-control bg-white shadow-none text-primary fw-bold border-secondary-subtle" id="semanaLoteInput">
                                </div>
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-bold small text-muted">Nombre del Lote (Automático)</label>
                                <input type="text" class="form-control bg-light border-secondary-subtle shadow-none text-muted fw-bold" name="nombre_generado" id="nombreGeneradoLote" readonly>
                            </div>
                        </div>
                        <div class="modal-footer bg-white border-top shadow-sm rounded-bottom-4">
                            <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary fw-bold px-4">Generar Lote</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL: AJUSTAR NÓMINA -->
        <div class="modal fade" id="modalAjustarNomina" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header bg-dark text-white border-bottom-0 pb-3 rounded-top-4">
                        <h5 class="modal-title fw-bold"><i class="bi bi-sliders me-2"></i>Ajustar Nómina Manual</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    
                    <form action="<?php echo e(route_url('planillas/agregar_concepto')); ?>" method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                        <input type="hidden" name="id_detalle_nomina" id="ajusteIdDetalle" value="">
                        
                        <div class="modal-body p-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                            <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-secondary-subtle">
                                <div class="bg-primary-subtle text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 45px; height: 45px;">
                                    <i class="bi bi-person-fill fs-4"></i>
                                </div>
                                <div>
                                    <span class="text-muted small fw-bold d-block text-uppercase">Empleado</span>
                                    <span class="fw-bold fs-5 text-dark" id="ajusteNombreEmpleado">--</span>
                                </div>
                            </div>

                            <div id="contenedorMovimientosNomina" class="d-flex flex-column gap-3 mb-3">
                                <!-- Movimientos vía JS -->
                            </div>

                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100 border-dashed py-2 rounded-3 mt-2" id="btnAgregarMovimientoNomina">
                                <i class="bi bi-plus-circle me-2"></i>Agregar otro concepto
                            </button>
                        </div>
                        <div class="modal-footer bg-white border-top shadow-sm rounded-bottom-4">
                            <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal">Cerrar</button>
                            <button type="submit" class="btn btn-dark fw-bold px-4">Guardar Ajustes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <?php if ($lote_actual && $lote_actual['estado'] === 'APROBADO'): ?>
        <!-- MODAL: REGISTRAR PAGO -->
        <div class="modal fade" id="modalPagarPlanilla" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header bg-primary text-white border-bottom-0 pb-3 rounded-top-4">
                        <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Registrar Pago Masivo</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <form action="<?php echo e(route_url('planillas/pagar')); ?>" method="POST" id="formPagarLote">
                        <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                        <input type="hidden" name="id_lote" value="<?php echo $lote_actual['id']; ?>">
                        
                        <div class="modal-body p-4 bg-light" style="margin-top: -10px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                            <div class="alert alert-primary bg-primary-subtle border-primary-subtle text-primary-emphasis fw-medium mb-4">
                                <i class="bi bi-info-circle-fill me-2"></i>
                                El dinero saldrá directamente de Tesorería a los empleados, afectando el saldo de la cuenta seleccionada.
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">Monto Total a Pagar (Neto)</label>
                                <div class="input-group shadow-sm">
                                    <span class="input-group-text bg-white border-secondary-subtle border-end-0 text-success fw-bold">S/</span>
                                    <input type="text" class="form-control bg-white shadow-none text-success fw-bold border-secondary-subtle border-start-0 fs-5" value="<?php echo number_format((float)($lote_actual['total_neto'] ?? 0), 2); ?>" readonly>
                                </div>
                            </div>

                            <div class="mb-0">
                                <label class="form-label fw-bold small text-muted mb-2">¿De qué cuenta(s) saldrá el dinero? <span class="text-danger">*</span></label>
                                
                                <!-- Contenedor dinámico de los métodos de pago -->
                                <div id="contenedorCuentasPlanilla" class="d-flex flex-column gap-2 mb-2">
                                    <!-- JS inyectará las filas del template aquí -->
                                </div>

                                <!-- Controles para agregar otra cuenta y el totalizador -->
                                <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top border-secondary-subtle">
                                    <button type="button" class="btn btn-sm btn-light text-primary fw-bold shadow-sm border border-secondary-subtle transition-hover" id="btnAgregarCuentaPlanilla">
                                        <i class="bi bi-plus-circle me-1"></i> Añadir otra cuenta
                                    </button>
                                    <div class="text-end">
                                        <small class="text-primary-emphasis fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL DISTRIBUIDO</small>
                                        <span class="fw-bold text-dark fs-5" id="totalPagadoPlanilla">S/ 0.00</span>
                                        <!-- Input oculto para validación en el backend (opcional pero recomendado) -->
                                        <input type="hidden" id="inputTotalNetoLote" value="<?php echo number_format((float)($lote_actual['total_neto'] ?? 0), 2, '.', ''); ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-white border-top shadow-sm rounded-bottom-4">
                            <button type="button" class="btn btn-light fw-bold text-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-check-lg me-2"></i>Procesar Pago</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- MODAL: DETALLE DEL EMPLEADO (CONSULTA RRHH) -->
        <div class="modal fade" id="modalInfoEmpleado" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg rounded-4">
                    <div class="modal-header bg-light border-bottom-0 pb-3 rounded-top-4">
                        <div>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="infoEmpNombre">Nombre Empleado</h5>
                            <span class="text-muted small fw-medium" id="infoEmpCargo">Cargo</span>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body p-4 bg-white">
                        
                        <h6 class="fw-bold text-muted text-uppercase small mb-3 border-bottom pb-2">Resumen de Tiempo</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-4 text-center">
                                <div class="fs-5 fw-bold text-dark" id="infoEmpDias">0</div>
                                <div class="small text-muted">Días Pagados</div>
                            </div>
                            <div class="col-4 text-center border-start border-end">
                                <div class="fs-5 fw-bold text-dark" id="infoEmpHoras">0h</div>
                                <div class="small text-muted">Hrs. Normales</div>
                            </div>
                            <div class="col-4 text-center">
                                <div class="fs-5 fw-bold text-warning-emphasis" id="infoEmpExtras">0h</div>
                                <div class="small text-muted">Hrs. Extras</div>
                            </div>
                        </div>

                        <h6 class="fw-bold text-muted text-uppercase small mb-3 border-bottom pb-2">Desglose Financiero</h6>
                        <ul class="list-group list-group-flush mb-0">
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                <span class="text-muted">Sueldo Base Calculado</span>
                                <span class="fw-medium text-dark" id="infoEmpSueldoBase">S/ 0.00</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                <span class="text-muted">Pago por Horas Extras</span>
                                <span class="fw-medium text-dark" id="infoEmpPagoExtras">S/ 0.00</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0 mt-2">
                                <strong class="text-dark">Total Ingresos</strong>
                                <strong class="text-success" id="infoEmpPercepciones">S/ 0.00</strong>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center px-0 py-2 border-0">
                                <strong class="text-dark">Total Deducciones</strong>
                                <strong class="text-danger" id="infoEmpDeducciones">S/ 0.00</strong>
                            </li>
                        </ul>

                        <div class="mt-4 pt-3 border-top d-flex justify-content-between align-items-center bg-light p-3 rounded-3 border border-secondary-subtle">
                            <span class="fw-bold text-dark text-uppercase small">Neto a Pagar</span>
                            <span class="fw-bold text-primary fs-4" id="infoEmpNeto">S/ 0.00</span>
                        </div>

                        <div class="modal-footer bg-light border-top shadow-sm rounded-bottom-4 d-flex justify-content-between align-items-center">
                            <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cerrar</button>
                            <a href="#" target="_blank" id="btnImprimirBoletaModal" class="btn btn-primary fw-bold px-4 d-flex align-items-center">
                                <i class="bi bi-printer-fill me-2"></i> Imprimir Boleta
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TEMPLATE: ITEM DE MOVIMIENTO -->
        <template id="tplMovimientoNomina">
            <div class="movimiento-nomina-item bg-white border border-secondary-subtle rounded-3 p-3 position-relative shadow-sm mb-2 js-mov-container">
                <input type="hidden" data-name="id_concepto" value="">
                <input type="hidden" data-name="id_adelanto_ref" value="">
                
                <button type="button" class="btn btn-link text-danger p-0 position-absolute top-0 end-0 mt-2 me-2 js-remove-movimiento" title="Eliminar fila">
                    <i class="bi bi-x-circle-fill fs-5"></i>
                </button>
                
                <span class="badge bg-secondary position-absolute top-0 start-0 translate-middle js-mov-index mt-2 ms-2">#1</span>

                <div class="row g-2 mt-1">
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold mb-1">Tipo <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm shadow-none fw-medium border-secondary-subtle js-tipo-select" data-name="tipo_concepto" required>
                            <option value="" disabled selected>Seleccione...</option>
                            <option value="PERCEPCION">Bono / Percepción</option>
                            <option value="DEDUCCION">Deducción / Descuento</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small text-muted fw-bold mb-1">Categoría <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm shadow-none border-secondary-subtle fw-medium" data-name="categoria_concepto" required>
                            <option value="" disabled selected>Elija un Tipo primero</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small text-muted fw-bold mb-1">Descripción <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" data-name="descripcion" placeholder="Detalle el motivo..." required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small text-muted fw-bold mb-1">Monto (S/) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control form-control-sm shadow-none fw-bold border-secondary-subtle js-monto-input" data-name="monto" placeholder="0.00" required>
                    </div>
                </div>
                
                <div class="small text-warning-emphasis bg-warning-subtle rounded px-2 py-1 mt-2 d-none js-msg-adelanto border border-warning-subtle" style="font-size: 0.75rem;">
                    <i class="bi bi-info-circle-fill me-1"></i> Descuento de Tesorería. Puede editar el monto para cobrar en cuotas.
                </div>
            </div>
        </template>
        
    </div>

    <!-- TEMPLATE: FILA DE PAGO MULTICUENTA PARA PLANILLAS -->
    <template id="templateFilaPagoPlanilla">
        <div class="d-flex align-items-center gap-2 mb-2 fila-pago-planilla fade-in">
            <!-- Select de Cuentas Origen -->
            <select class="form-select form-select-sm shadow-none pago-cuenta border-primary-subtle" name="id_cuenta[]" required>
                <option value="" selected disabled>Cuenta Origen...</option>
                <?php foreach (($cuentas ?? []) as $cta): ?>
                    <option value="<?php echo $cta['id']; ?>">
                        <?php echo htmlspecialchars($cta['nombre']); ?> 
                        (Saldo: <?php echo $cta['moneda']; ?> <?php echo number_format((float)$cta['saldo_actual'], 2); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <!-- Input de Monto -->
            <div class="input-group input-group-sm" style="width: 150px;">
                <span class="input-group-text bg-primary-subtle text-primary border-primary-subtle fw-bold">S/</span>
                <input type="number" name="monto_cuenta[]" class="form-control text-end shadow-none pago-monto border-primary-subtle fw-bold text-dark input-monto-planilla" min="0.01" step="0.01" placeholder="0.00" required>
            </div>
            
            <!-- Botón Eliminar Fila -->
            <button type="button" class="btn btn-sm text-danger bg-danger-subtle border-0 rounded-circle btn-quitar-pago p-1" data-bs-toggle="tooltip" title="Quitar cuenta" style="width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">
                <i class="bi bi-trash-fill"></i>
            </button>
        </div>
    </template>
</turbo-frame>