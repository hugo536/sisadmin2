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

<style>
    /* ========================================================================
       ESTILOS RESPONSIVOS PARA TABLAS (MODO CARDS EN MÓVILES)
       ======================================================================== */
    @media (max-width: 767.98px) {
        .table-mobile-cards table, 
        .table-mobile-cards thead, 
        .table-mobile-cards tbody, 
        .table-mobile-cards tr, 
        .table-mobile-cards th, 
        .table-mobile-cards td {
            display: block;
            width: 100%;
        }
        
        /* Ocultar encabezados de tabla en móviles */
        .table-mobile-cards thead tr {
            position: absolute;
            top: -9999px;
            left: -9999px;
        }
        
        /* Convertir filas en tarjetas */
        .table-mobile-cards tr {
            border: 1px solid #dbe7ff !important;
            border-radius: .85rem !important;
            background: #fff !important;
            box-shadow: 0 .125rem .5rem rgba(10, 37, 64, 0.08) !important;
            padding: .8rem !important;
            margin-bottom: 1rem !important;
        }
        
        /* Estilos de las celdas */
        .table-mobile-cards td {
            border: none !important;
            position: relative;
            padding: .5rem 0 .5rem 45% !important;
            text-align: right !important;
            min-height: 2.5rem;
        }
        
        /* Insertar el nombre de la columna usando el atributo data-label */
        .table-mobile-cards td::before {
            content: attr(data-label);
            position: absolute;
            left: 0;
            top: .5rem;
            width: 40%;
            font-weight: bold;
            text-align: left;
            color: #6c757d;
            font-size: 0.85rem;
        }

        /* Ajustes específicos para inputs dentro de las cards móviles */
        .table-mobile-cards td input {
            text-align: right !important;
        }
        
        /* Fila de mensaje vacío */
        .table-mobile-cards tr#filaVaciaMensaje td,
        .table-mobile-cards tr#filaVaciaAmortizaciones td {
            padding-left: 0 !important;
            text-align: center !important;
        }
        .table-mobile-cards tr#filaVaciaMensaje td::before,
        .table-mobile-cards tr#filaVaciaAmortizaciones td::before {
            display: none;
        }
    }
</style>

<div class="container-fluid p-4" id="tesoreriaSaldosInicialesApp">
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3 fade-in">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark d-flex align-items-center">
                <i class="bi bi-hourglass-split me-2 text-primary"></i> Estado de Cuenta (Saldos Iniciales)
            </h1>
            <p class="text-muted small mb-0 ms-1">Registre compras pasadas y amortizaciones para calcular el saldo real.</p>
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
          data-url-items="<?php echo e(route_url('tesoreria/ajax_items_saldos')); ?>"
          data-url-item-unidades="<?php echo e(route_url('tesoreria/ajax_item_unidades_saldos')); ?>">

        <div class="row g-4 fade-in">
            
            <!-- ================= COLUMNA IZQUIERDA: ENTRADA DE DATOS ================= -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 bg-light-subtle rounded-4">
                        <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="bi bi-pencil-square me-2"></i>Ingreso de Datos</h5>
                        
                        <!-- 1. Tercero y Naturaleza -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small mb-2">Naturaleza</label>
                            <div class="d-flex flex-column gap-2 mb-3">
                                <input type="radio" class="btn-check" name="tipo_deuda" id="tipoCliente" value="CLIENTE" checked autocomplete="off">
                                <label class="btn btn-outline-primary text-start px-3 py-2 shadow-sm fw-bold" for="tipoCliente" id="labelTipoCliente">
                                    <i class="bi bi-person-lines-fill me-2"></i>Deuda a favor (CxC)
                                </label>

                                <input type="radio" class="btn-check" name="tipo_deuda" id="tipoProveedor" value="PROVEEDOR" autocomplete="off">
                                <label class="btn btn-outline-warning text-dark text-start px-3 py-2 shadow-sm fw-bold" for="tipoProveedor" id="labelTipoProveedor">
                                    <i class="bi bi-shop me-2"></i>Deuda en contra (CxP)
                                </label>
                            </div>

                            <label for="saldoInicialTercero" class="form-label fw-bold text-muted small mb-1">Tercero <span class="text-danger">*</span></label>
                            <select name="id_tercero" id="saldoInicialTercero" class="form-select shadow-sm" required></select>
                        </div>

                        <!-- 2. Datos del Documento -->
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
                            <label class="form-label fw-bold text-muted small mb-1">N° Documento Ref. <span class="text-danger">*</span></label>
                            <input type="text" name="documento_referencia" class="form-control shadow-sm" maxlength="50" required placeholder="Ej. F001-445">
                        </div>

                        <!-- 3. Búsqueda de Ítems y Botón -->
                        <div class="mt-4 pt-3 border-top">
                            <label class="form-label fw-bold text-dark mb-2"><i class="bi bi-box-seam me-1"></i>Agregar Compra / Producto</label>
                            <div class="mb-3">
                                <select id="buscadorItemsSaldo" class="form-select shadow-sm"></select>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-4">
                                    <label for="saldoDetalleCantidad" class="form-label fw-bold text-muted small mb-1">Cantidad</label>
                                    <input type="number" id="saldoDetalleCantidad" class="form-control shadow-sm text-end" min="0.01" step="0.01" value="1">
                                </div>
                                <div class="col-8">
                                    <label for="saldoDetalleUnidad" class="form-label fw-bold text-muted small mb-1">Unid. conversión</label>
                                    <select id="saldoDetalleUnidad" class="form-select shadow-sm">
                                        <option value="">Base</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="saldoDetalleSubtotal" class="form-label fw-bold text-muted small mb-1">Subtotal</label>
                                <input type="number" id="saldoDetalleSubtotal" class="form-control shadow-sm text-end" min="0.00" step="0.01" value="0.00">
                            </div>
                            <button type="button" id="btnAgregarItemDetalle" class="btn btn-primary w-100 fw-bold shadow-sm mb-2">
                                <i class="bi bi-plus-circle me-2"></i>Agregar a la tabla
                            </button>
                            <button type="button" id="btnAgregarItemManual" class="btn btn-outline-secondary w-100 btn-sm shadow-sm d-none">
                                <i class="bi bi-pencil me-1"></i> Ítem manual (Sin código)
                            </button>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ================= COLUMNA DERECHA: RESUMEN Y ESTADO ================= -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-4 h-100">
                    <div class="card-body p-4 bg-white rounded-4 d-flex flex-column">
                        
                        <!-- TABLA DE COMPRAS / DETALLE -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cart-check me-2"></i>Detalle de Compras Generadas</h5>
                            <span class="badge bg-light text-muted border border-secondary-subtle">No afecta inventario</span>
                        </div>

                        <!-- Se agregó la clase table-mobile-cards para la responsividad -->
                        <div class="table-responsive table-mobile-cards border rounded-3 bg-light-subtle mb-4" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0" id="tablaDetalleSaldos">
                                <thead class="table-light text-secondary small sticky-top">
                                    <tr>
                                        <th style="width: 15%;">Fecha</th>
                                        <th style="width: 33%;">Ítem</th>
                                        <th style="width: 15%; text-align: center;">Cantidad</th>
                                        <th style="width: 17%; text-align: center;">Unid. conversión</th>
                                        <th style="width: 15%; text-align: right;">Subtotal</th>
                                        <th style="width: 5%; text-align: center;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr id="filaVaciaMensaje">
                                        <td colspan="6" class="text-center text-muted py-4">
                                            <i class="bi bi-basket d-block fs-3 mb-2 opacity-50"></i>
                                            Busque y seleccione un ítem en el panel izquierdo para agregarlo a la cuenta.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- TABLA DE AMORTIZACIONES (MOCKUP) -->
                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                            <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-cash-coin me-2"></i>Amortizaciones / Pagos Previos</h5>
                            <button type="button" id="btnRegistrarPagoPrevio" class="btn btn-sm btn-outline-success fw-bold shadow-sm"><i class="bi bi-plus-lg me-1"></i>Registrar Pago</button>
                        </div>

                        <!-- Se agregó la clase table-mobile-cards -->
                        <div class="table-responsive table-mobile-cards border rounded-3 bg-light-subtle mb-4">
                            <table class="table table-hover align-middle mb-0" id="tablaAmortizaciones">
                                <thead class="table-light text-secondary small">
                                    <tr>
                                        <th style="width: 20%;">Fecha</th>
                                        <th style="width: 25%;">Ref. Pago</th>
                                        <th style="width: 25%;">Método</th>
                                        <th style="width: 20%; text-align: right;">Monto</th>
                                        <th style="width: 10%; text-align: center;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Aquí irán los pagos vía JS/PHP. Por ahora mostramos vacío -->
                                    <tr id="filaVaciaAmortizaciones">
                                        <td colspan="5" class="text-center text-muted py-3 small">
                                            Aún no hay amortizaciones registradas para esta cuenta.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- ZONA DE TOTALES -->
                        <div class="mt-auto pt-3 border-top d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-3">
                            <div class="w-100 w-md-50">
                                <label class="form-label fw-bold text-dark mb-1">Monto Total Pendiente (Saldo Real)</label>
                                <input type="number" step="0.01" min="0.00" name="monto_total" id="saldoInicialMontoManual" class="form-control form-control-lg border-primary text-primary fw-bold shadow-sm bg-light" required placeholder="0.00" readonly>
                                <small class="text-muted"><i class="bi bi-calculator"></i> (Suma Compras) - (Suma Amortizaciones)</small>
                            </div>
                            
                            <!-- Botón de Guardado -->
                            <div class="w-100 w-md-auto text-end">
                                <button type="submit" id="btnGuardarCuentaTercero" class="btn btn-primary w-100 px-5 py-2 fw-bold shadow-sm fs-6">
                                    <i class="bi bi-cloud-upload me-2"></i>Guardar Saldo Inicial
                                </button>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="modalPagoPrevio" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-cash-stack me-2"></i>Registrar Pago Previo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formPagoPrevioLocal">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="pagoPrevioFecha" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Monto <span class="text-danger">*</span></label>
                            <input type="number" min="0.01" step="0.01" class="form-control" id="pagoPrevioMonto" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Referencia</label>
                            <input type="text" class="form-control" id="pagoPrevioReferencia" maxlength="120" placeholder="Ej. Recibo 001-2026">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small">Método</label>
                            <input type="text" class="form-control" id="pagoPrevioMetodo" maxlength="60" placeholder="Transferencia, efectivo, etc.">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success fw-bold">
                        <i class="bi bi-check2-circle me-1"></i>Guardar pago
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Carga del script específico de la vista (Vital para el funcionamiento de la SPA) -->
<script src="<?php echo base_url(); ?>/assets/js/tesoreria.js?v=<?php echo time(); ?>"></script>
