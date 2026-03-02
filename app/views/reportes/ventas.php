<h1 class="h4 mb-3">Reportes de Ventas</h1>
<form class="row g-2 mb-3" method="get" action="<?php echo e(route_url('reportes/ventas')); ?>">
  <div class="col-md-2"><input type="date" name="fecha_desde" class="form-control" value="<?php echo e($filtros['fecha_desde']); ?>" required></div>
  <div class="col-md-2"><input type="date" name="fecha_hasta" class="form-control" value="<?php echo e($filtros['fecha_hasta']); ?>" required></div>
  <div class="col-md-2"><input type="number" name="id_cliente" class="form-control" placeholder="Cliente" value="<?php echo (int)($filtros['id_cliente'] ?? 0); ?>"></div>
  <div class="col-md-2"><input type="number" name="estado" class="form-control" placeholder="Estado" value="<?php echo e((string)($filtros['estado'] ?? '')); ?>"></div>
  <div class="col-md-2"><button class="btn btn-primary w-100">Filtrar</button></div>
</form>
<h2 class="h6">Ventas por cliente</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Cliente</th><th>Total vendido</th><th>Ticket prom.</th><th>Docs</th></tr></thead><tbody>
<?php foreach (($porCliente['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['cliente']); ?></td><td><?php echo e((string)$r['total_vendido']); ?></td><td><?php echo e((string)$r['ticket_promedio']); ?></td><td><?php echo e((string)$r['documentos']); ?></td></tr><?php endforeach; ?>
</tbody></table>

<h2 class="h6 mt-4">Top productos vendidos</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Producto</th><th>Cantidad</th><th>Monto</th></tr></thead><tbody>
<?php foreach (($topProductos ?? []) as $r): ?><tr><td><?php echo e((string)$r['producto']); ?></td><td><?php echo e((string)$r['total_cantidad']); ?></td><td><?php echo e((string)$r['total_monto']); ?></td></tr><?php endforeach; ?>
</tbody></table>

<h2 class="h6 mt-4">Pendientes de despacho</h2>
<table class="table table-sm table-bordered"><thead><tr><th>Documento</th><th>Cliente</th><th>Saldo</th><th>Almacén</th><th>Días</th></tr></thead><tbody>
<?php foreach (($pendientes['rows'] ?? []) as $r): ?><tr><td><?php echo e((string)$r['documento']); ?></td><td><?php echo e((string)$r['cliente']); ?></td><td><?php echo e((string)$r['saldo_despachar']); ?></td><td><?php echo e((string)$r['almacen']); ?></td><td><?php echo e((string)$r['dias_desde_emision']); ?></td></tr><?php endforeach; ?>
</tbody></table>
