<?php
declare(strict_types=1);

$config = is_array($config ?? null) ? $config : [];
$mensaje = (string) ($mensaje ?? '');
$error = (string) ($error ?? '');
?>
<section>
  <h1>Configuración de Empresa</h1>
  <p>Administra la información principal de la empresa.</p>

  <?php if ($mensaje !== ''): ?>
    <div style="background:#ecfdf5;border:1px solid #10b981;color:#065f46;padding:10px;border-radius:8px;margin:10px 0;">
      <?php echo e($mensaje); ?>
    </div>
  <?php endif; ?>

  <?php if ($error !== ''): ?>
    <div style="background:#fef2f2;border:1px solid #ef4444;color:#991b1b;padding:10px;border-radius:8px;margin:10px 0;">
      <?php echo e($error); ?>
    </div>
  <?php endif; ?>

  <form method="post" action="<?php echo e(route_url('config/empresa')); ?>" enctype="multipart/form-data" style="background:#fff;padding:16px;border-radius:10px;border:1px solid #e5e7eb;max-width:860px;">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
      <label>Razón social
        <input type="text" name="razon_social" value="<?php echo e((string) ($config['razon_social'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>RUC
        <input type="text" name="ruc" value="<?php echo e((string) ($config['ruc'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>Dirección
        <input type="text" name="direccion" value="<?php echo e((string) ($config['direccion'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>Teléfono
        <input type="text" name="telefono" value="<?php echo e((string) ($config['telefono'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>Email
        <input type="email" name="email" value="<?php echo e((string) ($config['email'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>Tema
        <input type="text" name="tema" value="<?php echo e((string) ($config['tema'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>Moneda
        <input type="text" name="moneda" value="<?php echo e((string) ($config['moneda'] ?? '')); ?>" style="width:100%;padding:8px;margin-top:4px;">
      </label>
      <label>Logo (png/jpg/jpeg/webp, máx. 2MB)
        <input type="file" name="logo" accept=".png,.jpg,.jpeg,.webp" style="width:100%;padding:8px;margin-top:4px;">
      </label>
    </div>

    <?php if (!empty($config['logo_path'])): ?>
      <div style="margin:14px 0;">
        <p style="margin:0 0 8px;">Logo actual:</p>
        <img src="<?php echo e(base_url() . '/' . ltrim((string) $config['logo_path'], '/')); ?>" alt="Logo empresa" style="max-height:70px;max-width:220px;object-fit:contain;border:1px solid #e5e7eb;padding:6px;border-radius:8px;">
      </div>
    <?php endif; ?>

    <button type="submit" style="margin-top:8px;background:#111827;color:#fff;border:none;padding:10px 14px;border-radius:8px;cursor:pointer;">Guardar configuración</button>
  </form>
</section>
