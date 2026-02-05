(function () {
  const form = document.getElementById('login-form');
  const usuario = document.getElementById('usuario');
  const clave = document.getElementById('clave');
  const togglePassword = document.getElementById('toggle-password');

  if (togglePassword && clave) {
    togglePassword.addEventListener('click', function () {
      const isPassword = clave.getAttribute('type') === 'password';
      clave.setAttribute('type', isPassword ? 'text' : 'password');
      togglePassword.textContent = isPassword ? 'Ocultar' : 'Ver';
      togglePassword.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
      togglePassword.setAttribute('aria-label', isPassword ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });
  }

  if (!form || !usuario || !clave) {
    return;
  }

  form.addEventListener('submit', function (event) {
    if (usuario.value.trim() === '' || clave.value.trim() === '') {
      event.preventDefault();
      alert('Usuario y contraseña son obligatorios.');
    }
  });
})();
