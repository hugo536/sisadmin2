<?php
$lotes_recientes = $lotes_recientes ?? [];
$lote_actual = $lote_actual ?? null;
$detalles_nomina = $detalles_nomina ?? [];
$csrf_token = $csrf_token ?? '';
?>

<div class="container-fluid p-4 fade-in" id="planillasApp">
    
    <!-- ========================================== -->
    <!-- CABECERA: TÍTULO Y CONTROLES PRINCIPALES -->
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
                <button class="btn btn-outline-secondary fw-bold dropdown-toggle shadow-sm px-4" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-clock-history me-2"></i>Lotes Recientes
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow shadow-lg border-0" style="max-height: 400px; overflow-y: auto; width: 320px; z-index: 1050;">
                    <li class="dropdown-header fw-bold text-uppercase small text-muted bg-light border-bottom">Historial de Planillas</li>
                    <?php if (empty($lotes_recientes)): ?>
                        <li><span class="dropdown-item text-muted small py-3 text-center">No hay lotes generados.</span></li>
                    <?php else: ?>
                        <?php foreach ($lotes_recientes as $lote): ?>
                            <?php $activo = ($lote_actual && $lote_actual['id'] === $lote['id']) ? 'bg-primary-subtle' : ''; ?>
                            <li>
                                <a class="dropdown-item py-2 border-bottom transition-hover <?php echo $activo; ?>" href="<?php echo e(route_url('planillas')); ?>&id_lote=<?php echo $lote['id']; ?>">
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
            <button type="button" class="btn btn-primary fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#modalGenerarLote">
                <i class="bi bi-plus-lg me-2"></i>Generar Nuevo Lote
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- ÁREA DE TRABAJO (100% DE ANCHO) -->
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
                    
                    <div class="card-header bg-white border-bottom pt-4 pb-3 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-1"><?php echo htmlspecialchars($lote_actual['nombre']); ?></h5>
                            <span class="text-muted small fw-medium">Ref: <?php echo htmlspecialchars($lote_actual['referencia']); ?> | Frecuencia: <?php echo htmlspecialchars($lote_actual['frecuencia']); ?></span>
                        </div>
                        <div class="d-flex gap-2">
                            <?php if ($lote_actual['estado'] === 'BORRADOR'): ?>
                                <form action="<?php echo e(route_url('planillas/cerrar')); ?>" method="POST" id="formCerrarLote">
                                    <input type="hidden" name="csrf_token" value="<?php echo e($csrf_token); ?>">
                                    <input type="hidden" name="id_lote" value="<?php echo $lote_actual['id']; ?>">
                                    <button type="submit" class="btn btn-success fw-bold shadow-sm px-4">
                                        <i class="bi bi-lock-fill me-2"></i>Cerrar Planilla
                                    </button>
                                </form>
                            <?php elseif ($lote_actual['estado'] === 'APROBADO'): ?>
                                <!-- Indicador de Pendiente y Botón de Pagar (Sin botón redundante) -->
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill shadow-sm d-flex align-items-center fw-bold me-2">
                                    <i class="bi bi-clock-history me-2"></i> Falta Pagar
                                </span>
                                <button type="button" class="btn btn-primary fw-bold shadow-sm px-4" data-bs-toggle="modal" data-bs-target="#modalPagarPlanilla">
                                    <i class="bi bi-cash-stack me-2"></i>Registrar Pago
                                </button>
                            <?php elseif ($lote_actual['estado'] === 'PAGADO'): ?>
                                <!-- Indicador de Pagado Exitoso (Sin botón redundante) -->
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill shadow-sm d-flex align-items-center fw-bold me-2">
                                    <i class="bi bi-check-circle-fill me-2"></i> Planilla Pagada
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center px-4">
                        <!-- Buscador -->
                        <div class="input-group input-group-sm bg-white shadow-sm rounded-2" style="max-width: 350px;">
                            <span class="input-group-text bg-transparent border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 shadow-none py-2" id="searchDetalles" placeholder="Buscar por empleado, DNI o cargo...">
                        </div>
                        
                        <!-- Controles de la derecha (Botón Imprimir + Badge Edición) -->
                        <div class="d-flex align-items-center gap-3">
                            
                            <?php if ($lote_actual): ?>
                                <?php 
                                    $tienePagos = array_reduce($detalles_nomina, function($carry, $item) {
                                        return $carry || ((float)($item['neto_a_pagar'] ?? 0) > 0);
                                    }, false);
                                    $esBorrador = ($lote_actual['estado'] === 'BORRADOR');
                                ?>
                                
                                <!-- NUEVO BOTÓN EXPORTAR -->
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-light bg-white border border-secondary-subtle shadow-sm px-3 rounded-pill fw-bold text-secondary dropdown-toggle transition-hover" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-cloud-download me-1"></i> Exportar
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

                            <?php if ($lote_actual['estado'] === 'BORRADOR'): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill shadow-sm">
                                    <i class="bi bi-pencil-square me-1"></i> Modo Edición Activo
                                </span>
                            <?php endif; ?>
                            
                        </div>
                    </div>

                    <div class="table-responsive" style="max-height: calc(100vh - 280px);">
                        <table class="table table-hover align-middle mb-0 text-center" id="tablaDetallesNomina">
                            <thead class="table-light sticky-top shadow-sm" style="font-size: 0.8rem; text-transform: uppercase;">
                                <tr>
                                    <th class="text-start ps-4 py-3">Empleado</th>
                                    <th class="py-3">Asistencia</th>
                                    <th class="py-3">Ingresos (S/)</th>
                                    <th class="py-3">Deducciones (S/)</th>
                                    <th class="bg-success-subtle text-success py-3">Neto a Pagar</th>
                                    <th class="pe-4 py-3">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($detalles_nomina)): ?>
                                    <tr class="empty-msg-row">
                                        <td colspan="6" class="py-5 text-muted">No se encontraron empleados para este lote.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($detalles_nomina as $det): ?>
                                        <tr data-search="<?php echo strtolower(htmlspecialchars($det['nombre_completo'] . ' ' . $det['numero_documento'] . ' ' . $det['cargo'])); ?>">
                                            
                                            <!-- EMPLEADO -->
                                            <td class="text-start ps-4">
                                                <div class="fw-bold text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($det['nombre_completo']); ?></div>
                                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($det['cargo']); ?> | DNI: <?php echo htmlspecialchars((string)($det['numero_documento'] ?? 'No registrado')); ?></div>
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
                                            
                                            <!-- INGRESOS -->
                                            <td style="font-size: 0.9rem;">
                                                <?php if ($det['tiene_conflicto']): ?>
                                                    <span class="text-muted">--</span>
                                                <?php else: ?>
                                                    <div class="fw-bold text-dark"><?php echo number_format((float)$det['total_percepciones'], 2); ?></div>
                                                    <?php if ($det['monto_bonos'] > 0): ?>
                                                        <div class="text-success small fw-medium" style="font-size: 0.75rem;">+ <?php echo number_format((float)$det['monto_bonos'], 2); ?> Bonos/Otros</div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>

                                            <!-- DEDUCCIONES -->
                                            <td style="font-size: 0.9rem;">
                                                <?php if ($det['tiene_conflicto']): ?>
                                                    <span class="text-muted">--</span>
                                                <?php else: ?>
                                                    <div class="fw-bold text-danger"><?php echo number_format((float)$det['total_deducciones'], 2); ?></div>
                                                    <?php if ($det['descuento_tardanzas'] > 0): ?>
                                                        <div class="text-danger small fw-medium" style="font-size: 0.75rem;">- <?php echo number_format((float)$det['descuento_tardanzas'], 2); ?> Tardanzas</div>
                                                    <?php endif; ?>
                                                    <?php if ($det['descuento_adelanto'] > 0): ?>
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
                                            <td class="pe-4">
                                                <?php if ($lote_actual['estado'] === 'BORRADOR'): ?>
                                                    <?php if ($det['tiene_conflicto']): ?>
                                                        <a href="<?php echo e(route_url('asistencia/dashboard')); ?>" class="btn btn-sm btn-outline-danger shadow-sm px-3 rounded-pill fw-bold" title="Ir a corregir asistencia">
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
                                                    <!-- Botón de boleta individual -->
                                                    <a href="<?php echo e(route_url('planillas/imprimir_boleta')); ?>&id=<?php echo $det['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary shadow-sm px-3 rounded-pill fw-bold">
                                                        <i class="bi bi-file-earmark-pdf me-1"></i> Boleta
                                                    </a>
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
</div>

<!-- ============================================================== -->
<!-- MODAL: GENERAR LOTE -->
<!-- ============================================================== -->
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

                    <div class="row g-3 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Fecha Inicio</label>
                            <input type="date" class="form-control bg-white shadow-none text-primary fw-bold border-secondary-subtle" name="fecha_inicio" id="fechaInicioLote" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">Fecha Fin</label>
                            <input type="date" class="form-control bg-white shadow-none text-primary fw-bold border-secondary-subtle" name="fecha_fin" id="fechaFinLote" required>
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

<!-- ============================================================== -->
<!-- MODAL: AJUSTAR NÓMINA (BONOS / DEDUCCIONES) -->
<!-- ============================================================== -->
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
                        <!-- Aquí se inyectan los movimientos vía JS -->
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
<!-- ============================================================== -->
<!-- MODAL: REGISTRAR PAGO DE PLANILLA -->
<!-- ============================================================== -->
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
                        <label class="form-label fw-bold small text-muted">¿De qué cuenta saldrá el dinero? <span class="text-danger">*</span></label>
                        <select class="form-select bg-white shadow-none border-secondary-subtle fw-medium" name="id_cuenta" required>
                            <option value="" disabled selected>Seleccione cuenta origen...</option>
                            <?php foreach (($cuentas ?? []) as $cta): ?>
                                <option value="<?php echo $cta['id']; ?>">
                                    <?php echo htmlspecialchars($cta['nombre']); ?> 
                                    (Saldo: <?php echo $cta['moneda']; ?> <?php echo number_format((float)$cta['saldo_actual'], 2); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
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

<!-- ============================================================== -->
<!-- TEMPLATE: ITEM DE MOVIMIENTO (Para JS) -->
<!-- ============================================================== -->
<template id="tplMovimientoNomina">
    <div class="movimiento-nomina-item bg-white border border-secondary-subtle rounded-3 p-3 position-relative shadow-sm mb-2 js-mov-container">
        
        <!-- CAMPOS OCULTOS NUEVOS: Para rastrear origen de la bd y si es un adelanto -->
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
                    <option value="PERCEPCION">Bono / Percepción</option>
                    <option value="DEDUCCION">Deducción / Descuento</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small text-muted fw-bold mb-1">Categoría <span class="text-danger">*</span></label>
                <select class="form-select form-select-sm shadow-none border-secondary-subtle fw-medium" data-name="categoria_concepto" required>
                    <option value="" disabled selected>Seleccione...</option>
                    <option value="Mérito">Mérito / Productividad</option>
                    <option value="Movilidad">Movilidad / Viáticos</option>
                    <option value="Reintegro">Reintegro de Gastos</option>
                    <!-- ¡Opción de Adelanto eliminada de aquí! -->
                    <option value="Penalidad">Penalidad / Multa</option>
                    <option value="Otros">Otros</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small text-muted fw-bold mb-1">Descripción <span class="text-danger">*</span></label>
                <input type="text" class="form-control form-control-sm shadow-none border-secondary-subtle" data-name="descripcion" placeholder="Detalle el motivo..." required>
            </div>
            <div class="col-md-2">
                <label class="form-label small text-muted fw-bold mb-1">Monto (S/) <span class="text-danger">*</span></label>
                <input type="number" step="0.01" min="0.01" class="form-control form-control-sm shadow-none fw-bold text-primary border-secondary-subtle js-monto-input" data-name="monto" placeholder="0.00" required>
            </div>
        </div>
        
        <!-- AVISO VISUAL: Solo se mostrará vía JS si el concepto viene de un adelanto -->
        <div class="small text-warning-emphasis bg-warning-subtle rounded px-2 py-1 mt-2 d-none js-msg-adelanto border border-warning-subtle" style="font-size: 0.75rem;">
            <i class="bi bi-info-circle-fill me-1"></i> Descuento de Tesorería. Puede editar el monto para cobrar en cuotas.
        </div>
    </div>
</template>

