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

    if (toggleBtn) {
      toggleBtn.setAttribute('aria-label', collapsed ? 'Expandir barra lateral' : 'Contraer barra lateral');
      toggleBtn.setAttribute('title', collapsed ? 'Expandir barra lateral' : 'Contraer barra lateral');
      toggleBtn.innerHTML = collapsed
        ? '<i class="bi bi-layout-sidebar"></i>'
        : '<i class="bi bi-layout-sidebar-inset"></i>';
    }
  };

  applySidebarState();

  // Toggle manual (solo desktop)
  if (toggleBtn && desktopSidebar) {
    toggleBtn.addEventListener('click', function () {
      const collapsed = document.body.classList.toggle('sidebar-collapsed');
      localStorage.setItem(SIDEBAR_STATE_KEY, collapsed ? '1' : '0');
      applySidebarState();
    });
  }

  // =========================================================
  // 3) NAVEGACIÓN PARCIAL (sidebar estático, solo cambia main)
  // =========================================================
  const MAIN_CONTENT_SELECTOR = '.main-content > .p-3.p-lg-4';
  const ROUTE_SCRIPT_MARKER = 'data-route-script';
  const DYNAMIC_CSS_MARKER = 'data-route-css';
  const PERSISTENT_SCRIPTS = [
    '/assets/js/main.js',
    '/assets/js/tablas/renderizadores.js',
    '/assets/js/tablas/iconos_accion.js',
    'bootstrap.bundle.min.js',
    'sweetalert2',
    'tom-select',
    'sidebar-core-script'
  ];

  let navigationInProgress = false;

  const isPersistentScript = function (scriptEl) {
    if (!scriptEl) return true;
    if (scriptEl.id && scriptEl.id === 'sidebar-core-script') return true;
    const src = scriptEl.getAttribute('src') || '';
    return PERSISTENT_SCRIPTS.some((needle) => src.includes(needle));
  };

  const isSidebarNavLink = function (link) {
    return !!link && (
      link.classList.contains('sb-link') ||
      link.classList.contains('sb-bottom-item')
    );
  };

  const NAV_LOADING_DELAY_MS = 180;
  let loadingTimerId = null;
  let loadingStateVisible = false;

  const setLoadingState = function (enabled) {
    if (enabled) {
      if (loadingStateVisible || loadingTimerId !== null) return;
      loadingTimerId = window.setTimeout(function () {
        document.body.classList.add('page-is-loading');
        loadingStateVisible = true;
        loadingTimerId = null;
      }, NAV_LOADING_DELAY_MS);
      return;
    }

    if (loadingTimerId !== null) {
      window.clearTimeout(loadingTimerId);
      loadingTimerId = null;
    }
    if (loadingStateVisible) {
      document.body.classList.remove('page-is-loading');
      loadingStateVisible = false;
    } else {
      document.body.classList.remove('page-is-loading');
    }
  };

  const resetDynamicAssets = function () {
    document.querySelectorAll(`script[${ROUTE_SCRIPT_MARKER}="1"]`).forEach((node) => node.remove());
    document.querySelectorAll(`link[${DYNAMIC_CSS_MARKER}="1"]`).forEach((node) => node.remove());
  };

  const syncDynamicCss = function (nextDoc) {
    const routeCss = Array.from(nextDoc.querySelectorAll('head link[rel="stylesheet"][href]'))
      .filter((link) => (link.getAttribute('href') || '').includes('/assets/css/terceros_perfil.css'));

    routeCss.forEach((link) => {
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = link.href;
      css.setAttribute(DYNAMIC_CSS_MARKER, '1');
      document.head.appendChild(css);
    });
  };

  const updateActiveSidebarLinks = function (targetUrl) {
    const normalizedTarget = targetUrl.pathname + targetUrl.search;
    const allNavLinks = document.querySelectorAll('a.sb-link[href], a.sb-bottom-item[href]');

    allNavLinks.forEach((a) => a.classList.remove('active'));
    allNavLinks.forEach((a) => {
      const aUrl = new URL(a.href, window.location.href);
      const normalizedLink = aUrl.pathname + aUrl.search;
      if (normalizedLink === normalizedTarget) {
        a.classList.add('active');
      }
    });
  };

  const runRouteScripts = async function (nextDoc) {
    const scriptNodes = Array.from(nextDoc.querySelectorAll('body script'));
    const routeScripts = scriptNodes.filter((scriptEl) => !isPersistentScript(scriptEl));

    for (const scriptEl of routeScripts) {
      if (scriptEl.src) {
        await new Promise((resolve, reject) => {
          const js = document.createElement('script');
          js.src = scriptEl.src;
          js.async = false;
          js.setAttribute(ROUTE_SCRIPT_MARKER, '1');
          js.onload = resolve;
          js.onerror = reject;
          document.body.appendChild(js);
        });
      } else {
        const code = (scriptEl.textContent || '').trim();
        if (code === '') continue;
        const inline = document.createElement('script');
        inline.textContent = code;
        inline.setAttribute(ROUTE_SCRIPT_MARKER, '1');
        document.body.appendChild(inline);
      }
    }
  };

  const navigateWithoutReload = async function (url, pushState = true) {
    if (navigationInProgress) return;
    if (!window.fetch || !window.DOMParser) {
      window.location.href = url.href;
      return;
    }

    navigationInProgress = true;
    setLoadingState(true);

    try {
      const response = await fetch(url.href, {
        method: 'GET',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      });

      if (!response.ok) throw new Error(`HTTP ${response.status}`);

      const html = await response.text();
      const parser = new DOMParser();
      const nextDoc = parser.parseFromString(html, 'text/html');
      const nextMain = nextDoc.querySelector(MAIN_CONTENT_SELECTOR);
      const currentMain = document.querySelector(MAIN_CONTENT_SELECTOR);

      if (!nextMain || !currentMain) throw new Error('No se encontró el contenedor principal.');

      currentMain.innerHTML = nextMain.innerHTML;
      document.title = nextDoc.title || document.title;

      resetDynamicAssets();
      syncDynamicCss(nextDoc);
      await runRouteScripts(nextDoc);

      updateActiveSidebarLinks(url);

      if (pushState) {
        window.history.pushState({ sisadminPartial: true }, '', url.href);
      }
      window.scrollTo({ top: 0, behavior: 'auto' });
    } catch (_err) {
      window.location.href = url.href;
      return;
    } finally {
      setLoadingState(false);
      navigationInProgress = false;
    }
  };

  document.addEventListener('click', function (event) {
    const link = event.target.closest('a[href]');
    if (!isSidebarNavLink(link)) return;

    if (link.hasAttribute('download') || link.target === '_blank') return;
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

    const href = link.getAttribute('href') || '';
    if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) return;
    if (link.classList.contains('sb-logout-btn') || link.id === 'logoutLink') return;

    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin) return;

    event.preventDefault();

    const offcanvasEl = document.getElementById('appSidebarOffcanvas');
    if (offcanvasEl && window.bootstrap?.Offcanvas) {
      bootstrap.Offcanvas.getOrCreateInstance(offcanvasEl).hide();
    }

    navigateWithoutReload(url, true);
  });

  window.addEventListener('popstate', function () {
    navigateWithoutReload(new URL(window.location.href), false);
  });


})();
