<div class="container-fluid py-3">
  <h3>Cierres Contables</h3>
  <div class="row g-3">
    <div class="col-lg-5">
      <div class="card"><div class="card-body">
        <h5>Depreciación automática</h5>
        <form method="post" action="<?php echo e(route_url('cierre_contable/ejecutar_depreciacion')); ?>" class="row g-2">
          <div class="col-8"><input type="month" class="form-control" name="periodo" value="<?php echo date('Y-m'); ?>" required></div>
          <div class="col-4"><button class="btn btn-primary w-100">Ejecutar</button></div>
        </form>
      </div></div>
    </div>
    <div class="col-lg-7">
      <div class="card"><div class="card-body">
        <h5>Cierre mensual / anual</h5>
        <table class="table table-sm">
          <thead><tr><th>Periodo</th><th>Estado</th><th></th></tr></thead>
          <tbody><?php foreach (($periodos ?? []) as $p): ?><tr>
            <td><?php echo e($p['anio'] . '-' . str_pad((string)$p['mes'], 2, '0', STR_PAD_LEFT)); ?></td>
            <td><?php echo e($p['estado']); ?></td>
            <td>
              <?php if ($p['estado'] === 'ABIERTO'): ?>
              <form method="post" action="<?php echo e(route_url('cierre_contable/cierre_mensual')); ?>">
                <input type="hidden" name="id_periodo" value="<?php echo (int)$p['id']; ?>">
                <button class="btn btn-sm btn-outline-danger">Cerrar</button>
              </form>
              <?php endif; ?>
            </td>
          </tr><?php endforeach; ?></tbody>
        </table>
        <form method="post" action="<?php echo e(route_url('cierre_contable/cierre_anual')); ?>" class="row g-2 mt-3">
          <div class="col-8"><input type="number" class="form-control" name="anio" value="<?php echo date('Y'); ?>" required></div>
          <div class="col-4"><button class="btn btn-danger w-100">Cierre anual</button></div>
        </form>
      </div></div>
    </div>
  </div>
</div>
