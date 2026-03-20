<?php
$ok = isset($_GET['ok']) && (string) $_GET['ok'] === '1';
$error = trim((string) ($_GET['error'] ?? ''));
?>

<div class="container-fluid p-4" id="tesoreriaSaldosInicialesApp">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark"><i class="bi bi-hourglass-split me-2 text-primary"></i>Carga de Saldos Iniciales</h1>
            <p class="text-muted mb-0">Use este módulo únicamente para registrar deudas históricas previas al uso del sistema.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?php echo e(route_url('tesoreria/cxc')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-cash-stack me-1"></i>Ctas. por Cobrar
            </a>
            <a href="<?php echo e(route_url('tesoreria/cxp')); ?>" class="btn btn-white border shadow-sm text-secondary fw-semibold">
                <i class="bi bi-wallet me-1"></i>Ctas. por Pagar
            </a>
        </div>
    </div>

    <?php if ($ok): ?>
        <div class="alert alert-success border-0 shadow-sm">
            <i class="bi bi-check-circle-fill me-2"></i>Saldo inicial registrado correctamente.
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger border-0 shadow-sm">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo e($error); ?>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body p-4 p-lg-5 bg-light-subtle rounded-4">
            <form method="post"
                  action="<?php echo e(route_url('tesoreria/guardar_saldo_inicial')); ?>"
                  class="row g-4"
                  id="formSaldoInicial"
                  data-url-terceros="<?php echo e(route_url('tesoreria/ajax_terceros_saldos')); ?>">
                <div class="col-12">
                    <label class="form-label fw-semibold text-dark d-block mb-3">Tipo de saldo inicial</label>
                    <div class="d-flex flex-wrap gap-3">
                        <div class="form-check form-check-inline bg-white border rounded-3 px-4 py-3 shadow-sm">
                            <input class="form-check-input" type="radio" name="tipo_deuda" id="tipoCliente" value="CLIENTE" checked>
                            <label class="form-check-label fw-semibold" for="tipoCliente">Deuda a favor (Cliente)</label>
                        </div>
                        <div class="form-check form-check-inline bg-white border rounded-3 px-4 py-3 shadow-sm">
                            <input class="form-check-input" type="radio" name="tipo_deuda" id="tipoProveedor" value="PROVEEDOR">
                            <label class="form-check-label fw-semibold" for="tipoProveedor">Deuda en contra (Proveedor)</label>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="form-floating">
                        <select name="id_tercero" id="saldoInicialTercero" class="form-select shadow-sm" required>
                            <option value="">Seleccione...</option>
                        </select>
                        <label for="saldoInicialTercero">Tercero <span class="text-danger">*</span></label>
                    </div>
                    <small class="text-muted" id="saldoInicialTerceroHelp">Busque por nombre (clientes/proveedores según el tipo seleccionado).</small>
                </div>

                <div class="col-md-4">
                    <div class="form-floating">
                        <select name="moneda" id="saldoInicialMoneda" class="form-select shadow-sm" required>
                            <option value="PEN" selected>PEN</option>
                            <option value="USD">USD</option>
                        </select>
                        <label for="saldoInicialMoneda">Moneda <span class="text-danger">*</span></label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="number" min="0.01" step="0.01" name="monto_total" id="saldoInicialMonto" class="form-control shadow-sm" required placeholder="0.00">
                        <label for="saldoInicialMonto">Monto Original <span class="text-danger">*</span></label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="date" name="fecha_emision" id="saldoInicialFechaEmision" class="form-control shadow-sm" required>
                        <label for="saldoInicialFechaEmision">Fecha Emisión <span class="text-danger">*</span></label>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="form-floating">
                        <input type="date" name="fecha_vencimiento" id="saldoInicialFechaVencimiento" class="form-control shadow-sm" required>
                        <label for="saldoInicialFechaVencimiento">Fecha Vencimiento <span class="text-danger">*</span></label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-floating">
                        <input type="text" name="documento_referencia" id="saldoInicialDocumento" class="form-control shadow-sm" maxlength="50" required placeholder="Factura F001-00445">
                        <label for="saldoInicialDocumento">Documento Físico / Referencia <span class="text-danger">*</span></label>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-floating">
                        <textarea name="observaciones" id="saldoInicialObservaciones" class="form-control shadow-sm" style="height:120px" required placeholder="Motivo / Justificación"></textarea>
                        <label for="saldoInicialObservaciones">Motivo / Justificación <span class="text-danger">*</span></label>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4 fw-semibold shadow-sm js-form-confirm">
                        <i class="bi bi-save2 me-1"></i>Guardar saldo inicial
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?php echo e(base_url()); ?>/assets/js/tesoreria.js"></script>
