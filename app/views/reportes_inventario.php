<h1 class="h4 mb-3">Reportes de Inventario</h1>
<form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('reportes/inventario')); ?>">
  <input type="hidden" name="ruta" value="reportes/inventario">
  <div class="col-md-2"><input type="date" name="fecha_desde" class="form-control" value="<?php echo e($filtros['fecha_desde']); ?>" required></div>
  <div class="col-md-2"><input type="date" name="fecha_hasta" class="form-control" value="<?php echo e($filtros['fecha_hasta']); ?>" required></div>
  <div class="col-md-2"><input type="number" name="id_almacen" class="form-control" placeholder="Almacén" value="<?php echo (int)$filtros['id_almacen']; ?>"></div>
  <div class="col-md-2 form-check mt-2"><input class="form-check-input" type="checkbox" name="solo_bajo_minimo" value="1" <?php echo !empty($filtros['solo_bajo_minimo']) ? 'checked' : ''; ?>> Bajo mínimo</div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>

<h2 class="h6">Stock actual</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Ítem</th><th>Almacén</th><th>Stock</th><th>Mínimo</th><th>Unidad</th><th>Alerta</th></tr></thead><tbody>
<?php foreach (($stock['rows'] ?? []) as $r): ?><tr><td><?php echo e($r['item']); ?></td><td><?php echo e($r['almacen']); ?></td><td><?php echo e((string)$r['stock_actual']); ?></td><td><?php echo e((string)$r['stock_minimo']); ?></td><td><?php echo e((string)$r['unidad']); ?></td><td><?php echo e((string)$r['alerta']); ?></td></tr><?php endforeach; ?>
</tbody></table>

<h2 class="h6 mt-4">Kardex valorizado</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Fecha</th><th>Tipo</th><th>Cantidad</th><th>C/U</th><th>Total</th><th>Ref</th><th>Usuario</th></tr></thead><tbody>
<?php foreach (($kardex['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['fecha']); ?></td><td><?php echo e((string)$r['tipo']); ?></td><td><?php echo e((string)$r['cantidad']); ?></td><td><?php echo e((string)$r['costo_unitario']); ?></td><td><?php echo e((string)$r['costo_total']); ?></td><td><?php echo e((string)$r['referencia']); ?></td><td><?php echo e((string)$r['usuario']); ?></td></tr><?php endforeach; ?>
</tbody></table>

<h2 class="h6 mt-4">Vencimientos y lotes</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Ítem</th><th>Almacén</th><th>Lote</th><th>Vencimiento</th><th>Stock</th><th>Alerta</th></tr></thead><tbody>
<?php foreach (($vencimientos['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['item']); ?></td><td><?php echo e((string)$r['almacen']); ?></td><td><?php echo e((string)$r['lote']); ?></td><td><?php echo e((string)$r['fecha_vencimiento']); ?></td><td><?php echo e((string)$r['stock_lote']); ?></td><td><?php echo e((string)$r['alerta']); ?></td></tr><?php endforeach; ?>
</tbody></table>
