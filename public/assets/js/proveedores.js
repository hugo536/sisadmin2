(function () {
    const ROWS_PER_PAGE = 5;
    let currentPage = 1;

    function initTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }

    function initTableManager() {
        const searchInput = document.getElementById('proveedorSearch');
        const filtroDoc = document.getElementById('proveedorFiltroDoc');
        const filtroEstado = document.getElementById('proveedorFiltroEstado');
        const paginationControls = document.getElementById('proveedoresPaginationControls');
        const paginationInfo = document.getElementById('proveedoresPaginationInfo');
        const allRows = Array.from(document.querySelectorAll('#proveedoresTable tbody tr'));

        if (allRows.length === 0) {
            if (paginationInfo) paginationInfo.textContent = 'Sin resultados';
            return;
        }

        const updateTable = function () {
            const texto = (searchInput ? searchInput.value : '').toLowerCase().trim();
            const tipoSeleccionado = (filtroDoc ? filtroDoc.value : '');
            const estadoSeleccionado = (filtroEstado ? filtroEstado.value : '');

            const visibleRows = allRows.filter(row => {
                const dataSearch = row.getAttribute('data-search') || '';
                const dataTipo = row.getAttribute('data-tipo') || '';
                const dataEstado = row.getAttribute('data-estado') || '';

                const coincideTexto = dataSearch.includes(texto);
                const coincideTipo = tipoSeleccionado === '' || dataTipo === tipoSeleccionado;
                const coincideEstado = estadoSeleccionado === '' || dataEstado === estadoSeleccionado;

                return coincideTexto && coincideTipo && coincideEstado;
            });

            const totalRows = visibleRows.length;
            const totalPages = Math.ceil(totalRows / ROWS_PER_PAGE) || 1;

            if (currentPage > totalPages) currentPage = 1;
            if (currentPage < 1) currentPage = 1;

            allRows.forEach(row => row.style.display = 'none');

            const start = (currentPage - 1) * ROWS_PER_PAGE;
            const end = start + ROWS_PER_PAGE;
            visibleRows.slice(start, end).forEach(row => row.style.display = '');

            updatePaginationUI(start, end, totalRows, totalPages);
        };

        function updatePaginationUI(start, end, totalRows, totalPages) {
            if (paginationInfo) {
                if (totalRows === 0) {
                    paginationInfo.textContent = 'Sin resultados';
                } else {
                    const realEnd = Math.min(end, totalRows);
                    paginationInfo.textContent = `Mostrando ${start + 1}-${realEnd} de ${totalRows} proveedores`;
                }
            }

            if (paginationControls) {
                renderPaginationControls(totalPages);
            }
        }

        function renderPaginationControls(totalPages) {
            paginationControls.innerHTML = '';
            if (totalPages <= 1) return;

            const createItem = (text, page, isActive = false, isDisabled = false) => {
                const li = document.createElement('li');
                li.className = `page-item ${isActive ? 'active' : ''} ${isDisabled ? 'disabled' : ''}`;
                li.innerHTML = `<a class="page-link" href="#" onclick="return false;">${text}</a>`;
                li.onclick = () => {
                    if (!isActive && !isDisabled) {
                        currentPage = page;
                        updateTable();
                    }
                };
                return li;
            };

            paginationControls.appendChild(createItem('Anterior', currentPage - 1, false, currentPage === 1));

            for (let i = 1; i <= totalPages; i++) {
                paginationControls.appendChild(createItem(i, i, i === currentPage));
            }

            paginationControls.appendChild(createItem('Siguiente', currentPage + 1, false, currentPage === totalPages));
        }

        const onFilterChange = () => {
            currentPage = 1;
            updateTable();
        };

        if (searchInput) searchInput.addEventListener('input', onFilterChange);
        if (filtroDoc) filtroDoc.addEventListener('change', onFilterChange);
        if (filtroEstado) filtroEstado.addEventListener('change', onFilterChange);

        updateTable();
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTooltips();
        initTableManager();
    });
})();
