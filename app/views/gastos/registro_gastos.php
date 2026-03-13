<?php
$registros = $registros ?? [];
$proveedores = $proveedores ?? [];
$conceptos = $conceptos ?? [];
?>
<div class="container-fluid p-4" id="gastosRegistroApp">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1"><i class="bi bi-receipt-cutoff text-primary me-2"></i>Registro de Gastos</h4>
            <small class="text-muted">Registra gastos y genera CxP + asiento automático.</small>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNuevoGasto"><i class="bi bi-plus-circle me-1"></i>Nuevo Registro</button>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle" data-erp-table="true" data-rows-per-page="15">
                    <thead class="table-light"><tr><th>Fecha</th><th>Proveedor</th><th>Concepto</th><th>Monto</th><th>Impuesto</th><th>Total</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach($registros as $r): ?>
                        <tr>
                            <td><?php echo e((string)$r['fecha']); ?></td>
                            <td><?php echo e((string)$r['proveedor']); ?></td>
                            <td><?php echo e((string)$r['concepto']); ?></td>
                            <td><?php echo number_format((float)$r['monto'],2); ?></td>
                            <td><?php echo e((string)$r['impuesto_tipo']); ?></td>
                            <td class="fw-semibold"><?php echo number_format((float)$r['total'],2); ?></td>
                            <td><span class="badge bg-info-subtle text-info-emphasis"><?php echo e((string)$r['estado']); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalNuevoGasto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form method="post" action="<?php echo e(route_url('gastos/guardar_registro')); ?>" class="modal-content border-0 shadow">
      <div class="modal-header bg-primary text-white"><h5 class="modal-title">Nuevo Registro de Gasto</h5><button class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <div class="mb-2"><label class="form-label">Fecha</label><input type="date" class="form-control" name="fecha" value="<?php echo date('Y-m-d'); ?>" required></div>
        <div class="mb-2"><label class="form-label">Proveedor</label><select class="form-select" name="id_proveedor" required><?php foreach($proveedores as $p): ?><option value="<?php echo (int)$p['id']; ?>"><?php echo e((string)$p['nombre_completo']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Concepto</label><select id="idConceptoGasto" class="form-select" name="id_concepto" required><?php foreach($conceptos as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e((string)$c['codigo'].' - '.$c['nombre']); ?></option><?php endforeach; ?></select></div>
        <div class="mb-2"><label class="form-label">Monto</label><input type="number" step="0.01" min="0.01" class="form-control" name="monto" required></div>
        <div class="mb-2"><label class="form-label">Impuestos</label><select class="form-select" name="impuesto_tipo"><option value="NINGUNO">Sin impuesto</option><option value="IGV">IGV / IVA</option></select></div>
      </div>
      <div class="modal-footer"><button class="btn btn-light" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar</button></div>
    </form>
  </div>
</div>
