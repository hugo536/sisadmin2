(function () {
    'use strict';

    function initDistribuidoresTable() {
        // Verificar si existe la tabla antes de intentar inicializar
        const table = document.getElementById('distribuidoresTable');
        if (!table) return;

        if (typeof ERPTable !== 'undefined' && ERPTable.createTableManager) {
            ERPTable.createTableManager({
                tableSelector: '#distribuidoresTable',
                searchInput: '#distribuidorSearch',
                paginationControls: '#distribuidoresPaginationControls',
                paginationInfo: '#distribuidoresPaginationInfo',
                rowsPerPage: 25,
                emptyText: 'No se encontraron distribuidores registrados.',
                infoText: ({ start, end, total }) => `Mostrando ${start}-${end} de ${total} distribuidores`
            }).init();
        } else {
            console.warn('ERPTable no está definido. La paginación JS no funcionará.');
        }
    }

    document.addEventListener('DOMContentLoaded', initDistribuidoresTable);

    // Namespace global por si se necesita extender funcionalidad
    window.TercerosDistribuidores = window.TercerosDistribuidores || {};
})();