/**
 * public/assets/js/tablas/renderizadores.js
 * =========================================================
 * Renderizadores reutilizables para tablas:
 * - Tooltips bootstrap
 * - Filtros + paginación (client-side)
 *
 * Uso:
 * ERPTable.initTooltips();
 * ERPTable.createTableManager({...}).init();
 *
 * Requisitos:
 * - Bootstrap 5 JS cargado (window.bootstrap)
 * =========================================================
 */

(function (w) {
  'use strict';

  const ERPTable = {};

  // ---------------------------------------------------------
  // Tooltips
  // ---------------------------------------------------------
  ERPTable.initTooltips = function initTooltips(root = document) {
    if (!w.bootstrap || !bootstrap.Tooltip) return;

    const tooltipTriggerList = [].slice.call(
      root.querySelectorAll('[data-bs-toggle="tooltip"]')
    );

    tooltipTriggerList.forEach((el) => {
      if (bootstrap.Tooltip.getInstance(el)) return;
      new bootstrap.Tooltip(el);
    });
  };

  // ---------------------------------------------------------
  // Table Manager (Filtros + Paginación)
  // ---------------------------------------------------------
  ERPTable.createTableManager = function createTableManager(config) {
    const cfg = Object.assign(
      {
        tableSelector: null,          // ej: '#usuariosTable'
        rowsSelector: 'tbody tr:not(.empty-msg-row)',

        // Inputs
        searchInput: null,            // '#usuarioSearch'
        filters: [],                  // [{ el:'#filtroRol', attr:'data-rol', match:'equals|includes' }, { el:'#filtroEstado', attr:'data-estado' }]

        searchAttr: 'data-search',    // atributo donde buscar texto
        normalizeSearchText: (value) => (value || '').toString().toLowerCase().trim(),
        rowsPerPage: 25,              // REGLA GENERAL: 25 filas por defecto

        paginationControls: null,     // '#paginationControls'
        paginationInfo: null,         // '#paginationInfo'

        // Texto UI
        infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total}`,
        emptyText: 'Sin resultados',

        // Mejoras UX
        refreshOnUpdate: false,       // si el tbody se re-renderiza por JS, ponlo en true
        scrollToTopOnPageChange: true,
        scrollTarget: null,           // selector o elemento (si null usa tableSelector)

        onUpdate: null
      },
      config || {}
    );

    let currentPage = 1;

    let tableEl = null;
    let allRows = [];

    let searchEl = null;
    let paginationControlsEl = null;
    let paginationInfoEl = null;

    const filterEls = [];

    function q(selectorOrEl) {
      if (!selectorOrEl) return null;
      if (typeof selectorOrEl === 'string') return document.querySelector(selectorOrEl);
      return selectorOrEl;
    }

    function readRows() {
      tableEl = q(cfg.tableSelector);
      if (!tableEl) return [];
      return Array.from(tableEl.querySelectorAll(cfg.rowsSelector));
    }

    function buildFilterRefs() {
      filterEls.length = 0;
      (cfg.filters || []).forEach((f) => {
        const el = q(f.el);
        if (!el) return;
        filterEls.push({ el, attr: f.attr, match: f.match || 'equals' });
      });
    }

    function applyFilters() {
      const normalizar = typeof cfg.normalizeSearchText === 'function'
        ? cfg.normalizeSearchText
        : (value) => (value || '').toString().toLowerCase().trim();

      const texto = normalizar(searchEl ? searchEl.value : '');

      const actives = filterEls.map((f) => ({
        attr: f.attr,
        value: (f.el.value ?? '').toString()
      }));

      return allRows.filter((row) => {
        const haystack = normalizar((row.getAttribute(cfg.searchAttr) || '') + '');
        const coincideTexto = texto === '' || haystack.includes(texto);
        if (!coincideTexto) return false;

        for (const f of actives) {
          const rowVal = (row.getAttribute(f.attr) || '').toString();
          if (f.value === '') continue;

          if (f.match === 'includes') {
            if (!rowVal.includes(f.value)) return false;
            continue;
          }

          if (rowVal !== f.value) return false;
        }
        return true;
      });
    }

    function updatePaginationUI(startIndex, endIndex, totalRows, totalPages) {
      if (paginationInfoEl) {
        if (totalRows === 0) {
          paginationInfoEl.textContent = cfg.emptyText;
        } else {
          const startHuman = startIndex + 1;
          const endHuman = Math.min(endIndex, totalRows);
          paginationInfoEl.textContent = cfg.infoText({
            start: startHuman,
            end: endHuman,
            total: totalRows
          });
        }
      }
      if (paginationControlsEl) renderPaginationControls(totalPages);
    }

    // ================================================================
    // AQUÍ ESTÁ LA MAGIA DEL DISEÑO DE PAGINACIÓN
    // ================================================================
    function renderPaginationControls(totalPages) {
      paginationControlsEl.innerHTML = '';
      
      // Aseguramos que no tenga tamaño pequeño (DataTables usa el tamaño normal)
      if (paginationControlsEl.classList.contains('pagination-sm')) {
        paginationControlsEl.classList.remove('pagination-sm');
      }

      // ELIMINAMOS EL "if (totalPages <= 1) return;" 
      // Ahora siempre dibujaremos la botonera, incluso si hay solo 1 página.
      const safeTotalPages = totalPages < 1 ? 1 : totalPages;

      const createItem = (text, page, isActive = false, isDisabled = false) => {
        const li = document.createElement('li');
        li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;

        const a = document.createElement('a');
        a.className = 'page-link'; // Esto da el color nativo azul de Bootstrap
        a.href = '#';
        a.textContent = String(text);

        a.addEventListener('click', (e) => {
          e.preventDefault();
          if (isDisabled || isActive || page == null) return;

          currentPage = page;
          api.update();

          if (cfg.scrollToTopOnPageChange) {
            const target = q(cfg.scrollTarget) || q(cfg.tableSelector) || tableEl;
            if (target && target.scrollIntoView) {
              target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
          }
        });

        li.appendChild(a);
        return li;
      };

      const createDots = () => {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        const span = document.createElement('span');
        span.className = 'page-link';
        span.textContent = '...';
        li.appendChild(span);
        return li;
      };

      const buildPages = () => {
        const pages = new Set([1, safeTotalPages]);
        const siblingCount = 1;
        for (let i = currentPage - siblingCount; i <= currentPage + siblingCount; i += 1) {
          if (i > 1 && i < safeTotalPages) pages.add(i);
        }
        const ordered = Array.from(pages).sort((a, b) => a - b);
        const tokens = [];
        ordered.forEach((page, idx) => {
          if (idx > 0 && page - ordered[idx - 1] > 1) tokens.push('dots');
          tokens.push(page);
        });
        return tokens;
      };

      // 1. Dibujamos botón Anterior
      paginationControlsEl.appendChild(createItem('Anterior', currentPage - 1, false, currentPage === 1));

      // 2. Dibujamos los números (1, 2, 3...)
      buildPages().forEach((token) => {
        if (token === 'dots') paginationControlsEl.appendChild(createDots());
        else paginationControlsEl.appendChild(createItem(token, token, token === currentPage));
      });

      // 3. Dibujamos botón Siguiente
      paginationControlsEl.appendChild(createItem('Siguiente', currentPage + 1, false, currentPage === safeTotalPages));
    }

    function showRows(visibleRows) {
      allRows.forEach((r) => (r.style.display = 'none'));

      const totalRows = visibleRows.length;
      const totalPages = totalRows === 0 ? 0 : Math.ceil(totalRows / cfg.rowsPerPage);

      if (totalRows === 0) {
        currentPage = 1;
        updatePaginationUI(0, 0, 0, 0);
        if (typeof cfg.onUpdate === 'function') cfg.onUpdate({ currentPage, totalRows, totalPages });
        return;
      }

      if (currentPage > totalPages) currentPage = 1;
      if (currentPage < 1) currentPage = 1;

      const start = (currentPage - 1) * cfg.rowsPerPage;
      const end = start + cfg.rowsPerPage;

      visibleRows.slice(start, end).forEach((r) => (r.style.display = ''));

      updatePaginationUI(start, end, totalRows, totalPages);

      if (typeof cfg.onUpdate === 'function') cfg.onUpdate({ currentPage, totalRows, totalPages });
    }

    function onFilterChange() {
      currentPage = 1;
      api.update();
    }

    function bindEvents() {
      if (searchEl) searchEl.addEventListener('input', onFilterChange);
      filterEls.forEach((f) => f.el.addEventListener('change', onFilterChange));
    }

    const api = {
      init() {
        searchEl = q(cfg.searchInput);
        paginationControlsEl = q(cfg.paginationControls);
        paginationInfoEl = q(cfg.paginationInfo);

        allRows = readRows();
        if (!allRows.length) return api;

        buildFilterRefs();
        bindEvents();
        api.update();
        return api;
      },

      update() {
        if (cfg.refreshOnUpdate) {
          allRows = readRows();
          if (!allRows.length) {
            updatePaginationUI(0, 0, 0, 0);
            return;
          }
        }

        if (!allRows.length) return;

        const visible = applyFilters();
        showRows(visible);
      },

      setPage(page) {
        const p = parseInt(page, 10);
        if (Number.isNaN(p) || p < 1) return;
        currentPage = p;
        api.update();
      },

      refresh() {
        allRows = readRows();
        api.update();
      },

      getState() {
        return { currentPage, rowsPerPage: cfg.rowsPerPage, totalRows: allRows.length };
      },

      // --- NUEVOS PODERES GLOBALES: Spinner de carga ---
      showLoading() {
        // Buscamos el contenedor .table-responsive para ponerle la "cortina" encima
        const container = tableEl.closest('.table-responsive') || tableEl.parentElement;
        if (!container) return;
        
        // Evitamos crear dos spinners al mismo tiempo
        if (container.querySelector('.erp-table-loader')) return;

        container.style.position = 'relative'; // Necesario para que el absolute funcione
        
        const overlay = document.createElement('div');
        overlay.className = 'erp-table-loader d-flex justify-content-center align-items-center position-absolute w-100 h-100 start-0 top-0 rounded';
        overlay.style.backgroundColor = 'rgba(255, 255, 255, 0.6)';
        overlay.style.zIndex = '10';
        
        overlay.innerHTML = `
            <div class="spinner-border text-primary shadow-sm" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Procesando...</span>
            </div>
        `;
        
        container.appendChild(overlay);
      },

      hideLoading() {
        const container = tableEl.closest('.table-responsive') || tableEl.parentElement;
        if (!container) return;
        
        const overlay = container.querySelector('.erp-table-loader');
        if (overlay) {
            overlay.remove(); // Destruimos el spinner
        }
      }
      // -------------------------------------------------
    };

    return api;
  };

  // ---------------------------------------------------------
  // Auto-init por data attributes
  // ---------------------------------------------------------
  ERPTable.autoInitFromDataset = function autoInitFromDataset(root = document) {
    const tables = Array.from(root.querySelectorAll('[data-erp-table="true"]'));
    const managers = [];

    tables.forEach((tableEl) => {
      // --- MEJORA GLOBAL: Autodescubrimiento de Paginación ---
      let autoPagControls = null;
      let autoPagInfo = null;

      if (tableEl.id) {
          // Si el ID es "planillasTable", el prefijo será "planillas"
          const prefix = tableEl.id.replace('Table', ''); 
          
          // Verificamos si existen en el HTML antes de asignarlos
          if (document.getElementById(`${prefix}PaginationControls`)) {
              autoPagControls = `#${prefix}PaginationControls`;
          }
          if (document.getElementById(`${prefix}PaginationInfo`)) {
              autoPagInfo = `#${prefix}PaginationInfo`;
          }
      }
      // -------------------------------------------------------

      const cfg = {
        tableSelector: tableEl,
        rowsSelector: tableEl.dataset.rowsSelector || 'tbody tr:not(.empty-msg-row)',
        searchInput: tableEl.dataset.searchInput || null,
        
        // Ahora intenta usar el atributo data-, y si no existe, usa el autodescubierto
        paginationControls: tableEl.dataset.paginationControls || autoPagControls,
        paginationInfo: tableEl.dataset.paginationInfo || autoPagInfo,
        
        searchAttr: tableEl.dataset.searchAttr || 'data-search',
        emptyText: tableEl.dataset.emptyText || 'Sin resultados'
      };

      if (tableEl.dataset.rowsPerPage) {
        const parsed = parseInt(tableEl.dataset.rowsPerPage, 10);
        if (!Number.isNaN(parsed) && parsed > 0) cfg.rowsPerPage = parsed;
      }

      if (tableEl.dataset.searchNormalize === 'accent') {
        cfg.normalizeSearchText = (value) =>
          (value || '')
            .toString()
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();
      }

      if (tableEl.dataset.infoText === 'results') {
        cfg.infoText = ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} resultados`;
        cfg.emptyText = 'Mostrando 0-0 de 0 resultados';
      }

      if (tableEl.dataset.refreshOnUpdate === 'true') {
        cfg.refreshOnUpdate = true;
      }

      if (tableEl.dataset.infoTextTemplate) {
        cfg.infoText = ({ start, end, total }) =>
          tableEl.dataset.infoTextTemplate
            .replace('{start}', String(start))
            .replace('{end}', String(end))
            .replace('{total}', String(total));
      }

      if (tableEl.dataset.erpFilters) {
        try {
          const parsedFilters = JSON.parse(tableEl.dataset.erpFilters);
          if (Array.isArray(parsedFilters)) cfg.filters = parsedFilters;
        } catch (error) {
          console.error(`Error parseando data-erp-filters en tabla ${tableEl.id || '(sin id)'}`, error);
        }
      }

      if (tableEl.dataset.erpNestedRows === 'true') {
        cfg.onUpdate = function onUpdateNestedRows() {
          tableEl.querySelectorAll('.collapse-faltantes').forEach((childRow) => {
            const searchAttr = childRow.getAttribute('data-search') || '';
            const parentRow = tableEl.querySelector(`tr.op-main-row[data-search="${searchAttr}"]`) ||
              tableEl.querySelector(`tr:not(.collapse-faltantes)[data-search="${searchAttr}"]`);

            childRow.style.display = parentRow && parentRow.style.display !== 'none' ? '' : 'none';
          });
        };
      }

      const manager = ERPTable.createTableManager(cfg).init();

      if (tableEl.dataset.managerGlobal) {
        w[tableEl.dataset.managerGlobal] = manager;
      }

      managers.push(manager);
    });

    return managers;
  };

  w.ERPTable = ERPTable;

  document.addEventListener('DOMContentLoaded', function () {
    ERPTable.initTooltips();
    ERPTable.autoInitFromDataset();
  });

})(window);
