(function () {
    'use strict';

    function initDistribuidoresTable() {
        const table = document.getElementById('distribuidoresTable');
        if (!table) return;

        if (typeof ERPTable !== 'undefined' && ERPTable.createTableManager) {
            ERPTable.createTableManager({
                tableSelector: '#distribuidoresTable',
                searchInput: '#distribuidorSearch',
                paginationControls: '#distribuidoresPaginationControls',
                paginationInfo: '#distribuidoresPaginationInfo',
                rowsPerPage: 10,
                emptyText: 'No se encontraron distribuidores registrados.',
                infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} distribuidores`
            }).init();
        }
    }

    document.addEventListener('DOMContentLoaded', initDistribuidoresTable);

    window.TercerosDistribuidores = window.TercerosDistribuidores || {};
})();
