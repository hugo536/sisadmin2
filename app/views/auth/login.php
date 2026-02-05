<h1>Login</h1>

<?php if (!empty($error)): ?>
  <p style="color: #b91c1c;"><?php echo htmlspecialchars((string) $error, ENT_QUOTES, 'UTF-8'); ?></p>
<?php endif; ?>

<form id="login-form" method="post" action="?ruta=login/authenticate" novalidate>
  <div style="margin-bottom: 8px;">
    <label for="usuario">Usuario</label><br>
    <input id="usuario" name="usuario" type="text" autocomplete="username" required>
  </div>

  <div style="margin-bottom: 8px;">
    <label for="clave">Contraseña</label><br>
    <input id="clave" name="clave" type="password" autocomplete="current-password" required>
  </div>

  <button type="submit">Ingresar</button>
</form>

<script src="assets/js/login.js"></script>
