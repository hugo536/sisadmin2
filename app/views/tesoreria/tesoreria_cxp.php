<?php
$registros = $registros ?? [];
$filtros = $filtros ?? [];
$cuentas = $cuentas ?? [];
$metodos = $metodos ?? [];
$proveedores = $proveedores ?? [];
$centros_costo = $centros_costo ?? []; 

// 1. CAPTURAR LA PESTAÑA ACTIVA (Por defecto: pendientes)
$vistaActual = $_GET['vista'] ?? 'pendientes';

// 2. FILTRAR LOS REGISTROS SEGÚN LA PESTAÑA
$registrosFiltrados = array_filter($registros, function($r) use ($vistaActual) {
    $estado = strtoupper(trim((string) ($r['estado'] ?? '')));
    $esDeuda = in_array($estado, ['PENDIENTE', 'PARCIAL', 'VENCIDA', 'ABIERTA'], true);
    
    if ($vistaActual === 'pendientes') return $esDeuda;
    if ($vistaActual === 'resueltos') return !$esDeuda;
    return true; // 'todos'
});

// 3. ORDENAMIENTO INTELIGENTE
usort($registrosFiltrados, function($a, $b) {
    $estadosDeuda = ['PENDIENTE', 'PARCIAL', 'VENCIDA', 'ABIERTA']; 
    
    $estadoA = strtoupper(trim((string) ($a['estado'] ?? '')));
    $estadoB = strtoupper(trim((string) ($b['estado'] ?? '')));
    
    $esDeudaA = in_array($estadoA, $estadosDeuda, true) ? 1 : 0;
    $esDeudaB = in_array($estadoB, $estadosDeuda, true) ? 1 : 0;
    
    if ($esDeudaA !== $esDeudaB) {
        return $esDeudaB <=> $esDeudaA; 
    }
    
    $fechaA = strtotime((string) ($a['fecha_vencimiento'] ?? '')) ?: 0;
    $fechaB = strtotime((string) ($b['fecha_vencimiento'] ?? '')) ?: 0;
    
    if ($fechaA === $fechaB) {
        return (int)($b['id'] ?? 0) <=> (int)($a['id'] ?? 0);
    }
    return $fechaB <=> $fechaA;
});

$badge = static function (string $estado): string {
    if ($estado === 'PAGADA') return 'bg-success-subtle text-success border border-success-subtle';
    if ($estado === 'PARCIAL') return 'bg-warning-subtle text-warning-emphasis border border-warning-subtle';
    if ($estado === 'VENCIDA') return 'bg-danger-subtle text-danger border border-danger-subtle';
    if ($estado === 'ANULADA') return 'bg-secondary-subtle text-secondary border border-secondary-subtle';
    return 'bg-primary-subtle text-primary border border-primary-subtle';
};

$swalIcon = null;
$swalMessage = null;

if (!empty($_GET['error'])) {
    $swalIcon = 'error';
    $swalMessage = (string) $_GET['error'];
} elseif (!empty($_GET['ok'])) {
    $swalIcon = 'success';
    $swalMessage = 'Operación registrada correctamente.';
}
?>

<div class="container-fluid p-4" id="tesoreriaCxpApp">
    
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-shop me-2 text-warning"></i> Tesorería - Cuentas por Pagar
            </h1>
            <p class="text-muted small mb-0 ms-1">Control de deudas y saldos a favor con proveedores.</p>
        </div>
        
        <div class="d-flex gap-2 flex-wrap justify-content-end align-items-center">
            <a href="<?php echo e(route_url('reportes/tesoreria')); ?>" class="btn btn-sm btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-bar-chart-line me-2 text-primary"></i>Reportes
            </a>
            <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalPagoManual">
                <i class="bi bi-plus-circle me-2"></i>Registrar Pago Manual
            </button>
        </div>
    </div>
    
    <?php if ($swalMessage !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal === 'undefined') return;
                Swal.fire({
                    icon: <?php echo json_encode($swalIcon); ?>,
                    title: <?php echo json_encode($swalIcon === 'error' ? 'Error' : 'Éxito'); ?>,
                    text: <?php echo json_encode($swalMessage); ?>,
                    confirmButtonText: 'Entendido'
                });
            });
        </script>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-3 p-md-4">
            <form method="get" action="" class="row g-2 align-items-center" id="formFiltrosCxp">
                <input type="hidden" name="ruta" value="tesoreria/cxp">
                <input type="hidden" name="vista" id="inputVistaGlobal" value="<?php echo e($vistaActual); ?>">

                <div class="col-12 col-md-4">
                    <select class="form-select bg-light border-secondary-subtle shadow-none text-secondary auto-submit" name="tipo_tercero">
                        <option value="">Todos los Tipos de Tercero</option>
                        <option value="proveedor" <?php echo (($filtros['tipo_tercero'] ?? '') === 'proveedor') ? 'selected' : ''; ?>>Proveedor</option>
                        <option value="servicios" <?php echo (($filtros['tipo_tercero'] ?? '') === 'servicios') ? 'selected' : ''; ?>>Servicios</option>
                    </select>
                </div>

                <div class="col-12 col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary-subtle text-muted fw-semibold" style="font-size: 0.85rem;">Desde (Venc.)</span>
                        <input type="date" name="fecha_desde" class="form-control shadow-none border-secondary-subtle text-secondary auto-submit" value="<?php echo e((string) ($filtros['fecha_desde'] ?? date('Y-m-01'))); ?>">
                        
                        <span class="input-group-text bg-light border-secondary-subtle border-start-0 border-end-0 text-muted fw-semibold" style="font-size: 0.85rem;">Hasta</span>
                        <input type="date" name="fecha_hasta" class="form-control shadow-none border-secondary-subtle text-secondary auto-submit" value="<?php echo e((string) ($filtros['fecha_hasta'] ?? date('Y-m-d', strtotime('+30 days')))); ?>">
                        
                        <button type="submit" class="btn btn-secondary shadow-sm"><i class="bi bi-filter"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <ul class="nav nav-tabs border-bottom-1 mb-0 px-2" role="tablist">
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link fs-6 fw-semibold py-3 js-tab-cxp <?php echo $vistaActual === 'pendientes' ? 'active text-warning-emphasis border-warning border-bottom-0 bg-white' : 'text-secondary bg-light border-0'; ?>" data-vista="pendientes">
                <i class="bi bi-exclamation-circle me-2"></i>Por Pagar / Saldos a favor
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link fs-6 fw-semibold py-3 js-tab-cxp <?php echo $vistaActual === 'resueltos' ? 'active text-warning-emphasis border-warning border-bottom-0 bg-white' : 'text-secondary bg-light border-0'; ?>" data-vista="resueltos">
                <i class="bi bi-check2-all me-2"></i>Historial Pagado
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button type="button" class="nav-link fs-6 fw-semibold py-3 js-tab-cxp <?php echo $vistaActual === 'todos' ? 'active text-warning-emphasis border-warning border-bottom-0 bg-white' : 'text-secondary bg-light border-0'; ?>" data-vista="todos">
                <i class="bi bi-border-all me-2"></i>Todas
            </button>
        </li>
    </ul>

    <div class="card border-0 shadow-sm rounded-top-0 border-top border-warning border-3" id="contenedorTablaCxp">
        <div class="card-header bg-white border-bottom pt-4 pb-3 ps-4 pe-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <h2 class="h6 fw-bold text-dark mb-0">
                    <?php 
                        if($vistaActual === 'pendientes') echo "Cuentas y Saldos Pendientes";
                        elseif($vistaActual === 'resueltos') echo "Historial de Documentos Cerrados";
                        else echo "Todos los Documentos";
                    ?>
                </h2>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle px-3 py-2 rounded-pill ms-3" id="badgeRegistros"><?php echo count($registrosFiltrados); ?> Registros</span>
            </div>
            <div class="input-group shadow-sm" style="max-width: 300px;">
                <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="search" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" id="searchCxp" placeholder="Buscar proveedor, doc o fecha...">
            </div>
        </div>
        
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-pro" id="cxpTable"
                       data-erp-table="true"
                       data-manager-global="cxpManager"
                       data-rows-selector="#cxpTableBody tr:not(.empty-msg-row)"
                       data-search-input="#searchCxp"
                       data-empty-text="No hay cuentas en esta pestaña"
                       data-info-text-template="Mostrando {start} a {end} de {total} registros"
                       data-pagination-controls="#cxpPaginationControls"
                       data-pagination-info="#cxpPaginationInfo">
                    <thead class="bg-dark text-white border-bottom">
                        <tr>
                            <th class="ps-4 fw-semibold" style="width: 25%; min-width: 180px;">Proveedor</th>
                            <th class="fw-semibold" style="white-space: nowrap !important; width: 10%;">Documento</th>
                            <th class="text-center fw-semibold" style="white-space: nowrap !important; width: 10%;">Vencimiento</th>
                            <th class="text-end fw-semibold" style="white-space: nowrap !important; width: 12%;">Total</th>
                            <th class="text-end fw-semibold" style="white-space: nowrap !important; width: 12%;">Pagado / Aplicado</th>
                            <th class="text-end fw-bold" style="white-space: nowrap !important; width: 12%;">Saldo</th>
                            <th class="text-center fw-semibold" style="white-space: nowrap !important; width: 10%;">Estado</th>
                            <th class="text-center pe-4 fw-semibold" style="white-space: nowrap !important; width: 130px; min-width: 130px;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="cxpTableBody">
                        <?php if (empty($registrosFiltrados)): ?>
                            <tr class="empty-msg-row border-bottom-0">
                                <td colspan="8" class="text-center text-muted py-5">
                                    <i class="bi bi-inbox fs-1 d-block mb-2 text-light"></i>
                                    No se encontraron cuentas con los filtros y pestaña actuales.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($registrosFiltrados as $r): ?>
                                <?php 
                                    $estadoStr = (string) ($r['estado'] ?? 'PENDIENTE');
                                    
                                    $fechaVencimientoOriginal = (string) ($r['fecha_vencimiento'] ?? '');
                                    $fechaVencimientoFormateada = !empty($fechaVencimientoOriginal) ? date('d-m-Y', strtotime($fechaVencimientoOriginal)) : '';
                                    
                                    // Identificación del tipo de documento (Para saber si es Nota de Crédito)
                                    $tipoDoc = strtoupper(trim((string) ($r['tipo_documento'] ?? 'RECEPCIÓN')));
                                    $esNotaCredito = $tipoDoc === 'NOTA_CREDITO' || $tipoDoc === 'ANTICIPO';

                                    $searchStr = strtolower(($r['proveedor'] ?? '') . ' ' . ($r['id_recepcion'] ?? '') . ' ' . ($r['documento_referencia'] ?? '') . ' ' . $tipoDoc . ' ' . $estadoStr . ' ' . $fechaVencimientoFormateada);
                                ?>
                                <tr class="border-bottom" data-search="<?php echo htmlspecialchars($searchStr, ENT_QUOTES, 'UTF-8'); ?>">
                                    <td class="ps-4 align-top pt-3">
                                        <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem;">
                                            <?php echo e((string) ($r['proveedor'] ?? '')); ?>
                                        </div>
                                        <?php 
                                            // Agrupar todas las notas de CXP de manera dinámica respetando el origen
                                            $notasConsolidadas = [];
                                            if (!empty($r['observacion_cxp']))       $notasConsolidadas[] = $r['observacion_cxp'];
                                            if (!empty($r['observacion_compra']))    $notasConsolidadas[] = $r['observacion_compra'];
                                            if (!empty($r['observacion_recepcion'])) $notasConsolidadas[] = $r['observacion_recepcion'];
                                            if (!empty($r['observacion_gasto']))     $notasConsolidadas[] = $r['observacion_gasto'];
                                            
                                            // Fallback genérico
                                            if (empty($notasConsolidadas) && !empty($r['observaciones'])) {
                                                $notasConsolidadas[] = $r['observaciones'];
                                            }
                                            
                                            if (!empty($notasConsolidadas)): 
                                                $textoNota = implode(' - ', $notasConsolidadas);
                                        ?>
                                            <small class="text-muted fw-normal d-block mt-1 text-truncate" style="font-size: 0.75rem; max-width: 250px;" title="<?php echo htmlspecialchars($textoNota, ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo e($textoNota); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="align-top pt-3 text-muted fw-medium">
                                        <?php if (($r['origen'] ?? 'SISTEMA') === 'MIGRACION'): ?>
                                            <span class="badge bg-info-subtle text-info border border-info-subtle mb-1"><i class="bi bi-clock-history"></i> Saldo Inicial</span><br>
                                            <small class="text-dark fw-bold"><?php echo e((string) ($r['documento_referencia'] ?? '')); ?></small>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-secondary border mb-1" style="font-size: 0.65rem;">
                                                <?php echo e(str_replace('_', ' ', $tipoDoc)); ?>
                                            </span><br>
                                            <span class="text-dark fw-bold">#<?php echo str_pad((string) ($r['id_recepcion'] ?? $r['id_orden_compra'] ?? $r['id']), 6, '0', STR_PAD_LEFT); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3 text-muted" data-sort="<?php echo e($fechaVencimientoOriginal); ?>">
                                        <i class="bi bi-calendar-event small me-1 opacity-50"></i><?php echo $fechaVencimientoFormateada ?: '-'; ?>
                                    </td>

                                    <td class="text-end align-top pt-3 fw-medium text-secondary">
                                        <span class="small text-muted me-1"><?php echo e($r['moneda'] ?? ''); ?></span><?php echo number_format((float) ($r['monto_total'] ?? 0), 2); ?>
                                    </td>
                                    
                                    <?php 
                                        $montoPagado = (float) ($r['monto_pagado'] ?? 0);
                                        $colorPagado = $montoPagado > 0 ? 'text-success opacity-75' : 'text-muted';
                                    ?>
                                    <td class="text-end align-top pt-3 fw-medium <?php echo $colorPagado; ?>">
                                        <span class="small me-1"><?php echo e($r['moneda'] ?? ''); ?></span><?php echo number_format($montoPagado, 2); ?>
                                    </td>

                                    <td class="text-end align-top pt-3 fw-bold <?php echo $esNotaCredito ? 'text-success' : 'text-danger'; ?>">
                                        <span class="small text-muted me-1"><?php echo e($r['moneda'] ?? ''); ?></span>
                                        <?php echo $esNotaCredito ? '+' : ''; ?><?php echo number_format((float) ($r['saldo'] ?? 0), 2); ?>
                                    </td>
                                    
                                    <td class="text-center align-top pt-3">
                                        <span class="badge px-3 py-2 rounded-pill shadow-sm <?php echo e($badge($estadoStr)); ?>">
                                            <?php echo e($estadoStr); ?>
                                        </span>
                                    </td>
                                    
                                    <td class="text-center pe-4 align-top pt-3">
                                        <?php if (in_array($estadoStr, ['PENDIENTE', 'ABIERTA', 'PARCIAL', 'VENCIDA'], true)): ?>
                                            <?php if (!$esNotaCredito): ?>
                                                <!-- BOTÓN DE PAGO NORMAL (El que ya tienes) -->
                                                <button type="button" class="btn btn-sm btn-light text-warning border-0 rounded-circle js-open-pago shadow-sm me-1" 
                                                    data-bs-toggle="modal" data-bs-target="#modalPago"
                                                    data-id-origen="<?php echo (int) $r['id']; ?>" 
                                                    data-moneda="<?php echo e((string) $r['moneda']); ?>" 
                                                    data-saldo="<?php echo (float) $r['saldo']; ?>"
                                                    title="Registrar Pago">
                                                    <i class="bi bi-cash-coin fs-5"></i>
                                                </button>
                                            <?php else: ?>
                                                <!-- 👇 NUEVO: BOTÓN DE REEMBOLSO PARA SALDOS A FAVOR 👇 -->
                                                <button type="button" class="btn btn-sm btn-light text-success border-0 rounded-circle shadow-sm me-1 js-open-reembolso" 
                                                    data-bs-toggle="modal" data-bs-target="#modalReembolso"
                                                    data-id-origen="<?php echo (int) $r['id']; ?>" 
                                                    data-moneda="<?php echo e((string) $r['moneda']); ?>" 
                                                    data-saldo="<?php echo (float) $r['saldo']; ?>"
                                                    title="El proveedor devolverá este dinero al banco">
                                                    <i class="bi bi-box-arrow-in-down-right fs-5"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <a href="<?php echo e(route_url('tesoreria/movimientos')); ?>&origen=CXP&id_origen=<?php echo (int) $r['id']; ?>&id_tercero=<?php echo (int) ($r['id_proveedor'] ?? 0); ?>" 
                                           class="btn btn-sm btn-light text-primary border-0 rounded-circle shadow-sm"
                                           data-bs-toggle="tooltip" title="Ver Historial">
                                            <i class="bi bi-journal-text fs-5"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-4 pb-4 border-top pt-3">
                <div class="small text-muted fw-medium" id="cxpPaginationInfo">Calculando...</div>
                <nav aria-label="Paginación">
                    <ul class="pagination mb-0 shadow-sm" id="cxpPaginationControls"></ul>
                </nav>
            </div>
            
        </div>
    </div>
</div>

<!-- MODALES MANTENIDOS INTACTOS ABAJO -->
<div class="modal fade" id="modalPagoManual" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Registrar Pago Manual</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_pago_manual')); ?>" class="js-form-confirm js-form-monto" id="formPagoManual">
                <div class="modal-body p-4 bg-light">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <label class="form-label small text-muted fw-bold mb-0">Proveedor <span class="text-danger">*</span></label>
                                <small id="pagoManualDeudaHint" class="small text-end mb-0 fade-in fw-bold"></small>
                            </div>
                            <select name="id_tercero" id="pagoManualProveedor" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" selected disabled>Seleccione proveedor...</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo (int) $prov['id']; ?>">
                                        <?php echo e((string) $prov['nombre_completo']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Moneda de la Deuda <span class="text-danger">*</span></label>
                            <select name="moneda" id="pagoManualMoneda" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="PEN" selected>PEN (Soles)</option>
                                <option value="USD">USD (Dólares)</option>
                            </select>
                            <div class="form-text small text-muted">Esta moneda define qué deudas FIFO se pagarán.</div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Monto a Pagar <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="pagoManualMontoInput" class="form-control shadow-sm border-secondary-subtle fw-bold text-warning-emphasis" placeholder="0.00" required>
                            <div class="form-text small text-muted">Monto en la moneda de la deuda seleccionada.</div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Cuenta Origen <span class="text-danger">*</span></label>
                            <select name="id_cuenta" id="selectCuentaOrigenManual" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" data-saldo="0" data-moneda="" selected disabled>Seleccione cuenta...</option>
                                <?php foreach ($cuentas as $cta): ?>
                                    <?php $tieneAdvertenciaContable = empty($cta['id_cuenta_contable']); ?>
                                    <?php if (!$tieneAdvertenciaContable): ?>
                                        <option value="<?php echo (int) $cta['id']; ?>" data-saldo="<?php echo (float) ($cta['saldo_actual'] ?? 0); ?>" data-moneda="<?php echo e(strtoupper((string) $cta['moneda'])); ?>" data-metodos="<?php echo htmlspecialchars((string) ($cta['metodos_pago'] ?? '[]'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars((string) $cta['nombre']); ?> (Disp: <?php echo e(strtoupper((string) $cta['moneda'])); ?> <?php echo number_format((float) ($cta['saldo_actual'] ?? 0), 2); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                            <small id="textoSaldoDisponibleManual" class="text-primary fw-bold mt-1 d-block"></small>
                        </div>

                        <div class="col-md-12" id="pagoManualContainerConversion" style="display:none;">
                            <div class="p-3 bg-white border border-primary-subtle rounded-3 shadow-sm">
                                <div class="row g-2 align-items-center">
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-bold mb-1">Tipo de Cambio Real</label>
                                        <input type="number" step="0.000001" min="0.000001" name="tipo_cambio" id="pagoManualTipoCambio" class="form-control form-control-sm border-primary-subtle text-primary fw-bold" placeholder="Ej. 3.6000">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small text-muted fw-bold mb-1" id="pagoManualLabelMontoConvertido">Monto a descontar</label>
                                        <input type="text" id="pagoManualMontoConvertido" class="form-control form-control-sm bg-light fw-bold text-muted" readonly placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-text small mt-2 text-primary">
                                    <i class="bi bi-info-circle me-1"></i> Estás cruzando monedas. El FIFO pagará la deuda en su moneda original, pero el banco descontará el monto convertido.
                                </div>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Método de Pago <span class="text-danger">*</span></label>
                            <select name="id_metodo_pago" id="pagoManualMetodoOrigen" class="form-select shadow-sm border-secondary-subtle" required disabled>
                                <option value="" selected disabled>Seleccione un método...</option>
                                <?php foreach ($metodos as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Pago <span class="text-danger">*</span></label>
                            <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold mb-1">Referencia / N° Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. EGR-2026-001">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                            <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Se aplicará automáticamente a las deudas más antiguas según la moneda seleccionada."></textarea>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-light border-top-0 pt-0">
                    <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold mb-2 mb-md-0 d-block d-md-inline-block w-100 w-md-auto" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm d-block d-md-inline-block w-100 w-md-auto"><i class="bi bi-check-circle me-2"></i>Confirmar Pago Manual</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Pago Normal (Amarillo - Diseño unificado) -->
<div class="modal fade" id="modalPago" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-dark border-bottom-0">
                <h5 class="modal-title fw-bold"><i class="bi bi-wallet2 me-2"></i>Registrar Pago</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_pago')); ?>" class="js-form-confirm js-form-monto" id="formPago">
                <!-- Fondo blanco idéntico al manual -->
                <div class="modal-body p-4 bg-white"> 
                    <input type="hidden" name="id_origen" id="pagoIdOrigen">
                    
                    <!-- Contenedor dinámico para alerta de Saldo a Favor -->
                    <div id="alertaSaldoAFavorIndividual"></div>
                    
                    <div class="row g-3">
                        
                        <!-- 1. MONTO A PAGAR -->
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-end mb-1">
                                <label class="form-label small text-muted fw-bold mb-0">Monto a Pagar (Total) <span class="text-danger">*</span></label>
                                <div class="small fw-bold text-dark d-flex align-items-center">
                                    <i class="bi bi-info-circle me-1 text-muted"></i>
                                    <span class="text-muted me-1">Saldo:</span> 
                                    <input type="text" name="moneda" id="pagoMoneda" class="form-control-plaintext text-dark p-0 fw-bold text-end" style="width: 35px; pointer-events: none;" readonly>
                                    <input type="text" id="pagoSaldo" class="form-control-plaintext text-dark p-0 fw-bold" data-saldo-target="1" style="width: 65px; pointer-events: none;" readonly>
                                </div>
                            </div>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-light border-secondary-subtle fw-bold text-muted js-lbl-moneda-doc-addon"></span>
                                <input type="number" step="0.01" min="0.01" name="monto" id="pagoMonto" class="form-control border-secondary-subtle fw-bold text-dark bg-white" readonly required>
                            </div>
                        </div>
                        
                        <!-- 2. CUENTA Y MÉTODO (Rediseñado para que no se vea apretado) -->
                        <div class="col-md-12">
                            <label class="form-label small text-muted fw-bold mb-2">Cuenta Origen y Método <span class="text-danger">*</span></label>
                            
                            <div id="pagoDistribucionRows" class="d-grid gap-3">
                                <!-- Bloque de distribución -->
                                <div class="js-pago-distribucion-row" data-row-index="0">
                                    <div class="row g-2">
                                        <!-- Cuenta ocupa todo el ancho primero -->
                                        <div class="col-12">
                                            <select name="cuenta_origen_ids[]" class="form-select shadow-sm border-secondary-subtle js-pago-cuenta" required>
                                                <option value="" data-saldo="0" data-moneda="" selected disabled>Seleccione cuenta...</option>
                                                <?php foreach ($cuentas as $cta): ?>
                                                    <?php $tieneAdvertenciaContable = empty($cta['id_cuenta_contable']); ?>
                                                    <?php if (!$tieneAdvertenciaContable): ?>
                                                        <option value="<?php echo $cta['id']; ?>" data-saldo="<?php echo $cta['saldo_actual'] ?? 0; ?>" data-tipo="<?php echo e($cta['tipo']); ?>" data-moneda="<?php echo e(strtoupper((string) $cta['moneda'])); ?>" data-metodos="<?php echo htmlspecialchars((string)($cta['metodos_pago'] ?? '[]'), ENT_QUOTES, 'UTF-8'); ?>" data-tiene-advertencia="0">
                                                            <?php echo htmlspecialchars($cta['nombre']); ?> (Disp: <?php echo e($cta['moneda']); ?> <?php echo number_format((float)($cta['saldo_actual'] ?? 0), 2); ?>)
                                                        </option>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <!-- Método y Monto se dividen debajo -->
                                        <div class="col-7">
                                            <select name="metodo_pago_ids[]" class="form-select shadow-sm border-secondary-subtle js-pago-metodo" required disabled>
                                                <option value="" selected disabled>Seleccione un método...</option>
                                                <?php foreach($metodos as $m): ?>
                                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-5">
                                            <div class="input-group shadow-sm">
                                                <span class="input-group-text bg-light border-secondary-subtle text-muted fw-bold js-lbl-moneda-doc-addon"></span>
                                                <input type="number" step="0.01" min="0.01" name="metodo_montos[]" class="form-control border-secondary-subtle js-pago-monto-distribucion" placeholder="Monto" required>
                                                <button type="button" class="btn btn-outline-danger px-2 js-remove-pago-row d-none" title="Quitar"><i class="bi bi-trash"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-2">
                                <button type="button" id="btnAddPagoDistribucion" class="btn btn-sm btn-link text-decoration-none fw-bold p-0">
                                    <i class="bi bi-plus-circle me-1"></i>Añadir otro pago
                                </button>
                                <small id="pagoDistribucionHint" class="text-muted fw-bold fade-in"></small>
                            </div>
                        </div>

                        <!-- 3. CONVERSIÓN BANCARIA -->
                        <div class="col-md-12" id="pagoContainerConversion" style="display: none;">
                            <div class="p-3 bg-light border border-primary-subtle rounded-3 shadow-sm">
                                <div class="row g-2 align-items-center">
                                    <div class="col-sm-6">
                                        <label class="form-label small text-muted fw-bold mb-1">Tipo de Cambio Real</label>
                                        <input type="number" step="0.0001" min="0.0001" name="tipo_cambio" id="pagoTipoCambio" class="form-control shadow-sm border-primary-subtle text-primary fw-bold" placeholder="Ej. 3.7500">
                                    </div>
                                    <div class="col-sm-6">
                                        <label class="form-label small text-muted fw-bold mb-1" id="pagoLabelMontoConvertido">Monto a descontar</label>
                                        <input type="text" id="pagoMontoConvertido" class="form-control shadow-sm bg-white fw-bold text-muted border-secondary-subtle" readonly placeholder="0.00">
                                    </div>
                                </div>
                                <div class="form-text small mt-2 text-primary">
                                    <i class="bi bi-info-circle me-1"></i> El banco descontará el monto convertido basado en el T.C.
                                </div>
                            </div>
                        </div>
                        
                        <!-- 4. OPCIONES AVANZADAS -->
                        <div class="col-md-12 mt-2 pt-2 border-top">
                            <a class="text-decoration-none fw-bold text-dark d-flex align-items-center py-2" data-bs-toggle="collapse" href="#pagoOpcionesAvanzadas" role="button" aria-expanded="false" aria-controls="pagoOpcionesAvanzadas">
                                <i class="bi bi-gear-fill me-2 text-warning"></i> Mostrar opciones adicionales 
                                <i class="bi bi-chevron-down ms-auto small text-muted"></i>
                            </a>
                            
                            <div class="collapse mt-2" id="pagoOpcionesAvanzadas">
                                <div class="card card-body bg-light border border-secondary-subtle shadow-sm p-3 rounded-3">
                                    <div class="row g-3">
                                        <div class="col-12 col-md-6">
                                            <label class="form-label small text-muted fw-bold mb-1">Fecha de Pago <span class="text-danger">*</span></label>
                                            <input type="date" name="fecha" class="form-control shadow-sm border-secondary-subtle" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>

                                        <div class="col-12 col-md-6">
                                            <label class="form-label small text-muted fw-bold mb-1">Naturaleza <span class="text-danger">*</span></label>
                                            <select name="naturaleza_pago" id="pagoNaturaleza" class="form-select shadow-sm border-secondary-subtle" required>
                                                <option value="DOCUMENTO" selected>Pago de deuda normal</option>
                                                <option value="CAPITAL">Solo capital</option>
                                                <option value="INTERES">Solo interés (gasto financiero)</option>
                                                <option value="MIXTO">Mixto (capital + interés)</option>
                                            </select>
                                        </div>

                                        <div class="col-6 d-none" id="grupoPagoCapital">
                                            <label class="form-label small text-muted fw-bold mb-1">Desglose: Capital</label>
                                            <input type="number" step="0.01" min="0" name="monto_capital" id="pagoMontoCapital" class="form-control shadow-sm border-secondary-subtle" value="0">
                                        </div>

                                        <div class="col-6 d-none" id="grupoPagoInteres">
                                            <label class="form-label small text-muted fw-bold mb-1">Desglose: Interés</label>
                                            <input type="number" step="0.01" min="0" name="monto_interes" id="pagoMontoInteres" class="form-control shadow-sm border-secondary-subtle text-danger fw-bold" value="0">
                                        </div>

                                        <div class="col-12 d-none" id="grupoCentroCostoInteres">
                                            <label class="form-label small text-muted fw-bold mb-1">Centro de Costo (Gasto) <span class="text-danger">*</span></label>
                                            <select name="id_centro_costo" id="pagoCentroCosto" class="form-select shadow-sm border-secondary-subtle bg-warning-subtle">
                                                <option value="" selected disabled>Seleccione...</option>
                                                <?php foreach ($centros_costo as $cc): ?>
                                                    <option value="<?php echo (int) $cc['id']; ?>"><?php echo e((string) ($cc['codigo'] ?? '') . ' - ' . (string) ($cc['nombre'] ?? '')); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text small text-muted"><i class="bi bi-info-circle me-1"></i>Asignar el interés a un departamento.</div>
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label small text-muted fw-bold mb-1">Referencia / N° Operación</label>
                                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. TRF-849392">
                                        </div>
                                        
                                        <div class="col-12">
                                            <label class="form-label small text-muted fw-bold mb-1">Observaciones</label>
                                            <textarea name="observaciones" class="form-control shadow-sm border-secondary-subtle" rows="2" placeholder="Notas adicionales del pago..."></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                    </div>
                </div>
                
                <!-- FOOTER APILADO (Igual que el modal Azul) -->
                <div class="modal-footer bg-white border-top-0 px-4 pb-4 pt-0 d-flex flex-column gap-2">
                    <button type="button" class="btn btn-white border border-secondary-subtle shadow-sm text-secondary fw-semibold w-100 m-0" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning fw-bold shadow-sm w-100 m-0 text-dark">
                        <i class="bi bi-check-circle me-2"></i>Confirmar Pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reembolso (Proveedor devuelve dinero) -->
<div class="modal fade" id="modalReembolso" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-box-arrow-in-down-right me-2"></i>Recibir Reembolso de Proveedor</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?php echo e(route_url('tesoreria/registrar_reembolso_proveedor')); ?>" id="formReembolso">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="id_origen" id="reembolsoIdOrigen">
                    <input type="hidden" name="moneda" id="reembolsoMoneda">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold mb-1">Monto Devuelto por Proveedor <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0.01" name="monto" id="reembolsoMonto" class="form-control border-secondary-subtle fw-bold text-success bg-white" required>
                        </div>
                        
                        <!-- 👇 CAMBIO: Cuenta bancaria ahora incluye saldos y data-metodos 👇 -->
                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold mb-1">¿A qué cuenta bancaria ingresó el dinero? <span class="text-danger">*</span></label>
                            <select name="id_cuenta" id="reembolsoCuentaDestino" class="form-select shadow-sm border-secondary-subtle" required>
                                <option value="" data-saldo="0" data-moneda="" selected disabled>Seleccione cuenta destino...</option>
                                <?php foreach ($cuentas as $cta): ?>
                                    <?php $tieneAdvertenciaContable = empty($cta['id_cuenta_contable']); ?>
                                    <?php if (!$tieneAdvertenciaContable): ?>
                                        <option value="<?php echo $cta['id']; ?>" data-saldo="<?php echo $cta['saldo_actual'] ?? 0; ?>" data-moneda="<?php echo e(strtoupper((string) $cta['moneda'])); ?>" data-metodos="<?php echo htmlspecialchars((string)($cta['metodos_pago'] ?? '[]'), ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars($cta['nombre']); ?> (Disp: <?php echo e($cta['moneda']); ?> <?php echo number_format((float)($cta['saldo_actual'] ?? 0), 2); ?>)
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- 👇 CAMBIO: Método de ingreso bloqueado por defecto y con ID 👇 -->
                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold mb-1">Método de Ingreso <span class="text-danger">*</span></label>
                            <select name="id_metodo_pago" id="reembolsoMetodoIngreso" class="form-select shadow-sm border-secondary-subtle" required disabled>
                                <option value="" selected disabled>Seleccione un método...</option>
                                <?php foreach($metodos as $m): ?>
                                    <option value="<?php echo (int) $m['id']; ?>"><?php echo e((string) $m['nombre']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label small text-muted fw-bold mb-1">Referencia / N° Operación</label>
                            <input type="text" name="referencia" class="form-control shadow-sm border-secondary-subtle" placeholder="Ej. OP-12345">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 pt-0">
                    <button type="button" class="btn btn-white border shadow-sm text-secondary fw-semibold mb-2 mb-md-0 w-100 w-md-auto" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold shadow-sm w-100 w-md-auto"><i class="bi bi-check-circle me-2"></i>Confirmar Ingreso</button>
                </div>
            </form>
        </div>
    </div>
</div>