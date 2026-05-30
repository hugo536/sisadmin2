<?php
$registros = $registros ?? [];
$proveedores = $proveedores ?? [];
$conceptos = $conceptos ?? [];

// --- INICIO BLOQUE DE ALERTAS SWEETALERT2 ---
$swalIcon = null;
$swalMessage = null;

if (!empty($_GET['error'])) {
    $swalIcon = 'error';
    $swalMessage = (string) $_GET['error'];
} elseif (!empty($_GET['ok'])) {
    $swalIcon = 'success';
    $swalMessage = 'El registro de gasto se guardó correctamente.';
}
// --- FIN BLOQUE DE ALERTAS ---


// Configuración de Estados Operativos
$estadoLabels = [
    'REGISTRADO' => ['texto' => 'Registrado', 'clase' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    'ANULADO'    => ['texto' => 'Anulado',    'clase' => 'bg-danger-subtle text-danger border border-danger-subtle'],
    'PENDIENTE'  => ['texto' => 'Pendiente',  'clase' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle'],
    'PAGADO'     => ['texto' => 'Pagado',     'clase' => 'bg-success-subtle text-success border border-success-subtle'],
];
?>

<div class="container-fluid p-4" id="gastosRegistroApp">

    <?php if ($swalMessage !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: '<?php echo $swalIcon; ?>',
                        title: '<?php echo $swalIcon === 'error' ? 'Error en la Base de Datos' : '¡Éxito!'; ?>',
                        text: '<?php echo htmlspecialchars($swalMessage, ENT_QUOTES, 'UTF-8'); ?>',
                        confirmButtonText: 'Entendido'
                    });
                }
            });
        </script>
    <?php endif; ?>
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 fade-in inventario-sticky-header">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-receipt-cutoff me-2 text-primary"></i> Registro de Gastos
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión operativa de gastos (Los pagos se manejan en Tesorería).</p>
        </div>
        <div class="d-flex gap-2 flex-wrap justify-content-md-end">
            <button class="btn btn-primary shadow-sm fw-bold px-3 transition-hover" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
                <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Registro
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm mb-3 inventario-sticky-filters">
        <div class="card-body p-3">
            <form method="get" action="<?php echo e(route_url('gastos/registros')); ?>" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="gastos/registros">

                <div class="col-12 col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" id="buscarRegistro" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" placeholder="Buscar concepto o proveedor...">
                    </div>
                </div>

                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary-subtle text-muted fw-semibold" style="font-size: 0.85rem;">Desde</span>
                        <input type="date" name="fecha_desde" class="form-control shadow-none border-secondary-subtle text-secondary" value="<?php echo htmlspecialchars($filtros['fecha_desde'] ?? date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>">
                        <span class="input-group-text bg-light border-secondary-subtle border-start-0 border-end-0 text-muted fw-semibold" style="font-size: 0.85rem;">Hasta</span>
                        <input type="date" name="fecha_hasta" class="form-control shadow-none border-secondary-subtle text-secondary" value="<?php echo htmlspecialchars($filtros['fecha_hasta'] ?? date('Y-m-t'), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-secondary shadow-sm"><i class="bi bi-filter"></i></button>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <select id="filtroEstado" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Estados</option>
                        <option value="PENDIENTE">Pendiente</option>
                        <option value="PAGADO">Pagado</option>
                        <option value="ANULADO">Anulado</option>
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive inventario-table-wrapper">
                
                <table id="registrosTable" class="table align-middle mb-0 table-pro table-hover" 
                       data-erp-table="true" 
                       data-rows-selector="#registrosTableBody tr:not(.empty-msg-row)"
                       data-search-input="#buscarRegistro" 
                       data-empty-text="No se encontraron registros de gastos."
                       data-info-text-template="Mostrando {start} a {end} de {total} registros"
                       data-erp-filters='[{"el":"#filtroEstado", "attr":"data-estado"}]'
                       data-rows-per-page="15"
                       data-pagination-controls="#registrosPaginationControls"
                       data-pagination-info="#registrosPaginationInfo">
                       
                    <thead class="inventario-sticky-thead bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Proveedor</th>
                            <th class="text-secondary fw-semibold">Concepto</th>
                            <th class="text-secondary fw-semibold text-center">Impuesto</th>
                            <th class="text-end text-secondary fw-semibold">Total</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="registrosTableBody">
                    <?php foreach($registros as $r): ?>
                        <?php 
                            $textoBusqueda = strtolower($r['fecha'] . ' ' . $r['proveedor'] . ' ' . $r['concepto']); 
                            $estado = strtoupper((string)$r['estado']);
                            $badge = $estadoLabels[$estado] ?? $estadoLabels['REGISTRADO'];
                            $estaActivo = $estado !== 'ANULADO';
                        ?>
                        
                        <tr class="border-bottom" 
                            data-search="<?php echo e($textoBusqueda); ?>"
                            data-proveedor="<?php echo (int)($r['id_proveedor'] ?? 0); ?>"
                            data-estado="<?php echo e($estado); ?>">
                            
                            <td class="ps-4 text-muted"><i class="bi bi-calendar me-1 opacity-50"></i><?php echo date('d/m/Y', strtotime((string)$r['fecha'])); ?></td>
                            <td class="fw-medium text-dark">
                                <div class="d-block"><?php echo e((string)$r['proveedor']); ?></div>
                                <?php if (!empty(trim((string)$r['observacion']))): ?>
                                    <small class="text-muted fw-normal d-block mt-1 text-truncate" style="font-size: 0.75rem; max-width: 250px;" title="<?php echo htmlspecialchars((string)$r['observacion'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars((string)$r['observacion'], ENT_QUOTES, 'UTF-8'); ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted"><?php echo e((string)$r['concepto']); ?></td>
                            <td class="text-center"><span class="badge bg-light text-secondary border px-2 py-1"><?php echo e((string)$r['impuesto_tipo']); ?></span></td>
                            <td class="text-end fw-bold text-primary">S/ <?php echo number_format((float)$r['total'], 2); ?></td>
                            
                            <td class="text-center">
                                <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo e($badge['clase']); ?>">
                                    <?php echo e($badge['texto']); ?>
                                </span>
                            </td>

                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center justify-content-end gap-1">
                                    <button type="button"
                                            class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle js-ver-gasto"
                                            data-bs-toggle="tooltip" title="Ver Detalle"
                                            data-id="<?php echo (int)$r['id']; ?>"
                                            data-fecha="<?php echo date('d/m/Y', strtotime((string)$r['fecha'])); ?>"
                                            data-proveedor="<?php echo e((string)$r['proveedor']); ?>"
                                            data-concepto="<?php echo e((string)$r['concepto']); ?>"
                                            data-impuesto="<?php echo e((string)$r['impuesto_tipo']); ?>"
                                            data-monto="<?php echo number_format((float)$r['monto'], 2); ?>"
                                            data-total="<?php echo number_format((float)$r['total'], 2); ?>"
                                            data-estado="<?php echo e($estado); ?>"
                                            data-cxp="<?php echo (int)($r['id_cxp'] ?? 0); ?>"
                                            data-asiento="<?php echo (int)($r['id_asiento'] ?? 0); ?>"
                                            data-observacion="<?php echo htmlspecialchars((string)($r['observacion'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                        <i class="bi bi-eye fs-5"></i>
                                    </button>

                                    <?php if ($estaActivo): ?>
                                        <form method="post" action="<?php echo e(route_url('gastos/anular_registro')); ?>" class="d-inline m-0 p-0 js-form-confirm">
                                            <input type="hidden" name="id" value="<?php echo (int)$r['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-bs-toggle="tooltip" title="Anular Gasto">
                                                <i class="bi bi-trash fs-5"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if(empty($registros)): ?>
                        <tr class="empty-msg-row border-bottom-0">
                            <td colspan="7" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                No se encontraron registros de gastos.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-0 px-4 py-3 border-top bg-white rounded-bottom">
                <div class="small text-muted fw-medium" id="registrosPaginationInfo">Calculando resultados...</div>
                <nav aria-label="Paginación de registros">
                    <ul class="pagination pagination-sm mb-0 shadow-sm" id="registrosPaginationControls"></ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalDetalleGasto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-dark text-white py-3 border-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i>Detalle del Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4 bg-light">
                
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom border-secondary-subtle">
                    <span class="text-muted small fw-semibold">ID Registro Sistema: <span id="detGastoId" class="text-dark fw-bold"></span></span>
                    <span id="detGastoEstado" class="badge bg-secondary bg-opacity-25 text-dark border border-secondary-subtle rounded-pill px-3">-</span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-6">
                        <label class="text-muted small fw-bold mb-1 d-block"><i class="bi bi-calendar-event me-1"></i>Fecha</label>
                        <span id="detGastoFecha" class="fw-medium text-dark">-</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-bold mb-1 d-block"><i class="bi bi-building me-1"></i>Proveedor</label>
                        <span id="detGastoProveedor" class="fw-medium text-dark">-</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-bold mb-1 d-block"><i class="bi bi-tags me-1"></i>Concepto</label>
                        <span id="detGastoConcepto" class="fw-medium text-dark">-</span>
                    </div>
                    <div class="col-sm-6">
                        <label class="text-muted small fw-bold mb-1 d-block"><i class="bi bi-percent me-1"></i>Impuesto</label>
                        <span id="detGastoImpuesto" class="fw-medium text-dark">-</span>
                    </div>
                </div>

                <div class="bg-white p-3 rounded-3 border border-secondary-subtle mb-4 shadow-sm">
                    <label class="text-muted small fw-bold d-block mb-2"><i class="bi bi-chat-left-text me-1"></i>Observación</label>
                    <p id="detGastoObservacion" class="mb-0 text-dark fst-italic text-break" style="font-size: 0.95rem;">-</p>
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <div class="bg-white p-3 rounded-3 border border-secondary-subtle h-100 text-center d-flex flex-column justify-content-center shadow-sm">
                            <small class="text-muted fw-bold d-block mb-1">Monto Base</small>
                            <span id="detGastoMonto" class="fs-5 fw-semibold text-secondary">-</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-primary-subtle p-3 rounded-3 border border-primary-subtle h-100 text-center d-flex flex-column justify-content-center shadow-sm">
                            <small class="text-primary-emphasis fw-bold d-block mb-1">TOTAL GASTO</small>
                            <span id="detGastoTotal" class="fs-4 fw-bold text-primary">-</span>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer bg-white border-top justify-content-between py-2">
                <div class="small">
                    <span class="text-muted fw-semibold me-1"><i class="bi bi-safe me-1"></i>ID CxP:</span>
                    <span id="detGastoCxp" class="badge bg-warning text-dark border border-warning-subtle">-</span>
                </div>
                <div class="small">
                    <span class="text-muted fw-semibold me-1"><i class="bi bi-journal-bookmark me-1"></i>ID Asiento:</span>
                    <span id="detGastoAsiento" class="badge bg-info text-dark border border-info-subtle">-</span>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="<?php echo e(route_url('gastos/guardar_registro')); ?>" class="modal-content border-0 shadow-lg" id="formNuevoGasto">
            
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Registro de Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                <div class="card modal-pastel-card mb-4 border-0 shadow-sm">
                    <div class="card-body p-3">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-semibold mb-1">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control shadow-none border-secondary-subtle" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-semibold mb-1">Impuestos</label>
                                <select class="form-select shadow-none border-secondary-subtle" name="impuesto_tipo">
                                    <option value="NINGUNO">Sin impuesto</option>
                                    <option value="IGV">IGV / IVA</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Proveedor <span class="text-danger">*</span></label>
                                <select id="id_proveedor" class="form-select shadow-none border-secondary-subtle" name="id_proveedor" required data-tom-placeholder="Buscar proveedor...">
                                    <option value="" selected disabled hidden>Seleccione proveedor...</option>
                                    <?php foreach($proveedores as $p): ?>
                                        <option value="<?php echo (int)$p['id']; ?>"><?php echo e((string)$p['nombre_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Concepto <span class="text-danger">*</span></label>
                                <select id="idConceptoGasto" class="form-select shadow-none border-secondary-subtle" name="id_concepto" required placeholder="Buscar concepto...">
                                    <option value="">Buscar concepto...</option>
                                    <?php foreach($conceptos as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>" data-centro-costo="<?php echo (int)($c['id_centro_costo'] ?? 0); ?>"><?php echo e((string)$c['codigo'].' - '.$c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Centro de Costo <span class="text-danger">*</span></label>
                                <select id="idCentroCostoGasto" class="form-select shadow-none border-secondary-subtle" name="id_centro_costo" required>
                                    <option value="" selected disabled hidden>Seleccione centro de costo...</option>
                                    <?php foreach(($centrosCosto ?? []) as $cc): ?>
                                        <option value="<?php echo (int)$cc['id']; ?>"><?php echo e((string)$cc['codigo'].' - '.$cc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label small text-muted fw-semibold mb-1">Observación (Opcional)</label>
                                <textarea class="form-control shadow-none border-secondary-subtle" name="observacion" rows="2" placeholder="Detalles adicionales o justificación del gasto..."></textarea>
                            </div>

                            <div class="col-12 mt-4 pt-3 border-top">
                                <label class="form-label small text-muted fw-semibold mb-1">Monto Total <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted fw-bold border-secondary-subtle">S/</span>
                                    <input type="number" id="gastoMontoTotal" step="0.01" min="0.01" class="form-control shadow-none border-secondary-subtle text-primary fw-bold fs-5" name="monto" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-success-subtle shadow-sm d-none fade-in" id="seccionPagoInmediato">
                    <div class="card-body p-3 bg-success-subtle rounded">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-success-emphasis">Registro de Pago Rápido</h6>
                                <small class="text-success-emphasis opacity-75">Define la salida de dinero de caja o bancos para liquidar este gasto al instante.</small>
                            </div>
                        </div>

                        <div id="contenedorMetodosPagoGasto" class="d-flex flex-column gap-2 mb-2"></div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" id="btnAgregarPagoInmediatoGasto">
                                <i class="bi bi-plus-circle me-1"></i> Añadir otra cuenta/método
                            </button>
                            <div class="text-end">
                                <small class="text-success-emphasis fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL PAGADO</small>
                                <span class="fw-bold text-dark fs-5" id="totalPagadoInmediatoGasto">S/ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="form-check form-switch m-0 ps-5" id="switchPagoGastoContainer">
                    <input class="form-check-input" type="checkbox" id="switchPagoInmediato" name="pago_inmediato" value="1" style="cursor: pointer; transform: scale(1.1);">
                    <label class="form-check-label fw-bold text-primary small ms-2" for="switchPagoInmediato" style="cursor: pointer;">
                        Pagar Al Contado (Inmediato)
                    </label>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-light text-secondary fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnGuardarGasto"><i class="bi bi-save me-2"></i>Guardar Gasto</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    // Puente de datos PHP -> JavaScript para Pagos Inmediatos de Gastos
    window.TESORERIA_CUENTAS = <?php echo json_encode($cuentas ?? []); ?>;
    window.TESORERIA_METODOS = <?php echo json_encode($metodos ?? []); ?>;
</script>