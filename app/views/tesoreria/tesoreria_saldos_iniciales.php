<?php
$ok = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
$error = trim((string) ($_GET['error'] ?? ''));

$swalIcon = null;
$swalMessage = null;

if ($error !== '') {
    $swalIcon = 'error';
    $swalMessage = $error;
} elseif ($ok) {
    $swalIcon = 'success';
    $swalMessage = 'Saldo inicial registrado y justificado correctamente.';
}
?>

<div class="container-fluid p-4" id="tesoreriaSaldosInicialesApp">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 fade-in">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-hourglass-split me-2 text-primary"></i> Carga de Saldos Iniciales
            </h1>
            <p class="text-muted small mb-0 ms-1">Use este módulo para registrar saldos migrados y justificar el detalle de ítems (solo informativo).</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo e(route_url('tesoreria/cxc')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-cash-stack me-1 text-primary"></i>Ctas. por Cobrar
            </a>
            <a href="<?php echo e(route_url('tesoreria/cxp')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-shop me-1 text-warning"></i>Ctas. por Pagar
            </a>
        </div>
    </div>

    <?php if ($swalMessage !== null): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: '<?php echo $swalIcon; ?>',
                        title: '<?php echo $swalIcon === 'error' ? 'Error' : 'Éxito'; ?>',
                        text: '<?php echo $swalMessage; ?>',
                        confirmButtonText: 'Entendido'
                    });
                }
            });
        </script>
    <?php endif; ?>

    <form method="post"
          action="<?php echo e(route_url('tesoreria/guardar_saldo_inicial')); ?>"
          class="js-form-confirm"
          id="formSaldoInicial"
          data-url-terceros="<?php echo e(route_url('tesoreria/ajax_terceros_saldos')); ?>"
          data-url-items="<?php echo e(route_url('tesoreria/ajax_items_saldos')); ?>">

        <div class="row g-4 fade-in">
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 bg-light-subtle rounded-4">
                        <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="bi bi-info-circle me-2"></i>Datos del Saldo</h5>
                        
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small mb-2">Naturaleza</label>
                            <div class="d-flex flex-column gap-2">
                                <input type="radio" class="btn-check" name="tipo_deuda" id="tipoCliente" value="CLIENTE" checked autocomplete="off">
                                <label class="btn btn-outline-primary text-start px-3 py-2 shadow-sm fw-bold" for="tipoCliente">
                                    <i class="bi bi-person-lines-fill me-2"></i>Deuda a favor (CxC)
                                </label>

                                <input type="radio" class="btn-check" name="tipo_deuda" id="tipoProveedor" value="PROVEEDOR" autocomplete="off">
                                <label class="btn btn-outline-warning text-dark text-start px-3 py-2 shadow-sm fw-bold" for="tipoProveedor">
                                    <i class="bi bi-shop me-2"></i>Deuda en contra (CxP)
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="saldoInicialTercero" class="form-label fw-bold text-muted small mb-1">Tercero <span class="text-danger">*</span></label>
                            <select name="id_tercero" id="saldoInicialTercero" class="form-select shadow-sm" required></select>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold text-muted small mb-1">Moneda <span class="text-danger">*</span></label>
                                <select name="moneda" id="saldoInicialMoneda" class="form-select shadow-sm fw-bold text-secondary" required>
                                    <option value="PEN" selected>PEN (S/)</option>
                                    <option value="USD">USD ($)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold text-muted small mb-1">Fecha <span class="text-danger">*</span></label>
                                <input type="date" name="fecha_emision" class="form-control shadow-sm fw-medium" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small mb-1">Documento Antiguo / N° Ref. <span class="text-danger">*</span></label>
                            <input type="text" name="documento_referencia" class="form-control shadow-sm" maxlength="50" required placeholder="Ej. F001-445">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small mb-1">Motivo Corto</label>
                            <input type="text" name="observaciones" class="form-control shadow-sm" placeholder="Ej. Saldo migrado del 2024">
                        </div>
                        
                        <div class="mt-4 pt-3 border-top">
                            <label class="form-label fw-bold text-dark mb-1">Monto Total a Registrar</label>
                            <input type="number" step="0.01" min="0.01" name="monto_total" id="saldoInicialMontoManual" class="form-control form-control-lg border-primary text-primary fw-bold shadow-sm" required placeholder="0.00">
                            <small class="text-muted" id="alertaMontoManual" style="display:none;"><i class="bi bi-info-circle"></i> Calculado automáticamente por el detalle.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 bg-white rounded-4 d-flex flex-column">
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-list-check me-2"></i>Detalle Informativo (Opcional)</h5>
                            <span class="badge bg-light text-muted border border-secondary-subtle">No afecta inventario</span>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-muted small mb-1">Buscar y agregar Ítem al detalle:</label>
                            <select id="buscadorItemsSaldo" class="form-select shadow-sm"></select>
                        </div>

                        <div class="table-responsive flex-grow-1 border rounded-3 bg-light-subtle">
                            <table class="table table-hover align-middle mb-0" id="tablaDetalleSaldos">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th style="width: 45%;">Ítem / Descripción</th>
                                        <th style="width: 15%; text-align: center;">Cantidad</th>
                                        <th style="width: 20%; text-align: right;">P. Unit.</th>
                                        <th style="width: 15%; text-align: right;">Subtotal</th>
                                        <th style="width: 5%; text-align: center;"><i class="bi bi-trash"></i></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="filaVaciaMensaje">
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="bi bi-basket d-block fs-3 mb-2 opacity-50"></i>
                                            Puede registrar el monto manualmente a la izquierda,<br>o buscar ítems arriba para calcularlo automáticamente.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                            <button type="submit" class="btn btn-primary px-5 py-2 fw-bold shadow-sm fs-6">
                                <i class="bi bi-cloud-upload me-2"></i>Guardar Saldo Inicial
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria_saldos.js"></script>