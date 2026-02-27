/**
 * public/assets/js/main.js
 * =========================================================
 * Core UI logic:
 * - Logout confirmation (SweetAlert2)
 * - Desktop sidebar collapse / expand
 * - State persistence (localStorage)
 * =========================================================
 */

(function () {
  'use strict';

  // =========================================================
  // 1) LOGOUT CONFIRMATION
  // =========================================================
  const logoutLink = document.getElementById('logoutLink');

  if (logoutLink) {
    logoutLink.addEventListener('click', function (e) {
      e.preventDefault();

      if (typeof Swal === 'undefined') {
        window.location.href = logoutLink.href;
        return;
      }

      Swal.fire({
        icon: 'warning',
        title: 'Cerrar sesión',
        text: '¿Deseas salir del sistema?',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#ef4444'
      }).then(function (result) {
        if (result.isConfirmed) {
          window.location.href = logoutLink.href;
        }
      });
    });
  }

  // =========================================================
  // 2) DESKTOP SIDEBAR COLLAPSE
  // =========================================================
  const SIDEBAR_STATE_KEY = 'erp.sidebar.collapsed';
  const toggleBtn = document.getElementById('toggleSidebar');
  const desktopSidebar = document.querySelector('.sidebar-desktop');

  // Aplica estado guardado
  const applySidebarState = function () {
    const collapsed = localStorage.getItem(SIDEBAR_STATE_KEY) === '1';
    document.body.classList.toggle('sidebar-collapsed', collapsed);
  };

  applySidebarState();

  // Toggle manual (solo desktop)
  if (toggleBtn && desktopSidebar) {
    toggleBtn.addEventListener('click', function () {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem(SIDEBAR_STATE_KEY, collapsed ? '1' : '0');
    });
  }

})();