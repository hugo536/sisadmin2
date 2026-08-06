<?php
/** @var array $ingresos */
/** @var array $cuentas */
/** @var array $filtros */

$ingresos = $ingresos ?? [];
$cuentas = $cuentas ?? [];
$filtros = $filtros ?? [];

// Formateador de fechas
$formatearFechaDMY = static function ($fecha): string {
    $texto = trim((string) $fecha);
    if ($texto === '') return '-';
    $timestamp = strtotime($texto);
    return $timestamp !== false ? date('d/m/Y', $timestamp) : $texto;
};
?>

<div class="container-fluid p-4" id="ingresosExtraApp"
     data-url-guardar="<?= e(route_url('tesoreria/ingresos/guardar')) ?>"
     data-url-anular="<?= e(route_url('tesoreria/ingresos/anular')) ?>">

    <!-- ENCABEZADO -->
    <div class="d-flex justify-content-between align-items-center mb-4 fade-in">
        <div>
            <h1 class="h3 fw-bold mb-1 text-dark d-flex align-items-center">
                <i class="bi bi-cash-coin me-2 text-success"></i> Otros Ingresos
            </h1>
            <p class="text-muted small mb-0 ms-1">Registro de ingresos extraordinarios (alquileres, reembolsos, etc.).</p>
        </div>
        <button type="button" class="btn btn-success shadow-sm fw-semibold" id="btnNuevoIngreso">
            <i class="bi bi-plus-circle-fill me-2"></i>Nuevo Ingreso
        </button>
    </div>

    <!-- FILTROS -->
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-body p-3">
            <form method="get" action="" class="row g-2 align-items-center" id="formFiltrosIngresos">
                <input type="hidden" name="ruta" value="tesoreria/ingresos">

                <div class="col-12 col-lg-4">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-light border-secondary-subtle border-end-0"><i class="bi bi-search text-muted"></i></span>
                        <input type="search" name="q" class="form-control bg-light border-secondary-subtle border-start-0 ps-0" placeholder="Buscar por concepto o referencia..." value="<?= e((string) ($filtros['q'] ?? '')) ?>">
                    </div>
                </div>
                
                <div class="col-12 col-lg-3">
                    <select name="cuenta" class="form-select bg-light border-secondary-subtle shadow-sm text-secondary">
                        <option value="">Todas las cuentas</option>
                        <?php foreach ($cuentas as $cuenta): ?>
                            <option value="<?= (int) ($cuenta['id'] ?? 0) ?>" <?= ($filtros['cuenta'] ?? '') === (string) $cuenta['id'] ? 'selected' : '' ?>>
                                <?= e($cuenta['nombre'] ?? '') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-lg-5">
                    <div class="input-group shadow-sm">
                        <span class="input-group-text bg-white text-muted border-end-0">Desde</span>
                        <input type="date" name="fecha_desde" class="form-control bg-light border-start-0 border-end-0" value="<?= e((string) ($filtros['fecha_desde'] ?? date('Y-m-d', strtotime('-30 days')))) ?>">
                        
                        <span class="input-group-text bg-white text-muted border-start-0 border-end-0">Hasta</span>
                        <input type="date" name="fecha_hasta" class="form-control bg-light border-start-0" value="<?= e((string) ($filtros['fecha_hasta'] ?? date('Y-m-d'))) ?>">
                        
                        <button type="submit" class="btn btn-light border text-success px-3 transition-hover" title="Aplicar filtros">
                            <i class="bi bi-funnel-fill"></i>
                        </button>
                        
                        <a href="<?= e(route_url('tesoreria/ingresos')) ?>" class="btn btn-light border text-danger px-3 transition-hover d-flex align-items-center" title="Limpiar filtros">
                            <i class="bi bi-eraser-fill"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- TABLA PRINCIPAL -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-pro" id="tablaIngresos">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 text-secondary fw-semibold">Fecha</th>
                            <th class="text-secondary fw-semibold">Cuenta Destino</th>
                            <th class="text-secondary fw-semibold col-w-300">Concepto</th>
                            <th class="text-end text-secondary fw-semibold">Monto</th>
                            <th class="text-center text-secondary fw-semibold">Estado</th>
                            <th class="text-end pe-4 text-secondary fw-semibold">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <?php if (!empty($ingresos)): ?>
                            <?php foreach ($ingresos as $ingreso): ?>
                                <tr class="border-bottom">
                                    <td class="ps-4">
                                        <div class="fw-bold text-dark"><i class="bi bi-calendar3 me-1 text-muted"></i> <?= e($formatearFechaDMY($ingreso['fecha'])) ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-primary"><i class="bi bi-bank me-1"></i> <?= e($ingreso['cuenta_nombre']) ?></div>
                                    </td>
                                    <td>
                                        <div class="text-dark small text-break"><?= e($ingreso['concepto']) ?></div>
                                    </td>
                                    <td class="text-end fw-bold text-success fs-6">
                                        + S/ <?= number_format((float) $ingreso['monto'], 2) ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ((int) $ingreso['estado'] === 1): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Completado</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2 rounded-pill">Anulado</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-4">
                                        <?php if ((int) $ingreso['estado'] === 1): ?>
                                            <button type="button" class="btn btn-sm btn-light text-danger border-0 btn-anular rounded-circle" data-id="<?= (int) $ingreso['id'] ?>" data-bs-toggle="tooltip" title="Anular Ingreso">
                                                <i class="bi bi-x-circle fs-5"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="bi bi-wallet2 fs-1 d-block mb-2 text-light"></i>No hay ingresos registrados en estas fechas.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- MODAL NUEVO INGRESO -->
<div class="modal fade" id="modalIngreso" tabindex="-1" data-bs-backdrop="static" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white border-bottom-0 pb-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-arrow-down-left-circle-fill me-2"></i>Registrar Ingreso Extra</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4" style="margin-top: -15px; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <form id="formNuevoIngreso" autocomplete="off">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="ingresoFecha" class="form-label text-muted small fw-bold mb-1">Fecha <span class="text-danger">*</span></label>
                            <input type="date" class="form-control border-secondary-subtle shadow-sm" id="ingresoFecha" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="ingresoMonto" class="form-label text-success small fw-bold mb-1">Monto a Ingresar <span class="text-danger">*</span></label>
                            <div class="input-group shadow-sm">
                                <span class="input-group-text bg-success-subtle text-success border-success-subtle fw-bold">S/</span>
                                <input type="number" class="form-control text-end border-success-subtle fw-bold text-dark fs-5" id="ingresoMonto" min="0.01" step="0.01" placeholder="0.00" required>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="ingresoCuenta" class="form-label text-muted small fw-bold mb-1">Cuenta Destino <span class="text-danger">*</span></label>
                        <select id="ingresoCuenta" class="form-select border-secondary-subtle shadow-sm fw-semibold text-secondary" required>
                            <option value="">Seleccione a dónde ingresa el dinero...</option>
                            <?php foreach ($cuentas as $cuenta): ?>
                                <option value="<?= (int) ($cuenta['id'] ?? 0) ?>">
                                    <?= e($cuenta['nombre'] ?? '') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-2">
                        <label for="ingresoConcepto" class="form-label text-muted small fw-bold mb-1">Concepto / Referencia <span class="text-danger">*</span></label>
                        <textarea class="form-control border-secondary-subtle shadow-sm" id="ingresoConcepto" rows="3" maxlength="200" placeholder="Ej. Pago por alquiler de unidad de transporte a cliente Juan Pérez..." required></textarea>
                        <div class="form-text text-secondary mt-1" style="font-size: 0.75rem;">
                            <i class="bi bi-info-circle me-1"></i>Escribe un detalle claro para que contabilidad sepa el origen del dinero.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer bg-white border-top-0 rounded-bottom">
                <button type="button" class="btn btn-light text-secondary fw-semibold me-2" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-success px-4 fw-bold shadow-sm" id="btnGuardarIngreso">
                    <i class="bi bi-check-circle-fill me-2"></i>Guardar Ingreso
                </button>
            </div>
        </div>
    </div>
</div>