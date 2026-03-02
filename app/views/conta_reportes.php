<div class="container-fluid py-3">
  <h3>Reportes Contables</h3>
  <form method="get" class="row g-2 mb-3">
    <input type="hidden" name="ruta" value="contabilidad/reportes">
    <div class="col-md-3"><select class="form-select" name="id_periodo"><option value="0">Todos los periodos</option><?php foreach (($periodos ?? []) as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo (int)($filtros['id_periodo'] ?? 0) === (int)$p['id'] ? 'selected' : ''; ?>><?php echo e($p['anio'].'-'.$p['mes']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_desde" value="<?php echo e((string)($filtros['fecha_desde'] ?? '')); ?>"></div>
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_hasta" value="<?php echo e((string)($filtros['fecha_hasta'] ?? '')); ?>"></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filtrar</button></div>
  </form>
  <h5>Libro Diario</h5>
  <?php foreach ($libroDiario as $a): ?>
    <div><b><?php echo e($a['codigo']); ?></b> <?php echo e($a['fecha']); ?> - <?php echo e($a['glosa']); ?></div>
    <ul><?php foreach ($detalleFn((int)$a['id']) as $d): ?><li><?php echo e($d['cuenta_codigo'].' '.$d['cuenta_nombre'].' | D:'.$d['debe'].' H:'.$d['haber']); ?></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
  <h5>Balance de Comprobación</h5>
  <table class="table table-sm"><thead><tr><th>Cuenta</th><th>Debe</th><th>Haber</th><th>Saldo</th></tr></thead><tbody>
    <?php foreach ($balance as $b): ?><tr><td><?php echo e($b['codigo'].' '.$b['nombre']); ?></td><td><?php echo e($b['debe']); ?></td><td><?php echo e($b['haber']); ?></td><td><?php echo e($b['saldo']); ?></td></tr><?php endforeach; ?>
  </tbody></table>
</div>
