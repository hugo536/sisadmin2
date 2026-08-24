<?php
$registros = $registros ?? [];
$proveedores = $proveedores ?? [];
$conceptos = $conceptos ?? [];
$filtros = $filtros ?? [];

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

// Configuración de Estados Operativos (Estilo optimizado)
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
    
    <!-- ========================================== -->
    <!-- ENCABEZADO Y BOTONES PRINCIPALES           -->
    <!-- ========================================== -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-receipt-cutoff me-2 text-primary"></i> Registro de Gastos
            </h1>
            <p class="text-muted small mb-0 ms-1">Gestión operativa de compras y gastos menores.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary shadow-sm fw-semibold" type="button" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto">
                <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Gasto
            </button>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- BARRA DE FILTROS (Estilo Ventas)           -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" action="<?php echo e(route_url('gastos/registros')); ?>" class="row g-2 align-items-center">
                <input type="hidden" name="ruta" value="gastos/registros">

                <div class="col-12 col-lg-4">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" id="buscarRegistro" name="q" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" placeholder="Buscar proveedor o concepto..." value="<?php echo e((string) ($filtros['q'] ?? '')); ?>">
                    </div>
                </div>

                <div class="col-12 col-lg-2">
                    <select id="filtroEstado" name="estado" class="form-select bg-light border-secondary-subtle shadow-sm text-secondary">
                        <option value="">Todos los Estados</option>
                        <option value="PENDIENTE" <?php echo ($filtros['estado'] ?? '') === 'PENDIENTE' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="PAGADO" <?php echo ($filtros['estado'] ?? '') === 'PAGADO' ? 'selected' : ''; ?>>Pagado</option>
                        <option value="ANULADO" <?php echo ($filtros['estado'] ?? '') === 'ANULADO' ? 'selected' : ''; ?>>Anulado</option>
                    </select>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" class="form-control bg-light border-start-0 border-end-0 border-secondary-subtle text-secondary" value="<?php echo htmlspecialchars($filtros['fecha_desde'] ?? date('Y-m-01'), ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" class="form-control bg-light border-start-0 border-secondary-subtle text-secondary" value="<?php echo htmlspecialchars($filtros['fecha_hasta'] ?? date('Y-m-t'), ENT_QUOTES, 'UTF-8'); ?>">
                        
                        <button type="submit" class="btn btn-light border text-primary px-3 transition-hover" title="Aplicar filtros" style="z-index: 0;">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        
                        <a href="<?php echo e(route_url('gastos/registros')); ?>" class="btn btn-light border text-danger px-3 transition-hover d-flex align-items-center spa-link" title="Limpiar filtros" style="z-index: 0;">
                            <i class="bi bi-eraser-fill"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================== -->
    <!-- TABLA DE REGISTROS                         -->
    <!-- ========================================== -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                
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
                       
                    <thead class="bg-light border-bottom">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Código</th>
                            <th class="text-secondary fw-semibold">Proveedor y Detalles</th>
                            <th class="text-secondary fw-semibold">Fecha Emisión</th>
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
                            
                            <!-- COLUMNA 1: CÓDIGO Y HUELLA DE REGISTRO -->
                            <td class="ps-4">
                                <div class="fw-bold text-primary">GST-<?php echo str_pad((int)$r['id'], 5, '0', STR_PAD_LEFT); ?></div>
                                <div class="text-muted mt-1" style="font-size: 0.7rem;" title="Huella de registro en el sistema">
                                    <i class="bi bi-clock"></i> Reg: <?php echo isset($r['created_at']) ? date('d/m/Y H:i', strtotime($r['created_at'])) : '-'; ?>
                                </div>
                            </td>
                            
                            <!-- COLUMNA 2: PROVEEDOR, CONCEPTO E IMPUESTO -->
                            <td>
                                <div class="fw-semibold text-dark"><?php echo e((string)$r['proveedor']); ?></div>
                                <div class="small mt-1 d-flex flex-wrap gap-2 align-items-center">
                                    
                                    <!-- Concepto -->
                                    <span class="text-secondary"><i class="bi bi-tags me-1"></i><?php echo e((string)$r['concepto']); ?></span>
                                    
                                    <span class="text-secondary opacity-25">|</span>
                                    
                                    <!-- Badge de Impuesto integrado aquí para ahorrar columna -->
                                    <span class="badge bg-light text-secondary border border-secondary-subtle" style="font-size: 0.65rem;">
                                        <?php echo e((string)$r['impuesto_tipo']); ?>
                                    </span>

                                    <?php if (!empty(trim((string)$r['observacion']))): ?>
                                        <span class="text-secondary opacity-25">|</span>
                                        <span title="Observación" class="text-muted text-truncate" style="max-width: 150px;">
                                            <i class="bi bi-chat-text text-info me-1"></i><?php echo htmlspecialchars((string)$r['observacion'], ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <!-- COLUMNA 3: FECHA DE EMISIÓN (Realidad Comercial) -->
                            <td>
                                <div class="fw-bold text-dark mb-1">
                                    <i class="bi bi-calendar3 me-1 text-primary"></i> <?php echo date('d/m/Y', strtotime((string)$r['fecha'])); ?>
                                </div>
                            </td>
                            
                            <!-- COLUMNA 4: TOTAL -->
                            <td class="text-end fw-bold text-dark">
                                <span class="small text-muted me-1"><?php echo e($r['moneda'] ?? 'PEN'); ?></span><?php echo number_format((float)$r['total'], 2); ?>
                            </td>
                            
                            <!-- COLUMNA 5: ESTADO -->
                            <td class="text-center">
                                <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo e($badge['clase']); ?>">
                                    <?php echo e($badge['texto']); ?>
                                </span>
                            </td>

                            <!-- COLUMNA 6: ACCIONES -->
                            <td class="text-end pe-4">
                                <div class="d-inline-flex align-items-center gap-1">
                                    <button type="button"
                                            class="btn btn-sm btn-light text-secondary border-0 btn-editar rounded-circle js-ver-gasto"
                                            data-bs-toggle="tooltip" title="Ver Detalle"
                                            data-id="<?php echo (int)$r['id']; ?>"
                                            data-fecha="<?php echo date('d/m/Y', strtotime((string)$r['fecha'])); ?>"
                                            data-proveedor="<?php echo e((string)$r['proveedor']); ?>"
                                            data-concepto="<?php echo e((string)$r['concepto']); ?>"
                                            data-impuesto="<?php echo e((string)$r['impuesto_tipo']); ?>"
                                            data-moneda="<?php echo e($r['moneda'] ?? 'PEN'); ?>"
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
                            <td colspan="6" class="text-center text-muted py-5">
                                <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                No hay registros de gastos que coincidan con los filtros.
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-footer bg-white border-top-0 py-3 d-flex justify-content-between align-items-center">
                <small class="text-muted fw-semibold" id="registrosPaginationInfo">Cargando...</small>
                <nav aria-label="Navegación de registros">
                    <ul class="pagination mb-0 justify-content-end" id="registrosPaginationControls"></ul>
                </nav>
            </div>

        </div>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: NUEVO GASTO (Estilo Ventas con Cards)-->
<!-- ========================================== -->
<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form method="post" action="<?php echo e(route_url('gastos/guardar_registro')); ?>" class="modal-content border-0 shadow-lg" id="formNuevoGasto">
            
            <div class="modal-header bg-primary text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2"></i>Registrar Nuevo Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                
                <!-- Tarjeta 1: Información General del Comprobante -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Información del Documento</h6>
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label small text-muted fw-bold mb-1">Fecha de Emisión <span class="text-danger">*</span></label>
                                <input type="date" class="form-control shadow-none border-secondary-subtle" name="fecha" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small text-muted fw-bold mb-1">Proveedor / Beneficiario <span class="text-danger">*</span></label>
                                <select id="id_proveedor" class="form-select shadow-none border-secondary-subtle" name="id_proveedor" required data-tom-placeholder="Buscar proveedor...">
                                    <option value="" selected disabled hidden>Seleccione proveedor...</option>
                                    <?php foreach($proveedores as $p): ?>
                                        <option value="<?php echo (int)$p['id']; ?>"><?php echo e((string)$p['nombre_completo']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta 2: Clasificación y Montos -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-4">
                        <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">Clasificación y Valor</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-bold mb-1">Concepto de Gasto <span class="text-danger">*</span></label>
                                <select id="idConceptoGasto" class="form-select shadow-none border-secondary-subtle" name="id_concepto" required>
                                    <option value="">Buscar concepto...</option>
                                    <?php foreach($conceptos as $c): ?>
                                        <option value="<?php echo (int)$c['id']; ?>" data-centro-costo="<?php echo (int)($c['id_centro_costo'] ?? 0); ?>"><?php echo e((string)$c['codigo'].' - '.$c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label small text-muted fw-bold mb-1">Centro de Costo <span class="text-danger">*</span></label>
                                <select id="idCentroCostoGasto" class="form-select shadow-none border-secondary-subtle" name="id_centro_costo" required>
                                    <option value="" selected disabled hidden>Seleccione centro de costo...</option>
                                    <?php foreach(($centrosCosto ?? []) as $cc): ?>
                                        <option value="<?php echo (int)$cc['id']; ?>"><?php echo e((string)$cc['codigo'].' - '.$cc['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label small text-muted fw-bold mb-1">Observaciones (Opcional)</label>
                                <input type="text" class="form-control shadow-none border-secondary-subtle" name="observacion" placeholder="Detalles adicionales o justificación...">
                            </div>

                            <div class="col-md-4 mt-4">
                                <label class="form-label small text-muted fw-bold mb-1">Impuestos</label>
                                <select class="form-select shadow-none border-secondary-subtle" name="impuesto_tipo">
                                    <option value="NINGUNO">Exonerado (0%)</option>
                                    <option value="IGV" selected>Incluye IGV</option>
                                </select>
                            </div>

                            <div class="col-md-4 mt-4">
                                <label class="form-label small text-muted fw-bold mb-1">Moneda <span class="text-danger">*</span></label>
                                <select name="moneda" id="gastoMoneda" class="form-select shadow-none border-secondary-subtle" required>
                                    <option value="PEN" selected>PEN (Soles)</option>
                                    <option value="USD">USD (Dólares)</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4 mt-4">
                                <label class="form-label small text-muted fw-bold mb-1">Monto Total <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted fw-bold border-secondary-subtle js-lbl-moneda-gasto">S/</span>
                                    <input type="number" id="gastoMontoTotal" step="0.01" min="0.01" class="form-control shadow-none border-secondary-subtle text-primary fw-bold" name="monto" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tarjeta 3: Pago Inmediato (Manteniendo tu lógica original de JS) -->
                <div class="card border-success-subtle shadow-sm d-none fade-in" id="seccionPagoInmediato">
                    <div class="card-body p-3 bg-success-subtle rounded">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3 shadow-sm" style="width: 42px; height: 42px;">
                                <i class="bi bi-cash-stack fs-5"></i>
                            </div>
                            <div>
                                <h6 class="mb-0 fw-bold text-success-emphasis">Registro de Pago Rápido</h6>
                                <small class="text-success-emphasis opacity-75">Define la salida de dinero para liquidar este gasto al instante.</small>
                            </div>
                        </div>

                        <div id="contenedorMetodosPagoGasto" class="d-flex flex-column gap-2 mb-2"></div>
                        
                        <div id="gastoContainerConversion" class="mt-3 p-3 bg-white border border-primary-subtle rounded-3 shadow-sm" style="display: none;">
                            <div class="row g-2 align-items-center">
                                <div class="col-sm-6">
                                    <label class="form-label small text-muted fw-bold mb-1">Tipo de Cambio Real</label>
                                    <input type="number" step="0.0001" min="0.0001" name="tipo_cambio" id="gastoTipoCambio" class="form-control form-control-sm border-primary-subtle text-primary fw-bold" placeholder="Ej. 3.7500">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small text-muted fw-bold mb-1" id="gastoLabelMontoConvertido">Monto a descontar de cuenta</label>
                                    <input type="text" id="gastoMontoConvertido" class="form-control form-control-sm bg-light fw-bold text-muted" readonly placeholder="0.00">
                                </div>
                                <div class="col-12">
                                    <div class="form-text small mt-1 text-primary">
                                        <i class="bi bi-info-circle me-1"></i>
                                        Cruce de monedas: El gasto original se registra, pero se descontará el equivalente bancario.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mt-3">
                            <button type="button" class="btn btn-sm btn-light text-success fw-bold shadow-sm" id="btnAgregarPagoInmediatoGasto">
                                <i class="bi bi-plus-circle me-1"></i> Añadir otra cuenta
                            </button>
                            <div class="text-end">
                                <small class="text-success-emphasis fw-bold d-block lh-1" style="font-size: 0.7rem;">TOTAL PAGADO</small>
                                <span class="fw-bold text-dark fs-5" id="totalPagadoInmediatoGasto">S/ 0.00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top-0 rounded-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="form-check form-switch m-0 ps-5" id="switchPagoGastoContainer">
                    <input class="form-check-input border-success" type="checkbox" id="switchPagoInmediato" name="pago_inmediato" value="1" style="cursor: pointer; transform: scale(1.1);">
                    <label class="form-check-label fw-bold text-success small ms-2" for="switchPagoInmediato" style="cursor: pointer;">
                        Pagar Al Contado (Inmediato)
                    </label>
                </div>
                
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-light text-secondary fw-semibold border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm" id="btnGuardarGasto"><i class="bi bi-save me-2"></i>Guardar Gasto</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ========================================== -->
<!-- MODAL: DETALLE DEL GASTO (Estilo Resumen Ventas) -->
<!-- ========================================== -->
<div class="modal fade" id="modalDetalleGasto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg bg-light">
            
            <!-- Encabezado -->
            <div class="modal-header bg-dark text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold">
                    <i class="bi bi-info-circle-fill me-2"></i>Detalle del Registro
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <!-- Cuerpo del Modal -->
            <div class="modal-body p-3 p-md-4 bg-light" style="margin-top: -15px; border-top-left-radius: 1.2rem; border-top-right-radius: 1.2rem;">
                
                <!-- Tarjeta de Información General -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 p-md-4">
                        
                        <!-- Título del Pedido -->
                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                            <h6 class="fw-bold text-dark mb-0">Información del Documento</h6>
                            <span class="badge bg-secondary-subtle text-secondary fs-6 px-3 py-2 rounded-pill fw-bold">
                                ID: <span id="detGastoId">0000</span>
                            </span>
                        </div>
                        
                        <!-- Grid de Datos -->
                        <div class="row g-4">
                            <!-- Bloque Izquierdo (Proveedor, Concepto, Obs) -->
                            <div class="col-12 col-lg-8">
                                <div class="row g-3">
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Proveedor / Beneficiario</small>
                                        <div class="fw-semibold text-dark text-break" id="detGastoProveedor">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Fecha Emisión</small>
                                        <div class="fw-semibold text-dark" id="detGastoFecha">-</div>
                                    </div>
                                    
                                    <div class="col-12"><hr class="text-muted opacity-25 my-1"></div>
                                    
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Concepto de Gasto</small>
                                        <div class="text-dark small" id="detGastoConcepto">-</div>
                                    </div>
                                    <div class="col-6">
                                        <small class="text-muted fw-bold d-block mb-1">Impuesto</small>
                                        <div class="text-dark small" id="detGastoImpuesto">-</div>
                                    </div>
                                    
                                    <div class="col-12 mt-3">
                                        <small class="text-muted fw-bold d-block mb-2">Observaciones</small>
                                        <div class="bg-light p-2 rounded border border-secondary-subtle">
                                            <p id="detGastoObservacion" class="mb-0 text-secondary fst-italic small text-break">-</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Bloque Derecho (Estado de Pago) - Estilo Tarjeta Interna -->
                            <div class="col-12 col-lg-4">
                                <div class="bg-white p-3 rounded-3 border h-100 d-flex flex-column shadow-sm">
                                    <small class="text-muted fw-bold d-block mb-2 text-center">Estado del Gasto</small>
                                    
                                    <!-- Badge de Estado -->
                                    <div class="text-center mb-3">
                                        <span id="detGastoEstado" class="badge fs-6 px-3 py-2 rounded-pill">-</span>
                                    </div>
                                    
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between mb-2">
                                            <span class="text-muted small">Subtotal / Base:</span>
                                            <span id="detGastoMonto" class="fw-semibold text-secondary">-</span>
                                        </div>
                                        <hr class="my-2 opacity-25">
                                        <div class="d-flex justify-content-between align-items-center bg-primary-subtle p-2 rounded">
                                            <span class="text-primary-emphasis fw-bold small">TOTAL:</span>
                                            <span id="detGastoTotal" class="fw-bold fs-5 text-primary">-</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
            
            <!-- Pie del Modal -->
            <div class="modal-footer bg-white border-top-0 rounded-bottom d-flex justify-content-between">
                <div class="small d-flex gap-3">
                    <span title="Cuenta por Pagar generada">
                        <i class="bi bi-safe text-warning me-1"></i><span class="text-muted fw-semibold">CxP:</span> 
                        <span id="detGastoCxp" class="fw-bold text-dark">-</span>
                    </span>
                    <span title="Asiento contable vinculado">
                        <i class="bi bi-journal-bookmark text-info me-1"></i><span class="text-muted fw-semibold">Asiento:</span> 
                        <span id="detGastoAsiento" class="fw-bold text-dark">-</span>
                    </span>
                </div>
                <button type="button" class="btn btn-secondary fw-semibold px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
            
        </div>
    </div>
</div>

<script>
    // Puente de datos PHP -> JavaScript para Pagos Inmediatos de Gastos
    window.TESORERIA_CUENTAS = <?php echo json_encode($cuentas ?? []); ?>;
    window.TESORERIA_METODOS = <?php echo json_encode($metodos ?? []); ?>;
</script>