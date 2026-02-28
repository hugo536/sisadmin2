/**
 * public/assets/js/permisos.js
 * Catálogo de Permisos (tabla reutilizable)
 */

document.addEventListener('DOMContentLoaded', function () {
    'use strict';

    if (typeof ERPTable === 'undefined' || !ERPTable.createTableManager) return;

    ERPTable.createTableManager({
        tableSelector: '#permisosTable',
        rowsSelector: 'tbody tr[data-search]',
        searchInput: '#permisoSearch',
        rowsPerPage: 25,
        paginationControls: '#permisosPaginationControls',
        paginationInfo: '#permisosPaginationInfo',
        normalizeSearchText: (value) =>
            (value || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim(),
        infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} permisos`,
        emptyText: 'No se encontraron permisos que coincidan con la búsqueda.'
    }).init();
});
