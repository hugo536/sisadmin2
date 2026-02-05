<?php
$error = isset($error) ? (string) $error : '';
?>
<link rel="stylesheet" href="assets/css/login.css">

<div class="login-shell">
  <section class="login-welcome" aria-label="Bienvenida">
    <span class="badge">SISADMIN2</span>
    <h1>Bienvenido</h1>
    <p>
      Gestiona tu plataforma de forma segura con una experiencia moderna,
      ágil y profesional.
    </p>
    <ul>
      <li>Acceso centralizado</li>
      <li>Monitoreo de sesiones</li>
      <li>Seguridad empresarial</li>
    </ul>
  </section>

  <section class="login-panel" aria-label="Formulario de acceso">
    <h2>Iniciar sesión</h2>
    <p class="subtitle">Ingresa con tus credenciales para continuar.</p>

    <?php if ($error !== ''): ?>
      <div class="alert" role="alert"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form id="login-form" method="post" action="?ruta=login/authenticate" novalidate>
      <label for="usuario">Usuario</label>
      <input id="usuario" name="usuario" type="text" autocomplete="username" required>

      <label for="clave">Contraseña</label>
      <div class="password-wrap">
        <input id="clave" name="clave" type="password" autocomplete="current-password" required>
        <button id="toggle-password" type="button" class="toggle-password" aria-label="Mostrar contraseña" aria-pressed="false">
          Ver
        </button>
      </div>

      <button type="submit" class="submit-btn">Ingresar</button>
    </form>
  </section>
</div>

<script src="assets/js/app.js/login.js"></script>
