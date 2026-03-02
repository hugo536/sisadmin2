<div class="container-fluid py-3">
  <h3>Periodos Contables</h3>
  <form method="get" class="row g-2 mb-2">
    <input type="hidden" name="ruta" value="contabilidad/periodos">
    <div class="col-auto"><input class="form-control" type="number" name="anio" value="<?php echo (int)$anio; ?>"></div>
    <div class="col-auto"><button class="btn btn-secondary">Ver</button></div>
  </form>
  <form method="post" action="<?php echo e(route_url('contabilidad/crear_periodo')); ?>" class="row g-2 mb-3">
    <div class="col-auto"><input type="number" class="form-control" name="anio" value="<?php echo (int)$anio; ?>"></div>
    <div class="col-auto"><input type="number" class="form-control" name="mes" min="1" max="12" placeholder="Mes"></div>
    <div class="col-auto"><button class="btn btn-primary">Crear/Abrir</button></div>
  </form>
  <table class="table table-sm"><thead><tr><th>Año</th><th>Mes</th><th>Inicio</th><th>Fin</th><th>Estado</th><th></th></tr></thead><tbody>
  <?php foreach ($periodos as $p): ?>
    <tr><td><?php echo (int)$p['anio']; ?></td><td><?php echo (int)$p['mes']; ?></td><td><?php echo e($p['fecha_inicio']); ?></td><td><?php echo e($p['fecha_fin']); ?></td><td><?php echo e($p['estado']); ?></td><td>
      <?php if ($p['estado'] === 'ABIERTO'): ?><form method="post" action="<?php echo e(route_url('contabilidad/cerrar_periodo')); ?>"><input type="hidden" name="id_periodo" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-warning btn-sm" onclick="return confirm('¿Cerrar periodo?')">Cerrar</button></form><?php else: ?><form method="post" action="<?php echo e(route_url('contabilidad/abrir_periodo')); ?>"><input type="hidden" name="id_periodo" value="<?php echo (int)$p['id']; ?>"><button class="btn btn-success btn-sm">Reabrir</button></form><?php endif; ?>
    </td></tr>
  <?php endforeach; ?>
  </tbody></table>
</div>
