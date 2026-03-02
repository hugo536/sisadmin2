<h1 class="h4 mb-3">Reportes de Compras</h1>
<form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('reportes/compras')); ?>">
  <div class="col-md-2"><input type="date" name="fecha_desde" class="form-control" value="<?php echo e($filtros['fecha_desde']); ?>" required></div>
  <div class="col-md-2"><input type="date" name="fecha_hasta" class="form-control" value="<?php echo e($filtros['fecha_hasta']); ?>" required></div>
  <div class="col-md-2"><input type="number" name="id_proveedor" class="form-control" placeholder="Proveedor" value="<?php echo (int)($filtros['id_proveedor'] ?? 0); ?>"></div>
  <div class="col-md-2"><input type="number" name="id_almacen" class="form-control" placeholder="Almacén" value="<?php echo (int)($filtros['id_almacen'] ?? 0); ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<h2 class="h6">Compras por proveedor</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Proveedor</th><th>Total recibido</th><th># Recepciones</th><th>Costo prom.</th></tr></thead><tbody>
<?php foreach (($porProveedor['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['proveedor']); ?></td><td><?php echo e((string)$r['total_recibido']); ?></td><td><?php echo e((string)$r['recepciones']); ?></td><td><?php echo e((string)$r['costo_promedio_item']); ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2 class="h6 mt-4">Estado y cumplimiento OC</h2>
<table class="table table-sm table-bordered"><thead><tr><th>OC</th><th>Proveedor</th><th>Solicitado</th><th>Recibido</th><th>%</th><th>Retraso</th></tr></thead><tbody>
<?php foreach (($ocCumplimiento['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['codigo']); ?></td><td><?php echo e((string)$r['proveedor']); ?></td><td><?php echo e((string)$r['solicitado']); ?></td><td><?php echo e((string)$r['recibido']); ?></td><td><?php echo e((string)$r['pct_cumplimiento']); ?>%</td><td><?php echo (int)$r['retrasada'] === 1 ? 'Sí' : 'No'; ?></td></tr><?php endforeach; ?>
</tbody></table>
