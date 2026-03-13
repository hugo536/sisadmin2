<?php
$registros = $registros ?? [];
$centrosCosto = $centrosCosto ?? [];
$codigoSugerido = $codigoSugerido ?? '';
$filtros = $filtros ?? [];
?>
<div class="container-fluid p-4" id="gastosConceptosApp">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-tags-fill text-primary me-2"></i>Conceptos de Gasto</h4>
            <small class="text-muted">Catálogo maestro de gastos.</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoConcepto"><i class="bi bi-plus-circle me-1"></i>Nuevo Concepto</button>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-4"><input id="buscarConcepto" class="form-control" placeholder="Buscar código / nombre / centro de costo"></div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" data-erp-table="true" data-search-input="#buscarConcepto" data-rows-per-page="15">
                    <thead class="table-light">
                        <tr><th>Código</th><th>Concepto</th><th>Centro de costo</th><th>Recurrente</th><th>Estado</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($registros as $r): ?>
                        <?php $sinCuenta = (int)($r['id_cuenta_contable'] ?? 0) <= 0; ?>
                        <tr>
                            <td class="fw-semibold text-primary"><?php echo e((string)$r['codigo']); ?></td>
                            <td>
                                <?php echo e((string)$r['nombre']); ?>
                                <?php if ($sinCuenta): ?>
                                    <i class="bi bi-exclamation-triangle-fill text-warning ms-1" data-bs-toggle="tooltip" title="No vinculado a una cuenta contable. Vincúlelo en Contabilidad > Configurar Parámetros."></i>
                                <?php endif; ?>
                            </td>
                            <td><?php echo e((string)($r['centro_costo_codigo'] . ' - ' . $r['centro_costo_nombre'])); ?></td>
                            <td><?php echo (int)$r['es_recurrente'] === 1 ? 'Sí' : 'No'; ?></td>
                            <td>
                                <?php if ((int)$r['es_recurrente'] === 1): ?>
                                    <span class="badge bg-warning-subtle text-warning-emphasis">Alerta <?php echo (int)($r['dias_anticipacion'] ?? 0); ?> días antes</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary-subtle text-secondary">Sin recordatorio</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoConcepto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="post" action="<?php echo e(route_url('gastos/guardar_concepto')); ?>" class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white"><h5 class="modal-title">Nuevo Concepto de Gasto</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div class="mb-2"><label class="form-label">Código</label><input readonly class="form-control bg-light" name="codigo" value="<?php echo e($codigoSugerido); ?>"></div>
                <div class="mb-2"><label class="form-label">Nombre del Concepto *</label><input required class="form-control" name="nombre"></div>
                <div class="mb-2"><label class="form-label">Centro de Costo *</label><select required class="form-select" name="id_centro_costo"><?php foreach($centrosCosto as $cc): ?><option value="<?php echo (int)$cc['id']; ?>"><?php echo e($cc['codigo'].' - '.$cc['nombre']); ?></option><?php endforeach; ?></select></div>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="es_recurrente" id="esRecurrente"><label class="form-check-label" for="esRecurrente">Gasto Recurrente / Recordatorio</label></div>
                <div class="row g-2 d-none" id="bloqueRecurrente"><div class="col-6"><label class="form-label">Día de vencimiento</label><input type="number" min="1" max="31" class="form-control" name="dia_vencimiento"></div><div class="col-6"><label class="form-label">Días anticipación</label><input type="number" min="0" max="60" class="form-control" name="dias_anticipacion" value="0"></div></div>
            </div>
            <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
        </form>
    </div>
</div>
