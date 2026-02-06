(function () {
  var logoutLink = document.getElementById('logoutLink');
  if (logoutLink) {
    logoutLink.classList.add('bg-danger', 'text-white', 'rounded');

    logoutLink.addEventListener('click', function (e) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Cerrar sesión',
        text: '¿Deseas salir del sistema?',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar'
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = logoutLink.href;
        }
      });
    });
  }

  var btn = document.getElementById('toggleSidebar');
  var sidebar = document.querySelector('.sidebar');
  if (btn && sidebar) {
    btn.addEventListener('click', function () {
      sidebar.classList.toggle('active');
    });
  }
})();
