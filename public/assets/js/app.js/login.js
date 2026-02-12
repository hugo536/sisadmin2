(function () {
  const form = document.getElementById('login-form');
  const usuario = document.getElementById('usuario');
  const clave = document.getElementById('clave');
  const toggleBtn = document.getElementById('toggle-password');

  // Función para mostrar alertas bonitas
  function mostrarError(mensaje) {
    if (window.Swal && typeof window.Swal.fire === 'function') {
      window.Swal.fire({
        icon: 'error',
        title: 'Acceso Denegado',
        text: mensaje,
        confirmButtonColor: '#2563eb', // Usa tu color primario
        confirmButtonText: 'Intentar de nuevo',
        customClass: {
          popup: 'rounded-4' // Bordes redondeados modernos
        }
      });
    } else {
      console.error(mensaje);
    }
  }

  // Lógica del Ojo (Mostrar/Ocultar contraseña)
  if (toggleBtn && clave) {
    toggleBtn.addEventListener('click', function () {
      const type = clave.getAttribute('type') === 'password' ? 'text' : 'password';
      clave.setAttribute('type', type);
      
      // Cambiar icono de Bootstrap
      const icon = this.querySelector('i');
      if (icon) {
        if (type === 'text') {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
      }
    });
  }

  if (!form) return;

  // Verificar errores de PHP
  const serverError = form.dataset.error;
  if (serverError && serverError.trim() !== '') {
    mostrarError(serverError);
  }

  // Validación antes de enviar
  form.addEventListener('submit', function (event) {
    if (!usuario.value.trim() || !clave.value.trim()) {
      event.preventDefault();
      // Animación visual de error en los inputs vacíos
      if(!usuario.value.trim()) usuario.style.borderColor = '#ef4444';
      if(!clave.value.trim()) clave.style.borderColor = '#ef4444';
      
      mostrarError('Por favor ingresa tu usuario y contraseña.');
      
      // Restaurar color al escribir
      setTimeout(() => {
        usuario.style.borderColor = '';
        clave.style.borderColor = '';
      }, 2000);
    }
  });
})();