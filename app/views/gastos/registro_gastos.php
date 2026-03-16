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

// Configuración de Estados Operativos (Estilo Ventas)
$estadoLabels = [
    'REGISTRADO' => ['texto' => 'Registrado', 'clase' => 'bg-primary-subtle text-primary border border-primary-subtle'],
    'ANULADO'    => ['texto' => 'Anulado',    'clase' => 'bg-danger-subtle text-danger border border-danger-subtle'],
    // Dejamos estos por compatibilidad con los gastos viejos que ya tenías en la base de datos
    'PENDIENTE'  => ['texto' => 'Migrando...', 'clase' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
    'PAGADO'     => ['texto' => 'Migrando...', 'clase' => 'bg-secondary-subtle text-secondary border border-secondary-subtle'],
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
            <div class="row g-2 align-items-center">
                <div class="col-12 col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="search" id="buscarRegistro" class="form-control bg-light border-secondary-subtle border-start-0 ps-0 shadow-none" placeholder="Buscar por fecha, proveedor o concepto...">
                    </div>
                </div>
                <div class="col-6 col-md-4">
                    <select id="filtroProveedor" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Proveedores</option>
                        <?php foreach($proveedores as $p): ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo e((string)$p['nombre_completo']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-3">
                    <select id="filtroEstado" class="form-select bg-light border-secondary-subtle shadow-none text-secondary">
                        <option value="">Todos los Estados</option>
                        <option value="REGISTRADO">Registrado</option>
                        <option value="ANULADO">Anulado</option>
                    </select>
                </div>
            </div>
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
                       data-erp-filters='[{"el":"#filtroProveedor", "attr":"data-proveedor"}, {"el":"#filtroEstado", "attr":"data-estado"}]'
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
                            
                            <td class="ps-4 text-muted"><i class="bi bi-calendar me-1 opacity-50"></i><?php echo e((string)$r['fecha']); ?></td>
                            <td class="fw-medium text-dark"><?php echo e((string)$r['proveedor']); ?></td>
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
                                            data-fecha="<?php echo e((string)$r['fecha']); ?>"
                                            data-proveedor="<?php echo e((string)$r['proveedor']); ?>"
                                            data-concepto="<?php echo e((string)$r['concepto']); ?>"
                                            data-impuesto="<?php echo e((string)$r['impuesto_tipo']); ?>"
                                            data-monto="<?php echo number_format((float)$r['monto'], 2); ?>"
                                            data-total="<?php echo number_format((float)$r['total'], 2); ?>"
                                            data-estado="<?php echo e($estado); ?>"
                                            data-cxp="<?php echo (int)($r['id_cxp'] ?? 0); ?>"
                                            data-asiento="<?php echo (int)($r['id_asiento'] ?? 0); ?>">
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
            <div class="modal-header bg-dark text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-eye me-2"></i>Detalle del Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-3 p-md-4">
                <div class="list-group list-group-flush rounded-3 overflow-hidden border border-secondary-subtle shadow-sm">
                    <div class="list-group-item d-flex justify-content-between"><strong>ID Sistema</strong><span id="detGastoId">-</span></div>
                    <div class="list-group-item d-flex justify-content-between"><strong>Fecha</strong><span id="detGastoFecha">-</span></div>
                    <div class="list-group-item d-flex justify-content-between"><strong>Proveedor</strong><span id="detGastoProveedor">-</span></div>
                    <div class="list-group-item d-flex justify-content-between"><strong>Concepto</strong><span id="detGastoConcepto">-</span></div>
                    <div class="list-group-item d-flex justify-content-between"><strong>Impuesto</strong><span id="detGastoImpuesto">-</span></div>
                    <div class="list-group-item d-flex justify-content-between"><strong>Monto Base</strong><span id="detGastoMonto" class="text-primary fw-medium">-</span></div>
                    <div class="list-group-item d-flex justify-content-between"><strong>Total Gasto</strong><span id="detGastoTotal" class="text-primary fw-bold">-</span></div>
                    <div class="list-group-item d-flex justify-content-between bg-light mt-2"><strong>ID CxP Tesorería</strong><span id="detGastoCxp" class="badge bg-warning text-dark border">-</span></div>
                    <div class="list-group-item d-flex justify-content-between bg-light"><strong>ID Asiento Contable</strong><span id="detGastoAsiento" class="badge bg-info text-dark border">-</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?php echo e(route_url('gastos/guardar_registro')); ?>" class="modal-content border-0 shadow-lg">
            
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nuevo Registro de Gasto</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body bg-light p-3 p-md-4">
                <div class="card modal-pastel-card mb-0">
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
                                <select id="id_proveedor" class="form-select shadow-none border-secondary-subtle" name="id_proveedor" required>
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
                                        <option value="<?php echo (int)$c['id']; ?>"><?php echo e((string)$c['codigo'].' - '.$c['nombre']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-12 mt-4 pt-3 border-top">
                                <label class="form-label small text-muted fw-semibold mb-1">Monto Total <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-muted fw-bold border-secondary-subtle">S/</span>
                                    <input type="number" step="0.01" min="0.01" class="form-control shadow-none border-secondary-subtle text-primary fw-bold fs-5" name="monto" placeholder="0.00" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer bg-white border-top">
                <button type="button" class="btn btn-light text-secondary me-2 fw-medium border border-secondary-subtle" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm"><i class="bi bi-save me-2"></i>Guardar Gasto</button>
            </div>
        </form>
    </div>
</div>