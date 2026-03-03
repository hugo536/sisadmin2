<?php
$filtros = $filtros ?? [];
$periodos = $periodos ?? [];
$asientos = $asientos ?? [];
$totalPaginas = $totalPaginas ?? 1;
?>
<div class="container-fluid p-4">

    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-journal-text me-2 text-primary"></i> Asientos Manuales
            </h1>
            <p class="text-muted small mb-0 ms-1">Registro de comprobantes de diario, ajustes y provisiones.</p>
        </div>

        <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#modalNuevoAsiento">
                <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Asiento
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3">
            <form method="get" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="contabilidad/asientos">
                
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Desde</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_desde" value="<?php echo e((string)($filtros['fecha_desde'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-3">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 small text-muted">Hasta</span>
                        <input type="date" class="form-control bg-light border-start-0" name="fecha_hasta" value="<?php echo e((string)($filtros['fecha_hasta'] ?? '')); ?>">
                    </div>
                </div>
                
                <div class="col-12 col-md-4">
                    <select class="form-select bg-light shadow-none" name="id_periodo">
                        <option value="0">Todos los periodos contables</option>
                        <?php foreach ($periodos as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>" <?php echo (int)($filtros['id_periodo'] ?? 0) === (int)$p['id'] ? 'selected' : ''; ?>>
                                <?php echo e($p['anio'] . '-' . str_pad((string)$p['mes'], 2, '0', STR_PAD_LEFT)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-12 col-md-2">
                    <button type="submit" class="btn btn-primary w-100 shadow-sm">
                        <i class="bi bi-funnel-fill me-2"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-list me-2"></i>Comprobantes Registrados</h6>
    
    <div class="row g-3">
        <?php if (!empty($asientos)): ?>
            <?php foreach ($asientos as $a): ?>
                <?php 
                    $estado = strtoupper(trim($a['estado']));
                    $esRegistrado = ($estado === 'REGISTRADO');
                ?>
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="badge bg-light text-dark border border-secondary-subtle px-2 py-1 me-2 font-monospace fs-6">
                                    <?php echo e($a['codigo']); ?>
                                </span>
                                <span class="text-muted small me-3"><i class="bi bi-calendar3 me-1"></i><?php echo e($a['fecha']); ?></span>
                                <span class="fw-semibold text-dark"><?php echo e($a['glosa']); ?></span>
                            </div>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge rounded-pill <?php echo $esRegistrado ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?>">
                                    <?php echo $esRegistrado ? '<i class="bi bi-check-circle-fill me-1"></i>Registrado' : '<i class="bi bi-x-circle-fill me-1"></i>Anulado'; ?>
                                </span>
                                
                                <?php if ($esRegistrado): ?>
                                    <form method="post" action="<?php echo e(route_url('contabilidad/anular_asiento')); ?>" class="m-0" onsubmit="return confirm('¿Está seguro de anular este asiento contable? Esta acción es irreversible.');">
                                        <input type="hidden" name="id_asiento" value="<?php echo (int)$a['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger px-3 rounded-pill fw-semibold">
                                            Anular
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0 table-borderless table-striped table-hover">
                                    <thead class="bg-light text-muted" style="font-size: 0.8rem;">
                                        <tr>
                                            <th class="ps-4" style="width: 15%;">Código</th>
                                            <th style="width: 35%;">Cuenta Contable</th>
                                            <th style="width: 15%;">Centro de Costo</th>
                                            <th class="text-end" style="width: 15%;">Debe</th>
                                            <th class="text-end pe-4" style="width: 15%;">Haber</th>
                                        </tr>
                                    </thead>
                                    <tbody style="font-size: 0.85rem;">
                                        <?php 
                                        $sumaDebe = 0; $sumaHaber = 0;
                                        foreach ($detalleFn((int)$a['id']) as $d): 
                                            $sumaDebe += (float)$d['debe'];
                                            $sumaHaber += (float)$d['haber'];
                                        ?>
                                            <tr>
                                                <td class="ps-4 font-monospace text-primary fw-semibold"><?php echo e($d['cuenta_codigo']); ?></td>
                                                <td class="text-dark"><?php echo e($d['cuenta_nombre']); ?></td>
                                                <td class="text-muted"><span class="badge bg-light text-secondary border"><?php echo e($d['centro_costo_codigo'] ?? '-'); ?></span></td>
                                                <td class="text-end fw-medium"><?php echo number_format((float)$d['debe'], 4); ?></td>
                                                <td class="text-end fw-medium pe-4"><?php echo number_format((float)$d['haber'], 4); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot class="border-top">
                                        <tr>
                                            <td colspan="3" class="text-end text-muted fw-bold pb-2 pt-2">Totales:</td>
                                            <td class="text-end fw-bold text-dark pb-2 pt-2"><?php echo number_format($sumaDebe, 4); ?></td>
                                            <td class="text-end fw-bold text-dark pe-4 pb-2 pt-2"><?php echo number_format($sumaHaber, 4); ?></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="card border-0 shadow-sm py-5 text-center text-muted bg-light">
                    <i class="bi bi-journal-x fs-1 d-block mb-2 text-secondary opacity-50"></i>
                    No se encontraron asientos contables en este rango de fechas.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($totalPaginas > 1): ?>
        <nav aria-label="Paginación de asientos" class="mt-4 d-flex justify-content-end">
            <ul class="pagination pagination-sm shadow-sm">
                <?php $base = "?ruta=contabilidad/asientos&id_periodo=" . (int)($filtros['id_periodo'] ?? 0) . "&fecha_desde=" . urlencode((string)($filtros['fecha_desde'] ?? "")) . "&fecha_hasta=" . urlencode((string)($filtros['fecha_hasta'] ?? "")); ?>
                
                <?php for($i=1; $i<=$totalPaginas; $i++): ?>
                    <li class="page-item <?php echo $i === (int)($filtros['pagina'] ?? 1) ? 'active' : ''; ?>">
                        <a class="page-link" href="<?php echo e($base . "&pagina=" . $i); ?>"><?php echo $i; ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>

</div>

<div class="modal fade" id="modalNuevoAsiento" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-journal-plus me-2"></i>Registrar Asiento Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <form method="post" action="<?php echo e(route_url('contabilidad/guardar_asiento')); ?>" id="form-asiento" autocomplete="off">
                    
                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-body">
                            <h6 class="fw-bold text-dark border-bottom pb-2 mb-3">Datos del Comprobante</h6>
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold">Fecha Contable <span class="text-danger">*</span></label>
                                    <input type="date" name="fecha" class="form-control shadow-none" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small text-muted fw-bold">Periodo <span class="text-danger">*</span></label>
                                    <select name="id_periodo" class="form-select shadow-none" required>
                                        <option value="">Seleccione...</option>
                                        <?php foreach ($periodos as $p): 
                                            if($p['estado'] !== 'ABIERTO') continue; 
                                        ?>
                                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['anio'].'-'.str_pad((string)$p['mes'], 2, '0', STR_PAD_LEFT)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small text-muted fw-bold">Glosa / Concepto general <span class="text-danger">*</span></label>
                                    <input type="text" name="glosa" class="form-control shadow-none" placeholder="Motivo del asiento contable..." required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm mb-3">
                        <div class="card-header bg-white border-bottom pt-3 pb-2 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold text-dark mb-0">Detalle de Cuentas</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3" id="agregar-linea">
                                <i class="bi bi-plus-lg me-1"></i> Agregar Línea
                            </button>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive" style="min-height: 200px; max-height: 400px; overflow-y: auto;">
                                <table class="table align-middle mb-0 table-pro" id="lineas">
                                    <thead class="table-light sticky-top">
                                        <tr>
                                            <th class="ps-3 text-secondary" style="width: 35%;">Cuenta Contable</th>
                                            <th class="text-secondary" style="width: 20%;">Centro Costo</th>
                                            <th class="text-end text-secondary" style="width: 15%;">Debe</th>
                                            <th class="text-end text-secondary" style="width: 15%;">Haber</th>
                                            <th class="text-secondary" style="width: 10%;">Ref/Doc</th>
                                            <th class="text-center pe-3 text-secondary" style="width: 5%;"><i class="bi bi-trash"></i></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="card-footer bg-light border-top">
                            <div class="d-flex justify-content-between align-items-center py-1">
                                <span class="text-muted small"><i class="bi bi-info-circle me-1"></i>Asegúrese de que el asiento esté cuadrado para poder guardarlo.</span>
                                
                                <div class="d-flex align-items-center bg-white border rounded px-3 py-2 shadow-sm">
                                    <div class="text-end me-4">
                                        <small class="text-muted fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL DEBE</small>
                                        <span class="fs-5 fw-bold text-primary" id="sumDebe">0.0000</span>
                                    </div>
                                    <div class="text-end me-3">
                                        <small class="text-muted fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL HABER</small>
                                        <span class="fs-5 fw-bold text-primary" id="sumHaber">0.0000</span>
                                    </div>
                                    <div class="vr mx-2"></div>
                                    <span id="balanceEstado" class="badge bg-secondary ms-2 px-3 py-2 fs-6 rounded-pill">Pendiente</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end pt-3 mt-4 border-top">
                        <button type="button" class="btn btn-light text-secondary me-2 fw-semibold border" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Registrar Asiento</button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
    window.CONTA_CUENTAS = <?php echo json_encode($cuentas, JSON_UNESCAPED_UNICODE); ?>;
    window.CONTA_CENTROS = <?php echo json_encode($centrosCosto ?? [], JSON_UNESCAPED_UNICODE); ?>;
</script>

<script src="<?php echo e(base_url()); ?>/assets/js/contabilidad.js"></script>