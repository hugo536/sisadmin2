(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', () => {
    // 1. Desactivar autocompletado en el buscador interno
    const searchInput = document.getElementById('searchKardex');
    if (searchInput) {
      searchInput.setAttribute('autocomplete', 'off');
    }

    // 2. Elementos del formulario de filtros
    const filtrosForm = document.getElementById('kardexFiltrosForm');
    const itemSelect = document.getElementById('kardexItemSelect');
    const dateInputs = document.querySelectorAll('.kardex-auto-submit');

    // 3. Lógica para el Select de Ítems (Soporte nativo y TomSelect)
    if (itemSelect && typeof window.AppSelects !== 'undefined' && typeof window.TomSelect !== 'undefined') {
      const tomItem = window.AppSelects.initLocal('#kardexItemSelect', {
        placeholder: 'Buscar ítem...'
      });

      tomItem.on('change', () => {
        if (filtrosForm) filtrosForm.submit();
      });
    } else if (itemSelect) {
      itemSelect.addEventListener('change', () => {
        if (filtrosForm) filtrosForm.submit();
      });
    }

    // 4. Lógica para los inputs de fecha (Desde / Hasta)
    dateInputs.forEach((input) => {
      input.addEventListener('change', () => {
        if (filtrosForm) filtrosForm.submit();
      });
    });
  });
})();