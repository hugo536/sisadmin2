(function () {
  const form = document.getElementById('login-form');
  const usuario = document.getElementById('usuario');
  const clave = document.getElementById('clave');
  const togglePassword = document.getElementById('toggle-password');

  function mostrarError(mensaje) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: 'error',
        title: 'Error de acceso',
        text: mensaje,
        confirmButtonText: 'Entendido',
      });
      return;
    }

    window.alert(mensaje);
  }

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

  const serverError = form.dataset.error;
  if (serverError && serverError.trim() !== '') {
    mostrarError(serverError);
  }

  form.addEventListener('submit', function (event) {
    if (usuario.value.trim() === '' || clave.value.trim() === '') {
      event.preventDefault();
      mostrarError('Usuario y contraseña son obligatorios.');
    }
  });
})();
