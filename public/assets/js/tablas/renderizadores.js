/**
 * public/assets/js/tablas/renderizadores.js
 * =========================================================
 * Renderizadores reutilizables para tablas:
 * - Tooltips bootstrap
 * - Filtros + paginación (client-side)
 *
 * Uso:
 *   ERPTable.initTooltips();
 *   ERPTable.createTableManager({...}).init();
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
        rowsSelector: 'tbody tr',

        // Inputs
        searchInput: null,            // '#usuarioSearch'
        filters: [],                  // [{ el:'#filtroRol', attr:'data-rol' }, { el:'#filtroEstado', attr:'data-estado' }]

        searchAttr: 'data-search',    // atributo donde buscar texto
        rowsPerPage: 25,

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
        filterEls.push({ el, attr: f.attr });
      });
    }

    function applyFilters() {
      const texto = (searchEl ? searchEl.value : '').toLowerCase().trim();

      const actives = filterEls.map((f) => ({
        attr: f.attr,
        value: (f.el.value ?? '').toString()
      }));

      return allRows.filter((row) => {
        const haystack = ((row.getAttribute(cfg.searchAttr) || '') + '').toLowerCase();
        const coincideTexto = texto === '' || haystack.includes(texto);
        if (!coincideTexto) return false;

        for (const f of actives) {
          const rowVal = (row.getAttribute(f.attr) || '').toString();
          if (f.value !== '' && rowVal !== f.value) return false;
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

    function renderPaginationControls(totalPages) {
      paginationControlsEl.innerHTML = '';
      if (totalPages <= 1) return;

      const createItem = (text, page, isActive = false, isDisabled = false) => {
        const li = document.createElement('li');
        li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;

        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = String(text);

        a.addEventListener('click', (e) => {
          e.preventDefault();
          if (isDisabled || isActive) return;

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

      paginationControlsEl.appendChild(
        createItem('Anterior', currentPage - 1, false, currentPage === 1)
      );

      for (let i = 1; i <= totalPages; i++) {
        paginationControlsEl.appendChild(createItem(i, i, i === currentPage));
      }

      paginationControlsEl.appendChild(
        createItem('Siguiente', currentPage + 1, false, currentPage === totalPages)
      );
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
      }
    };

    return api;
  };

  w.ERPTable = ERPTable;

})(window);
