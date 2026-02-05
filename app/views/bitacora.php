<?php
declare(strict_types=1);

$logs = is_array($logs ?? null) ? $logs : [];
?>
<section>
  <h1>Bitácora de Seguridad</h1>
  <p>Registro consolidado de eventos de autenticación, permisos y cambios críticos del sistema.</p>

  <article class="card">
    <?php if ($logs === []): ?>
      <p>No hay registros disponibles para mostrar.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Evento</th>
              <th>Descripción</th>
              <th>IP</th>
              <th>User Agent</th>
              <th>Usuario</th>
              <th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
              <tr>
                <td><?php echo (int) ($log['id'] ?? 0); ?></td>
                <td><?php echo e((string) ($log['evento'] ?? '')); ?></td>
                <td><?php echo e((string) ($log['descripcion'] ?? '')); ?></td>
                <td><?php echo e((string) ($log['ip_address'] ?? '')); ?></td>
                <td><?php echo e((string) ($log['user_agent'] ?? '')); ?></td>
                <td><?php echo (int) ($log['created_by'] ?? 0); ?></td>
                <td><?php echo e((string) ($log['created_at'] ?? '')); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </article>
</section>
