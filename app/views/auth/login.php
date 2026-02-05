<?php
$error = isset($error) ? (string) $error : '';
?>
<link rel="stylesheet" href="<?php echo e(asset_url('css/login.css')); ?>">

<div class="login-shell">
  <section class="login-welcome" aria-label="Bienvenida">
    <div class="welcome-overlay"></div>
    <div class="welcome-content">
      <span class="brand-mark">✶</span>
      <h1>Hola, SISADMIN2</h1>
      <p>
        Administra tu sistema de forma segura y rápida.
        Centraliza accesos, controla sesiones y trabaja con un entorno profesional.
      </p>
      <small>© <?php echo date('Y'); ?> SISADMIN2. Todos los derechos reservados.</small>
    </div>
  </section>

  <section class="login-panel" aria-label="Formulario de acceso">
    <div class="panel-brand">SISADMIN2</div>
    <h2>Bienvenido de nuevo</h2>
    <p class="subtitle">Ingresa tus credenciales para continuar.</p>

    <form id="login-form" method="post" action="<?php echo e(route_url('login/authenticate')); ?>" novalidate data-error="<?php echo e($error); ?>">
      <label for="usuario">Usuario</label>
      <input id="usuario" name="usuario" type="text" autocomplete="username" required>

      <label for="clave">Contraseña</label>
      <div class="password-wrap">
        <input id="clave" name="clave" type="password" autocomplete="current-password" required>
        <button id="toggle-password" type="button" class="toggle-password" aria-label="Mostrar contraseña" aria-pressed="false">
          Ver
        </button>
      </div>

      <button type="submit" class="submit-btn">Iniciar sesión</button>
    </form>
  </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo e(asset_url('js/app.js/login.js')); ?>"></script>
