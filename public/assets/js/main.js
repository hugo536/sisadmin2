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

    const withClearButtonPlugin = function (settings, inputEl) {
      if (!inputEl || inputEl.tagName !== 'SELECT' || inputEl.multiple) return;
      const plugins = settings.plugins;

      if (Array.isArray(plugins)) {
        if (!plugins.includes('clear_button')) {
          settings.plugins = [...plugins, 'clear_button'];
        }
        return;
      }

      if (plugins && typeof plugins === 'object') {
        if (!Object.prototype.hasOwnProperty.call(plugins, 'clear_button')) {
          settings.plugins = { ...plugins, clear_button: { title: 'Limpiar selección' } };
        }
        return;
      }

      settings.plugins = { clear_button: { title: 'Limpiar selección' } };
    };

    const PatchedTomSelect = function (inputArg, userSettings) {
      const settings = userSettings && typeof userSettings === 'object' ? { ...userSettings } : {};
      const inputEl = resolveInputElement(inputArg);

      if (inputEl) {
        const modalParent = inputEl.closest('.modal');
        const isBodyParent = settings.dropdownParent === 'body' || settings.dropdownParent === document.body;
        if (modalParent && (!settings.dropdownParent || isBodyParent)) {
          settings.dropdownParent = modalParent;
        }

        ensureEmptyOption(inputEl);
        if (settings.allowEmptyOption === undefined) {
          settings.allowEmptyOption = true;
        }
        withClearButtonPlugin(settings, inputEl);
      }

      return new TomSelectOriginal(inputArg, settings);
    };

    PatchedTomSelect.prototype = TomSelectOriginal.prototype;
    Object.setPrototypeOf(PatchedTomSelect, TomSelectOriginal);
    window.TomSelect = PatchedTomSelect;

    window.__TOMSELECT_MODAL_PATCHED__ = true;
  };

  const patchBootstrapFocusTrapForTomSelect = function () {
    if (window.__TOMSELECT_FOCUS_PATCHED__) return;

    document.addEventListener('focusin', function (event) {
      const target = event.target;
      if (!(target instanceof Element)) return;

      const tomSelectNode = target.closest('.ts-wrapper, .ts-control, .ts-dropdown, .ts-dropdown-content');
      if (!tomSelectNode) return;

      const openModal = document.querySelector('.modal.show');
      if (!openModal) return;

      event.stopImmediatePropagation();
    }, true);

    window.__TOMSELECT_FOCUS_PATCHED__ = true;
  };

  patchTomSelectForModals();
  patchBootstrapFocusTrapForTomSelect();

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
