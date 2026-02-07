/**
 * public/assets/js/permisos.js
 * Gestión del Catálogo de Permisos (Búsqueda y Paginación Cliente)
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    // Configuración
    const TABLE_ID = 'permisosTable';
    const SEARCH_INPUT_ID = 'permisoSearch';
    const INFO_ID = 'permisosPaginationInfo';
    const CONTROLS_ID = 'permisosPaginationControls';
    const ROWS_PER_PAGE = 10; // Muestra más filas por página en catálogo

    // Estado
    let currentPage = 1;

    // Elementos DOM
    const table = document.getElementById(TABLE_ID);
    const searchInput = document.getElementById(SEARCH_INPUT_ID);
    const paginationInfo = document.getElementById(INFO_ID);
    const paginationControls = document.getElementById(CONTROLS_ID);

    if (!table || !searchInput) return;

    // Obtenemos todas las filas del cuerpo de la tabla
    // Excluimos filas de mensaje "Sin resultados" si las hubiera hardcodeadas
    const allRows = Array.from(table.querySelectorAll('tbody tr')).filter(r => r.dataset.search);

    /**
     * Lógica Principal: Filtra y Pagina
     */
    function renderTable() {
        const searchText = (searchInput.value || '').toLowerCase().trim();

        // 1. Filtrar
        const filteredRows = allRows.filter(row => {
            const searchData = (row.dataset.search || '').toLowerCase();
            return !searchText || searchData.includes(searchText);
        });

        // 2. Calcular Paginación
        const totalItems = filteredRows.length;
        const totalPages = Math.ceil(totalItems / ROWS_PER_PAGE) || 1;

        if (currentPage > totalPages) currentPage = 1;

        const startIndex = (currentPage - 1) * ROWS_PER_PAGE;
        const endIndex = startIndex + ROWS_PER_PAGE;
        
        // 3. Renderizar DOM
        
        // Ocultar todas primero
        allRows.forEach(row => row.style.display = 'none');

        // Mostrar solo las de la página actual
        filteredRows.slice(startIndex, endIndex).forEach(row => {
            row.style.display = '';
        });

        // 4. Actualizar Texto Info
        if (paginationInfo) {
            if (totalItems === 0) {
                paginationInfo.textContent = 'No se encontraron permisos coinciden con la búsqueda.';
            } else {
                paginationInfo.textContent = `Mostrando ${startIndex + 1} a ${Math.min(endIndex, totalItems)} de ${totalItems} permisos`;
            }
        }

        // 5. Renderizar Controles Paginación
        renderControls(totalPages);
    }

    /**
     * Genera los botones de paginación
     */
    function renderControls(totalPages) {
        if (!paginationControls) return;
        paginationControls.innerHTML = '';

        if (totalPages <= 1) return;

        // Botón Anterior
        const prevLi = document.createElement('li');
        prevLi.className = `page-item ${currentPage === 1 ? 'disabled' : ''}`;
        prevLi.innerHTML = `<a class="page-link" href="#" aria-label="Anterior">&laquo;</a>`;
        prevLi.onclick = (e) => { e.preventDefault(); if (currentPage > 1) { currentPage--; renderTable(); } };
        paginationControls.appendChild(prevLi);

        // Números de página (Lógica simple: mostrar todos si son pocos, o rango si son muchos)
        // Para simplificar UX en catálogos medianos, mostramos un máximo de 5 botones
        let startPage = Math.max(1, currentPage - 2);
        let endPage = Math.min(totalPages, startPage + 4);
        
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const li = document.createElement('li');
            li.className = `page-item ${i === currentPage ? 'active' : ''}`;
            li.innerHTML = `<a class="page-link" href="#">${i}</a>`;
            li.onclick = (e) => { e.preventDefault(); currentPage = i; renderTable(); };
            paginationControls.appendChild(li);
        }

        // Botón Siguiente
        const nextLi = document.createElement('li');
        nextLi.className = `page-item ${currentPage === totalPages ? 'disabled' : ''}`;
        nextLi.innerHTML = `<a class="page-link" href="#" aria-label="Siguiente">&raquo;</a>`;
        nextLi.onclick = (e) => { e.preventDefault(); if (currentPage < totalPages) { currentPage++; renderTable(); } };
        paginationControls.appendChild(nextLi);
    }

    // Event Listeners
    searchInput.addEventListener('input', () => {
        currentPage = 1; // Reset a pág 1 al buscar
        renderTable();
    });

    // Inicializar
    renderTable();
});