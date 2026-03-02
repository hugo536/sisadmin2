<?php
$err = (string)($_GET['error'] ?? '');
$ok = (string)($_GET['ok'] ?? '');
?>
<div class="container-fluid py-3">
  <h3>Plan Contable</h3>
  <?php if ($err !== ''): ?><div class="alert alert-danger"><?php echo e($err); ?></div><?php endif; ?>
  <?php if ($ok !== ''): ?><div class="alert alert-success">Operación realizada.</div><?php endif; ?>
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card"><div class="card-body">
        <h5>Nueva cuenta</h5>
        <form method="post" action="<?php echo e(route_url('contabilidad/guardar_cuenta')); ?>">
          <input class="form-control mb-2" name="codigo" placeholder="Código" required>
          <input class="form-control mb-2" name="nombre" placeholder="Nombre" required>
          <select class="form-select mb-2" name="tipo" required><option>ACTIVO</option><option>PASIVO</option><option>PATRIMONIO</option><option>INGRESO</option><option>GASTO</option></select>
          <input class="form-control mb-2" type="number" min="1" name="nivel" value="1" required>
          <select class="form-select mb-2" name="id_padre"><option value="0">Sin padre</option><?php foreach ($cuentas as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'] . ' - ' . $c['nombre']); ?></option><?php endforeach; ?></select>
          <select class="form-select mb-2" name="permite_movimiento"><option value="0">No movimiento</option><option value="1">Movimiento</option></select>
          <button class="btn btn-primary">Guardar</button>
        </form>
      </div></div>
      <div class="card mt-3"><div class="card-body">
        <h5>Parámetros contables</h5>
        <form method="post" action="<?php echo e(route_url('contabilidad/guardar_parametro')); ?>">
          <select class="form-select mb-2" name="clave" required>
            <option value="CTA_CAJA_DEFECTO">CTA_CAJA_DEFECTO</option><option value="CTA_CXC">CTA_CXC</option><option value="CTA_CXP">CTA_CXP</option>
          </select>
          <select class="form-select mb-2" name="id_cuenta" required><?php foreach ($cuentasMovimiento as $c): ?><option value="<?php echo (int)$c['id']; ?>"><?php echo e($c['codigo'].' - '.$c['nombre']); ?></option><?php endforeach; ?></select>
          <button class="btn btn-outline-primary">Guardar parámetro</button>
        </form>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card"><div class="card-body table-responsive">
        <table class="table table-sm"><thead><tr><th>Código</th><th>Nombre</th><th>Tipo</th><th>Nivel</th><th>Mov.</th><th>Estado</th><th>Acción</th></tr></thead><tbody>
          <?php foreach ($cuentas as $c): ?><tr><td><?php echo e($c['codigo']); ?></td><td><?php echo e($c['nombre']); ?></td><td><?php echo e($c['tipo']); ?></td><td><?php echo (int)$c['nivel']; ?></td><td><?php echo (int)$c['permite_movimiento'] === 1 ? 'Sí' : 'No'; ?></td><td><?php echo (int)$c['estado'] === 1 ? 'Activo' : 'Inactivo'; ?></td><td><?php if ((int)$c['estado'] === 1): ?><form method="post" action="<?php echo e(route_url('contabilidad/inactivar_cuenta')); ?>"><input type="hidden" name="id_cuenta" value="<?php echo (int)$c['id']; ?>"><button class="btn btn-outline-danger btn-sm" onclick="return confirm('¿Inactivar cuenta?')">Inactivar</button></form><?php endif; ?></td></tr><?php endforeach; ?>
        </tbody></table>
      </div></div>
    </div>
  </div>
</div>
