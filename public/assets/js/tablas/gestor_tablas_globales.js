(function (w) {
  'use strict';

  function crearContenedorResponsive(tableEl) {
    const parent = tableEl.parentElement;
    if (parent && parent.classList.contains('table-responsive')) {
      parent.classList.add('tabla-global-scroll-wrapper');
      return parent;
    }

    const wrapper = document.createElement('div');
    wrapper.className = 'table-responsive tabla-global-scroll-wrapper';
    parent.insertBefore(wrapper, tableEl);
    wrapper.appendChild(tableEl);
    return wrapper;
  }

  function crearZonaPaginacion(wrapperEl, tableEl) {
    const footer = document.createElement('div');
    footer.className = 'd-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mt-3 px-1 tabla-global-pagination';

    const info = document.createElement('div');
    info.className = 'small text-muted';
    info.id = `${tableEl.id || 'tabla'}AutoPaginationInfo`;

    const nav = document.createElement('nav');
    nav.setAttribute('aria-label', 'Paginación de tabla');

    const ul = document.createElement('ul');
    ul.className = 'pagination pagination-sm mb-0';
    ul.id = `${tableEl.id || 'tabla'}AutoPaginationControls`;

    nav.appendChild(ul);
    footer.appendChild(info);
    footer.appendChild(nav);

    wrapperEl.insertAdjacentElement('afterend', footer);

    return { infoEl: `#${info.id}`, controlsEl: `#${ul.id}` };
  }

  function buscarInputBusqueda(tableEl) {
    const card = tableEl.closest('.card') || tableEl.closest('.container-fluid') || document;
    return card.querySelector('input[type="search"]');
  }

  function esTablaPrincipal(tableEl) {
    if (!tableEl.classList.contains('table-pro')) return false;
    if (tableEl.classList.contains('table-sm')) return false;
    if (tableEl.closest('.modal')) return false;
    const zona = tableEl.closest('.card') || tableEl.parentElement || document;
    if (zona.querySelector('ul[id$="PaginationControls"]')) return false;
    return true;
  }

  function initAutoTables() {
    if (!w.ERPTable || !w.ERPTable.createTableManager) return;

    const tablaIdsExcluidas = new Set([
      'tablaInventarioStock',
      'usuariosTable',
      'distribuidoresTable'
    ]);

    const tablas = Array.from(document.querySelectorAll('table.table'))
      .filter((tableEl) => esTablaPrincipal(tableEl))
      .filter((tableEl) => !tablaIdsExcluidas.has(tableEl.id || ''));

    if (!tablas.length) return;

    w.ERPTableAutoManagers = w.ERPTableAutoManagers || [];

    tablas.forEach((tableEl, index) => {
      if (!tableEl.id) tableEl.id = `tablaAutoGenerica${index + 1}`;

      const thead = tableEl.querySelector('thead');
      if (thead) thead.classList.add('tabla-global-sticky-thead');

      const wrapper = crearContenedorResponsive(tableEl);
      const { infoEl, controlsEl } = crearZonaPaginacion(wrapper, tableEl);
      const searchInput = buscarInputBusqueda(tableEl);

      const manager = w.ERPTable.createTableManager({
        tableSelector: `#${tableEl.id}`,
        rowsSelector: 'tbody tr',
        searchInput: searchInput ? searchInput : null,
        searchAttr: 'data-search',
        rowsPerPage: 20,
        paginationControls: controlsEl,
        paginationInfo: infoEl,
        infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} resultados`,
        emptyText: 'Mostrando 0-0 de 0 resultados',
        scrollToTopOnPageChange: false
      }).init();

      w.ERPTableAutoManagers.push(manager);
    });
  }

  document.addEventListener('DOMContentLoaded', initAutoTables);
})(window);
