(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    // --- 0. Gestión del Modal de CREACIÓN (Nuevo) ---
    function initCreateModal() {
        const modalCreate = document.getElementById('modalCrearItem');
        if (!modalCreate) return;

        modalCreate.addEventListener('show.bs.modal', function () {
            const form = document.getElementById('formCrearItem');
            if (form) form.reset();
        });
    }

    // --- 1. Gestión del Modal de EDICIÓN ---
    function initEditModal() {
        const modalEdit = document.getElementById('modalEditarItem');
        if (!modalEdit) return;

        modalEdit.addEventListener('show.bs.modal', function (event) {
            const btn = event.relatedTarget;
            if (!btn) return;

            const fields = {
                'editId': 'data-id',
                'editSku': 'data-sku',
                'editNombre': 'data-nombre',
                'editDescripcion': 'data-descripcion',
                'editTipo': 'data-tipo',
                'editMarca': 'data-marca',
                'editUnidad': 'data-unidad',
                'editMoneda': 'data-moneda',
                'editImpuesto': 'data-impuesto',
                'editPrecio': 'data-precio',
                'editStockMinimo': 'data-stock-minimo',
                'editCosto': 'data-costo',
                'editEstado': 'data-estado'
            };

            for (let id in fields) {
                const el = document.getElementById(id);
                if (el) el.value = btn.getAttribute(fields[id]) || '';
            }

            // Mapeo de Switches
            const checks = {
                'editControlaStock': 'data-controla-stock',
                'editPermiteDecimales': 'data-permite-decimales',
                'editRequiereLote': 'data-requiere-lote',
                'editRequiereVencimiento': 'data-requiere-vencimiento'
            };

            for (let id in checks) {
                const el = document.getElementById(id);
                if (el) {
                    el.checked = btn.getAttribute(checks[id]) === '1';
                }
            }
        });
    }

    // --- 2. Gestión de Tabla (Búsqueda, Filtros, Paginación) ---
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

            const visibleRows = allRows.filter(row => {
                const rowSearch = row.getAttribute('data-search') || '';
                const rowTipo = row.getAttribute('data-tipo') || '';
                const rowEstado = row.getAttribute('data-estado') || '';
                return rowSearch.includes(texto) && (tipo === '' || rowTipo === tipo) && (estado === '' || rowEstado === estado);
            });

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE) || 1;
            if (currentPage > totalPages) currentPage = 1;

            allRows.forEach(row => row.style.display = 'none');
            const start = (currentPage - 1) * ROWS_PER_PAGE;
            visibleRows.slice(start, start + ROWS_PER_PAGE).forEach(row => row.style.display = '');

            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = "Sin resultados";
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
                li.onclick = (e) => { e.preventDefault(); if (!active && !disabled) { currentPage = page; updateTable(); }};
                paginationControls.appendChild(li);
            };

            addBtn('«', currentPage - 1, false, currentPage === 1);
            for (let i = 1; i <= totalPages; i++) addBtn(i, i, i === currentPage);
            addBtn('»', currentPage + 1, false, currentPage === totalPages);
        }

        [searchInput, filtroTipo, filtroEstado].forEach(el => el?.addEventListener('input', () => { currentPage = 1; updateTable(); }));
        updateTable();
    }

    document.addEventListener('DOMContentLoaded', () => {
        initCreateModal(); 
        initEditModal(); 
        initTableManager(); 
    });
})();