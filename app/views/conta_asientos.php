<div class="container-fluid py-3">
  <h3>Asientos Manuales</h3>
  <form method="get" class="row g-2 mb-2">
    <input type="hidden" name="ruta" value="contabilidad/asientos">
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_desde" value="<?php echo e((string)($filtros['fecha_desde'] ?? '')); ?>"></div>
    <div class="col-md-2"><input type="date" class="form-control" name="fecha_hasta" value="<?php echo e((string)($filtros['fecha_hasta'] ?? '')); ?>"></div>
    <div class="col-md-3"><select class="form-select" name="id_periodo"><option value="0">Todos los periodos</option><?php foreach ($periodos as $p): ?><option value="<?php echo (int)$p['id']; ?>" <?php echo (int)($filtros['id_periodo'] ?? 0) === (int)$p['id'] ? 'selected' : ''; ?>><?php echo e($p['anio'].'-'.$p['mes']); ?></option><?php endforeach; ?></select></div>
    <div class="col-md-2"><button class="btn btn-outline-secondary w-100">Filtrar</button></div>
  </form>
  <form method="post" action="<?php echo e(route_url('contabilidad/guardar_asiento')); ?>" id="form-asiento">
    <div class="row g-2">
      <div class="col-md-2"><input type="date" name="fecha" class="form-control" value="<?php echo date('Y-m-d'); ?>" required></div>
      <div class="col-md-3"><select name="id_periodo" class="form-select" required><?php foreach ($periodos as $p): if($p['estado']!=='ABIERTO') continue; ?><option value="<?php echo (int)$p['id']; ?>"><?php echo e($p['anio'].'-'.$p['mes']); ?></option><?php endforeach; ?></select></div>
      <div class="col-md-5"><input name="glosa" class="form-control" placeholder="Glosa" required></div>
      <div class="col-md-2"><button class="btn btn-primary w-100">Registrar</button></div>
    </div>
    <table class="table table-sm mt-2" id="lineas"><thead><tr><th>Cuenta</th><th>Debe</th><th>Haber</th><th>Ref</th><th></th></tr></thead><tbody></tbody></table>
    <button type="button" class="btn btn-outline-secondary btn-sm" id="agregar-linea">+ Línea</button>
    <span class="ms-3">Debe: <b id="sumDebe">0.0000</b> | Haber: <b id="sumHaber">0.0000</b> <span id="balanceEstado" class="badge bg-secondary ms-2">Pendiente</span></span>
  </form>
  <hr>
  <h5>Asientos</h5>
  <?php foreach ($asientos as $a): ?>
    <div class="card mb-2"><div class="card-body">
      <div class="d-flex justify-content-between"><div><b><?php echo e($a['codigo']); ?></b> - <?php echo e($a['fecha']); ?> - <?php echo e($a['glosa']); ?> (<?php echo e($a['estado']); ?>)</div>
      <?php if ($a['estado'] === 'REGISTRADO'): ?><form method="post" action="<?php echo e(route_url('contabilidad/anular_asiento')); ?>"><input type="hidden" name="id_asiento" value="<?php echo (int)$a['id']; ?>"><button class="btn btn-danger btn-sm">Anular</button></form><?php endif; ?></div>
      <ul><?php foreach ($detalleFn((int)$a['id']) as $d): ?><li><?php echo e($d['cuenta_codigo'].' '.$d['cuenta_nombre'].' | D:'.$d['debe'].' H:'.$d['haber']); ?></li><?php endforeach; ?></ul>
    </div></div>
  <?php endforeach; ?>
</div>
<script>window.CONTA_CUENTAS=<?php echo json_encode($cuentas, JSON_UNESCAPED_UNICODE); ?>;</script>
<script src="<?php echo e(base_url()); ?>/assets/js/contabilidad.js"></script>

<?php if (($totalPaginas ?? 1) > 1): ?>
<nav class="mt-3"><ul class="pagination pagination-sm">
<?php $base = "?ruta=contabilidad/asientos&id_periodo=" . (int)($filtros['id_periodo'] ?? 0) . "&fecha_desde=" . urlencode((string)($filtros['fecha_desde'] ?? "")) . "&fecha_hasta=" . urlencode((string)($filtros['fecha_hasta'] ?? "")); ?>
<?php for($i=1;$i<=(int)$totalPaginas;$i++): ?>
<li class="page-item <?php echo $i === (int)($filtros['pagina'] ?? 1) ? "active" : ""; ?>"><a class="page-link" href="<?php echo e($base . "&pagina=" . $i); ?>"><?php echo $i; ?></a></li>
<?php endfor; ?>
</ul></nav>
<?php endif; ?>
