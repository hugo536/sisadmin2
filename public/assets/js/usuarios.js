/**
 * public/assets/js/usuarios.js
 * =========================================================
 * Lógica específica de Usuarios:
 * - Switch de estado (POST form)
 * - Confirmación eliminar (SweetAlert2)
 * - Modales crear/editar
 * - Inicializa renderizador reutilizable ERPTable (filtros + paginación)
 * =========================================================
 */

(function () {
  'use strict';

  // ---------- Config SweetAlert (solo usuarios) ----------
  const swalBootstrap = Swal.mixin({
    customClass: {
      confirmButton: 'btn btn-primary px-4 fw-bold',
      cancelButton: 'btn btn-outline-secondary px-4 me-2',
      popup: 'rounded-3 shadow-sm'
    },
    buttonsStyling: false
  });

  // (Opcional) Toast si lo usas luego
  const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 2000,
    customClass: { popup: 'rounded-3 shadow-sm' }
  });

  // ---------- 1) Switch de estado ----------
  function initStatusSwitch() {
    document.querySelectorAll('.switch-estado').forEach((switchInput) => {
      switchInput.addEventListener('change', function () {
        const idUsuario = this.getAttribute('data-id');
        const nuevoEstado = this.checked ? 1 : 0;

        const form = document.createElement('form');
        form.method = 'POST';
        form.style.display = 'none';

        const inputAccion = document.createElement('input');
        inputAccion.name = 'accion';
        inputAccion.value = 'estado';

        const inputId = document.createElement('input');
        inputId.name = 'id';
        inputId.value = idUsuario;

        const inputEstado = document.createElement('input');
        inputEstado.name = 'estado';
        inputEstado.value = nuevoEstado;

        form.appendChild(inputAccion);
        form.appendChild(inputId);
        form.appendChild(inputEstado);
        document.body.appendChild(form);
        form.submit();
      });
    });
  }

  // ---------- 2) Confirm delete ----------
  function bindDeleteForms() {
    const forms = document.querySelectorAll('.delete-form');
    forms.forEach((form) => {
      form.addEventListener('submit', function (e) {
        e.preventDefault();

        swalBootstrap.fire({
          title: '¿Eliminar usuario?',
          text: 'Esta acción eliminará al usuario del sistema.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: 'Sí, eliminar',
          confirmButtonColor: '#ef4444',
          cancelButtonText: 'Cancelar'
        }).then((result) => {
          if (result.isConfirmed) form.submit();
        });
      });
    });
  }

  // ---------- 3) Modales ----------
  window.abrirModalCrear = function () {
    const form = document.getElementById('formCrearUsuario');
    if (form) form.reset();
    const modalEl = document.getElementById('modalCrearUsuario');
    if (!modalEl) return;
    new bootstrap.Modal(modalEl).show();
  };

  window.editarUsuario = function (id, nombreCompleto, usuario, email, idRol) {
    const byId = (x) => document.getElementById(x);

    byId('editId').value = id;
    byId('editNombreCompleto').value = nombreCompleto;
    byId('editUsuario').value = usuario;
    byId('editEmail').value = email;
    byId('editRol').value = idRol;

    const passField = document.querySelector('#formEditarUsuario input[name="clave"]');
    if (passField) passField.value = '';

    const modalEl = document.getElementById('modalEditarUsuario');
    if (!modalEl) return;
    new bootstrap.Modal(modalEl).show();
  };

  // ---------- 4) Init ----------
  document.addEventListener('DOMContentLoaded', function () {
    // Tooltips
    if (window.ERPTable) {
      window.ERPTable.initTooltips();
    } else {
      // fallback: si no cargó renderizadores.js
      if (window.bootstrap && bootstrap.Tooltip) {
        [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]')).forEach((el) => {
          if (bootstrap.Tooltip.getInstance(el)) return;
          new bootstrap.Tooltip(el);
        });
      }
    }

    // Tabla: filtros + paginación (reutilizable)
    if (window.ERPTable) {
      window.UsuariosTableManager = window.ERPTable.createTableManager({
        tableSelector: '#usuariosTable',
        rowsSelector: 'tbody tr',
        searchInput: '#usuarioSearch',
        filters: [
          { el: '#filtroRol', attr: 'data-rol' },
          { el: '#filtroEstado', attr: 'data-estado' }
        ],
        searchAttr: 'data-search',
        rowsPerPage: 5,
        paginationControls: '#paginationControls',
        paginationInfo: '#paginationInfo',
        emptyText: 'Sin resultados',
        infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} usuarios`
      }).init();
    }

    initStatusSwitch();
    bindDeleteForms();
  });

})();
