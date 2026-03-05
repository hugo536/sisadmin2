<?php
// Inicialización de variables
$desde = $desde ?? date('Y-m-d');
$hasta = $hasta ?? date('Y-m-d');
$idTercero = (int) ($id_tercero ?? 0);
$frecuencia = $frecuencia ?? ''; // NUEVO: Mantenemos el estado del filtro
$semana = $semana ?? '';
$empleados = $empleados ?? [];
$planillas = $planillas ?? [];
$totales = $totales ?? ['planilla' => 0, 'descuentos' => 0, 'extras' => 0];
$cuentas = $cuentas ?? []; 
$metodos = $metodos ?? [];
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-primary"></i> Planillas y Pagos
            </h1>
            <p class="text-muted small mb-0 ms-1">Liquidación de nómina, cálculo de horas extras y registro de pagos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end d-print-none">
            <button type="button" id="btnImprimirResumen" class="btn btn-white border shadow-sm text-secondary fw-semibold" onclick="window.print()">
                <i class="bi bi-printer me-2 text-info"></i>Imprimir Resumen
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white" style="border-radius: 12px;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="small text-white-50 text-uppercase fw-bold mb-1">Total Nómina Neta</p>
                        <h3 class="fw-bold mb-0" id="totalPlanilla">S/ <?php echo number_format($totales['planilla'], 2); ?></h3>
                    </div>
                    <div class="bg-white bg-opacity-25 rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-wallet2 fs-4 text-white"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="small text-muted text-uppercase fw-bold mb-1">Total Horas Extras</p>
                        <h3 class="fw-bold text-success mb-0" id="totalExtras">+ S/ <?php echo number_format($totales['extras'], 2); ?></h3>
                    </div>
                    <div class="bg-success-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-plus-circle text-success fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm h-100" style="border-radius: 12px;">
                <div class="card-body p-4 d-flex align-items-center justify-content-between">
                    <div>
                        <p class="small text-muted text-uppercase fw-bold mb-1">Total Descuentos</p>
                        <h3 class="fw-bold text-danger mb-0" id="totalDescuentos">- S/ <?php echo number_format($totales['descuentos'], 2); ?></h3>
                    </div>
                    <div class="bg-danger-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-dash-circle text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4 d-print-none" style="border-radius: 12px;">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="" class="row g-3 align-items-end" id="formFiltrosPlanillas">
                <input type="hidden" name="ruta" value="planillas">
                
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Desde</label>
                    <input type="date" id="filtroDesde" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="desde" value="<?php echo e($desde); ?>" required>
                </div>
                
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
                    <input type="date" id="filtroHasta" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="hasta" value="<?php echo e($hasta); ?>" required>
                </div>

                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Semana</label>
                    <input type="week" id="filtroSemana" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" value="<?php echo e($semana); ?>">
                </div>
                
                <div class="col-12 col-md-2">
                    <label class="form-label small text-muted fw-bold mb-1">Frecuencia</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="frecuencia_pago" id="filtroFrecuencia">
                        <option value="">Todas</option>
                        <option value="SEMANAL" <?php echo $frecuencia === 'SEMANAL' ? 'selected' : ''; ?>>Semanal (Jornal)</option>
                        <option value="QUINCENAL" <?php echo $frecuencia === 'QUINCENAL' ? 'selected' : ''; ?>>Quincenal</option>
                        <option value="MENSUAL" <?php echo $frecuencia === 'MENSUAL' ? 'selected' : ''; ?>>Mensual</option>
                    </select>
                </div>
                
                <div class="col-12 col-md-4">
                    <label class="form-label small text-muted fw-bold mb-1">Filtrar Empleado (Opcional)</label>
                    <select class="form-select bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="id_tercero">
                        <option value="">Todo el personal</option>
                        <?php foreach ($empleados as $emp): ?>
                            <?php if (!empty($emp['es_empleado'])): ?>
                                <?php $empId = (int) ($emp['id'] ?? 0); ?>
                                <option value="<?php echo $empId; ?>" <?php echo $idTercero === $empId ? 'selected' : ''; ?>>
                                    <?php echo e((string) ($emp['nombre_completo'] ?? '')); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 12px; overflow: hidden;">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">Detalle de Pagos</h2>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill ms-3"><?php echo count($planillas); ?> Trabajadores</span>
            </div>
            <div class="input-group shadow-sm d-print-none" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchPlanilla" placeholder="Buscar empleado..." aria-label="Buscar empleado">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="planillasTable"
                        data-erp-table="true"
                        data-search-input="#searchPlanilla"
                        data-pagination-controls="#planillasPaginationControls"
                        data-pagination-info="#planillasPaginationInfo"
                        data-manager-global="planillasManager"
                        data-empty-text="No hay planillas calculadas para este periodo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                            <th class="text-center text-secondary fw-semibold">Días (Asist/Falta)</th>
                            <th class="text-end text-secondary fw-semibold">Sueldo Base</th>
                            <th class="text-end text-success fw-semibold">H. Extras</th>
                            <th class="text-end text-danger fw-semibold">Tardanzas</th>
                            <th class="text-end text-dark fw-bold">Total a Pagar</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-center text-secondary fw-semibold pe-4 d-print-none">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planillas)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-receipt fs-1 d-block mb-2 text-light"></i>
                                    No se encontraron datos de asistencia para calcular en este periodo.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($planillas as $row): ?>
                                <?php 
                                    $searchStr = strtolower(($row['nombre_completo'] ?? '') . ' ' . ($row['numero_documento'] ?? '')); 
                                    $estadoStr = (string) ($row['estado_pago'] ?? 'PENDIENTE');
                                ?>
                                
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 align-top pt-3">
                                        <div class="fw-bold text-dark"><?php echo e($row['nombre_completo']); ?></div>
                                        <div class="small text-muted mb-1"><i class="bi bi-person-badge me-1"></i><?php echo e($row['cargo'] ?: 'Sin cargo'); ?></div>
                                        
                                        <?php if(($row['tipo_pago'] ?? '') === 'SEMANAL'): ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle" style="font-size: 0.65rem;">SEMANAL (Jornal)</span>
                                        <?php elseif(($row['tipo_pago'] ?? '') === 'QUINCENAL'): ?>
                                            <span class="badge bg-info-subtle text-info-emphasis border border-info-subtle" style="font-size: 0.65rem;">QUINCENAL</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary-emphasis border border-secondary-subtle" style="font-size: 0.65rem;">MENSUAL</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" title="Días Pagados (Asistencias + Justificados)">
                                            <i class="bi bi-check-circle me-1"></i><?php echo e((string)($row['dias_asistidos'] ?? 0)); ?>
                                        </span>
                                        <?php if(($row['dias_falta'] ?? 0) > 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill ms-1" title="Días de Falta">
                                                <i class="bi bi-x-circle me-1"></i><?php echo e((string)$row['dias_falta']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 fw-medium text-secondary">
                                        <?php echo e($row['moneda'] ?? 'S/'); ?> <?php echo number_format((float)($row['sueldo_base_calculado'] ?? 0), 2); ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 fw-medium text-success">
                                        <?php if(($row['monto_horas_extras'] ?? 0) > 0): ?>
                                            + <?php echo number_format((float)$row['monto_horas_extras'], 2); ?>
                                            <div class="small text-success opacity-75">(<?php echo e((string)$row['horas_extras']); ?> hrs)</div>
                                        <?php else: ?>
                                            <span class="text-muted opacity-50">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 fw-medium text-danger">
                                        <?php if(($row['monto_descuento_tardanza'] ?? 0) > 0): ?>
                                            - <?php echo number_format((float)$row['monto_descuento_tardanza'], 2); ?>
                                            <div class="small text-danger opacity-75">(<?php echo e((string)$row['minutos_tardanza']); ?> min)</div>
                                        <?php else: ?>
                                            <span class="text-muted opacity-50">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3">
                                        <span class="fs-5 fw-bold text-primary">
                                            <?php echo e($row['moneda'] ?? 'S/'); ?> <?php echo number_format((float)($row['neto_a_pagar'] ?? 0), 2); ?>
                                        </span>
                                    </td>

                                    <td class="text-center align-top pt-3">
                                        <?php if ($estadoStr === 'PAGADA'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill shadow-sm">PAGADA</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill shadow-sm">PENDIENTE</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3 pe-4 d-print-none">
                                        <div class="d-flex justify-content-center gap-1">
                                            <?php 
                                                $montoNeto = (float) ($row['neto_a_pagar'] ?? 0); 
                                            ?>
                                            <?php if ($estadoStr === 'PENDIENTE' && $montoNeto > 0): ?>
                                                <button type="button" class="btn btn-sm btn-light text-warning border-0 rounded-circle shadow-sm"
                                                    data-bs-toggle="modal" data-bs-target="#modalPagarPlanilla"
                                                    data-id-empleado="<?php echo (int) $row['id_tercero']; ?>"
                                                    data-nombre-empleado="<?php echo htmlspecialchars($row['nombre_completo'], ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-monto-pagar="<?php echo $montoNeto; ?>"
                                                    data-fecha-desde="<?php echo e($desde); ?>"
                                                    data-fecha-hasta="<?php echo e($hasta); ?>"
                                                    data-bs-toggle="tooltip" title="Registrar Pago">
                                                    <i class="bi bi-cash-coin fs-5"></i>
                                                </button>
                                            <?php elseif ($estadoStr === 'PENDIENTE' && $montoNeto <= 0): ?>
                                                 <span class="badge bg-secondary-subtle text-secondary" title="Sin monto a pagar">S/ 0.00</span>
                                            <?php endif; ?>

                                            <?php 
                                            $printParams = http_build_query([
                                                'id' => $row['id_tercero'],
                                                'desde' => $desde,
                                                'hasta' => $hasta
                                            ]);
                                            ?>
                                            <a href="<?php echo e(route_url("planillas/imprimirTicket&{$printParams}")); ?>" 
                                            target="_blank" 
                                            class="btn btn-sm btn-light text-primary border-0 rounded-circle shadow-sm"
                                            data-bs-toggle="tooltip" title="Imprimir Ticket (80mm)">
                                                <i class="bi bi-printer fs-5"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3 d-print-none">
                <div class="small text-muted fw-medium" id="planillasPaginationInfo">Calculando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="planillasPaginationControls"></ul>
                </nav>
            </div>
            
        </div>
    </div>
</div>

<div class="modal fade" id="modalPagarPlanilla" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Registrar Pago de Nómina</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('planillas/registrar_pago')); ?>" class="js-form-confirm">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="id_empleado" id="pagoIdEmpleado">
                    <input type="hidden" name="fecha_inicio" id="pagoFechaDesde">
                    <input type="hidden" name="fecha_fin" id="pagoFechaHasta">
                    
                    <div class="alert alert-primary shadow-sm border-0 d-flex align-items-center mb-4">
                        <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                        <div>
                            Se registrará el pago para <strong id="lblEmpleadoNombre"></strong> por el periodo del <strong id="lblPeriodo"></strong>.
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Total a Pagar <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-white border-secondary-subtle fw-bold">S/</span>
                                <input type="number" step="0.01" name="monto_pagar" id="pagoMontoTotal" class="form-control border-secondary-subtle fw-bold text-primary" readonly>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Desembolso <span class="text-danger">*</span></label>
                            <input type="date" name="fecha_pago" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-bold small text-muted">Extraer dinero de: (Cuenta de Tesorería) <span class="text-danger">*</span></label>
                            <select name="id_cuenta" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione una cuenta de origen...</option>
                                <?php foreach ($cuentas as $cta): ?>
                                    <option value="<?php echo $cta['id']; ?>">
                                        <?php echo htmlspecialchars($cta['nombre']); ?> (Saldo: <?php echo number_format($cta['saldo'] ?? 0, 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Método de Pago <span class="text-danger">*</span></label>
                            <select name="id_metodo_pago" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione método (Transferencia, Efectivo...)</option>
                                <?php foreach($metodos as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">N° Operación / Referencia (Opcional)</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. TRF-10293">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-light border shadow-sm text-secondary fw-semibold" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-check-circle me-2"></i>Confirmar Pago</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="assets/js/rrhh/planillas.js"></script>