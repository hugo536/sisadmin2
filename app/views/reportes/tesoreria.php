<h1 class="h4 mb-3">Reportes de Tesorería</h1>
<form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('reportes/tesoreria')); ?>">
  <div class="col-md-2"><input type="date" name="fecha_desde" class="form-control" value="<?php echo e($filtros['fecha_desde']); ?>" required></div>
  <div class="col-md-2"><input type="date" name="fecha_hasta" class="form-control" value="<?php echo e($filtros['fecha_hasta']); ?>" required></div>
  <div class="col-md-2"><input type="number" name="id_cuenta" class="form-control" placeholder="Cuenta" value="<?php echo (int)($filtros['id_cuenta'] ?? 0); ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<h2 class="h6">Aging CxC</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Cliente</th><th>Saldo</th><th>Vencimiento</th><th>Días atraso</th><th>Bucket</th></tr></thead><tbody>
<?php foreach (($agingCxc['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['cliente']); ?></td><td><?php echo e((string)$r['saldo']); ?></td><td><?php echo e((string)$r['fecha_vencimiento']); ?></td><td><?php echo e((string)$r['dias_atraso']); ?></td><td><?php echo e((string)$r['bucket']); ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2 class="h6 mt-4">Aging CxP</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Proveedor</th><th>Saldo</th><th>Vencimiento</th><th>Días atraso</th><th>Bucket</th></tr></thead><tbody>
<?php foreach (($agingCxp['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['proveedor']); ?></td><td><?php echo e((string)$r['saldo']); ?></td><td><?php echo e((string)$r['fecha_vencimiento']); ?></td><td><?php echo e((string)$r['dias_atraso']); ?></td><td><?php echo e((string)$r['bucket']); ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2 class="h6 mt-4">Flujo por cuenta</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Cuenta</th><th>Ingresos</th><th>Egresos</th><th>Neto</th></tr></thead><tbody>
<?php foreach (($flujo['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['cuenta']); ?></td><td><?php echo e((string)$r['total_ingresos']); ?></td><td><?php echo e((string)$r['total_egresos']); ?></td><td><?php echo e((string)$r['saldo_neto']); ?></td></tr><?php endforeach; ?>
</tbody></table>
