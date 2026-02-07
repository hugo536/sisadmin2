/**
 * public/assets/js/rol.js
 * =========================================================
 * Roles UI:
 * - Tooltips (sin duplicar instancias)
 * - Modales crear/editar
 * - Tabla: filtros + paginación (considera filas "pares" como rol y "siguiente" como detalle)
 * - Confirmaciones SweetAlert2
 * =========================================================
 */

(function () {
  'use strict';

  const ROWS_PER_PAGE = 5;
  let currentPage = 1;

  // -----------------------------
  // 1) Tooltips (safe)
  // -----------------------------
  function initTooltips(root = document) {
    if (!window.bootstrap || !bootstrap.Tooltip) return;

    root.querySelectorAll('[data-bs-toggle="tooltip"]').forEach((el) => {
      if (bootstrap.Tooltip.getInstance(el)) return; // evita duplicado
      new bootstrap.Tooltip(el);
    });
  }

  // -----------------------------
  // 2) Modal CREAR: reset
  // -----------------------------
  function initCreateModal() {
    const modalCreate = document.getElementById('modalCrearRol');
    if (!modalCreate) return;

    modalCreate.addEventListener('show.bs.modal', function () {
      const form = document.getElementById('formCrearRol');
      if (form) form.reset();
    });
  }

  // -----------------------------
  // 3) Modal EDITAR: delegación
  // -----------------------------
  function initEditModal() {
    document.addEventListener('click', function (e) {
      const btn = e.target.closest('.btn-editar-rol');
      if (!btn) return;

      const id = btn.dataset.id || '';
      const nombre = btn.dataset.nombre || '';
      const estado = btn.dataset.estado || '1';

      const idEl = document.getElementById('editRolId');
      const nomEl = document.getElementById('editRolNombre');
      const estEl = document.getElementById('editRolEstado');
      const modalEl = document.getElementById('modalEditarRol');

      if (!idEl || !nomEl || !estEl || !modalEl) return;

      idEl.value = id;
      nomEl.value = nombre;
      estEl.value = estado;

      new bootstrap.Modal(modalEl).show();
    });
  }

  // -----------------------------
  // Helper: cerrar acordeones abiertos
  // -----------------------------
  function closeOpenAccordions() {
    if (!window.bootstrap || !bootstrap.Collapse) return;

    document.querySelectorAll('#rolesTable .accordion-collapse.show').forEach((el) => {
      const inst = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
      inst.hide();
    });
  }

  // -----------------------------
  // 4) Tabla: filtros + paginación (rol + detalle)
  // -----------------------------
  function initTable() {
    const table = document.getElementById('rolesTable');
    if (!table) return;

    const search = document.getElementById('rolesSearch');
    const estado = document.getElementById('filtroEstadoRol');

    // Filas del tbody: pares=rol, impar=sus permisos/accordion
    const allBodyRows = Array.from(table.querySelectorAll('tbody tr'));
    const roleRows = allBodyRows.filter((_, idx) => idx % 2 === 0);

    const info = document.getElementById('rolesPaginationInfo');
    const pager = document.getElementById('rolesPaginationControls');

    function render() {
      const txt = (search?.value || '').toLowerCase().trim();
      const st = (estado?.value || '').toString();

      // cerrar acordeones antes de ocultar/mostrar para que no "salte"
      closeOpenAccordions();

      const filtered = roleRows.filter((r) => {
        const ds = (r.getAttribute('data-search') || '').toLowerCase();
        const de = (r.getAttribute('data-estado') || '').toString();
        const okTxt = txt === '' || ds.includes(txt);
        const okSt = st === '' || de === st;
        return okTxt && okSt;
      });

      // ocultar todas (rol + detalle)
      allBodyRows.forEach((r) => (r.style.display = 'none'));

      const total = filtered.length;
      const pages = Math.max(1, Math.ceil(total / ROWS_PER_PAGE));
      if (currentPage > pages) currentPage = 1;
      if (currentPage < 1) currentPage = 1;

      const start = (currentPage - 1) * ROWS_PER_PAGE;
      const end = start + ROWS_PER_PAGE;

      // mostrar paginadas (rol + detalle siguiente)
      filtered.slice(start, end).forEach((r) => {
        r.style.display = '';
        if (r.nextElementSibling) r.nextElementSibling.style.display = '';
      });

      // info
      if (info) {
        info.textContent =
          total === 0
            ? 'Sin resultados'
            : `Mostrando ${start + 1}-${Math.min(end, total)} de ${total} roles`;
      }

      // pager
      if (!pager) return;
      pager.innerHTML = '';
      if (pages <= 1) return;

      const mk = (label, page, disabled, active) => {
        const li = document.createElement('li');
        li.className = `page-item ${disabled ? 'disabled' : ''} ${active ? 'active' : ''}`;
        const a = document.createElement('a');
        a.className = 'page-link';
        a.href = '#';
        a.textContent = label;
        a.addEventListener('click', (ev) => {
          ev.preventDefault();
          if (disabled || active) return;
          currentPage = page;
          render();
        });
        li.appendChild(a);
        return li;
      };

      pager.appendChild(mk('«', currentPage - 1, currentPage === 1, false));
      for (let i = 1; i <= pages; i++) pager.appendChild(mk(String(i), i, false, i === currentPage));
      pager.appendChild(mk('»', currentPage + 1, currentPage === pages, false));
    }

    search?.addEventListener('input', () => {
      currentPage = 1;
      render();
    });
    estado?.addEventListener('change', () => {
      currentPage = 1;
      render();
    });

    render();
  }

  // -----------------------------
  // 5) Confirmaciones SweetAlert2
  // -----------------------------
  function bindConfirmations() {
    if (!window.Swal) return;

    const confirmForm = (selector, opts) => {
      document.querySelectorAll(selector).forEach((form) => {
        form.addEventListener('submit', function (e) {
          e.preventDefault();
          Swal.fire({
            icon: opts.icon || 'question',
            title: opts.title || 'Confirmar',
            text: opts.text || '¿Seguro?',
            showCancelButton: true,
            confirmButtonText: opts.confirmText || 'Sí',
            cancelButtonText: 'Cancelar'
          }).then((r) => r.isConfirmed && form.submit());
        });
      });
    };

    confirmForm('.toggle-form', {
      title: 'Cambiar estado',
      text: '¿Deseas cambiar el estado de este rol?',
      icon: 'question',
      confirmText: 'Sí, cambiar'
    });

    confirmForm('.delete-form', {
      title: 'Eliminar rol',
      text: '¿Deseas eliminar este rol?',
      icon: 'warning',
      confirmText: 'Sí, eliminar'
    });

    // OJO: aquí mejor question (success solo después del backend)
    confirmForm('.permiso-form', {
      title: 'Guardar permisos',
      text: '¿Deseas guardar la configuración de permisos?',
      icon: 'question',
      confirmText: 'Guardar'
    });
  }

  // -----------------------------
  // INIT
  // -----------------------------
  document.addEventListener('DOMContentLoaded', function () {
    initTooltips();
    initCreateModal();
    initEditModal();
    initTable();
    bindConfirmations();
  });
})();
