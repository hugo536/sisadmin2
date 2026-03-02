<h1 class="h4 mb-3">Reportes de Producción</h1>
<form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('reportes/produccion')); ?>">
  <div class="col-md-2"><input type="date" name="fecha_desde" class="form-control" value="<?php echo e($filtros['fecha_desde']); ?>" required></div>
  <div class="col-md-2"><input type="date" name="fecha_hasta" class="form-control" value="<?php echo e($filtros['fecha_hasta']); ?>" required></div>
  <div class="col-md-2"><input type="number" name="id_item" class="form-control" placeholder="Producto" value="<?php echo (int)($filtros['id_item'] ?? 0); ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<h2 class="h6">Producción por producto</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Producto</th><th>Cant. producida</th><th>Costo unitario prom.</th><th>Primer registro</th><th>Último registro</th></tr></thead><tbody>
<?php foreach (($porProducto['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['producto']); ?></td><td><?php echo e((string)$r['cantidad_producida']); ?></td><td><?php echo e((string)$r['costo_unitario_promedio']); ?></td><td><?php echo e((string)$r['primer_registro']); ?></td><td><?php echo e((string)$r['ultimo_registro']); ?></td></tr><?php endforeach; ?>
</tbody></table>
<h2 class="h6 mt-4">Consumo de insumos</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo total</th></tr></thead><tbody>
<?php foreach (($consumos['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['insumo']); ?></td><td><?php echo e((string)$r['cantidad_consumida']); ?></td><td><?php echo e((string)$r['costo_total']); ?></td></tr><?php endforeach; ?>
</tbody></table>
