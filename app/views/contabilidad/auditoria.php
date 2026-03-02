<div class="container-fluid py-3">
  <h3>Modo Auditoría (solo lectura)</h3>
  <form method="get" class="row g-2 mb-3">
    <input type="hidden" name="ruta" value="auditoria/index">
    <div class="col-md-3"><input class="form-control" name="evento" placeholder="Evento" value="<?php echo e($filtros['evento'] ?? ''); ?>"></div>
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_desde" value="<?php echo e($filtros['fecha_desde'] ?? ''); ?>"></div>
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_hasta" value="<?php echo e($filtros['fecha_hasta'] ?? ''); ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filtrar</button></div>
    <div class="col-md-3"><a class="btn btn-outline-primary w-100" href="<?php echo e(route_url('auditoria/exportar_csv?evento=' . urlencode((string)($filtros['evento'] ?? '')))); ?>">Exportar CSV</a></div>
  </form>

  <div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>Fecha</th><th>Evento</th><th>Descripción</th><th>IP</th><th>Usuario</th></tr></thead>
    <tbody>
      <?php foreach (($rows ?? []) as $r): ?>
      <tr>
        <td><?php echo e((string)$r['created_at']); ?></td>
        <td><span class="badge bg-light text-dark border"><?php echo e((string)$r['evento']); ?></span></td>
        <td><?php echo e((string)$r['descripcion']); ?></td>
        <td><?php echo e((string)$r['ip_address']); ?></td>
        <td><?php echo e((string)($r['usuario'] ?? '')); ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div></div>
</div>
