(function () {
  function initTooltips() {
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
      new bootstrap.Tooltip(tooltipTriggerEl);
    });
  }

  function initSearch() {
    var searchInput = document.getElementById('usuarioSearch');
    var tableBody = document.querySelector('#usuariosTable tbody');

    if (!searchInput || !tableBody) {
      return;
    }

    searchInput.addEventListener('input', function () {
      var query = (searchInput.value || '').trim().toLowerCase();
      Array.from(tableBody.querySelectorAll('tr')).forEach(function (row) {
        var source = row.getAttribute('data-search') || '';
        row.style.display = source.indexOf(query) !== -1 ? '' : 'none';
      });
    });
  }

  function bindEstadoForms() {
    Array.from(document.querySelectorAll('.estado-form')).forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
          title: 'Confirmar',
          text: '¿Aplicar cambio de estado?',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, continuar',
          cancelButtonText: 'Cancelar'
        }).then(function (r) {
          if (r.isConfirmed) {
            form.submit();
          }
        });
      });
    });
  }

  function bindDeleteForms() {
    Array.from(document.querySelectorAll('.delete-form')).forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        Swal.fire({
          title: '¿Eliminar usuario?',
          text: 'Esta acción desactivará el usuario.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          cancelButtonText: 'Cancelar'
        }).then(function (result) {
          if (result.isConfirmed) {
            form.submit();
          }
        });
      });
    });
  }

  function handleCreateFlash() {
    if (!window.USUARIOS_FLASH || window.USUARIOS_FLASH.tipo !== 'success' || window.USUARIOS_FLASH.accion !== 'crear') {
      return;
    }

    var collapseElement = document.getElementById('crearUsuarioCollapse');
    var createForm = document.getElementById('formCrearUsuario');

    if (createForm) {
      createForm.reset();
    }

    if (collapseElement) {
      var collapseInstance = bootstrap.Collapse.getOrCreateInstance(collapseElement, { toggle: false });
      collapseInstance.hide();
    }
  }

  window.editarUsuario = function (id, nombreCompleto, usuario, email, idRol) {
    document.getElementById('editId').value = id;
    document.getElementById('editNombreCompleto').value = nombreCompleto;
    document.getElementById('editUsuario').value = usuario;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRol').value = idRol;
    new bootstrap.Modal(document.getElementById('modalEditarUsuario')).show();
  };

  initTooltips();
  initSearch();
  bindEstadoForms();
  bindDeleteForms();
  handleCreateFlash();
})();
