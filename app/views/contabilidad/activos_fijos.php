<div class="container-fluid py-3">
  <h3>Activos Fijos</h3>
  <form method="post" action="<?php echo e(route_url('activos/guardar')); ?>" class="row g-2 mb-3">
    <input type="hidden" name="id" id="af_id" value="0">
    <div class="col-md-2"><input class="form-control" name="codigo_activo" id="af_codigo" placeholder="Código" required></div>
    <div class="col-md-4"><input class="form-control" name="nombre" id="af_nombre" placeholder="Nombre" required></div>
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_adquisicion" required></div>
    <div class="col-md-2"><input type="number" step="0.0001" class="form-control" name="costo_adquisicion" placeholder="Costo" required></div>
    <div class="col-md-2"><input type="number" class="form-control" name="vida_util_meses" placeholder="Vida útil" required></div>
    <div class="col-md-2"><input type="number" step="0.0001" class="form-control" name="valor_residual" placeholder="Valor residual" value="0"></div>
    <div class="col-md-4"><select class="form-select" name="id_cuenta_activo" required><option value="">Cuenta activo</option><?php foreach ($cuentas as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-4"><select class="form-select" name="id_cuenta_depreciacion" required><option value="">Cuenta depreciación</option><?php foreach ($cuentas as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Guardar</button></div>
  </form>

  <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Código</th><th>Activo</th><th>Costo</th><th>Dep. Acum.</th><th>Valor libros</th><th>Estado</th></tr></thead>
    <tbody><?php foreach (($activos ?? []) as $a): ?><tr>
      <td><?php echo e($a['codigo_activo']); ?></td>
      <td><?php echo e($a['nombre']); ?></td>
      <td><?php echo number_format((float)$a['costo_adquisicion'], 4); ?></td>
      <td><?php echo number_format((float)$a['depreciacion_acumulada'], 4); ?></td>
      <td><?php echo number_format((float)$a['valor_libros'], 4); ?></td>
      <td><?php echo e($a['estado']); ?></td>
    </tr><?php endforeach; ?></tbody>
  </table></div></div>
</div>
