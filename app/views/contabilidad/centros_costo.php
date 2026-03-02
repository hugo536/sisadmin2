<div class="container-fluid py-3">
  <h3>Centros de Costo</h3>
  <form method="post" action="<?php echo e(route_url('contabilidad/guardar_centro_costo')); ?>" class="row g-2 mb-3">
    <input type="hidden" name="id" id="cc_id" value="0">
    <div class="col-md-2"><input class="form-control" name="codigo" id="cc_codigo" placeholder="Código" required></div>
    <div class="col-md-6"><input class="form-control" name="nombre" id="cc_nombre" placeholder="Nombre" required></div>
    <div class="col-md-2"><select class="form-select" name="estado" id="cc_estado"><option value="1">Activo</option><option value="0">Inactivo</option></select></div>
    <div class="col-md-2"><button class="btn btn-primary w-100">Guardar</button></div>
  </form>
  <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Código</th><th>Nombre</th><th>Estado</th><th></th></tr></thead>
    <tbody>
      <?php foreach (($centros ?? []) as $c): ?>
      <tr>
        <td><?php echo e($c['codigo']); ?></td><td><?php echo e($c['nombre']); ?></td>
        <td><span class="badge <?php echo (int)$c['estado'] === 1 ? 'bg-success' : 'bg-secondary'; ?>"><?php echo (int)$c['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></span></td>
        <td><button type="button" class="btn btn-outline-secondary btn-sm btn-editar-cc" data-id="<?php echo (int)$c['id']; ?>" data-codigo="<?php echo e($c['codigo']); ?>" data-nombre="<?php echo e($c['nombre']); ?>" data-estado="<?php echo (int)$c['estado']; ?>">Editar</button></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
