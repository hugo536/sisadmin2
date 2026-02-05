(function () {
  const form = document.getElementById('login-form');
  if (!form) {
    return;
  }

  form.addEventListener('submit', function (event) {
    const usuario = document.getElementById('usuario');
    const clave = document.getElementById('clave');

    if (!usuario || !clave) {
      return;
    }

    if (usuario.value.trim() === '' || clave.value.trim() === '') {
      event.preventDefault();
      alert('Usuario y clave son obligatorios.');
    }
  });
})();
