<?php
declare(strict_types=1);

$totales = is_array($totales ?? null) ? $totales : [];
$movimientos = is_array($movimientos ?? null) ? $movimientos : [];
?>
<section>
  <h1>Dashboard</h1>
  <p>Resumen general del sistema.</p>

  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin:16px 0 24px;">
    <article style="background:#fff;padding:14px;border:1px solid #e5e7eb;border-radius:8px;">
      <h3>Total Ítems activos</h3>
      <strong style="font-size:24px;"><?php echo (int) ($totales['items'] ?? 0); ?></strong>
    </article>
    <article style="background:#fff;padding:14px;border:1px solid #e5e7eb;border-radius:8px;">
      <h3>Total Terceros activos</h3>
      <strong style="font-size:24px;"><?php echo (int) ($totales['terceros'] ?? 0); ?></strong>
    </article>
    <article style="background:#fff;padding:14px;border:1px solid #e5e7eb;border-radius:8px;">
      <h3>Total Usuarios activos</h3>
      <strong style="font-size:24px;"><?php echo (int) ($totales['usuarios'] ?? 0); ?></strong>
    </article>
  </div>

  <article style="background:#fff;padding:14px;border:1px solid #e5e7eb;border-radius:8px;">
    <h2>Movimientos de inventario recientes</h2>

    <?php if ($movimientos === []): ?>
      <p>No hay movimientos disponibles para mostrar.</p>
    <?php else: ?>
      <table style="width:100%;border-collapse:collapse;">
        <thead>
          <tr>
            <?php if (isset($movimientos[0]['fecha'])): ?><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px;">Fecha</th><?php endif; ?>
            <?php if (isset($movimientos[0]['created_at'])): ?><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px;">Creado</th><?php endif; ?>
            <?php if (isset($movimientos[0]['tipo'])): ?><th style="text-align:left;border-bottom:1px solid #e5e7eb;padding:8px;">Tipo</th><?php endif; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($movimientos as $mov): ?>
            <tr>
              <?php if (isset($mov['fecha'])): ?><td style="padding:8px;border-bottom:1px solid #f3f4f6;"><?php echo e((string) $mov['fecha']); ?></td><?php endif; ?>
              <?php if (isset($mov['created_at'])): ?><td style="padding:8px;border-bottom:1px solid #f3f4f6;"><?php echo e((string) $mov['created_at']); ?></td><?php endif; ?>
              <?php if (isset($mov['tipo'])): ?><td style="padding:8px;border-bottom:1px solid #f3f4f6;"><?php echo e((string) $mov['tipo']); ?></td><?php endif; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </article>
</section>
