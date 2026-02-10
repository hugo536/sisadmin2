(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    function toggleAlertaVencimiento(inputId, containerId, diasInputId) {
        const trigger = document.getElementById(inputId);
        const container = document.getElementById(containerId);
        const diasInput = document.getElementById(diasInputId);
        if (!trigger || !container || !diasInput) return;

        const applyVisibility = () => {
            const visible = trigger.checked;
            container.classList.toggle('d-none', !visible);
            diasInput.disabled = !visible;
            if (!visible) {
                diasInput.value = '';
            } else if (diasInput.value === '') {
                diasInput.value = '30';
            }
        };

        trigger.addEventListener('change', applyVisibility);
        applyVisibility();
    }

    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearItem');
        if (!modalCreate) return;

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearItem');
            if (form) form.reset();
            toggleAlertaVencimiento('newRequiereVencimiento', 'newDiasAlertaContainer', 'newDiasAlerta');
        });
    }

    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarItem');
        if (!modalEdit) return;

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;

            const fields = {
                editId: 'data-id',
                editSku: 'data-sku',
                editNombre: 'data-nombre',
                editDescripcion: 'data-descripcion',
                editTipo: 'data-tipo',
                editMarca: 'data-marca',
                editUnidad: 'data-unidad',
                editMoneda: 'data-moneda',
                editImpuesto: 'data-impuesto',
                editPrecio: 'data-precio',
                editStockMinimo: 'data-stock-minimo',
                editCosto: 'data-costo',
                editCategoria: 'data-categoria',
                editEstado: 'data-estado',
                editDiasAlerta: 'data-dias-alerta-vencimiento'
            };

            Object.keys(fields).forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = btn.getAttribute(fields[id]) || '';
            });

            const checks = {
                editControlaStock: 'data-controla-stock',
                editPermiteDecimales: 'data-permite-decimales',
                editRequiereLote: 'data-requiere-lote',
                editRequiereVencimiento: 'data-requiere-vencimiento'
            };

            Object.keys(checks).forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.checked = btn.getAttribute(checks[id]) === '1';
            });

            toggleAlertaVencimiento('editRequiereVencimiento', 'editDiasAlertaContainer', 'editDiasAlerta');
        });
    }

    

    function initCategoriasModal() {
        const form = document.getElementById('formGestionCategoria');
        if (!form) return;

        const accion = document.getElementById('categoriaAccion');
        const idInput = document.getElementById('categoriaId');
        const nombre = document.getElementById('categoriaNombre');
        const descripcion = document.getElementById('categoriaDescripcion');
        const estado = document.getElementById('categoriaEstado');
        const btnGuardar = document.getElementById('btnGuardarCategoria');
        const btnReset = document.getElementById('btnResetCategoria');

        const resetForm = () => {
            if (accion) accion.value = 'crear_categoria';
            if (idInput) idInput.value = '';
            if (nombre) nombre.value = '';
            if (descripcion) descripcion.value = '';
            if (estado) estado.value = '1';
            if (btnGuardar) btnGuardar.textContent = 'Guardar categoría';
        };

        document.querySelectorAll('.btn-editar-categoria').forEach((btn) => {
            btn.addEventListener('click', () => {
                if (accion) accion.value = 'editar_categoria';
                if (idInput) idInput.value = btn.getAttribute('data-id') || '';
                if (nombre) nombre.value = btn.getAttribute('data-nombre') || '';
                if (descripcion) descripcion.value = btn.getAttribute('data-descripcion') || '';
                if (estado) estado.value = btn.getAttribute('data-estado') || '1';
                if (btnGuardar) btnGuardar.textContent = 'Actualizar categoría';
                nombre?.focus();
            });
        });

        btnReset?.addEventListener('click', resetForm);

        document.getElementById('modalGestionCategorias')?.addEventListener('show.bs.modal', resetForm);
    }

    function initTableManager() {
        const searchInput = document.getElementById('itemSearch');
        const filtroTipo = document.getElementById('itemFiltroTipo');
        const filtroEstado = document.getElementById('itemFiltroEstado');
        const paginationControls = document.getElementById('itemsPaginationControls');
        const paginationInfo = document.getElementById('itemsPaginationInfo');

        const table = document.getElementById('itemsTable');
        if (!table) return;

        const allRows = Array.from(table.querySelectorAll('tbody tr'));

        const updateTable = function () {
            const texto = (searchInput?.value || '').toLowerCase().trim();
            const tipo = filtroTipo?.value || '';
            const estado = filtroEstado?.value || '';

            const visibleRows = allRows.filter((row) => {
                const rowSearch = row.getAttribute('data-search') || '';
                const rowTipo = row.getAttribute('data-tipo') || '';
                const rowEstado = row.getAttribute('data-estado') || '';
                return rowSearch.includes(texto) && (tipo === '' || rowTipo === tipo) && (estado === '' || rowEstado === estado);
            });

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE) || 1;
            if (currentPage > totalPages) currentPage = 1;

            allRows.forEach((row) => {
                row.style.display = 'none';
            });
            const start = (currentPage - 1) * ROWS_PER_PAGE;
            visibleRows.slice(start, start + ROWS_PER_PAGE).forEach((row) => {
                row.style.display = '';
            });

            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const end = Math.min(start + ROWS_PER_PAGE, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${end} de ${totalRows} ítems`;
                }
            }
            renderPagination(totalPages);
        };

        function renderPagination(totalPages) {
            if (!paginationControls) return;
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return;

            const addBtn = (label, page, active = false, disabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${active ? 'active' : ''} ${disabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#">${label}</a>`;
                li.onclick = (e) => {
                    e.preventDefault();
                    if (!active && !disabled) {
                        currentPage = page;
                        updateTable();
                    }
                };
                paginationControls.appendChild(li);
            };

            addBtn('«', currentPage - 1, false, currentPage === 1);
            for (let i = 1; i <= totalPages; i += 1) addBtn(i, i, i === currentPage);
            addBtn('»', currentPage + 1, false, currentPage === totalPages);
        }

        [searchInput, filtroTipo, filtroEstado].forEach((el) => el?.addEventListener('input', () => {
            currentPage = 1;
            updateTable();
        }));
        updateTable();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCreateModal();
        initEditModal();
        initTableManager();
        initCategoriasModal();
        toggleAlertaVencimiento('newRequiereVencimiento', 'newDiasAlertaContainer', 'newDiasAlerta');
        toggleAlertaVencimiento('editRequiereVencimiento', 'editDiasAlertaContainer', 'editDiasAlerta');
    });
})();
