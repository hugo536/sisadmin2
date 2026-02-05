<?php
declare(strict_types=1);
?>
<section>
  <h1>En construcción</h1>
  <p>La ruta solicitada aún no está implementada.</p>
  <?php if (!empty($destino)): ?>
    <p>Destino: <code><?php echo e((string) $destino); ?></code></p>
  <?php endif; ?>
</section>
