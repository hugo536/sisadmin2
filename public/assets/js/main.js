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
  // 0) TOMSELECT + BOOTSTRAP MODAL (focus trap)
  // =========================================================
  const patchTomSelectForModals = function () {
    if (typeof window.TomSelect !== 'function' || window.__TOMSELECT_MODAL_PATCHED__) return;

    const TomSelectOriginal = window.TomSelect;

    const ensureEmptyOption = function (inputEl) {
      if (!inputEl || inputEl.tagName !== 'SELECT' || inputEl.multiple) return;
      if (inputEl.querySelector('option[value=""]')) return;

      const emptyOption = document.createElement('option');
      emptyOption.value = '';
      emptyOption.textContent = '';
      inputEl.insertBefore(emptyOption, inputEl.firstChild);
    };

    const resolveInputElement = function (inputArg) {
      if (!inputArg) return null;
      if (inputArg instanceof Element) return inputArg;
      if (typeof inputArg === 'string') return document.querySelector(inputArg);
      return null;
    };

    window.TomSelect = new Proxy(TomSelectOriginal, {
      construct(target, args, newTarget) {
        const inputArg = args[0];
        const userSettings = args[1] && typeof args[1] === 'object' ? args[1] : {};
        const settings = { ...userSettings };
        const inputEl = resolveInputElement(inputArg);

        if (inputEl) {
          const modalParent = inputEl.closest('.modal');
          if (modalParent && (!settings.dropdownParent || settings.dropdownParent === 'body')) {
            settings.dropdownParent = modalParent;
          }

          ensureEmptyOption(inputEl);
          if (settings.allowEmptyOption === undefined) {
            settings.allowEmptyOption = true;
          }
        }

        return Reflect.construct(target, [inputArg, settings], newTarget);
      }
    });

    window.__TOMSELECT_MODAL_PATCHED__ = true;
  };

  patchTomSelectForModals();

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
