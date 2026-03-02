<div class="container-fluid py-3">
  <h3>Conciliación Bancaria</h3>
  <form method="post" action="<?php echo e(route_url('conciliacion/guardar')); ?>" class="row g-2 mb-3">
    <input type="hidden" name="id" value="0">
    <div class="col-md-4"><select class="form-select" name="id_cuenta_bancaria" required><option value="">Cuenta bancaria</option><?php foreach ($cuentas as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'].' - '.$c['nombre']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input type="month" class="form-control" name="periodo" value="<?php echo date('Y-m'); ?>" required></div>
    <div class="col-md-2"><input type="number" step="0.0001" class="form-control" name="saldo_estado_cuenta" placeholder="Saldo cartola" required></div>
    <div class="col-md-3"><input class="form-control" name="observaciones" placeholder="Observaciones"></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Crear</button></div>
  </form>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card"><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Periodo</th><th>Cuenta</th><th>Sistema</th><th>Cartola</th><th>Diferencia</th><th></th></tr></thead><tbody>
      <?php foreach (($conciliaciones ?? []) as $c): ?><tr>
        <td><?php echo e($c['periodo']); ?> <span class="badge <?php echo $c['estado']==='CERRADA' ? 'bg-success' : 'bg-warning text-dark'; ?>"><?php echo e($c['estado']); ?></span></td>
        <td><?php echo e($c['cuenta_nombre']); ?></td>
        <td><?php echo number_format((float)$c['saldo_sistema'], 4); ?></td>
        <td><?php echo number_format((float)$c['saldo_estado_cuenta'], 4); ?></td>
        <td><?php echo number_format((float)$c['diferencia'], 4); ?></td>
        <td><a class="btn btn-sm btn-outline-secondary" href="<?php echo e(route_url('conciliacion/index?id='.(int)$c['id'])); ?>">Ver</a></td>
      </tr><?php endforeach; ?></tbody></table></div></div>
    </div>
    <div class="col-lg-5">
      <?php if (($idConciliacionActiva ?? 0) > 0): ?>
        <div class="card mb-2"><div class="card-body">
          <form method="post" action="<?php echo e(route_url('conciliacion/importar')); ?>" enctype="multipart/form-data" class="row g-2">
            <input type="hidden" name="id_conciliacion" value="<?php echo (int)$idConciliacionActiva; ?>">
            <div class="col-8"><input type="file" class="form-control" name="archivo" accept=".csv" required></div>
            <div class="col-4"><button class="btn btn-outline-primary w-100">Importar CSV</button></div>
          </form>
          <form method="post" action="<?php echo e(route_url('conciliacion/cerrar')); ?>" class="mt-2">
            <input type="hidden" name="id_conciliacion" value="<?php echo (int)$idConciliacionActiva; ?>">
            <button class="btn btn-success w-100">Cerrar conciliación</button>
          </form>
        </div></div>
        <div class="card"><div class="card-body"><h6>Movimientos importados</h6>
          <?php foreach (($detalle ?? []) as $d): ?>
            <form method="post" action="<?php echo e(route_url('conciliacion/marcar_detalle')); ?>" class="d-flex align-items-center justify-content-between border-bottom py-1">
              <input type="hidden" name="id_conciliacion" value="<?php echo (int)$idConciliacionActiva; ?>">
              <input type="hidden" name="id_detalle" value="<?php echo (int)$d['id']; ?>">
              <div>
                <div class="small fw-semibold"><?php echo e($d['fecha']); ?> - <?php echo e($d['descripcion']); ?></div>
                <div class="small text-muted"><?php echo number_format((float)$d['monto'], 4); ?> | <?php echo e((string)$d['referencia']); ?></div>
              </div>
              <div>
                <input type="hidden" name="conciliado" value="<?php echo (int)$d['conciliado'] === 1 ? 0 : 1; ?>">
                <button class="btn btn-sm <?php echo (int)$d['conciliado'] === 1 ? 'btn-success' : 'btn-outline-secondary'; ?>"><?php echo (int)$d['conciliado'] === 1 ? 'Conciliado' : 'Marcar'; ?></button>
              </div>
            </form>
          <?php endforeach; ?>
        </div></div>
      <?php else: ?>
        <div class="alert alert-info">Selecciona una conciliación para importar y marcar movimientos.</div>
      <?php endif; ?>
    </div>
  </div>
</div>
