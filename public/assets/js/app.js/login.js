(function () {
  const form = document.getElementById('login-form');
  if (!form) {
    return;
  }

  form.addEventListener('submit', function (event) {
    const usuario = document.getElementById('usuario');
    const password = document.getElementById('password');

    if (!usuario || !password) {
      return;
    }

    if (usuario.value.trim() === '' || password.value.trim() === '') {
      event.preventDefault();
      alert('Usuario y contraseña son obligatorios.');
    }
  });
})();