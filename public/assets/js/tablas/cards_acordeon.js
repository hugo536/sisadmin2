/**
 * public/assets/js/tablas/cards_acordeon.js
 * =========================================================
 * Generador global de Cards / Acordeón para tablas en móviles
 * =========================================================
 */

(function (w) {
    'use strict';

    const ERPCards = {};
    const MOBILE_BREAKPOINT = 767.98;

    ERPCards.init = function() {
        // Blindaje SPA: Evitar que se dupliquen los eventos
        if (w.ERPCards_isInitialized) return;
        w.ERPCards_isInitialized = true;

        console.log("🟢 ERPCards: Listo para funcionar en toda la SPA.");

        // Escuchar clics en todo el documento
        document.addEventListener('click', function (e) {
            // Si la pantalla es grande, no hacemos nada
            if (window.innerWidth > MOBILE_BREAKPOINT) return;

            // Buscar si el clic fue en una fila expandible
            const trClick = e.target.closest('.mobile-expandable-row');
            if (!trClick) return;

            // Ignorar clics dentro de inputs, botones o selects (para que puedas editar el precio tranquilo)
            if (e.target.closest('input') || e.target.closest('button') || e.target.closest('select') || e.target.classList.contains('input-group-text')) {
                return;
            }

            // Lógica de expansión
            const isExpanded = trClick.classList.contains('expanded');
            const table = trClick.closest('table');

            // 1. Cerrar TODAS las filas de esta tabla primero
            if (table) {
                table.querySelectorAll('.mobile-expandable-row.expanded').forEach(row => {
                    row.classList.remove('expanded');
                });
            }

            // 2. Si la fila no estaba expandida, la abrimos (si ya estaba, se queda cerrada por el paso 1)
            if (!isExpanded) {
                trClick.classList.add('expanded');
            }
        });
    };

    // Auto-inicializar
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ERPCards.init);
    } else {
        ERPCards.init();
    }

    w.ERPCards = ERPCards;

})(window);