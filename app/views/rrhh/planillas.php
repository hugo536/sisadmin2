<?php
$desde = $desde ?? date('Y-m-d');
$hasta = $hasta ?? date('Y-m-d');
$idTercero = (int) ($id_tercero ?? 0);
$empleados = $empleados ?? [];
$planillas = $planillas ?? [];
$totales = $totales ?? ['planilla' => 0, 'descuentos' => 0, 'extras' => 0];
?>

<div class="container-fluid p-4">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-primary"></i> Planillas y Pagos
            </h1>
            <p class="text-muted small mb-0 ms-1">Liquidación de nómina, cálculo de horas extras y descuentos.</p>
        </div>

        <div class="d-flex gap-2 flex-wrap justify-content-end">
            <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold" onclick="window.print()">
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
                        <h3 class="fw-bold mb-0">S/ <?php echo number_format($totales['planilla'], 2); ?></h3>
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
                        <h3 class="fw-bold text-success mb-0">+ S/ <?php echo number_format($totales['extras'], 2); ?></h3>
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
                        <p class="small text-muted text-uppercase fw-bold mb-1">Total Descuentos (Tardanzas)</p>
                        <h3 class="fw-bold text-danger mb-0">- S/ <?php echo number_format($totales['descuentos'], 2); ?></h3>
                    </div>
                    <div class="bg-danger-subtle rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                        <i class="bi bi-dash-circle text-danger fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4" style="border-radius: 12px;">
        <div class="card-body p-3 p-md-4">
            <form method="get" class="row g-3 align-items-end">
                <input type="hidden" name="ruta" value="planillas">

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Desde</label>
                    <input type="date" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="desde" value="<?php echo e($desde); ?>" required>
                </div>

                <div class="col-12 col-md-3">
                    <label class="form-label small text-muted fw-bold mb-1">Hasta</label>
                    <input type="date" class="form-control bg-light border-secondary-subtle shadow-sm text-secondary fw-medium" name="hasta" value="<?php echo e($hasta); ?>" required>
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

                <div class="col-12 col-md-2" style="height: 42px;">
                    <button class="btn btn-primary shadow-sm fw-bold w-100 h-100" type="submit">
                        <i class="bi bi-calculator me-2"></i>Calcular
                    </button>
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
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchPlanilla" placeholder="Buscar empleado...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="planillasTable"
                       data-erp-table="true"
                       data-search-input="#searchPlanilla"
                       data-empty-text="No hay planillas calculadas para este periodo">
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Empleado</th>
                            <th class="text-center text-secondary fw-semibold">Días (Asist/Falta)</th>
                            <th class="text-end text-secondary fw-semibold">Sueldo Base</th>
                            <th class="text-end text-success fw-semibold">H. Extras</th>
                            <th class="text-end text-danger fw-semibold">Tardanzas</th>
                            <th class="text-end text-dark fw-bold pe-4">Total a Pagar</th>
                            <th class="text-center text-secondary fw-semibold pe-4">Imprimir</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($planillas)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="7" class="text-center text-muted py-5">
                                    <i class="bi bi-receipt fs-1 d-block mb-2 text-light"></i>
                                    No se encontraron datos de asistencia para calcular en este periodo.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($planillas as $row): ?>
                                <?php $searchStr = strtolower($row['nombre_completo'] . ' ' . $row['numero_documento']); ?>
                                
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 align-top pt-3">
                                        <div class="fw-bold text-dark"><?php echo e($row['nombre_completo']); ?></div>
                                        <div class="small text-muted"><i class="bi bi-person-badge me-1"></i><?php echo e($row['cargo'] ?: 'Sin cargo'); ?></div>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill" title="Días Pagados (Asistencias + Justificados)">
                                            <i class="bi bi-check-circle me-1"></i><?php echo $row['dias_asistidos']; ?>
                                        </span>
                                        <?php if($row['dias_falta'] > 0): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill ms-1" title="Días de Falta">
                                                <i class="bi bi-x-circle me-1"></i><?php echo $row['dias_falta']; ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 fw-medium text-secondary">
                                        <?php echo $row['moneda']; ?> <?php echo number_format($row['sueldo_base_calculado'], 2); ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 fw-medium text-success">
                                        <?php if($row['monto_horas_extras'] > 0): ?>
                                            + <?php echo number_format($row['monto_horas_extras'], 2); ?>
                                            <div class="small text-success opacity-75">(<?php echo $row['horas_extras']; ?> hrs)</div>
                                        <?php else: ?>
                                            <span class="text-muted opacity-50">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 fw-medium text-danger">
                                        <?php if($row['monto_descuento_tardanza'] > 0): ?>
                                            - <?php echo number_format($row['monto_descuento_tardanza'], 2); ?>
                                            <div class="small text-danger opacity-75">(<?php echo $row['minutos_tardanza']; ?> min)</div>
                                        <?php else: ?>
                                            <span class="text-muted opacity-50">0.00</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-end align-top pt-3 pe-4">
                                        <span class="fs-5 fw-bold text-primary">
                                            <?php echo $row['moneda']; ?> <?php echo number_format($row['neto_a_pagar'], 2); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3 pe-4">
                                        <a href="<?php echo e(route_url('planillas/imprimirTicket&id=' . $row['id_tercero'] . '&desde=' . $desde . '&hasta=' . $hasta)); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-light text-primary border-0 rounded-circle shadow-sm"
                                           data-bs-toggle="tooltip" title="Imprimir Ticket (80mm)">
                                            <i class="bi bi-printer fs-5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="planillasPaginationInfo">Calculando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="planillasPaginationControls"></ul>
                </nav>
            </div>
            
        </div>
    </div>
</div>